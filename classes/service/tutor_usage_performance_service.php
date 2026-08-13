<?php
// This file is part of Moodle - http://moodle.org/
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

namespace local_dixeo\service;

/**
 * Performance section data for the tutor usage report (grades vs usage).
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tutor_usage_performance_service {
    /** @var int Max scatter points. */
    public const SCATTER_POINT_CAP = 500;

    /** @var string Application cache name. */
    public const CACHE_NAME = 'tutorusageperformance';

    /**
     * Ensure grade libraries are loaded once.
     */
    protected function require_grade_libs(): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/grade/lib.php');
    }

    /**
     * Whether the Performance section may be shown for this report request.
     *
     * @param string $level Report level.
     * @param string $rolescope Users filter scope.
     * @param int $courseid Course id.
     * @return bool
     */
    public function is_section_eligible(string $level, string $rolescope, int $courseid): bool {
        if (
            $level !== tutor_usage_report_service::LEVEL_COURSE
            && $level !== tutor_usage_report_service::LEVEL_USER
        ) {
            return false;
        }
        if ($courseid <= 0) {
            return false;
        }
        if ($rolescope === tutor_usage_report_service::ROLE_SCOPE_TEACHERS) {
            return false;
        }

        $context = \context_course::instance($courseid);
        return has_capability('moodle/grade:viewall', $context);
    }

    /**
     * Build Mustache context for the Performance section, or null when hidden.
     *
     * @param string $level Report level.
     * @param int $courseid Course id.
     * @param int $userid Highlighted user id (user level), or 0.
     * @param string $rolescope Users filter scope.
     * @return array|null
     */
    public function get_section_context(string $level, int $courseid, int $userid, string $rolescope): ?array {
        if (!$this->is_section_eligible($level, $rolescope, $courseid)) {
            return null;
        }

        $this->require_grade_libs();
        $context = \context_course::instance($courseid);
        $canviewhidden = has_capability('moodle/grade:viewhidden', $context);
        $payload = $this->get_cached_course_payload($courseid, $canviewhidden);

        if ($payload === null || empty($payload['activities'])) {
            return null;
        }

        $highlightuserid = ($level === tutor_usage_report_service::LEVEL_USER && $userid > 0) ? $userid : 0;
        if ($highlightuserid > 0) {
            $payload = $this->ensure_user_in_scatter($payload, $courseid, $highlightuserid, $canviewhidden);
        }

        $points = [];
        $names = $this->get_user_display_names(array_column($payload['scatterpoints'], 'userid'));
        foreach ($payload['scatterpoints'] as $point) {
            $userid = (int) $point['userid'];
            $iscurrent = $highlightuserid > 0 && $userid === $highlightuserid;
            $points[] = [
                'x' => (int) $point['messages'],
                'y' => round((float) $point['grade'], 2),
                'iscurrent' => $iscurrent,
                'name' => $names[$userid] ?? '',
            ];
        }

        $activities = [];
        $usergrades = [];
        if ($highlightuserid > 0) {
            $usergrades = $this->get_user_percentages_for_items(
                $courseid,
                $highlightuserid,
                array_column($payload['activities'], 'itemid'),
                $canviewhidden
            );
        }

        foreach ($payload['activities'] as $activity) {
            $average = $activity['average'];
            $averageformatted = $average === null
                ? get_string('tutor_usage_report_performance_na', 'local_dixeo')
                : $this->format_percent((float) $average);

            $row = [
                'name' => $activity['name'],
                'url' => $activity['url'],
                'typelabel' => $activity['typelabel'],
                'averageformatted' => $averageformatted,
                'iscoursetotal' => !empty($activity['iscoursetotal']),
                'isuserlevel' => $highlightuserid > 0,
            ];

            if ($highlightuserid > 0) {
                $itemid = (int) $activity['itemid'];
                $userpct = $usergrades[$itemid] ?? null;
                $userformatted = $userpct === null
                    ? get_string('tutor_usage_report_performance_na', 'local_dixeo')
                    : $this->format_percent((float) $userpct);
                $row['userformatted'] = $userformatted;
                $row['uservsaverage'] = get_string(
                    'tutor_usage_report_performance_user_vs_average',
                    'local_dixeo',
                    (object) [
                        'user' => $userformatted,
                        'average' => $averageformatted,
                    ]
                );
            }

            $activities[] = $row;
        }

        $hasscatter = $points !== [];
        $scatterdata = [
            'points' => $points,
            'meanx' => (float) ($payload['meanmessages'] ?? 0),
            'meany' => (float) ($payload['meangrade'] ?? 0),
            'xmax' => (int) ($payload['maxmessages'] ?? 0),
            'xaxislabel' => get_string('tutor_usage_report_performance_axis_usage', 'local_dixeo'),
            'yaxislabel' => get_string('tutor_usage_report_performance_axis_grade', 'local_dixeo'),
            'meanusagelabel' => get_string('tutor_usage_report_performance_mean_usage', 'local_dixeo'),
            'meangradelabel' => get_string('tutor_usage_report_performance_mean_grade', 'local_dixeo'),
        ];

        return [
            'hasscatter' => $hasscatter,
            'hasactivities' => $activities !== [],
            'isuserlevel' => $highlightuserid > 0,
            'activities' => $activities,
            'scatterdata' => json_encode($scatterdata),
            'scatterempty' => get_string('tutor_usage_report_performance_scatter_empty', 'local_dixeo'),
        ];
    }

    /**
     * Get or build the cached course performance payload.
     *
     * @param int $courseid Course id.
     * @param bool $canviewhidden Whether hidden grades are visible.
     * @return array|null
     */
    public function get_cached_course_payload(int $courseid, bool $canviewhidden): ?array {
        $cache = \cache::make('local_dixeo', self::CACHE_NAME);
        $key = $this->cache_key($courseid, $canviewhidden);
        $cached = $cache->get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $payload = $this->build_course_payload($courseid, $canviewhidden);
        if ($payload !== null) {
            $cache->set($key, $payload);
        }

        return $payload;
    }

    /**
     * Build course performance payload (students only, all-time usage).
     *
     * @param int $courseid Course id.
     * @param bool $canviewhidden Whether hidden grades are visible.
     * @return array|null Null when there are no listable graded elements.
     */
    public function build_course_payload(int $courseid, bool $canviewhidden): ?array {
        $this->require_grade_libs();
        $items = $this->get_listable_grade_items($courseid, $canviewhidden);
        if ($items === []) {
            return null;
        }

        $reportservice = new tutor_usage_report_service();
        $studentroleids = tutor_usage_report_service::get_default_student_roleids();
        $studentids = $reportservice->get_in_scope_userids(
            tutor_usage_report_service::LEVEL_COURSE,
            $courseid,
            0,
            $studentroleids
        );

        $messagecounts = $this->get_alltime_message_counts($courseid, $studentids);
        $courseitem = null;
        foreach ($items as $item) {
            if ($item->is_course_item()) {
                $courseitem = $item;
                break;
            }
        }

        $scatterpoints = [];
        if ($courseitem !== null && $studentids !== []) {
            $coursegrades = \grade_grade::fetch_users_grades($courseitem, $studentids, true);
            foreach ($studentids as $studentid) {
                $grade = $coursegrades[$studentid] ?? null;
                $pct = $this->grade_to_percentage($courseitem, $grade, $canviewhidden);
                if ($pct === null) {
                    continue;
                }
                $scatterpoints[] = [
                    'userid' => (int) $studentid,
                    'messages' => (int) ($messagecounts[$studentid] ?? 0),
                    'grade' => $pct,
                ];
            }
        }

        if (count($scatterpoints) > self::SCATTER_POINT_CAP) {
            $scatterpoints = $this->random_sample($scatterpoints, self::SCATTER_POINT_CAP);
        }

        $meanmessages = 0.0;
        $meangrade = 0.0;
        $maxmessages = 0;
        if ($scatterpoints !== []) {
            $sumx = 0;
            $sumy = 0.0;
            foreach ($scatterpoints as $point) {
                $sumx += (int) $point['messages'];
                $sumy += (float) $point['grade'];
                $maxmessages = max($maxmessages, (int) $point['messages']);
            }
            $n = count($scatterpoints);
            $meanmessages = $sumx / $n;
            $meangrade = $sumy / $n;
        }

        $activities = [];
        foreach ($items as $item) {
            $grades = $studentids === []
                ? []
                : \grade_grade::fetch_users_grades($item, $studentids, true);
            $percents = [];
            foreach ($studentids as $studentid) {
                $pct = $this->grade_to_percentage($item, $grades[$studentid] ?? null, $canviewhidden);
                if ($pct !== null) {
                    $percents[] = $pct;
                }
            }
            $average = $percents === [] ? null : (array_sum($percents) / count($percents));

            $activities[] = [
                'itemid' => (int) $item->id,
                'name' => $item->get_name(),
                'url' => $this->get_activity_url($courseid, $item),
                'typelabel' => $this->get_item_type_label($item),
                'iscoursetotal' => $item->is_course_item(),
                'average' => $average,
                'sortorder' => (int) $item->sortorder,
            ];
        }

        return [
            'activities' => $activities,
            'scatterpoints' => $scatterpoints,
            'meanmessages' => $meanmessages,
            'meangrade' => $meangrade,
            'maxmessages' => $maxmessages,
        ];
    }

    /**
     * Listable grade items in gradebook order: course total + mod items.
     *
     * @param int $courseid Course id.
     * @param bool $canviewhidden Whether hidden items are included.
     * @return \grade_item[]
     */
    public function get_listable_grade_items(int $courseid, bool $canviewhidden): array {
        $this->require_grade_libs();
        $all = \grade_item::fetch_all(['courseid' => $courseid]);
        if (!$all) {
            return [];
        }

        $items = [];
        foreach ($all as $item) {
            if (!$this->is_listable_item($item, $canviewhidden)) {
                continue;
            }
            $items[] = $item;
        }

        usort($items, static function (\grade_item $a, \grade_item $b): int {
            if ((int) $a->sortorder !== (int) $b->sortorder) {
                return (int) $a->sortorder <=> (int) $b->sortorder;
            }
            return (int) $a->id <=> (int) $b->id;
        });

        return $items;
    }

    /**
     * Whether a grade item belongs in the Performance activities list.
     *
     * @param \grade_item $item Grade item.
     * @param bool $canviewhidden Whether hidden items are visible.
     * @return bool
     */
    public function is_listable_item(\grade_item $item, bool $canviewhidden): bool {
        if ($item->is_outcome_item()) {
            return false;
        }
        if ($item->gradetype != GRADE_TYPE_VALUE && $item->gradetype != GRADE_TYPE_SCALE) {
            return false;
        }
        if (!$canviewhidden && $item->is_hidden()) {
            return false;
        }
        if ($item->is_course_item()) {
            return true;
        }
        return $item->itemtype === 'mod';
    }

    /**
     * Convert a grade_grade to a percentage, or null when not plottable.
     *
     * @param \grade_item $item Grade item.
     * @param \grade_grade|null $grade User grade.
     * @param bool $canviewhidden Whether hidden grades are visible.
     * @return float|null
     */
    public function grade_to_percentage(\grade_item $item, ?\grade_grade $grade, bool $canviewhidden): ?float {
        $this->require_grade_libs();
        if ($grade === null || $grade->finalgrade === null) {
            return null;
        }
        if (!$canviewhidden && ($item->is_hidden() || $grade->is_hidden())) {
            return null;
        }
        if ($item->gradetype != GRADE_TYPE_VALUE && $item->gradetype != GRADE_TYPE_SCALE) {
            return null;
        }

        $grade->grade_item = $item;
        $min = (float) $grade->get_grade_min();
        $max = (float) $grade->get_grade_max();
        if ($max == $min) {
            return null;
        }

        $bounded = (float) $item->bounded_grade((float) $grade->finalgrade);
        return (($bounded - $min) * 100) / ($max - $min);
    }

    /**
     * All-time tutor message counts keyed by userid.
     *
     * @param int $courseid Course id.
     * @param int[] $userids Student ids.
     * @return array<int, int>
     */
    public function get_alltime_message_counts(int $courseid, array $userids): array {
        global $DB;

        $counts = [];
        foreach ($userids as $userid) {
            $counts[(int) $userid] = 0;
        }
        if ($userids === []) {
            return $counts;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $params['courseid'] = $courseid;
        $params['eventtype'] = tutor_usage_recorder::EVENT_MESSAGE;

        $sql = "SELECT userid, COUNT(1) AS messagecount
                  FROM {" . tutor_usage_recorder::TABLE_EVENT . "}
                 WHERE courseid = :courseid
                   AND eventtype = :eventtype
                   AND userid {$insql}
              GROUP BY userid";

        $records = $DB->get_records_sql($sql, $params);
        foreach ($records as $record) {
            $counts[(int) $record->userid] = (int) $record->messagecount;
        }

        return $counts;
    }

    /**
     * Random sample of points (stable enough for rare large courses).
     *
     * @param array $points Scatter points.
     * @param int $limit Max size.
     * @return array
     */
    public function random_sample(array $points, int $limit): array {
        if (count($points) <= $limit) {
            return array_values($points);
        }
        $keys = array_keys($points);
        shuffle($keys);
        $selected = [];
        foreach (array_slice($keys, 0, $limit) as $key) {
            $selected[] = $points[$key];
        }
        return $selected;
    }

    /**
     * Cache key for a course payload.
     *
     * @param int $courseid Course id.
     * @param bool $canviewhidden Hidden-grade visibility bit.
     * @return string
     */
    protected function cache_key(int $courseid, bool $canviewhidden): string {
        return $courseid . '_' . ($canviewhidden ? '1' : '0');
    }

    /**
     * Ensure the highlighted user appears in the scatter when they have a grade.
     *
     * @param array $payload Cached payload.
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param bool $canviewhidden Hidden visibility.
     * @return array
     */
    protected function ensure_user_in_scatter(
        array $payload,
        int $courseid,
        int $userid,
        bool $canviewhidden
    ): array {
        $this->require_grade_libs();
        foreach ($payload['scatterpoints'] as $point) {
            if ((int) $point['userid'] === $userid) {
                return $payload;
            }
        }

        $courseitem = \grade_item::fetch_course_item($courseid);
        if (!$this->is_listable_item($courseitem, $canviewhidden)) {
            return $payload;
        }

        $grades = \grade_grade::fetch_users_grades($courseitem, [$userid], true);
        $pct = $this->grade_to_percentage($courseitem, $grades[$userid] ?? null, $canviewhidden);
        if ($pct === null) {
            return $payload;
        }

        $messages = $this->get_alltime_message_counts($courseid, [$userid]);
        $newpoint = [
            'userid' => $userid,
            'messages' => (int) ($messages[$userid] ?? 0),
            'grade' => $pct,
        ];

        if (count($payload['scatterpoints']) >= self::SCATTER_POINT_CAP) {
            array_pop($payload['scatterpoints']);
        }
        $payload['scatterpoints'][] = $newpoint;

        $sumx = 0;
        $sumy = 0.0;
        $maxmessages = 0;
        foreach ($payload['scatterpoints'] as $point) {
            $sumx += (int) $point['messages'];
            $sumy += (float) $point['grade'];
            $maxmessages = max($maxmessages, (int) $point['messages']);
        }
        $n = count($payload['scatterpoints']);
        $payload['meanmessages'] = $n ? $sumx / $n : 0.0;
        $payload['meangrade'] = $n ? $sumy / $n : 0.0;
        $payload['maxmessages'] = $maxmessages;

        return $payload;
    }

    /**
     * Display names keyed by userid.
     *
     * @param int[] $userids User ids.
     * @return array<int, string>
     */
    protected function get_user_display_names(array $userids): array {
        global $DB;

        $names = [];
        $userids = array_values(array_unique(array_filter(array_map('intval', $userids))));
        if ($userids === []) {
            return $names;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        // Leading comma so we can safely prefix with id.
        $namefields = \core_user\fields::for_name()->get_sql('', false, '', '', true)->selects;
        $records = $DB->get_records_select('user', "id {$insql}", $params, '', 'id' . $namefields);
        foreach ($records as $user) {
            $names[(int) $user->id] = fullname($user);
        }

        return $names;
    }

    /**
     * Percentages for one user across item ids.
     *
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int[] $itemids Grade item ids.
     * @param bool $canviewhidden Hidden visibility.
     * @return array<int, float>
     */
    protected function get_user_percentages_for_items(
        int $courseid,
        int $userid,
        array $itemids,
        bool $canviewhidden
    ): array {
        $this->require_grade_libs();
        $result = [];
        if ($itemids === []) {
            return $result;
        }

        $items = $this->get_listable_grade_items($courseid, $canviewhidden);
        $byid = [];
        foreach ($items as $item) {
            $byid[(int) $item->id] = $item;
        }

        foreach ($itemids as $itemid) {
            $itemid = (int) $itemid;
            if (!isset($byid[$itemid])) {
                continue;
            }
            $item = $byid[$itemid];
            $grades = \grade_grade::fetch_users_grades($item, [$userid], true);
            $pct = $this->grade_to_percentage($item, $grades[$userid] ?? null, $canviewhidden);
            if ($pct !== null) {
                $result[$itemid] = $pct;
            }
        }

        return $result;
    }

    /**
     * Activity view URL when the CM is visible.
     *
     * @param int $courseid Course id.
     * @param \grade_item $item Grade item.
     * @return string|null
     */
    protected function get_activity_url(int $courseid, \grade_item $item): ?string {
        if ($item->itemtype !== 'mod' || empty($item->itemmodule) || empty($item->iteminstance)) {
            return null;
        }

        try {
            $modinfo = get_fast_modinfo($courseid);
            $instances = $modinfo->get_instances_of($item->itemmodule);
            $cm = $instances[$item->iteminstance] ?? null;
            if ($cm && $cm->uservisible) {
                return (new \moodle_url('/mod/' . $item->itemmodule . '/view.php', ['id' => $cm->id]))->out(false);
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    /**
     * Human type label for a grade item.
     *
     * @param \grade_item $item Grade item.
     * @return string
     */
    protected function get_item_type_label(\grade_item $item): string {
        if ($item->is_course_item()) {
            return get_string('tutor_usage_report_performance_course_total', 'local_dixeo');
        }
        if ($item->itemtype === 'mod' && !empty($item->itemmodule)) {
            $modname = get_string_manager()->string_exists('modulename', 'mod_' . $item->itemmodule)
                ? get_string('modulename', 'mod_' . $item->itemmodule)
                : $item->itemmodule;
            return $modname;
        }
        return $item->itemtype;
    }

    /**
     * Format a percentage for display.
     *
     * @param float $value Percentage.
     * @return string
     */
    protected function format_percent(float $value): string {
        return format_float($value, 1) . '%';
    }
}
