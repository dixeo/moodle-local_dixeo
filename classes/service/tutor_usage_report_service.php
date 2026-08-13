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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_dixeo\service;

use local_dixeo\dto\tutor_message;

/**
 * Builds tutor usage report data from usage events and sessions.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tutor_usage_report_service {
    /** @var string Week view mode. */
    public const VIEW_WEEK = 'week';

    /** @var string Month view mode. */
    public const VIEW_MONTH = 'month';

    /** @var string Custom date range view mode. */
    public const VIEW_CUSTOM = 'custom';

    /** @var string Site-wide report level. */
    public const LEVEL_SITE = 'site';

    /** @var string Course report level. */
    public const LEVEL_COURSE = 'course';

    /** @var string User drill-down report level. */
    public const LEVEL_USER = 'user';

    /** @var int Maximum custom range length in seconds (~6 months). */
    public const MAX_CUSTOM_RANGE_SECONDS = 15811200;

    /** @var string[] Tutor modes for stacked charts and columns. */
    public const MESSAGE_MODES = [
        tutor_message::MODE_NORMAL,
        tutor_message::MODE_GUIDE,
        tutor_message::MODE_QUIZ,
        tutor_message::MODE_TEACH,
    ];

    /** @var int Number of 3-hour heatmap rows. */
    public const HEATMAP_SLOT_COUNT = 8;

    /** @var string Role filter: all enrolled users (no role restriction). */
    public const ROLE_SCOPE_ALL = 'all';

    /** @var string Role filter: teacher archetypes only. */
    public const ROLE_SCOPE_TEACHERS = 'teachers';

    /** @var string Role filter: student archetype only. */
    public const ROLE_SCOPE_STUDENTS = 'students';

    /**
     * Allowed role-scope filter values.
     *
     * @return string[]
     */
    public static function role_scopes(): array {
        return [
            self::ROLE_SCOPE_ALL,
            self::ROLE_SCOPE_TEACHERS,
            self::ROLE_SCOPE_STUDENTS,
        ];
    }

    /**
     * Normalize a role-scope request value.
     *
     * @param string $scope Raw scope.
     * @return string One of role_scopes().
     */
    public static function normalize_role_scope(string $scope): string {
        return in_array($scope, self::role_scopes(), true) ? $scope : self::ROLE_SCOPE_ALL;
    }

    /**
     * Resolve role ids for a predefined role scope.
     *
     * Empty array means no role filter (all users).
     *
     * @param string $scope Role scope constant.
     * @return int[]
     */
    public static function get_roleids_for_scope(string $scope): array {
        $scope = self::normalize_role_scope($scope);
        if ($scope === self::ROLE_SCOPE_STUDENTS) {
            return self::get_default_student_roleids();
        }
        if ($scope === self::ROLE_SCOPE_TEACHERS) {
            return self::get_teacher_roleids();
        }
        return [];
    }

    /**
     * Parse a report date-from request value to a user-timezone midnight timestamp.
     *
     * @param string $raw Raw request value (Y-m-d or legacy unix timestamp).
     * @return int Timestamp or 0 when empty/invalid.
     */
    public static function parse_date_from_param(string $raw): int {
        if ($raw === '') {
            return 0;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $matches)) {
            return make_timestamp((int) $matches[1], (int) $matches[2], (int) $matches[3], 0, 0, 0);
        }
        if (ctype_digit($raw)) {
            return usergetmidnight((int) $raw);
        }
        return 0;
    }

    /**
     * Parse a report date-to request value to a user-timezone end-of-day timestamp.
     *
     * @param string $raw Raw request value (Y-m-d or legacy unix timestamp).
     * @return int Timestamp or 0 when empty/invalid.
     */
    public static function parse_date_to_param(string $raw): int {
        if ($raw === '') {
            return 0;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $matches)) {
            return make_timestamp((int) $matches[1], (int) $matches[2], (int) $matches[3], 23, 59, 59);
        }
        if (ctype_digit($raw)) {
            $date = usergetdate((int) $raw);
            return make_timestamp($date['year'], $date['mon'], $date['mday'], 23, 59, 59);
        }
        return 0;
    }

    /**
     * Format a timestamp for report date form fields and URLs.
     *
     * @param int $timestamp Unix timestamp.
     * @return string Date in Y-m-d format.
     */
    public static function format_date_param(int $timestamp): string {
        $date = usergetdate($timestamp);
        return sprintf('%04d-%02d-%02d', $date['year'], $date['mon'], $date['mday']);
    }

    /**
     * Build a tutor usage report URL, supporting multi-value filter parameters.
     *
     * @param array $params URL parameters.
     * @return string Rendered URL.
     */
    public static function build_report_url(array $params): string {
        $scalarparams = [];
        $arrayparams = [];

        foreach ($params as $key => $value) {
            if (is_array($value)) {
                if (!empty($value)) {
                    $arrayparams[$key] = array_values($value);
                }
                continue;
            }
            if ($value !== null && $value !== '') {
                $scalarparams[$key] = $value;
            }
        }

        $url = (new \moodle_url('/local/dixeo/tutor_usage_report.php', $scalarparams))->out(false);
        if (empty($arrayparams)) {
            return $url;
        }

        $parts = [];
        foreach ($arrayparams as $key => $values) {
            foreach ($values as $index => $value) {
                $parts[] = rawurlencode($key . '[' . $index . ']') . '=' . rawurlencode((string) $value);
            }
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . implode('&', $parts);
    }

    /**
     * Build URL parameters when switching period view modes.
     *
     * @param string $targetview Target view mode.
     * @param array $period Resolved current period data.
     * @return array
     */
    public static function build_view_switch_params(string $targetview, array $period): array {
        if ($targetview === self::VIEW_CUSTOM) {
            return [
                'view' => self::VIEW_CUSTOM,
                'datefrom' => self::format_date_param((int) $period['timestart']),
                'dateto' => self::format_date_param((int) $period['timeend']),
            ];
        }

        return [
            'view' => $targetview,
            'anchor' => self::format_date_param((int) $period['timestart']),
        ];
    }

    /**
     * Whether the current user may view the site-wide tutor usage report.
     *
     * @param int|null $userid User id (defaults to current user).
     * @return bool
     */
    public static function can_view_site(?int $userid = null): bool {
        global $USER;

        $userid = $userid ?? (int) $USER->id;
        return has_capability('local/dixeo:viewtutorusagesite', \context_system::instance(), $userid);
    }

    /**
     * Whether the current user may view tutor usage for a course.
     *
     * @param int $courseid Course id.
     * @param int|null $userid User id (defaults to current user).
     * @return bool
     */
    public static function can_view_course(int $courseid, ?int $userid = null): bool {
        global $USER;

        $userid = $userid ?? (int) $USER->id;
        if (self::can_view_site($userid)) {
            return true;
        }

        if ($courseid < 1) {
            return false;
        }

        $context = \context_course::instance($courseid, IGNORE_MISSING);
        return $context && has_capability('local/dixeo:viewtutorusage', $context, $userid);
    }

    /**
     * Course ids the user may open in the tutor usage report.
     *
     * @param int|null $userid User id (defaults to current user).
     * @return int[]
     */
    public static function get_accessible_courseids(?int $userid = null): array {
        global $DB, $USER;

        $userid = $userid ?? (int) $USER->id;
        if (self::can_view_site($userid)) {
            $ids = $DB->get_fieldset_select('course', 'id', 'id > 1', []);
            $ids = array_map('intval', $ids ?: []);
            sort($ids);
            return $ids;
        }

        $courses = get_user_capability_course(
            'local/dixeo:viewtutorusage',
            $userid,
            true,
            '',
            'sortorder ASC',
            0
        );
        if (empty($courses)) {
            return [];
        }

        $courseids = [];
        foreach ($courses as $course) {
            $courseids[] = (int) $course->id;
        }
        return $courseids;
    }

    /**
     * Default student role ids for the students role scope.
     *
     * @return int[]
     */
    public static function get_default_student_roleids(): array {
        global $DB;

        return array_map('intval', $DB->get_fieldset_select(
            'role',
            'id',
            'archetype = :archetype',
            ['archetype' => 'student']
        ));
    }

    /**
     * Teacher role ids (teacher + editingteacher archetypes).
     *
     * @return int[]
     */
    public static function get_teacher_roleids(): array {
        global $DB;

        return array_map('intval', $DB->get_fieldset_select(
            'role',
            'id',
            'archetype = :teacher OR archetype = :editingteacher',
            [
                'teacher' => 'teacher',
                'editingteacher' => 'editingteacher',
            ]
        ));
    }

    /**
     * Predefined role-scope options for the report filter UI.
     *
     * @param string $selectedscope Selected scope.
     * @return array<int, array{id: string, name: string, selected: bool}>
     */
    public function get_role_scope_options(string $selectedscope): array {
        $selectedscope = self::normalize_role_scope($selectedscope);
        $labels = [
            self::ROLE_SCOPE_ALL => get_string('tutor_usage_report_rolescope_all', 'local_dixeo'),
            self::ROLE_SCOPE_TEACHERS => get_string('tutor_usage_report_rolescope_teachers', 'local_dixeo'),
            self::ROLE_SCOPE_STUDENTS => get_string('tutor_usage_report_rolescope_students', 'local_dixeo'),
        ];

        $options = [];
        foreach (self::role_scopes() as $scope) {
            $options[] = [
                'id' => $scope,
                'name' => $labels[$scope],
                'selected' => $scope === $selectedscope,
            ];
        }

        return $options;
    }

    /**
     * Resolve period boundaries from view parameters.
     *
     * @param string $view View mode.
     * @param string|null $anchor Anchor date Y-m-d.
     * @param int|null $datefrom Custom start timestamp.
     * @param int|null $dateto Custom end timestamp.
     * @return array
     * @throws \moodle_exception When custom range exceeds six months.
     */
    public function resolve_period(
        string $view,
        ?string $anchor = null,
        ?int $datefrom = null,
        ?int $dateto = null
    ): array {
        $anchordate = new \DateTime($anchor ?: 'today');

        if ($view === self::VIEW_MONTH) {
            $start = (clone $anchordate)->modify('first day of this month')->setTime(0, 0, 0);
            $end = (clone $anchordate)->modify('last day of this month')->setTime(23, 59, 59);
            $prevstart = (clone $start)->modify('-1 month');
            $prevend = (clone $prevstart)->modify('last day of this month')->setTime(23, 59, 59);
            $prev = (clone $anchordate)->modify('-1 month')->format('Y-m-d');
            $next = (clone $anchordate)->modify('+1 month')->format('Y-m-d');
            $label = userdate($start->getTimestamp(), get_string('strftimemonthyear', 'langconfig'));
        } else if ($view === self::VIEW_CUSTOM) {
            if (empty($datefrom) && empty($dateto)) {
                $dayofweek = (int) $anchordate->format('N');
                $start = (clone $anchordate)->modify('-' . ($dayofweek - 1) . ' days')->setTime(0, 0, 0);
                $end = (clone $start)->modify('+6 days')->setTime(23, 59, 59);
            } else {
                $startts = (int) ($datefrom ?: usergetmidnight(time()));
                if ($dateto) {
                    $endts = (int) $dateto;
                } else {
                    $dateparts = usergetdate($startts);
                    $endts = make_timestamp($dateparts['year'], $dateparts['mon'], $dateparts['mday'], 23, 59, 59);
                }
                $start = (new \DateTime())->setTimestamp($startts);
                $end = (new \DateTime())->setTimestamp($endts);
            }

            if (($end->getTimestamp() - $start->getTimestamp()) > self::MAX_CUSTOM_RANGE_SECONDS) {
                throw new \moodle_exception('tutor_usage_report_range_too_long', 'local_dixeo');
            }

            $prev = null;
            $next = null;
            $prevstart = null;
            $prevend = null;
            $label = userdate($start->getTimestamp(), '%d %b %Y') . ' - ' . userdate($end->getTimestamp(), '%d %b %Y');
        } else {
            $dayofweek = (int) $anchordate->format('N');
            $start = (clone $anchordate)->modify('-' . ($dayofweek - 1) . ' days')->setTime(0, 0, 0);
            $end = (clone $start)->modify('+6 days')->setTime(23, 59, 59);
            $prevstart = (clone $start)->modify('-7 days');
            $prevend = (clone $prevstart)->modify('+6 days')->setTime(23, 59, 59);
            $prev = $prevstart->format('Y-m-d');
            $next = (clone $start)->modify('+7 days')->format('Y-m-d');
            $label = userdate($start->getTimestamp(), '%d %b') . ' - ' . userdate($end->getTimestamp(), '%d %b %Y');
        }

        $result = [
            'timestart' => $start->getTimestamp(),
            'timeend' => $end->getTimestamp(),
            'label' => $label,
            'prevanchor' => $prev ?? null,
            'nextanchor' => $next ?? null,
        ];

        if ($view !== self::VIEW_CUSTOM) {
            $result['prevtimestart'] = $prevstart->getTimestamp();
            $result['prevtimeend'] = $prevend->getTimestamp();
        }

        return $result;
    }

    /**
     * Resolve in-scope user ids for adoption and per-user metrics.
     *
     * @param string $level Report level.
     * @param int $courseid Course id for course/user levels.
     * @param int $userid User id for user level.
     * @param int[] $roleids Selected role ids.
     * @return int[]
     */
    public function get_in_scope_userids(string $level, int $courseid, int $userid, array $roleids): array {
        global $DB;

        if ($level === self::LEVEL_USER && $userid > 0) {
            return [$userid];
        }

        if ($level === self::LEVEL_COURSE && $courseid > 0) {
            if ($roleids === []) {
                $sql = "SELECT DISTINCT ue.userid
                          FROM {user_enrolments} ue
                          JOIN {enrol} e ON e.id = ue.enrolid
                         WHERE e.courseid = :courseid
                           AND ue.status = 0";
                return array_map('intval', $DB->get_fieldset_sql($sql, ['courseid' => $courseid]));
            }

            [$rolesql, $roleparams] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'role');
            $sql = "SELECT DISTINCT ue.userid
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON e.id = ue.enrolid
                      JOIN {role_assignments} ra ON ra.userid = ue.userid
                      JOIN {context} ctx ON ctx.id = ra.contextid
                     WHERE e.courseid = :courseid
                       AND ue.status = 0
                       AND ctx.contextlevel = :contextlevel
                       AND ctx.instanceid = :courseid2
                       AND ra.roleid {$rolesql}";

            return array_map('intval', $DB->get_fieldset_sql($sql, array_merge([
                'courseid' => $courseid,
                'courseid2' => $courseid,
                'contextlevel' => CONTEXT_COURSE,
            ], $roleparams)));
        }

        $courseids = self::get_accessible_courseids();
        if ($courseids === []) {
            return [];
        }
        [$courseinsql, $courseparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'scopecourse');

        if ($roleids === []) {
            $sql = "SELECT DISTINCT ue.userid
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON e.id = ue.enrolid
                     WHERE ue.status = 0
                       AND e.courseid {$courseinsql}";
            return array_map('intval', $DB->get_fieldset_sql($sql, $courseparams));
        }

        [$rolesql, $roleparams] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'role');
        $sql = "SELECT DISTINCT ue.userid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {role_assignments} ra ON ra.userid = ue.userid
                  JOIN {context} ctx ON ctx.id = ra.contextid
                 WHERE ue.status = 0
                   AND ctx.contextlevel = :contextlevel
                   AND ctx.instanceid = e.courseid
                   AND ra.roleid {$rolesql}
                   AND e.courseid {$courseinsql}";

        return array_map('intval', $DB->get_fieldset_sql($sql, array_merge([
            'contextlevel' => CONTEXT_COURSE,
        ], $roleparams, $courseparams)));
    }

    /**
     * Get KPI aggregates for the active scope.
     *
     * @param string $level Report level.
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int $timestart Period start.
     * @param int $timeend Period end.
     * @param int[] $roleids Role filter.
     * @param int|null $prevtimestart Previous period start for % change.
     * @param int|null $prevtimeend Previous period end for % change.
     * @param string $view Active view mode (week|month|custom) for change labels.
     * @return array
     */
    public function get_kpis(
        string $level,
        int $courseid,
        int $userid,
        int $timestart,
        int $timeend,
        array $roleids,
        ?int $prevtimestart = null,
        ?int $prevtimeend = null,
        string $view = self::VIEW_WEEK
    ): array {
        $current = $this->compute_kpi_metrics($level, $courseid, $userid, $timestart, $timeend, $roleids);
        $previous = null;
        if ($prevtimestart !== null && $prevtimeend !== null) {
            $previous = $this->compute_kpi_metrics($level, $courseid, $userid, $prevtimestart, $prevtimeend, $roleids);
        }

        return $this->format_kpis($current, $previous, $view);
    }

    /**
     * Stacked bar chart data: messages by mode per day.
     *
     * @param string $level Report level.
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int $timestart Period start.
     * @param int $timeend Period end.
     * @param int[] $roleids Role filter.
     * @return array{labels: string[], datasets: array}
     */
    public function get_stacked_bar_data(
        string $level,
        int $courseid,
        int $userid,
        int $timestart,
        int $timeend,
        array $roleids
    ): array {
        global $DB;

        $scope = $this->build_event_scope_sql($level, $courseid, $userid, $roleids);
        $sql = "SELECT e.id, e.timecreated, e.mode
                  FROM {" . tutor_usage_recorder::TABLE_EVENT . "} e
                 WHERE e.eventtype = :eventtype
                   AND e.timecreated >= :timestart
                   AND e.timecreated <= :timeend
                   AND {$scope['sql']}";
        $params = array_merge([
            'eventtype' => tutor_usage_recorder::EVENT_MESSAGE,
            'timestart' => $timestart,
            'timeend' => $timeend,
        ], $scope['params']);

        $records = $DB->get_recordset_sql($sql, $params);
        $buckets = [];

        foreach ($records as $record) {
            $day = userdate((int) $record->timecreated, '%Y-%m-%d');
            $mode = tutor_message::normalize_mode((string) $record->mode);
            if (!isset($buckets[$day])) {
                $buckets[$day] = array_fill_keys(self::MESSAGE_MODES, 0);
            }
            $buckets[$day][$mode] = ($buckets[$day][$mode] ?? 0) + 1;
        }
        $records->close();

        $labels = [];
        $datasets = [];
        foreach (self::MESSAGE_MODES as $mode) {
            $datasets[$mode] = [];
        }

        $daystart = usergetmidnight($timestart);
        $dayend = usergetmidnight($timeend);
        for ($ts = $daystart; $ts <= $dayend; $ts += DAYSECS) {
            $daykey = userdate($ts, '%Y-%m-%d');
            $labels[] = userdate($ts, '%d %b');
            foreach (self::MESSAGE_MODES as $mode) {
                $datasets[$mode][] = $buckets[$daykey][$mode] ?? 0;
            }
        }

        $formatteddatasets = [];
        foreach (self::MESSAGE_MODES as $mode) {
            $formatteddatasets[] = [
                'label' => $this->get_mode_label($mode),
                'mode' => $mode,
                'data' => $datasets[$mode],
            ];
        }

        return [
            'labels' => $labels,
            'datasets' => $formatteddatasets,
        ];
    }

    /**
     * Heatmap data from message events (Mon–Sun columns, eight 3-hour rows).
     *
     * @param string $level Report level.
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int $timestart Period start.
     * @param int $timeend Period end.
     * @param int[] $roleids Role filter.
     * @return array{rows: array, max: int}
     */
    public function get_heatmap_data(
        string $level,
        int $courseid,
        int $userid,
        int $timestart,
        int $timeend,
        array $roleids
    ): array {
        global $DB;

        $scope = $this->build_event_scope_sql($level, $courseid, $userid, $roleids);
        $sql = "SELECT e.id, e.timecreated
                  FROM {" . tutor_usage_recorder::TABLE_EVENT . "} e
                 WHERE e.eventtype = :eventtype
                   AND e.timecreated >= :timestart
                   AND e.timecreated <= :timeend
                   AND {$scope['sql']}";
        $params = array_merge([
            'eventtype' => tutor_usage_recorder::EVENT_MESSAGE,
            'timestart' => $timestart,
            'timeend' => $timeend,
        ], $scope['params']);

        $records = $DB->get_recordset_sql($sql, $params);
        $grid = [];
        $max = 0;

        foreach ($records as $record) {
            $date = usergetdate((int) $record->timecreated);
            $dow = ((int) $date['wday'] + 6) % 7;
            $slot = min(self::HEATMAP_SLOT_COUNT - 1, (int) floor(((int) $date['hours']) / 3));
            $grid[$slot][$dow] = ($grid[$slot][$dow] ?? 0) + 1;
            $max = max($max, $grid[$slot][$dow]);
        }
        $records->close();

        $rows = [];
        for ($slot = 0; $slot < self::HEATMAP_SLOT_COUNT; $slot++) {
            $starthour = $slot * 3;
            $endhour = $starthour + 2;
            $cells = [];
            for ($dow = 0; $dow < 7; $dow++) {
                $value = $grid[$slot][$dow] ?? 0;
                $cells[] = [
                    'value' => $value,
                    'intensity' => $max > 0 ? round($value / $max, 2) : 0,
                ];
            }
            $rows[] = [
                'label' => sprintf('%02d:00–%02d:59', $starthour, $endhour),
                'cells' => $cells,
            ];
        }

        return [
            'rows' => $rows,
            'max' => $max,
        ];
    }

    /**
     * Paginated summary table rows for the active level.
     *
     * @param string $level Report level.
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int $timestart Period start.
     * @param int $timeend Period end.
     * @param int[] $roleids Role filter.
     * @param int $page Page number (0-based).
     * @param int $perpage Rows per page.
     * @return array{rows: array, total: int}
     */
    public function get_rows(
        string $level,
        int $courseid,
        int $userid,
        int $timestart,
        int $timeend,
        array $roleids,
        int $page = 0,
        int $perpage = 50
    ): array {
        $aggregates = $this->aggregate_row_metrics($level, $courseid, $userid, $timestart, $timeend, $roleids);
        $rows = [];

        foreach ($aggregates as $key => $metrics) {
            $rows[] = $this->format_summary_row($level, $key, $metrics, $courseid, $roleids);
        }

        usort($rows, static function (array $a, array $b): int {
            $bymessages = ($b['messages'] ?? 0) <=> ($a['messages'] ?? 0);
            if ($bymessages !== 0) {
                return $bymessages;
            }
            return strcasecmp((string) ($a['namelabel'] ?? ''), (string) ($b['namelabel'] ?? ''));
        });

        $total = count($rows);
        $offset = max(0, $page) * max(1, $perpage);
        $rows = array_slice($rows, $offset, max(1, $perpage));

        return [
            'rows' => $rows,
            'total' => $total,
        ];
    }

    /**
     * Column definitions for dataformat export.
     *
     * @param string $level Report level (site|course|user).
     * @return array<string, string>
     */
    public function get_export_columns(string $level = self::LEVEL_SITE): array {
        $columns = [
            'name' => get_string('tutor_usage_report_column_name', 'local_dixeo'),
        ];
        if ($level === self::LEVEL_SITE) {
            $columns['adoption'] = get_string('tutor_usage_report_kpi_adoption', 'local_dixeo');
        }
        if ($level === self::LEVEL_USER) {
            $columns['moduletype'] = get_string('tutor_usage_report_column_moduletype', 'local_dixeo');
        }
        $columns['messages'] = get_string('tutor_usage_report_column_messages', 'local_dixeo');
        $columns['normal'] = get_string('tutor_usage_report_column_standard', 'local_dixeo');
        $columns['guide'] = get_string('tutor_usage_report_column_guide', 'local_dixeo');
        $columns['quiz'] = get_string('tutor_usage_report_column_quiz', 'local_dixeo');
        $columns['teach'] = get_string('tutor_usage_report_column_teach', 'local_dixeo');
        $columns['sessions'] = get_string('tutor_usage_report_column_sessions', 'local_dixeo');
        $columns['lastactive'] = get_string('tutor_usage_report_column_lastactive', 'local_dixeo');
        return $columns;
    }

    /**
     * Export rows in report order.
     *
     * @param string $level Report level.
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int $timestart Period start.
     * @param int $timeend Period end.
     * @param int[] $roleids Role filter.
     * @return array<int, array<string, string|int>>
     */
    public function get_export_rows(
        string $level,
        int $courseid,
        int $userid,
        int $timestart,
        int $timeend,
        array $roleids
    ): array {
        $result = $this->get_rows($level, $courseid, $userid, $timestart, $timeend, $roleids, 0, PHP_INT_MAX);
        $export = [];

        foreach ($result['rows'] as $row) {
            $item = [
                'name' => $row['namelabel'],
            ];
            if ($level === self::LEVEL_SITE) {
                $item['adoption'] = $row['adoptionformatted'] ?? '';
            }
            if ($level === self::LEVEL_USER) {
                $item['moduletype'] = $row['moduletypelabel'] ?? '';
            }
            $item['messages'] = $row['messagesformatted'];
            $item['normal'] = $row['normalformatted'];
            $item['guide'] = $row['guideformatted'];
            $item['quiz'] = $row['quizformatted'];
            $item['teach'] = $row['teachformatted'];
            $item['sessions'] = $row['sessionsformatted'];
            $item['lastactive'] = $row['lastactiveformatted'];
            $export[] = $item;
        }

        return $export;
    }

    /**
     * Compute raw KPI metrics for one period.
     *
     * @param string $level Report level.
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int $timestart Period start.
     * @param int $timeend Period end.
     * @param int[] $roleids Role filter.
     * @return array
     */
    protected function compute_kpi_metrics(
        string $level,
        int $courseid,
        int $userid,
        int $timestart,
        int $timeend,
        array $roleids
    ): array {
        global $DB;

        $scopeusers = $this->get_in_scope_userids($level, $courseid, $userid, $roleids);
        $totalusers = count($scopeusers);

        $eventscope = $this->build_event_scope_sql($level, $courseid, $userid, $roleids);
        $eventsql = "SELECT e.userid, e.mode, e.eventtype, e.timecreated
                       FROM {" . tutor_usage_recorder::TABLE_EVENT . "} e
                      WHERE e.timecreated >= :timestart
                        AND e.timecreated <= :timeend
                        AND {$eventscope['sql']}";
        $eventparams = array_merge([
            'timestart' => $timestart,
            'timeend' => $timeend,
        ], $eventscope['params']);

        $events = $DB->get_recordset_sql($eventsql, $eventparams);

        $messages = 0;
        $modecounts = array_fill_keys(self::MESSAGE_MODES, 0);
        $messagesbymodebyuser = array_fill_keys(self::MESSAGE_MODES, []);
        $quizcreated = 0;
        $lessoncreated = 0;
        $activeusers = [];
        $messagesbyuser = array_fill_keys($scopeusers, 0);

        foreach ($events as $event) {
            $uid = (int) $event->userid;
            if ($event->eventtype === tutor_usage_recorder::EVENT_MESSAGE) {
                $messages++;
                $mode = tutor_message::normalize_mode((string) $event->mode);
                $modecounts[$mode] = ($modecounts[$mode] ?? 0) + 1;
                $activeusers[$uid] = true;
                if (array_key_exists($uid, $messagesbyuser)) {
                    $messagesbyuser[$uid]++;
                }
                if (isset($messagesbymodebyuser[$mode])) {
                    $messagesbymodebyuser[$mode][$uid] = ($messagesbymodebyuser[$mode][$uid] ?? 0) + 1;
                }
            } else if ($event->eventtype === tutor_usage_recorder::EVENT_QUIZ_CREATED) {
                $quizcreated++;
                $activeusers[$uid] = true;
            } else if ($event->eventtype === tutor_usage_recorder::EVENT_LESSON_CREATED) {
                $lessoncreated++;
                $activeusers[$uid] = true;
            }
        }
        $events->close();

        $sessionmetrics = $this->get_session_metrics($level, $courseid, $userid, $timestart, $timeend, $roleids);
        foreach ($sessionmetrics['sessionsbyuser'] as $uid => $count) {
            if ($count > 0) {
                $activeusers[$uid] = true;
            }
        }

        $adoption = $totalusers > 0 ? (count($activeusers) / $totalusers) * 100 : 0;
        $sessionsbyuser = $sessionmetrics['sessionsbyuser'];
        $durationbyuser = $sessionmetrics['durationbyuser'];

        // Avg/median are among active users only so inactive zeros do not dilute the stats.
        $activeuserids = array_map('intval', array_keys($activeusers));
        $sessionsperuser = $this->build_per_user_values($activeuserids, $sessionsbyuser);
        $durationperuser = $this->build_per_user_values($activeuserids, $durationbyuser);
        $messagesperuser = $this->build_per_user_values($activeuserids, $messagesbyuser);

        // Mode avg/median among users who sent that mode (standard / guide).
        $modeavg = array_fill_keys(self::MESSAGE_MODES, 0.0);
        $modemedian = array_fill_keys(self::MESSAGE_MODES, 0.0);
        foreach ([tutor_message::MODE_NORMAL, tutor_message::MODE_GUIDE] as $mode) {
            $modevalues = array_map('floatval', array_values($messagesbymodebyuser[$mode]));
            $modeavg[$mode] = $this->average($modevalues);
            $modemedian[$mode] = $this->median($modevalues);
        }

        return [
            'adoption' => $adoption,
            'activeusers' => count($activeusers),
            'totalusers' => $totalusers,
            'messages' => $messages,
            'modecounts' => $modecounts,
            'modeavg' => $modeavg,
            'modemedian' => $modemedian,
            'quizcreated' => $quizcreated,
            'lessoncreated' => $lessoncreated,
            'sessions' => $sessionmetrics['sessions'],
            'duration' => $sessionmetrics['duration'],
            'avgmessages' => $this->average($messagesperuser),
            'medianmessages' => $this->median($messagesperuser),
            'avgsessions' => $this->average($sessionsperuser),
            'mediansessions' => $this->median($sessionsperuser),
            'avgduration' => $this->average($durationperuser),
            'medianduration' => $this->median($durationperuser),
        ];
    }

    /**
     * Format KPI metrics for template output, including optional % change.
     *
     * @param array $current Current period metrics.
     * @param array|null $previous Previous period metrics.
     * @param string $view Active view mode (week|month|custom).
     * @return array
     */
    protected function format_kpis(array $current, ?array $previous, string $view = self::VIEW_WEEK): array {
        $changesuffix = '';
        if ($view === self::VIEW_WEEK) {
            $changesuffix = ' ' . get_string('tutor_usage_report_change_since_week', 'local_dixeo');
        } else if ($view === self::VIEW_MONTH) {
            $changesuffix = ' ' . get_string('tutor_usage_report_change_since_month', 'local_dixeo');
        }

        $formatchange = static function (float $cur, float $prev) use ($changesuffix): ?array {
            if ($prev == 0.0) {
                $pct = $cur > 0 ? 100.0 : 0.0;
            } else {
                $pct = (($cur - $prev) / $prev) * 100;
            }
            return [
                'value' => ($pct >= 0 ? '+' : '') . number_format($pct, 1) . '%' . $changesuffix,
                'positive' => $pct >= 0,
                'negative' => $pct < 0,
            ];
        };

        $build = function (string $key, callable $formatter) use ($current, $previous, $formatchange) {
            $item = [
                'value' => $formatter($current[$key] ?? 0),
                'raw' => $current[$key] ?? 0,
            ];
            if ($previous !== null) {
                $item['change'] = $formatchange((float) ($current[$key] ?? 0), (float) ($previous[$key] ?? 0));
            }
            return $item;
        };

        $activeformatted = number_format((int) ($current['activeusers'] ?? 0));
        $totalformatted = number_format((int) ($current['totalusers'] ?? 0));
        $inactiveformatted = number_format(max(
            0,
            (int) ($current['totalusers'] ?? 0) - (int) ($current['activeusers'] ?? 0)
        ));
        $avgmessages = number_format((float) ($current['avgmessages'] ?? 0), 1);
        $medianmessages = number_format((float) ($current['medianmessages'] ?? 0), 1);
        $avgsessions = number_format((float) ($current['avgsessions'] ?? 0), 1);
        $mediansessions = number_format((float) ($current['mediansessions'] ?? 0), 1);
        $avgduration = self::format_duration((int) round((float) ($current['avgduration'] ?? 0)));
        $medianduration = self::format_duration((int) round((float) ($current['medianduration'] ?? 0)));

        $adoption = $build('adoption', static fn($v) => number_format($v, 1) . '%');
        $adoption['tooltip'] = get_string('tutor_usage_report_kpi_adoption_secondary', 'local_dixeo', (object) [
            'active' => $activeformatted,
            'inactive' => $inactiveformatted,
            'total' => $totalformatted,
        ]);

        $messages = $build('messages', static fn($v) => number_format((int) $v));
        $messages['tooltip'] = get_string('tutor_usage_report_kpi_messages_secondary', 'local_dixeo', (object) [
            'median' => $medianmessages,
            'average' => $avgmessages,
        ]);

        $sessions = $build('sessions', static fn($v) => number_format((int) $v));
        $sessions['tooltip'] = get_string('tutor_usage_report_kpi_sessions_secondary', 'local_dixeo', (object) [
            'median' => $mediansessions,
            'average' => $avgsessions,
        ]);

        $duration = $build('duration', static fn($v) => self::format_duration((int) $v));
        $duration['tooltip'] = get_string('tutor_usage_report_kpi_duration_secondary', 'local_dixeo', (object) [
            'median' => $medianduration,
            'average' => $avgduration,
        ]);

        return [
            'adoption' => $adoption,
            'active' => [
                'value' => $activeformatted,
                'raw' => (int) ($current['activeusers'] ?? 0),
            ],
            'inactive' => [
                'value' => $inactiveformatted,
                'raw' => max(0, (int) ($current['totalusers'] ?? 0) - (int) ($current['activeusers'] ?? 0)),
            ],
            'total' => [
                'value' => $totalformatted,
                'raw' => (int) ($current['totalusers'] ?? 0),
            ],
            'messages' => $messages,
            'sessions' => $sessions,
            'duration' => $duration,
            'modes' => array_map(function (string $mode) use ($current, $previous, $formatchange) {
                $value = (int) ($current['modecounts'][$mode] ?? 0);
                $tooltip = '';
                if ($mode === tutor_message::MODE_NORMAL || $mode === tutor_message::MODE_GUIDE) {
                    $tooltip = get_string('tutor_usage_report_kpi_messages_secondary', 'local_dixeo', (object) [
                        'median' => number_format((float) ($current['modemedian'][$mode] ?? 0), 1),
                        'average' => number_format((float) ($current['modeavg'][$mode] ?? 0), 1),
                    ]);
                } else if ($mode === tutor_message::MODE_QUIZ) {
                    $tooltip = get_string(
                        'tutor_usage_report_kpi_mode_secondary_quiz',
                        'local_dixeo',
                        number_format((int) ($current['quizcreated'] ?? 0))
                    );
                } else if ($mode === tutor_message::MODE_TEACH) {
                    $tooltip = get_string(
                        'tutor_usage_report_kpi_mode_secondary_lesson',
                        'local_dixeo',
                        number_format((int) ($current['lessoncreated'] ?? 0))
                    );
                }

                $item = [
                    'mode' => $mode,
                    'label' => $this->get_mode_label($mode),
                    'value' => number_format($value),
                    'raw' => $value,
                    'isquiz' => $mode === tutor_message::MODE_QUIZ,
                    'isteach' => $mode === tutor_message::MODE_TEACH,
                    'tooltip' => $tooltip,
                    'hastooltip' => $tooltip !== '',
                ];
                if ($previous !== null) {
                    $prevvalue = (int) ($previous['modecounts'][$mode] ?? 0);
                    $item['change'] = $formatchange((float) $value, (float) $prevvalue);
                }
                return $item;
            }, self::MESSAGE_MODES),
        ];
    }

    /**
     * Aggregate session counts and duration for a period.
     *
     * @param string $level Report level.
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int $timestart Period start.
     * @param int $timeend Period end.
     * @param int[] $roleids Role filter.
     * @return array{sessions: int, duration: int, sessionsbyuser: array, durationbyuser: array}
     */
    protected function get_session_metrics(
        string $level,
        int $courseid,
        int $userid,
        int $timestart,
        int $timeend,
        array $roleids
    ): array {
        global $DB;

        $sessionscope = $this->build_session_scope_sql($level, $courseid, $userid, $roleids);
        $sql = "SELECT s.id, s.userid, s.duration
                  FROM {" . tutor_usage_aggregator::TABLE_SESSION . "} s
                 WHERE s.timestart >= :timestart
                   AND s.timestart <= :timeend
                   AND {$sessionscope['sql']}";
        $params = array_merge([
            'timestart' => $timestart,
            'timeend' => $timeend,
        ], $sessionscope['params']);

        $records = $DB->get_recordset_sql($sql, $params);
        $sessions = 0;
        $duration = 0;
        $sessionsbyuser = [];
        $durationbyuser = [];

        foreach ($records as $record) {
            $uid = (int) $record->userid;
            $sessions++;
            $duration += (int) $record->duration;
            $sessionsbyuser[$uid] = ($sessionsbyuser[$uid] ?? 0) + 1;
            $durationbyuser[$uid] = ($durationbyuser[$uid] ?? 0) + (int) $record->duration;
        }
        $records->close();

        $openscope = $this->build_open_session_scope_sql($level, $courseid, $userid, $roleids);
        $opensql = "SELECT o.id, o.userid, o.timestart, o.lastmessage
                    FROM {" . tutor_usage_aggregator::TABLE_OPEN . "} o
                   WHERE {$openscope['sql']}";
        $openrecords = $DB->get_recordset_sql($opensql, $openscope['params']);

        foreach ($openrecords as $open) {
            $lastmessage = (int) $open->lastmessage;
            $start = (int) $open->timestart;
            if ($lastmessage < $timestart) {
                continue;
            }
            if ($start > $timeend) {
                continue;
            }

            $uid = (int) $open->userid;
            $timedout = ($timeend - $lastmessage) > tutor_usage_aggregator::SESSION_TIMEOUT;
            $effectiveend = $timedout ? $lastmessage : min($timeend, $lastmessage);
            if ($effectiveend < $timestart) {
                continue;
            }

            $sessionduration = tutor_usage_aggregator::calculate_duration(
                max($start, $timestart),
                $effectiveend
            );

            $sessions++;
            $duration += $sessionduration;
            $sessionsbyuser[$uid] = ($sessionsbyuser[$uid] ?? 0) + 1;
            $durationbyuser[$uid] = ($durationbyuser[$uid] ?? 0) + $sessionduration;
        }
        $openrecords->close();

        return [
            'sessions' => $sessions,
            'duration' => $duration,
            'sessionsbyuser' => $sessionsbyuser,
            'durationbyuser' => $durationbyuser,
        ];
    }

    /**
     * Aggregate summary-table metrics keyed by entity id.
     *
     * @param string $level Report level.
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int $timestart Period start.
     * @param int $timeend Period end.
     * @param int[] $roleids Role filter.
     * @return array<int|string, array>
     */
    protected function aggregate_row_metrics(
        string $level,
        int $courseid,
        int $userid,
        int $timestart,
        int $timeend,
        array $roleids
    ): array {
        global $DB;

        $keyfield = match ($level) {
            self::LEVEL_COURSE => 'userid',
            self::LEVEL_USER => 'cmid',
            default => 'courseid',
        };

        // Always list in-scope entities, even with zero usage, so navigation stays available.
        $aggregates = $this->seed_empty_row_metrics($level, $courseid, $userid, $roleids);
        $eventscope = $this->build_event_scope_sql($level, $courseid, $userid, $roleids);
        $eventsql = "SELECT e.id, e.{$keyfield} AS entityid, e.userid, e.mode, e.eventtype, e.timecreated
                       FROM {" . tutor_usage_recorder::TABLE_EVENT . "} e
                      WHERE e.timecreated >= :timestart
                        AND e.timecreated <= :timeend
                        AND {$eventscope['sql']}";
        $eventparams = array_merge([
            'timestart' => $timestart,
            'timeend' => $timeend,
        ], $eventscope['params']);

        $events = $DB->get_recordset_sql($eventsql, $eventparams);
        foreach ($events as $event) {
            $key = (int) $event->entityid;
            if (!isset($aggregates[$key])) {
                // Keep unexpected ids (e.g. deleted cm) so historical usage is not dropped.
                $aggregates[$key] = $this->empty_row_metrics();
            }
            $eventuserid = (int) $event->userid;
            if ($event->eventtype === tutor_usage_recorder::EVENT_MESSAGE) {
                $aggregates[$key]['messages']++;
                $mode = tutor_message::normalize_mode((string) $event->mode);
                if ($mode === tutor_message::MODE_NORMAL) {
                    $aggregates[$key]['normal']++;
                } else if ($mode === tutor_message::MODE_GUIDE) {
                    $aggregates[$key]['guide']++;
                } else if ($mode === tutor_message::MODE_QUIZ) {
                    $aggregates[$key]['quiz']++;
                } else if ($mode === tutor_message::MODE_TEACH) {
                    $aggregates[$key]['teach']++;
                }
                $this->mark_row_active_user($aggregates, $key, $eventuserid);
            } else if (
                $event->eventtype === tutor_usage_recorder::EVENT_QUIZ_CREATED
                || $event->eventtype === tutor_usage_recorder::EVENT_LESSON_CREATED
            ) {
                $this->mark_row_active_user($aggregates, $key, $eventuserid);
            }
            $aggregates[$key]['lastactive'] = max($aggregates[$key]['lastactive'], (int) $event->timecreated);
        }
        $events->close();

        $sessionfield = match ($level) {
            self::LEVEL_COURSE => 'userid',
            self::LEVEL_USER => 'cmid',
            default => 'courseid',
        };

        if ($level === self::LEVEL_USER) {
            $this->add_user_module_sessions($aggregates, $courseid, $userid, $timestart, $timeend, $roleids);
            return $aggregates;
        }

        $sessionscope = $this->build_session_scope_sql($level, $courseid, $userid, $roleids);
        $sessionsql = "SELECT s.id, s.{$sessionfield} AS entityid, s.userid, s.duration, s.timeend
                         FROM {" . tutor_usage_aggregator::TABLE_SESSION . "} s
                        WHERE s.timestart >= :timestart
                          AND s.timestart <= :timeend
                          AND {$sessionscope['sql']}";
        $sessionparams = array_merge([
            'timestart' => $timestart,
            'timeend' => $timeend,
        ], $sessionscope['params']);

        $sessions = $DB->get_recordset_sql($sessionsql, $sessionparams);
        foreach ($sessions as $session) {
            $key = (int) $session->entityid;
            if (!isset($aggregates[$key])) {
                $aggregates[$key] = $this->empty_row_metrics();
            }
            $aggregates[$key]['sessions']++;
            $aggregates[$key]['duration'] = ($aggregates[$key]['duration'] ?? 0) + (int) $session->duration;
            $aggregates[$key]['lastactive'] = max($aggregates[$key]['lastactive'], (int) $session->timeend);
            $this->mark_row_active_user($aggregates, $key, (int) $session->userid);
        }
        $sessions->close();

        $this->add_open_sessions_to_row_aggregates(
            $aggregates,
            $level,
            $courseid,
            $userid,
            $timestart,
            $timeend,
            $roleids,
            $sessionfield
        );

        return $aggregates;
    }

    /**
     * Seed zeroed summary rows for every in-scope entity at the current level.
     *
     * @param string $level Report level.
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int[] $roleids Role filter.
     * @return array<int, array>
     */
    protected function seed_empty_row_metrics(
        string $level,
        int $courseid,
        int $userid,
        array $roleids
    ): array {
        $aggregates = [];
        foreach ($this->get_summary_entity_ids($level, $courseid, $userid, $roleids) as $entityid) {
            $aggregates[(int) $entityid] = $this->empty_row_metrics();
        }
        return $aggregates;
    }

    /**
     * Entity ids that must appear in the summary table for the active level.
     *
     * @param string $level Report level.
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int[] $roleids Role filter.
     * @return int[]
     */
    public function get_summary_entity_ids(
        string $level,
        int $courseid,
        int $userid,
        array $roleids
    ): array {
        if ($level === self::LEVEL_SITE) {
            return self::get_accessible_courseids();
        }

        if ($level === self::LEVEL_COURSE) {
            return $this->get_in_scope_userids($level, $courseid, $userid, $roleids);
        }

        // User level: course page + all course activities.
        return $this->get_course_activity_entity_ids($courseid);
    }

    /**
     * Course page (cmid 0) plus visible course module ids for the user-level table.
     *
     * @param int $courseid Course id.
     * @return int[]
     */
    protected function get_course_activity_entity_ids(int $courseid): array {
        $ids = [0];
        if ($courseid < 1) {
            return $ids;
        }

        try {
            $modinfo = get_fast_modinfo($courseid);
        } catch (\Throwable $e) {
            return $ids;
        }

        foreach ($modinfo->get_cms() as $cm) {
            if (!empty($cm->deletioninprogress)) {
                continue;
            }
            $ids[] = (int) $cm->id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Add provisional open-session counts to summary row aggregates.
     *
     * @param array $aggregates Aggregates keyed by entity id (by reference).
     * @param string $level Report level.
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int $timestart Period start.
     * @param int $timeend Period end.
     * @param int[] $roleids Role filter.
     * @param string $sessionfield Entity field name.
     */
    protected function add_open_sessions_to_row_aggregates(
        array &$aggregates,
        string $level,
        int $courseid,
        int $userid,
        int $timestart,
        int $timeend,
        array $roleids,
        string $sessionfield
    ): void {
        global $DB;

        $openscope = $this->build_open_session_scope_sql($level, $courseid, $userid, $roleids);
        $opensql = "SELECT o.id, o.userid, o.courseid, o.timestart, o.lastmessage
                      FROM {" . tutor_usage_aggregator::TABLE_OPEN . "} o
                     WHERE {$openscope['sql']}";
        $openrecords = $DB->get_recordset_sql($opensql, $openscope['params']);

        foreach ($openrecords as $open) {
            $lastmessage = (int) $open->lastmessage;
            $start = (int) $open->timestart;
            if ($lastmessage < $timestart || $start > $timeend) {
                continue;
            }

            $key = match ($sessionfield) {
                'userid' => (int) $open->userid,
                'courseid' => (int) $open->courseid,
                default => 0,
            };

            if (!isset($aggregates[$key])) {
                $aggregates[$key] = $this->empty_row_metrics();
            }

            $timedout = ($timeend - $lastmessage) > tutor_usage_aggregator::SESSION_TIMEOUT;
            $effectiveend = $timedout ? $lastmessage : min($timeend, $lastmessage);
            $sessionduration = tutor_usage_aggregator::calculate_duration(
                max($start, $timestart),
                $effectiveend
            );

            $aggregates[$key]['sessions']++;
            $aggregates[$key]['duration'] = ($aggregates[$key]['duration'] ?? 0) + $sessionduration;
            $aggregates[$key]['lastactive'] = max($aggregates[$key]['lastactive'], $lastmessage);
            $this->mark_row_active_user($aggregates, $key, (int) $open->userid);
        }
        $openrecords->close();
    }

    /**
     * Add session counts to user-level module rows (sessions are course-scoped only).
     *
     * @param array $aggregates Existing aggregates keyed by cmid.
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int $timestart Period start.
     * @param int $timeend Period end.
     * @param int[] $roleids Role filter.
     */
    protected function add_user_module_sessions(
        array &$aggregates,
        int $courseid,
        int $userid,
        int $timestart,
        int $timeend,
        array $roleids
    ): void {
        global $DB;

        $metrics = $this->get_session_metrics(self::LEVEL_USER, $courseid, $userid, $timestart, $timeend, $roleids);
        if (($metrics['sessions'] ?? 0) < 1) {
            return;
        }

        $cmid = 0;
        if (!isset($aggregates[$cmid])) {
            $aggregates[$cmid] = $this->empty_row_metrics();
        }
        $aggregates[$cmid]['sessions'] += (int) $metrics['sessions'];
        $aggregates[$cmid]['duration'] = ($aggregates[$cmid]['duration'] ?? 0) + (int) ($metrics['duration'] ?? 0);
    }

    /**
     * Format one summary-table row.
     *
     * @param string $level Report level.
     * @param int|string $key Entity key.
     * @param array $metrics Row metrics.
     * @param int $courseid Course id for user level labels.
     * @param int[] $roleids Role filter (used for site-level adoption).
     * @return array
     */
    protected function format_summary_row(
        string $level,
        int|string $key,
        array $metrics,
        int $courseid,
        array $roleids = []
    ): array {
        global $DB;

        $namelabel = (string) $key;
        $url = null;
        $moduletype = '';
        $moduletypelabel = '';
        $isuserlevel = $level === self::LEVEL_USER;
        $issitelevel = $level === self::LEVEL_SITE;
        $adoption = 0.0;
        $adoptionformatted = '';
        $adoptiontooltip = '';
        $activeusers = 0;
        $totalusers = 0;

        if ($level === self::LEVEL_SITE) {
            $course = $DB->get_record('course', ['id' => (int) $key], '*', IGNORE_MISSING);
            if ($course) {
                $namelabel = format_string($course->fullname);
                $url = self::build_report_url([
                    'level' => self::LEVEL_COURSE,
                    'courseid' => (int) $course->id,
                ]);
            }
            $adoptionstats = $this->get_course_adoption_stats((int) $key, $metrics, $roleids);
            $adoption = $adoptionstats['adoption'];
            $activeusers = $adoptionstats['active'];
            $totalusers = $adoptionstats['total'];
            $adoptionformatted = number_format($adoption, 1) . '%';
            $adoptiontooltip = get_string('tutor_usage_report_cell_tooltip_adoption', 'local_dixeo', (object) [
                'active' => number_format($activeusers),
                'total' => number_format($totalusers),
            ]);
        } else if ($level === self::LEVEL_COURSE) {
            $user = \core_user::get_user((int) $key, '*', IGNORE_MISSING);
            if ($user) {
                $namelabel = fullname($user);
                $url = self::build_report_url([
                    'level' => self::LEVEL_USER,
                    'courseid' => $courseid,
                    'userid' => (int) $user->id,
                ]);
            }
        } else {
            $cmid = (int) $key;
            if ($cmid > 0) {
                $cm = get_coursemodule_from_id('', $cmid, 0, false, IGNORE_MISSING);
                if ($cm) {
                    $namelabel = format_string($cm->name);
                    $moduletype = (string) $cm->modname;
                    $moduletypelabel = get_string('modulename', $cm->modname);
                    $url = (new \moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]))->out(false);
                }
            } else {
                $namelabel = get_string('tutor_usage_report_course_page', 'local_dixeo');
                $moduletype = 'course';
                $moduletypelabel = get_string('course');
                $url = (new \moodle_url('/course/view.php', ['id' => $courseid]))->out(false);
            }
        }

        $messages = (int) $metrics['messages'];
        $normal = (int) ($metrics['normal'] ?? 0);
        $guide = (int) $metrics['guide'];
        $quiz = (int) $metrics['quiz'];
        $teach = (int) $metrics['teach'];
        $sessions = (int) $metrics['sessions'];
        $duration = (int) ($metrics['duration'] ?? 0);
        $avgduration = $sessions > 0 ? (int) round($duration / $sessions) : 0;

        return [
            'key' => (int) $key,
            'namelabel' => $namelabel,
            'url' => $url,
            'isuserlevel' => $isuserlevel,
            'issitelevel' => $issitelevel,
            'moduletype' => $moduletype,
            'moduletypelabel' => $moduletypelabel,
            'adoption' => $adoption,
            'adoptionformatted' => $adoptionformatted,
            'adoptiontooltip' => $adoptiontooltip,
            'messages' => $messages,
            'messagesformatted' => number_format($messages),
            'normal' => $normal,
            'normalformatted' => number_format($normal),
            'normaltooltip' => $this->format_mode_pct($normal, $messages),
            'guide' => $guide,
            'guideformatted' => number_format($guide),
            'guidetooltip' => $this->format_mode_pct($guide, $messages),
            'quiz' => $quiz,
            'quizformatted' => number_format($quiz),
            'quiztooltip' => $this->format_mode_pct($quiz, $messages),
            'teach' => $teach,
            'teachformatted' => number_format($teach),
            'teachtooltip' => $this->format_mode_pct($teach, $messages),
            'sessions' => $sessions,
            'sessionsformatted' => number_format($sessions),
            'sessionstooltip' => get_string(
                'tutor_usage_report_cell_tooltip_sessions',
                'local_dixeo',
                self::format_duration($avgduration)
            ),
            'lastactive' => (int) $metrics['lastactive'],
            'lastactiveformatted' => !empty($metrics['lastactive'])
                ? userdate((int) $metrics['lastactive'], get_string('strftimedatetime', 'langconfig'))
                : get_string('never', 'moodle'),
        ];
    }

    /**
     * Compute course adoption stats for a site-level summary row.
     *
     * @param int $courseid Course id.
     * @param array $metrics Row metrics including activeuserids.
     * @param int[] $roleids Role filter.
     * @return array{adoption: float, active: int, total: int}
     */
    protected function get_course_adoption_stats(int $courseid, array $metrics, array $roleids): array {
        $scopeusers = $this->get_in_scope_userids(self::LEVEL_COURSE, $courseid, 0, $roleids);
        $total = count($scopeusers);
        if ($total < 1) {
            return [
                'adoption' => 0.0,
                'active' => 0,
                'total' => 0,
            ];
        }

        $scopeflip = array_flip($scopeusers);
        $active = 0;
        foreach (array_keys($metrics['activeuserids'] ?? []) as $uid) {
            if (isset($scopeflip[(int) $uid])) {
                $active++;
            }
        }

        return [
            'adoption' => ($active / $total) * 100,
            'active' => $active,
            'total' => $total,
        ];
    }

    /**
     * Mark a user as active on a summary-row aggregate.
     *
     * @param array $aggregates Aggregates keyed by entity id (by reference).
     * @param int $key Entity key.
     * @param int $userid User id.
     */
    protected function mark_row_active_user(array &$aggregates, int $key, int $userid): void {
        if ($userid < 1) {
            return;
        }
        if (!isset($aggregates[$key])) {
            $aggregates[$key] = $this->empty_row_metrics();
        }
        $aggregates[$key]['activeuserids'][$userid] = true;
    }

    /**
     * Build SQL scope for event queries.
     *
     * @param string $level Report level.
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int[] $roleids Role filter.
     * @return array{sql: string, params: array}
     */
    protected function build_event_scope_sql(string $level, int $courseid, int $userid, array $roleids): array {
        global $DB;

        $conditions = ['1 = 1'];
        $params = [];

        if ($level === self::LEVEL_COURSE && $courseid > 0) {
            $conditions[] = 'e.courseid = :courseid';
            $params['courseid'] = $courseid;
        } else if ($level === self::LEVEL_USER) {
            $conditions[] = 'e.courseid = :courseid';
            $conditions[] = 'e.userid = :userid';
            $params['courseid'] = $courseid;
            $params['userid'] = $userid;
        } else if ($level === self::LEVEL_SITE) {
            $courseids = self::get_accessible_courseids();
            if ($courseids === []) {
                $conditions[] = '1 = 0';
            } else {
                [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'sitecourse');
                $conditions[] = "e.courseid {$insql}";
                $params = array_merge($params, $inparams);
            }
        }

        $userids = $this->get_in_scope_userids($level, $courseid, $userid, $roleids);
        if ($level !== self::LEVEL_USER && $userids !== []) {
            [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'scopeuser');
            $conditions[] = "e.userid {$insql}";
            $params = array_merge($params, $inparams);
        } else if ($level !== self::LEVEL_USER && $userids === []) {
            $conditions[] = '1 = 0';
        }

        return [
            'sql' => implode(' AND ', $conditions),
            'params' => $params,
        ];
    }

    /**
     * Build SQL scope for closed session queries.
     *
     * @param string $level Report level.
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int[] $roleids Role filter.
     * @return array{sql: string, params: array}
     */
    protected function build_session_scope_sql(string $level, int $courseid, int $userid, array $roleids): array {
        global $DB;

        $conditions = ['1 = 1'];
        $params = [];

        if ($level === self::LEVEL_COURSE && $courseid > 0) {
            $conditions[] = 's.courseid = :courseid';
            $params['courseid'] = $courseid;
        } else if ($level === self::LEVEL_USER) {
            $conditions[] = 's.courseid = :courseid';
            $conditions[] = 's.userid = :userid';
            $params['courseid'] = $courseid;
            $params['userid'] = $userid;
        } else if ($level === self::LEVEL_SITE) {
            $courseids = self::get_accessible_courseids();
            if ($courseids === []) {
                $conditions[] = '1 = 0';
            } else {
                [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'sitesessioncourse');
                $conditions[] = "s.courseid {$insql}";
                $params = array_merge($params, $inparams);
            }
        }

        $userids = $this->get_in_scope_userids($level, $courseid, $userid, $roleids);
        if ($level !== self::LEVEL_USER && $userids !== []) {
            [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'scopeuser');
            $conditions[] = "s.userid {$insql}";
            $params = array_merge($params, $inparams);
        } else if ($level !== self::LEVEL_USER && $userids === []) {
            $conditions[] = '1 = 0';
        }

        return [
            'sql' => implode(' AND ', $conditions),
            'params' => $params,
        ];
    }

    /**
     * Build SQL scope for open session queries.
     *
     * @param string $level Report level.
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int[] $roleids Role filter.
     * @return array{sql: string, params: array}
     */
    protected function build_open_session_scope_sql(string $level, int $courseid, int $userid, array $roleids): array {
        global $DB;

        $conditions = ['1 = 1'];
        $params = [];

        if ($level === self::LEVEL_COURSE && $courseid > 0) {
            $conditions[] = 'o.courseid = :opencourseid';
            $params['opencourseid'] = $courseid;
        } else if ($level === self::LEVEL_USER) {
            $conditions[] = 'o.courseid = :opencourseid';
            $conditions[] = 'o.userid = :openuserid';
            $params['opencourseid'] = $courseid;
            $params['openuserid'] = $userid;
        } else if ($level === self::LEVEL_SITE) {
            $courseids = self::get_accessible_courseids();
            if ($courseids === []) {
                $conditions[] = '1 = 0';
            } else {
                [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'siteopencourse');
                $conditions[] = "o.courseid {$insql}";
                $params = array_merge($params, $inparams);
            }
        }

        $userids = $this->get_in_scope_userids($level, $courseid, $userid, $roleids);
        if ($level !== self::LEVEL_USER && $userids !== []) {
            [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'openscopeuser');
            $conditions[] = "o.userid {$insql}";
            $params = array_merge($params, $inparams);
        } else if ($level !== self::LEVEL_USER && $userids === []) {
            $conditions[] = '1 = 0';
        }

        return [
            'sql' => implode(' AND ', $conditions),
            'params' => $params,
        ];
    }

    /**
     * Default empty row metric structure.
     *
     * @return array
     */
    protected function empty_row_metrics(): array {
        return [
            'messages' => 0,
            'normal' => 0,
            'guide' => 0,
            'quiz' => 0,
            'teach' => 0,
            'sessions' => 0,
            'duration' => 0,
            'lastactive' => 0,
            'activeuserids' => [],
        ];
    }

    /**
     * Format a mode count as a percentage of total messages.
     *
     * @param int $count Mode message count.
     * @param int $total Total messages.
     * @return string
     */
    protected function format_mode_pct(int $count, int $total): string {
        $pct = $total > 0 ? round(($count / $total) * 100, 1) : 0.0;
        return number_format($pct, 1) . '%';
    }

    /**
     * Build per-user metric list for the given user ids.
     *
     * @param int[] $userids User ids to include (typically active users).
     * @param array $values Values keyed by user id.
     * @return float[]
     */
    protected function build_per_user_values(array $userids, array $values): array {
        $result = [];
        foreach ($userids as $uid) {
            $result[] = (float) ($values[$uid] ?? 0);
        }
        return $result;
    }

    /**
     * Compute average of numeric values.
     *
     * @param float[] $values Values.
     * @return float
     */
    protected function average(array $values): float {
        if ($values === []) {
            return 0.0;
        }
        return array_sum($values) / max(count($values), 1);
    }

    /**
     * Compute median of numeric values.
     *
     * @param float[] $values Values.
     * @return float
     */
    protected function median(array $values): float {
        if ($values === []) {
            return 0.0;
        }
        sort($values);
        $count = count($values);
        $mid = (int) floor($count / 2);
        if ($count % 2 === 0) {
            return ($values[$mid - 1] + $values[$mid]) / 2;
        }
        return $values[$mid];
    }

    /**
     * Localised label for a tutor mode.
     *
     * @param string $mode Tutor mode.
     * @return string
     */
    protected function get_mode_label(string $mode): string {
        $key = 'tutor_usage_mode_' . $mode;
        $label = get_string($key, 'local_dixeo');
        return $label !== "[[$key]]" ? $label : ucfirst($mode);
    }

    /**
     * Human-readable duration.
     *
     * @param int $seconds Duration in seconds.
     * @return string
     */
    public static function format_duration(int $seconds): string {
        if ($seconds < 60) {
            return get_string('tutor_usage_duration_seconds', 'local_dixeo', $seconds);
        }
        if ($seconds < 3600) {
            return get_string('tutor_usage_duration_minutes', 'local_dixeo', (int) round($seconds / 60));
        }
        $hours = floor($seconds / 3600);
        $minutes = (int) round(($seconds % 3600) / 60);
        return get_string('tutor_usage_duration_hours', 'local_dixeo', (object) [
            'hours' => $hours,
            'minutes' => $minutes,
        ]);
    }
}
