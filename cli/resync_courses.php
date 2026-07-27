<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * CLI script to (re)trigger Dixeo file sync for a batch of courses.
 *
 * Replays the same path as the per-course "Resync" button
 * (file_sync_service::trigger_sync) across a set of courses selected by
 * recent activity, last modification, creation date, or an explicit id
 * list. Intended as a one-shot operation when onboarding a platform that
 * previously relied on a different sync mechanism.
 *
 * trigger_sync is idempotent: a course whose file manifest already matches
 * the remote store and is in the 'synchronized' state is skipped (no upload,
 * no API cost). Migrated courses with no local record are synced for real.
 *
 * Usage:
 *   php resync_courses.php --dry-run
 *   php resync_courses.php --since=14d
 *   php resync_courses.php --selector=created --since=2026-01-01
 *   php resync_courses.php --courseids=12,34,56
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_dixeo\service\file_sync_service;

[$options, $unrecognized] = cli_get_params(
    [
        'since'      => '14d',
        'selector'   => 'activity',
        'courseids'  => '',
        'limit'      => 0,
        'sleep'      => 500,
        'dry-run'    => false,
        'stop-on-error' => false,
        'help'       => false,
    ],
    [
        'd' => 'dry-run',
        'h' => 'help',
    ]
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    echo <<<EOF
(Re)trigger Dixeo file sync for a batch of courses.

Selects courses, then runs file_sync_service::trigger_sync() on each, exactly
as the per-course "Resync" button does. Already-synchronized courses are
skipped by trigger_sync itself (no re-upload, no API cost).

Options:
  --selector=NAME   How to pick courses (default: activity):
                      activity - courses with a log event since --since
                                 (logstore_standard_log)
                      modified - course.timemodified >= --since
                      created  - course.timecreated  >= --since
  --since=WHEN      Cutoff for the selector (default: 14d). Accepts:
                      Nd / Nw / Nm  relative days / weeks / months (30d)
                      YYYY-MM-DD    absolute date
                      N             plain integer = days
  --courseids=LIST  Comma-separated course ids; overrides --selector/--since.
  --limit=INT       Process at most this many courses (0 = no limit).
  --sleep=INT       Milliseconds to wait between courses (default: 500) to
                    spare the API queue.
  --dry-run, -d     List the selected courses and exit without syncing.
  --stop-on-error   Halt on the first course that fails (default: continue).
  --help, -h        Print this help.

Examples:
  php resync_courses.php --dry-run
  php resync_courses.php --since=14d --sleep=750
  php resync_courses.php --selector=created --since=2026-01-01
  php resync_courses.php --courseids=12,34,56

Long runs: wrap in nohup/screen and tee the output, e.g.
  nohup php resync_courses.php --since=14d > /tmp/dixeo_resync.log 2>&1 &

EOF;
    exit(0);
}

// Run as admin so new course_ai records get a real owner and capability-bound code paths behave.
$admin = get_admin();
if (!$admin) {
    cli_error('No admin account found; cannot establish a user context.');
}
\core\session\manager::set_user($admin);

$selector = (string) $options['selector'];
if (!in_array($selector, ['activity', 'modified', 'created'], true)) {
    cli_error("Invalid --selector '{$selector}'. Use: activity, modified or created.");
}

$courseids = local_dixeo_resync_select_courses($options, $selector);

if ($courseids === []) {
    cli_writeln('No courses matched the selection. Nothing to do.');
    exit(0);
}

$limit = (int) $options['limit'];
if ($limit > 0 && count($courseids) > $limit) {
    $courseids = array_slice($courseids, 0, $limit);
}

// Load surviving courses (deleted ones drop out via the join), keep stable order, skip the site.
$courses = $DB->get_records_list(
    'course',
    'id',
    $courseids,
    'id ASC',
    'id, shortname, fullname, visible'
);
unset($courses[SITEID]);

$total = count($courses);
$dryrun = (bool) $options['dry-run'];
$sincelabel = $options['courseids'] !== '' ? '(explicit ids)' : $options['since'];

cli_writeln('Dixeo bulk resync');
cli_writeln(str_repeat('-', 60));
cli_writeln("Selector : {$selector}");
cli_writeln("Since    : {$sincelabel}");
cli_writeln("Courses  : {$total}");
cli_writeln('Mode     : ' . ($dryrun ? 'DRY RUN (no sync)' : 'LIVE'));
cli_writeln(str_repeat('-', 60));

if ($dryrun) {
    foreach ($courses as $course) {
        $hidden = $course->visible ? '' : ' [hidden]';
        cli_writeln("  {$course->id}\t{$course->shortname}{$hidden}");
    }
    cli_writeln(str_repeat('-', 60));
    cli_writeln("{$total} course(s) would be processed. Re-run without --dry-run to sync.");
    exit(0);
}

$service = new file_sync_service();
$sleepus = max(0, (int) $options['sleep']) * 1000;
$index = 0;
$okcount = 0;
$errorcount = 0;
$statustally = [];

foreach ($courses as $course) {
    $index++;
    $prefix = "[{$index}/{$total}] course {$course->id} ({$course->shortname})";

    try {
        $service->trigger_sync($course->id);
        $status = $service->get_status($course->id);
        $okcount++;
        $statustally[$status->status] = ($statustally[$status->status] ?? 0) + 1;

        $files = $status->filestotal !== null
            ? " files {$status->filescompleted}/{$status->filestotal}"
            : '';
        cli_writeln("{$prefix} -> {$status->status}{$files}");
    } catch (\Throwable $e) {
        $errorcount++;
        cli_writeln("{$prefix} -> ERROR: " . $e->getMessage());
        if ($options['stop-on-error']) {
            cli_writeln('Stopping on first error as requested (--stop-on-error).');
            break;
        }
    }

    if ($sleepus > 0 && $index < $total) {
        usleep($sleepus);
    }
}

cli_writeln(str_repeat('-', 60));
cli_writeln("Done. {$okcount} ok, {$errorcount} error(s).");
foreach ($statustally as $statusname => $count) {
    cli_writeln("  {$statusname}: {$count}");
}

exit($errorcount > 0 ? 1 : 0);

/**
 * Resolve the set of candidate course ids from the CLI options.
 *
 * @param array $options Parsed CLI options.
 * @param string $selector One of activity|modified|created.
 * @return int[] Candidate course ids (unfiltered for existence).
 */
function local_dixeo_resync_select_courses(array $options, string $selector): array {
    global $DB;

    $explicit = trim((string) $options['courseids']);
    if ($explicit !== '') {
        $ids = array_filter(array_map('intval', explode(',', $explicit)), fn($id) => $id > 1);
        return array_values(array_unique($ids));
    }

    $since = local_dixeo_resync_resolve_since((string) $options['since']);

    if ($selector === 'activity') {
        if (!$DB->get_manager()->table_exists('logstore_standard_log')) {
            cli_error('logstore_standard_log not available; use --selector=modified or --selector=created.');
        }
        $sql = "SELECT DISTINCT l.courseid
                  FROM {logstore_standard_log} l
                  JOIN {course} c ON c.id = l.courseid
                 WHERE l.timecreated >= :since
                   AND l.courseid > 1";
        return array_map('intval', $DB->get_fieldset_sql($sql, ['since' => $since]));
    }

    $field = $selector === 'created' ? 'timecreated' : 'timemodified';
    $sql = "SELECT id FROM {course} WHERE {$field} >= :since AND id > 1 ORDER BY id ASC";
    return array_map('intval', $DB->get_fieldset_sql($sql, ['since' => $since]));
}

/**
 * Resolve a --since value to an absolute epoch timestamp.
 *
 * Accepts Nd / Nw / Nm relative windows, a YYYY-MM-DD date, or a plain
 * integer number of days.
 *
 * @param string $value Raw option value.
 * @return int Epoch timestamp; events at or after it are in scope.
 */
function local_dixeo_resync_resolve_since(string $value): int {
    $value = trim($value);

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        $ts = strtotime($value . ' 00:00:00');
        if ($ts === false) {
            cli_error("Invalid --since date '{$value}'.");
        }
        return $ts;
    }

    if (preg_match('/^(\d+)([dwm])$/', $value, $m)) {
        $units = ['d' => DAYSECS, 'w' => WEEKSECS, 'm' => 30 * DAYSECS];
        return time() - ((int) $m[1] * $units[$m[2]]);
    }

    if (ctype_digit($value)) {
        return time() - ((int) $value * DAYSECS);
    }

    cli_error("Invalid --since '{$value}'. Use Nd, Nw, Nm, YYYY-MM-DD or an integer day count.");
}
