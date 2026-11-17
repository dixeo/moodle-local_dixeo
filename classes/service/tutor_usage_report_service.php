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
use local_dixeo\repository\tutor_usage_rollup_repository;
use local_dixeo\util\site_calendar;

/**
 * Builds tutor usage report data from the daily totals, usage events and sessions.
 *
 * Dates, days and hour slots follow the site timezone. One service instance shares the
 * scope, usage totals and session reads of a request between the KPIs, charts and rows.
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

    /** @var string Role filter applied when the request does not specify one. */
    public const ROLE_SCOPE_DEFAULT = self::ROLE_SCOPE_STUDENTS;

    /** @var string Ascending sort direction. */
    public const SORT_ASC = 'asc';

    /** @var string Descending sort direction. */
    public const SORT_DESC = 'desc';

    /** @var string Summary table column sorted when the request does not specify one. */
    public const SORT_DEFAULT = 'messages';

    /** @var array<string, string> Columns compared as text, mapped to the row field holding their label. */
    protected const TEXT_SORT_FIELDS = [
        'name' => 'namelabel',
        'moduletype' => 'moduletypelabel',
    ];

    /** @var array<int, int[]> Accessible course ids memoised per user id for the request. */
    private static array $accessiblecourseids = [];

    /** @var tutor_usage_rollup_repository Daily totals repository. */
    private tutor_usage_rollup_repository $rollup;

    /** @var tutor_usage_scope[] Scopes memoised by request values. */
    private array $scopes = [];

    /** @var tutor_usage_dataset[] Usage totals memoised by scope and period. */
    private array $datasets = [];

    /** @var array Closed session totals memoised by scope and period. */
    private array $closedsessions = [];

    /** @var array Open sessions memoised by scope. */
    private array $opensessions = [];

    /**
     * Constructor.
     *
     * @param tutor_usage_rollup_repository|null $rollup Daily totals repository.
     */
    public function __construct(?tutor_usage_rollup_repository $rollup = null) {
        $this->rollup = $rollup ?? new tutor_usage_rollup_repository();
    }

    /**
     * Summary table columns for a level, in display order.
     *
     * @param string $level Report level.
     * @return string[] Column keys.
     */
    public static function table_column_keys(string $level): array {
        $keys = ['name'];
        if ($level !== self::LEVEL_USER) {
            $keys[] = 'engagement';
        }
        if ($level === self::LEVEL_SITE) {
            $keys[] = 'adoption';
        }
        if ($level === self::LEVEL_USER) {
            $keys[] = 'moduletype';
        }
        array_push($keys, 'messages', 'normal', 'guide', 'quiz', 'teach');
        if ($level !== self::LEVEL_USER) {
            $keys[] = 'duration';
        }
        $keys[] = 'lastactive';

        return $keys;
    }

    /**
     * Normalize a summary table sort request value.
     *
     * @param string $sort Raw column key.
     * @param string $level Report level.
     * @return string A column key available at this level.
     */
    public static function normalize_sort(string $sort, string $level): string {
        return in_array($sort, self::table_column_keys($level), true) ? $sort : self::SORT_DEFAULT;
    }

    /**
     * Direction a column is sorted in when it is first clicked.
     *
     * Text reads naturally from A to Z, while metrics are most useful highest first.
     *
     * @param string $sort Column key.
     * @return string
     */
    public static function default_sort_direction(string $sort): string {
        return isset(self::TEXT_SORT_FIELDS[$sort]) ? self::SORT_ASC : self::SORT_DESC;
    }

    /**
     * Normalize a sort direction request value.
     *
     * @param string $direction Raw direction.
     * @param string $sort Column key the direction applies to.
     * @return string
     */
    public static function normalize_sort_direction(string $direction, string $sort): string {
        return in_array($direction, [self::SORT_ASC, self::SORT_DESC], true)
            ? $direction
            : self::default_sort_direction($sort);
    }

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
        return in_array($scope, self::role_scopes(), true) ? $scope : self::ROLE_SCOPE_DEFAULT;
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
     * Parse a report date-from request value to a site-timezone midnight timestamp.
     *
     * @param string $raw Raw request value (Y-m-d or legacy unix timestamp).
     * @return int Timestamp or 0 when empty/invalid.
     */
    public static function parse_date_from_param(string $raw): int {
        if ($raw === '') {
            return 0;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $matches)) {
            return site_calendar::make((int) $matches[1], (int) $matches[2], (int) $matches[3]);
        }
        if (ctype_digit($raw)) {
            return site_calendar::day_start((int) $raw);
        }
        return 0;
    }

    /**
     * Parse a report date-to request value to a site-timezone end-of-day timestamp.
     *
     * @param string $raw Raw request value (Y-m-d or legacy unix timestamp).
     * @return int Timestamp or 0 when empty/invalid.
     */
    public static function parse_date_to_param(string $raw): int {
        if ($raw === '') {
            return 0;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $matches)) {
            return site_calendar::make((int) $matches[1], (int) $matches[2], (int) $matches[3], 23, 59, 59);
        }
        if (ctype_digit($raw)) {
            return site_calendar::day_end(site_calendar::day_start((int) $raw));
        }
        return 0;
    }

    /**
     * Format a timestamp for report date form fields and URLs, in the site timezone.
     *
     * @param int $timestamp Unix timestamp.
     * @return string Date in Y-m-d format.
     */
    public static function format_date_param(int $timestamp): string {
        $date = usergetdate($timestamp, site_calendar::timezone());
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
     * The result is memoised per user id for the request.
     *
     * @param int|null $userid User id (defaults to current user).
     * @return int[]
     */
    public static function get_accessible_courseids(?int $userid = null): array {
        global $DB, $USER;

        $userid = $userid ?? (int) $USER->id;
        if (isset(self::$accessiblecourseids[$userid])) {
            return self::$accessiblecourseids[$userid];
        }

        if (self::can_view_site($userid)) {
            $ids = $DB->get_fieldset_select('course', 'id', 'id > 1', []);
            $ids = array_map('intval', $ids ?: []);
            sort($ids);
            self::$accessiblecourseids[$userid] = $ids;
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
        $courseids = [];
        foreach ($courses ?: [] as $course) {
            $courseids[] = (int) $course->id;
        }
        self::$accessiblecourseids[$userid] = $courseids;
        return $courseids;
    }

    /**
     * Forget the per-request memo of accessible courses.
     */
    public static function reset_caches(): void {
        self::$accessiblecourseids = [];
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
     * Resolve period boundaries from view parameters, in the site timezone.
     *
     * @param string $view View mode.
     * @param string|null $anchor Anchor date Y-m-d; anything else means today.
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
        $anchor = site_calendar::normalise_date_param((string) $anchor);
        $timezone = \core_date::get_server_timezone_object();
        $anchordate = new \DateTime($anchor !== '' ? $anchor : 'today', $timezone);

        if ($view === self::VIEW_MONTH) {
            $start = (clone $anchordate)->modify('first day of this month')->setTime(0, 0, 0);
            $end = (clone $anchordate)->modify('last day of this month')->setTime(23, 59, 59);
            $prevstart = (clone $start)->modify('-1 month');
            $prevend = (clone $prevstart)->modify('last day of this month')->setTime(23, 59, 59);
            $prev = (clone $anchordate)->modify('-1 month')->format('Y-m-d');
            $next = (clone $anchordate)->modify('+1 month')->format('Y-m-d');
            $label = site_calendar::format($start->getTimestamp(), get_string('strftimemonthyear', 'langconfig'));
        } else if ($view === self::VIEW_CUSTOM) {
            if (empty($datefrom) && empty($dateto)) {
                $dayofweek = (int) $anchordate->format('N');
                $start = (clone $anchordate)->modify('-' . ($dayofweek - 1) . ' days')->setTime(0, 0, 0);
                $end = (clone $start)->modify('+6 days')->setTime(23, 59, 59);
            } else {
                $startts = (int) ($datefrom ?: site_calendar::day_start(time()));
                $endts = $dateto ? (int) $dateto : site_calendar::day_end(site_calendar::day_start($startts));
                $start = (new \DateTime('now', $timezone))->setTimestamp($startts);
                $end = (new \DateTime('now', $timezone))->setTimestamp($endts);
            }

            if (($end->getTimestamp() - $start->getTimestamp()) > self::MAX_CUSTOM_RANGE_SECONDS) {
                throw new \moodle_exception('tutor_usage_report_range_too_long', 'local_dixeo');
            }

            $prev = null;
            $next = null;
            $prevstart = null;
            $prevend = null;
            $label = site_calendar::format($start->getTimestamp(), '%d %b %Y') . ' - '
                . site_calendar::format($end->getTimestamp(), '%d %b %Y');
        } else {
            $dayofweek = (int) $anchordate->format('N');
            $start = (clone $anchordate)->modify('-' . ($dayofweek - 1) . ' days')->setTime(0, 0, 0);
            $end = (clone $start)->modify('+6 days')->setTime(23, 59, 59);
            $prevstart = (clone $start)->modify('-7 days');
            $prevend = (clone $prevstart)->modify('+6 days')->setTime(23, 59, 59);
            $prev = $prevstart->format('Y-m-d');
            $next = (clone $start)->modify('+7 days')->format('Y-m-d');
            $label = site_calendar::format($start->getTimestamp(), '%d %b') . ' - '
                . site_calendar::format($end->getTimestamp(), '%d %b %Y');
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
     * @return int[] User ids. User level is empty unless that user is an active participant.
     */
    public function get_in_scope_userids(string $level, int $courseid, int $userid, array $roleids): array {
        global $DB;

        if ($level === self::LEVEL_USER) {
            if ($userid < 1 || !$this->is_active_course_participant($courseid, $userid, $roleids)) {
                return [];
            }
            return [$userid];
        }

        if ($level !== self::LEVEL_COURSE || $courseid < 1) {
            $level = self::LEVEL_SITE;
        }
        [$usersql, $params] = $this->get_scope($level, $courseid, 0, $roleids)->users_sql();
        return array_map('intval', $DB->get_fieldset_sql("SELECT DISTINCT s.userid FROM ({$usersql}) s", $params));
    }


    /**
     * Whether a user is an active course participant, honoring the report role filter.
     *
     * Matches the course-level enrolment query: active enrolment, and a course-context
     * role assignment when a role filter is set.
     *
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int[] $roleids Selected role ids. Empty means any active enrolment.
     * @return bool
     */
    private function is_active_course_participant(int $courseid, int $userid, array $roleids): bool {
        global $DB;

        if ($courseid < 1 || $userid < 1) {
            return false;
        }

        if ($roleids === []) {
            $sql = "SELECT ue.id
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON e.id = ue.enrolid
                     WHERE e.courseid = :courseid
                       AND ue.userid = :userid
                       AND ue.status = 0";
            return $DB->record_exists_sql($sql, [
                'courseid' => $courseid,
                'userid' => $userid,
            ]);
        }

        [$rolesql, $roleparams] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'role');
        $sql = "SELECT ue.id
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {role_assignments} ra ON ra.userid = ue.userid
                  JOIN {context} ctx ON ctx.id = ra.contextid
                 WHERE e.courseid = :courseid
                   AND ue.userid = :userid
                   AND ue.status = 0
                   AND ctx.contextlevel = :contextlevel
                   AND ctx.instanceid = :courseid2
                   AND ra.roleid {$rolesql}";

        return $DB->record_exists_sql($sql, array_merge([
            'courseid' => $courseid,
            'courseid2' => $courseid,
            'userid' => $userid,
            'contextlevel' => CONTEXT_COURSE,
        ], $roleparams));
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

        return $this->format_kpis($current, $previous, $view, $level);
    }

    /**
     * Stacked bar chart data: messages by mode per site day.
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
        $perday = $this->get_dataset($level, $courseid, $userid, $roleids, $timestart, $timeend)->per_day();
        $columns = self::mode_columns();

        $labels = [];
        $datasets = array_fill_keys(self::MESSAGE_MODES, []);
        foreach (site_calendar::days($timestart, $timeend) as $daystart) {
            $labels[] = site_calendar::format($daystart, '%d %b');
            foreach (self::MESSAGE_MODES as $mode) {
                $datasets[$mode][] = (int) ($perday[$daystart][$columns[$mode]] ?? 0);
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
     * Heatmap data from message counts (Mon–Sun columns, eight 3-hour rows of the site day).
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
        $perday = $this->get_dataset($level, $courseid, $userid, $roleids, $timestart, $timeend)->per_day();

        $grid = [];
        foreach ($perday as $daystart => $day) {
            $dow = site_calendar::weekday((int) $daystart);
            foreach ($day['slots'] as $slot => $count) {
                $grid[$slot][$dow] = ($grid[$slot][$dow] ?? 0) + (int) $count;
            }
        }
        $max = 0;
        foreach ($grid as $cells) {
            $max = max($max, ...array_values($cells));
        }

        $rows = [];
        for ($slot = 0; $slot < self::HEATMAP_SLOT_COUNT; $slot++) {
            $starthour = $slot * 3;
            $cells = [];
            for ($dow = 0; $dow < 7; $dow++) {
                $value = $grid[$slot][$dow] ?? 0;
                $cells[] = [
                    'value' => $value,
                    'intensity' => $max > 0 ? round($value / $max, 2) : 0,
                ];
            }
            $rows[] = [
                'label' => sprintf('%d-%d', $starthour, $starthour + 3),
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
     * Row metrics are computed for every listed entity, then sorted and paginated; links,
     * formatted values and the session duration tooltip are built for the displayed page only.
     *
     * @param string $level Report level.
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int $timestart Period start.
     * @param int $timeend Period end.
     * @param int[] $roleids Role filter.
     * @param int $page Page number (0-based).
     * @param int $perpage Rows per page.
     * @param array $linkparams Extra URL parameters to carry into drill-down links (period, filters).
     * @param string $sort Column key to sort by.
     * @param string $sortdir Sort direction (asc|desc), defaulting to the column's natural direction.
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
        int $perpage = 50,
        array $linkparams = [],
        string $sort = self::SORT_DEFAULT,
        string $sortdir = ''
    ): array {
        $rows = $this->sort_rows(
            $this->build_row_metrics($level, $courseid, $userid, $timestart, $timeend, $roleids),
            $level,
            $sort,
            $sortdir
        );

        $total = count($rows);
        $perpage = max(1, $perpage);
        $rows = array_slice($rows, max(0, $page) * $perpage, $perpage);

        $durations = $this->get_session_durations(
            $level,
            $courseid,
            $userid,
            $roleids,
            $timestart,
            $timeend,
            array_column($rows, 'key')
        );
        $formatted = [];
        foreach ($rows as $row) {
            $formatted[] = $this->format_summary_row($level, $row, $courseid, $linkparams, $durations[$row['key']] ?? []);
        }

        return [
            'rows' => $formatted,
            'total' => $total,
        ];
    }


    /**
     * Sort summary rows by one column, falling back to name for equal values.
     *
     * @param array $rows Formatted summary rows.
     * @param string $level Report level.
     * @param string $sort Column key to sort by.
     * @param string $sortdir Sort direction (asc|desc).
     * @return array
     */
    protected function sort_rows(array $rows, string $level, string $sort, string $sortdir): array {
        $sort = self::normalize_sort($sort, $level);
        $direction = self::normalize_sort_direction($sortdir, $sort) === self::SORT_ASC ? 1 : -1;
        $textfield = self::TEXT_SORT_FIELDS[$sort] ?? null;

        usort($rows, static function (array $a, array $b) use ($sort, $textfield, $direction): int {
            if ($textfield !== null) {
                $compared = strcasecmp((string) ($a[$textfield] ?? ''), (string) ($b[$textfield] ?? ''));
            } else {
                $compared = ($a[$sort] ?? 0) <=> ($b[$sort] ?? 0);
            }
            if ($compared !== 0) {
                return $direction * $compared;
            }
            // Equal values keep a stable, readable order regardless of direction.
            return strcasecmp((string) ($a['namelabel'] ?? ''), (string) ($b['namelabel'] ?? ''));
        });

        return $rows;
    }

    /**
     * Summary table column labels for a level, in display order.
     *
     * Shared by the rendered table header and the dataformat export.
     *
     * @param string $level Report level (site|course|user).
     * @return array<string, string> Column key to label.
     */
    public function get_columns(string $level = self::LEVEL_SITE): array {
        $columns = [];
        foreach (self::table_column_keys($level) as $key) {
            $columns[$key] = self::column_label($key);
        }
        return $columns;
    }

    /**
     * Label for a summary table column.
     *
     * @param string $key Column key.
     * @return string
     */
    protected static function column_label(string $key): string {
        return match ($key) {
            'engagement' => get_string('tutor_usage_report_kpi_engagement', 'local_dixeo'),
            'adoption' => get_string('tutor_usage_report_kpi_adoption', 'local_dixeo'),
            'duration' => get_string('tutor_usage_report_kpi_duration', 'local_dixeo'),
            'moduletype' => get_string('tutor_usage_report_column_moduletype', 'local_dixeo'),
            'normal' => get_string('tutor_usage_report_column_standard', 'local_dixeo'),
            default => get_string('tutor_usage_report_column_' . $key, 'local_dixeo'),
        };
    }

    /**
     * Export rows in report order.
     *
     * Builds the same rows as the screen, in the same order, with no page limit and no
     * tooltip queries.
     *
     * @param string $level Report level.
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int $timestart Period start.
     * @param int $timeend Period end.
     * @param int[] $roleids Role filter.
     * @param string $sort Column key to sort by.
     * @param string $sortdir Sort direction (asc|desc).
     * @return array<int, array<string, string|int>>
     */
    public function get_export_rows(
        string $level,
        int $courseid,
        int $userid,
        int $timestart,
        int $timeend,
        array $roleids,
        string $sort = self::SORT_DEFAULT,
        string $sortdir = ''
    ): array {
        $rows = $this->sort_rows(
            $this->build_row_metrics($level, $courseid, $userid, $timestart, $timeend, $roleids),
            $level,
            $sort,
            $sortdir
        );
        $export = [];

        foreach ($rows as $metrics) {
            $row = $this->format_summary_row($level, $metrics, $courseid, [], null);
            $item = [
                'name' => $row['namelabel'],
            ];
            if ($level !== self::LEVEL_USER) {
                $item['engagement'] = $row['engagementformatted'] ?? '';
            }
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
            if ($level !== self::LEVEL_USER) {
                $item['duration'] = $row['durationformatted'];
            }
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
        $dataset = $this->get_dataset($level, $courseid, $userid, $roleids, $timestart, $timeend);
        $peruser = $dataset->per_user();
        $totalusers = $this->get_scope($level, $courseid, $userid, $roleids)->count_users();

        $messages = 0;
        $quizcreated = 0;
        $lessoncreated = 0;
        $lastactive = 0;
        $modecounts = array_fill_keys(self::MESSAGE_MODES, 0);
        $messagesbymodebyuser = array_fill_keys(self::MESSAGE_MODES, []);
        $messagesbyuser = [];
        $activedaysbyuser = [];
        $activeusers = [];
        $columns = self::mode_columns();

        foreach ($peruser as $uid => $partial) {
            $messages += $partial['messages'];
            $quizcreated += $partial['quizcreated'];
            $lessoncreated += $partial['lessoncreated'];
            $lastactive = max($lastactive, $partial['lastactive']);
            foreach (self::MESSAGE_MODES as $mode) {
                $count = $partial[$columns[$mode]];
                $modecounts[$mode] += $count;
                if ($count > 0) {
                    $messagesbymodebyuser[$mode][$uid] = $count;
                }
            }
            $messagesbyuser[$uid] = $partial['messages'];
            $activedaysbyuser[$uid] = $partial['activedays'];
            $activeusers[$uid] = true;
        }

        $activedays = 0;
        foreach ($dataset->per_day() as $day) {
            if (array_sum($day['slots']) + $day['quizcreated'] + $day['lessoncreated'] > 0) {
                $activedays++;
            }
        }

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
        $activedaysperuser = $this->build_per_user_values($activeuserids, $activedaysbyuser);

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
            'activedays' => $activedays,
            'avgactivedays' => $this->average($activedaysperuser),
            'medianactivedays' => $this->median($activedaysperuser),
            'lastactive' => $lastactive,
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
     * @param string $level Report level, which decides how engagement is expressed.
     * @return array
     */
    protected function format_kpis(
        array $current,
        ?array $previous,
        string $view = self::VIEW_WEEK,
        string $level = self::LEVEL_SITE
    ): array {
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

        $isuserlevel = $level === self::LEVEL_USER;

        // A single user's median and average are the KPI value itself, so that tooltip is dropped.
        $addstatstooltip = static function (array $item, string $stringkey, array $args) use ($isuserlevel): array {
            $item['hastooltip'] = !$isuserlevel;
            if ($item['hastooltip']) {
                $item['tooltip'] = get_string($stringkey, 'local_dixeo', (object) $args);
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
        $adoption['info'] = get_string('tutor_usage_report_kpi_adoption_info', 'local_dixeo');
        $adoption['hastooltip'] = true;
        $adoption['tooltip'] = get_string('tutor_usage_report_kpi_adoption_secondary', 'local_dixeo', (object) [
            'active' => $activeformatted,
            'inactive' => $inactiveformatted,
            'total' => $totalformatted,
        ]);

        // A single user has their own active days; above that, days are averaged per active user so the
        // number stays comparable instead of saturating at the number of days in the period.
        $engagement = $build(
            $isuserlevel ? 'activedays' : 'avgactivedays',
            static function ($v) use ($isuserlevel) {
                $days = $isuserlevel ? number_format((int) $v) : number_format((float) $v, 1);
                return get_string('tutor_usage_report_kpi_engagement_value', 'local_dixeo', $days);
            }
        );
        $engagement['info'] = get_string(
            $isuserlevel ? 'tutor_usage_report_kpi_engagement_info_user' : 'tutor_usage_report_kpi_engagement_info',
            'local_dixeo'
        );
        $engagement['hastooltip'] = true;
        if ($isuserlevel) {
            $lastactive = (int) ($current['lastactive'] ?? 0);
            $lastactiveformatted = $lastactive > 0
                ? site_calendar::format($lastactive, get_string('strftimedatetime', 'langconfig'))
                : get_string('tutor_usage_report_performance_na', 'local_dixeo');
            $engagement['tooltip'] = get_string(
                'tutor_usage_report_kpi_engagement_secondary',
                'local_dixeo',
                $lastactiveformatted
            );
        } else {
            $engagement['tooltip'] = get_string(
                'tutor_usage_report_kpi_engagement_secondary_aggregate',
                'local_dixeo',
                (object) [
                    'median' => number_format((float) ($current['medianactivedays'] ?? 0), 1),
                    'average' => number_format((float) ($current['avgactivedays'] ?? 0), 1),
                ]
            );
        }

        $messages = $build('messages', static fn($v) => number_format((int) $v));
        $messages['info'] = get_string('tutor_usage_report_kpi_messages_info', 'local_dixeo');
        $messages = $addstatstooltip($messages, 'tutor_usage_report_kpi_messages_secondary', [
            'median' => $medianmessages,
            'average' => $avgmessages,
        ]);

        $sessions = $build('sessions', static fn($v) => number_format((int) $v));
        $sessions['info'] = get_string(
            'tutor_usage_report_kpi_sessions_info',
            'local_dixeo',
            format_time(tutor_usage_aggregator::SESSION_TIMEOUT)
        );
        $sessions = $addstatstooltip($sessions, 'tutor_usage_report_kpi_sessions_secondary', [
            'median' => $mediansessions,
            'average' => $avgsessions,
        ]);

        $duration = $build('duration', static fn($v) => self::format_duration((int) $v));
        $duration['info'] = get_string(
            'tutor_usage_report_kpi_duration_info',
            'local_dixeo',
            format_time(tutor_usage_aggregator::SESSION_BASE_DURATION)
        );
        $duration = $addstatstooltip($duration, 'tutor_usage_report_kpi_duration_secondary', [
            'median' => $medianduration,
            'average' => $avgduration,
        ]);

        return [
            'adoption' => $adoption,
            'engagement' => $engagement,
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
            'modes' => array_map(function (string $mode) use ($current, $previous, $formatchange, $isuserlevel) {
                $value = (int) ($current['modecounts'][$mode] ?? 0);
                $tooltip = '';
                $description = get_string('tutor_usage_report_kpi_mode_desc_messages', 'local_dixeo');
                if (($mode === tutor_message::MODE_NORMAL || $mode === tutor_message::MODE_GUIDE) && !$isuserlevel) {
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
                    $description = get_string('tutor_usage_report_kpi_mode_desc_quiz', 'local_dixeo');
                } else if ($mode === tutor_message::MODE_TEACH) {
                    // No tooltip: the lesson count it would show always equals the card value.
                    $description = get_string('tutor_usage_report_kpi_mode_desc_lesson', 'local_dixeo');
                }

                $item = [
                    'mode' => $mode,
                    'label' => $this->get_mode_label($mode),
                    'value' => number_format($value),
                    'raw' => $value,
                    'isquiz' => $mode === tutor_message::MODE_QUIZ,
                    'isteach' => $mode === tutor_message::MODE_TEACH,
                    'description' => $description,
                    'info' => get_string('tutor_usage_report_kpi_mode_info_' . $mode, 'local_dixeo'),
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
     * Scope of a report request for the current user, memoised for this service instance.
     *
     * @param string $level Report level.
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int[] $roleids Role filter.
     * @return tutor_usage_scope
     */
    protected function get_scope(string $level, int $courseid, int $userid, array $roleids): tutor_usage_scope {
        global $USER;

        $key = $this->memo_key([$level, $courseid, $userid, $roleids, (int) $USER->id]);
        if (!isset($this->scopes[$key])) {
            $this->scopes[$key] = tutor_usage_scope::for_viewer($level, $courseid, $userid, $roleids);
        }
        return $this->scopes[$key];
    }

    /**
     * Usage totals of a scope and period, memoised so KPIs, charts and rows share one load.
     *
     * @param string $level Report level.
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int[] $roleids Role filter.
     * @param int $timestart Period start.
     * @param int $timeend Period end.
     * @return tutor_usage_dataset
     */
    protected function get_dataset(
        string $level,
        int $courseid,
        int $userid,
        array $roleids,
        int $timestart,
        int $timeend
    ): tutor_usage_dataset {
        global $USER;

        $key = $this->memo_key([$level, $courseid, $userid, $roleids, $timestart, $timeend, (int) $USER->id]);
        if (!isset($this->datasets[$key])) {
            $this->datasets[$key] = new tutor_usage_dataset(
                $this->get_scope($level, $courseid, $userid, $roleids),
                $timestart,
                $timeend,
                $this->rollup
            );
        }
        return $this->datasets[$key];
    }

    /**
     * Memo key of request values.
     *
     * @param array $values Scalars and role id arrays.
     * @return string
     */
    private function memo_key(array $values): string {
        $parts = [];
        foreach ($values as $value) {
            if (is_array($value)) {
                $value = array_map('intval', $value);
                sort($value);
                $value = implode(',', $value);
            }
            $parts[] = (string) $value;
        }
        return implode('|', $parts);
    }

    /**
     * Closed session totals of a period, at the row grain of the level.
     *
     * Site: course id to user id to totals plus incoursescope. Course and user: user id to totals.
     *
     * @param string $level Report level.
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int[] $roleids Role filter.
     * @param int $timestart Period start.
     * @param int $timeend Period end.
     * @return array
     */
    protected function get_closed_sessions(
        string $level,
        int $courseid,
        int $userid,
        array $roleids,
        int $timestart,
        int $timeend
    ): array {
        global $DB, $USER;

        $key = $this->memo_key([$level, $courseid, $userid, $roleids, $timestart, $timeend, (int) $USER->id]);
        if (isset($this->closedsessions[$key])) {
            return $this->closedsessions[$key];
        }

        $scope = $this->get_scope($level, $courseid, $userid, $roleids);
        [$wheresql, $params] = $scope->where('s');
        $params += ['tusstart' => $timestart, 'tusend' => $timeend];
        $periodsql = "s.timestart >= :tusstart AND s.timestart <= :tusend AND {$wheresql}";
        $issite = $level === self::LEVEL_SITE;

        if ($issite) {
            [$pairsql, $pairparams] = $scope->pair_in_scope('g');
            // The course id alone is repeated once per user, so the row key is the pair.
            $rowkey = $DB->sql_concat('g.courseid', "'-'", 'g.userid');
            $sql = "SELECT {$rowkey} AS rowkey, g.courseid, g.userid, g.sessions, g.duration, g.lastend,
                           CASE WHEN {$pairsql} THEN 1 ELSE 0 END AS incoursescope
                      FROM (SELECT s.courseid, s.userid, COUNT(1) AS sessions, SUM(s.duration) AS duration,
                                   MAX(s.timeend) AS lastend
                              FROM {" . tutor_usage_aggregator::TABLE_SESSION . "} s
                             WHERE {$periodsql}
                          GROUP BY s.courseid, s.userid) g";
            $params += $pairparams;
        } else {
            $sql = "SELECT s.userid, COUNT(1) AS sessions, SUM(s.duration) AS duration, MAX(s.timeend) AS lastend
                      FROM {" . tutor_usage_aggregator::TABLE_SESSION . "} s
                     WHERE {$periodsql}
                  GROUP BY s.userid";
        }

        $result = [];
        $records = $DB->get_records_sql($sql, $params);
        foreach ($records as $record) {
            $totals = [
                'sessions' => (int) $record->sessions,
                'duration' => (int) $record->duration,
                'lastend' => (int) $record->lastend,
                'incoursescope' => !$issite || !empty($record->incoursescope),
            ];
            if ($issite) {
                $result[(int) $record->courseid][(int) $record->userid] = $totals;
            } else {
                $result[(int) $record->userid] = $totals;
            }
        }

        $this->closedsessions[$key] = $result;
        return $result;
    }

    /**
     * Open sessions of a scope, memoised for this service instance.
     *
     * @param string $level Report level.
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int[] $roleids Role filter.
     * @return \stdClass[] Rows with userid, courseid, timestart, lastmessage and incoursescope.
     */
    protected function get_open_sessions(string $level, int $courseid, int $userid, array $roleids): array {
        global $DB, $USER;

        $key = $this->memo_key([$level, $courseid, $userid, $roleids, (int) $USER->id]);
        if (isset($this->opensessions[$key])) {
            return $this->opensessions[$key];
        }

        $scope = $this->get_scope($level, $courseid, $userid, $roleids);
        [$wheresql, $params] = $scope->where('o');
        $select = 'o.id, o.userid, o.courseid, o.timestart, o.lastmessage';
        if ($level === self::LEVEL_SITE) {
            [$pairsql, $pairparams] = $scope->pair_in_scope('o');
            $select .= ", CASE WHEN {$pairsql} THEN 1 ELSE 0 END AS incoursescope";
            $params += $pairparams;
        }
        $opens = array_values($DB->get_records_sql(
            "SELECT {$select} FROM {" . tutor_usage_aggregator::TABLE_OPEN . "} o WHERE {$wheresql}",
            $params
        ));
        foreach ($opens as $open) {
            $open->incoursescope = $level !== self::LEVEL_SITE || !empty($open->incoursescope);
        }

        $this->opensessions[$key] = $opens;
        return $opens;
    }

    /**
     * Provisional duration of an open session within a period.
     *
     * @param \stdClass $open Open session.
     * @param int $timestart Period start.
     * @param int $timeend Period end.
     * @return int|null Duration in seconds, or null when the session is outside the period.
     */
    protected function open_session_duration(\stdClass $open, int $timestart, int $timeend): ?int {
        $lastmessage = (int) $open->lastmessage;
        $start = (int) $open->timestart;
        if ($lastmessage < $timestart || $start > $timeend) {
            return null;
        }

        $timedout = ($timeend - $lastmessage) > tutor_usage_aggregator::SESSION_TIMEOUT;
        $effectiveend = $timedout ? $lastmessage : min($timeend, $lastmessage);
        if ($effectiveend < $timestart) {
            return null;
        }

        return tutor_usage_aggregator::calculate_duration(max($start, $timestart), $effectiveend);
    }

    /**
     * Aggregate session counts and duration per user for a period.
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
        $sessionsbyuser = [];
        $durationbyuser = [];

        $closed = $this->get_closed_sessions($level, $courseid, $userid, $roleids, $timestart, $timeend);
        $rows = $level === self::LEVEL_SITE ? $closed : [$closed];
        foreach ($rows as $users) {
            foreach ($users as $uid => $totals) {
                $sessionsbyuser[$uid] = ($sessionsbyuser[$uid] ?? 0) + $totals['sessions'];
                $durationbyuser[$uid] = ($durationbyuser[$uid] ?? 0) + $totals['duration'];
            }
        }

        foreach ($this->get_open_sessions($level, $courseid, $userid, $roleids) as $open) {
            $seconds = $this->open_session_duration($open, $timestart, $timeend);
            if ($seconds !== null) {
                $uid = (int) $open->userid;
                $sessionsbyuser[$uid] = ($sessionsbyuser[$uid] ?? 0) + 1;
                $durationbyuser[$uid] = ($durationbyuser[$uid] ?? 0) + $seconds;
            }
        }

        return [
            'sessions' => array_sum($sessionsbyuser),
            'duration' => array_sum($durationbyuser),
            'sessionsbyuser' => $sessionsbyuser,
            'durationbyuser' => $durationbyuser,
        ];
    }

    /**
     * Session durations of the given row entities, for the median and average tooltip.
     *
     * @param string $level Report level.
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int[] $roleids Role filter.
     * @param int $timestart Period start.
     * @param int $timeend Period end.
     * @param int[] $keys Row keys (course ids at site level, user ids at course level).
     * @return array<int, int[]> Row key to session durations.
     */
    protected function get_session_durations(
        string $level,
        int $courseid,
        int $userid,
        array $roleids,
        int $timestart,
        int $timeend,
        array $keys
    ): array {
        global $DB;

        if ($level === self::LEVEL_USER || $keys === []) {
            return [];
        }

        $field = $level === self::LEVEL_SITE ? 'courseid' : 'userid';
        [$wheresql, $params] = $this->get_scope($level, $courseid, $userid, $roleids)->where('s');
        [$insql, $inparams] = $DB->get_in_or_equal(array_map('intval', $keys), SQL_PARAMS_NAMED, 'tuskey');
        $records = $DB->get_records_sql(
            "SELECT s.id, s.{$field} AS entityid, s.duration
               FROM {" . tutor_usage_aggregator::TABLE_SESSION . "} s
              WHERE s.timestart >= :tusstart AND s.timestart <= :tusend
                AND s.{$field} {$insql}
                AND {$wheresql}",
            $params + $inparams + ['tusstart' => $timestart, 'tusend' => $timeend]
        );
        $durations = [];
        foreach ($records as $record) {
            $durations[(int) $record->entityid][] = (int) $record->duration;
        }

        $wanted = array_flip(array_map('intval', $keys));
        foreach ($this->get_open_sessions($level, $courseid, $userid, $roleids) as $open) {
            $key = (int) $open->{$field};
            if (!isset($wanted[$key])) {
                continue;
            }
            $seconds = $this->open_session_duration($open, $timestart, $timeend);
            if ($seconds !== null) {
                $durations[$key][] = $seconds;
            }
        }

        return $durations;
    }

    /**
     * Numeric summary rows of every listed entity, unsorted and unformatted.
     *
     * @param string $level Report level.
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int $timestart Period start.
     * @param int $timeend Period end.
     * @param int[] $roleids Role filter.
     * @return array[]
     */
    protected function build_row_metrics(
        string $level,
        int $courseid,
        int $userid,
        int $timestart,
        int $timeend,
        array $roleids
    ): array {
        if ($level === self::LEVEL_SITE) {
            return $this->build_site_rows($timestart, $timeend, $roleids);
        }
        if ($level === self::LEVEL_COURSE) {
            return $this->build_course_rows($courseid, $timestart, $timeend, $roleids);
        }
        return $this->build_user_rows($courseid, $userid, $timestart, $timeend, $roleids);
    }

    /**
     * Site rows: the courses with in-scope activity (events or sessions) in the period.
     *
     * @param int $timestart Period start.
     * @param int $timeend Period end.
     * @param int[] $roleids Role filter.
     * @return array[]
     */
    protected function build_site_rows(int $timestart, int $timeend, array $roleids): array {
        global $DB;

        $level = self::LEVEL_SITE;
        $metrics = [];
        $columns = self::mode_columns();
        $perentity = $this->get_dataset($level, 0, 0, $roleids, $timestart, $timeend)->per_entity();
        foreach ($perentity as $cid => $users) {
            $metrics[$cid] = $metrics[$cid] ?? $this->empty_row_metrics();
            foreach ($users as $uid => $partial) {
                $metrics[$cid]['messages'] += $partial['messages'];
                foreach (self::MESSAGE_MODES as $mode) {
                    $metrics[$cid][$mode] += $partial[$columns[$mode]];
                }
                $metrics[$cid]['lastactive'] = max($metrics[$cid]['lastactive'], $partial['lastactive']);
                $this->mark_active($metrics[$cid], (int) $uid, $partial['incoursescope'], $partial['activedays']);
            }
        }

        foreach ($this->get_closed_sessions($level, 0, 0, $roleids, $timestart, $timeend) as $cid => $users) {
            $metrics[$cid] = $metrics[$cid] ?? $this->empty_row_metrics();
            foreach ($users as $uid => $totals) {
                $metrics[$cid]['sessions'] += $totals['sessions'];
                $metrics[$cid]['duration'] += $totals['duration'];
                $metrics[$cid]['lastactive'] = max($metrics[$cid]['lastactive'], $totals['lastend']);
                $this->mark_active($metrics[$cid], (int) $uid, $totals['incoursescope'], 0);
            }
        }

        foreach ($this->get_open_sessions($level, 0, 0, $roleids) as $open) {
            $seconds = $this->open_session_duration($open, $timestart, $timeend);
            if ($seconds === null) {
                continue;
            }
            $cid = (int) $open->courseid;
            $metrics[$cid] = $metrics[$cid] ?? $this->empty_row_metrics();
            $metrics[$cid]['sessions']++;
            $metrics[$cid]['duration'] += $seconds;
            $metrics[$cid]['lastactive'] = max($metrics[$cid]['lastactive'], (int) $open->lastmessage);
            $this->mark_active($metrics[$cid], (int) $open->userid, (bool) $open->incoursescope, 0);
        }

        if ($metrics === []) {
            return [];
        }

        $courseids = array_keys($metrics);
        $totals = $this->get_scope($level, 0, 0, $roleids)->count_users_per_course($courseids);
        $courses = $DB->get_records_list('course', 'id', $courseids, '', 'id, fullname');

        $rows = [];
        foreach ($metrics as $cid => $rowmetrics) {
            $course = $courses[$cid] ?? null;
            $total = $totals[$cid] ?? 0;
            $active = count(array_filter($rowmetrics['activeuserids']));
            $row = $this->numeric_row((int) $cid, $course ? format_string($course->fullname) : (string) $cid, $rowmetrics);
            $row['course'] = $course;
            $row['activeinscope'] = $total > 0 ? $active : 0;
            $row['totalinscope'] = $total;
            $row['adoption'] = $total > 0 ? ($active / $total) * 100 : 0.0;
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Course rows: every user in scope, with zeros when inactive.
     *
     * @param int $courseid Course id.
     * @param int $timestart Period start.
     * @param int $timeend Period end.
     * @param int[] $roleids Role filter.
     * @return array[]
     */
    protected function build_course_rows(int $courseid, int $timestart, int $timeend, array $roleids): array {
        global $DB;

        $level = self::LEVEL_COURSE;
        $scope = $this->get_scope($level, $courseid, 0, $roleids);
        [$usersql, $params] = $scope->users_sql();
        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $users = $DB->get_records_sql("SELECT u.id, {$namefields} FROM {user} u WHERE u.id IN ({$usersql})", $params);

        $metrics = [];
        foreach ($users as $uid => $user) {
            $metrics[(int) $uid] = $this->empty_row_metrics();
        }

        $columns = self::mode_columns();
        foreach ($this->get_dataset($level, $courseid, 0, $roleids, $timestart, $timeend)->per_entity() as $uid => $partial) {
            $metrics[$uid] = $metrics[$uid] ?? $this->empty_row_metrics();
            $metrics[$uid]['messages'] += $partial['messages'];
            foreach (self::MESSAGE_MODES as $mode) {
                $metrics[$uid][$mode] += $partial[$columns[$mode]];
            }
            $metrics[$uid]['lastactive'] = max($metrics[$uid]['lastactive'], $partial['lastactive']);
            $this->mark_active($metrics[$uid], (int) $uid, true, $partial['activedays']);
        }

        foreach ($this->get_closed_sessions($level, $courseid, 0, $roleids, $timestart, $timeend) as $uid => $totals) {
            $metrics[$uid] = $metrics[$uid] ?? $this->empty_row_metrics();
            $metrics[$uid]['sessions'] += $totals['sessions'];
            $metrics[$uid]['duration'] += $totals['duration'];
            $metrics[$uid]['lastactive'] = max($metrics[$uid]['lastactive'], $totals['lastend']);
            $this->mark_active($metrics[$uid], (int) $uid, true, 0);
        }

        foreach ($this->get_open_sessions($level, $courseid, 0, $roleids) as $open) {
            $seconds = $this->open_session_duration($open, $timestart, $timeend);
            if ($seconds === null) {
                continue;
            }
            $uid = (int) $open->userid;
            $metrics[$uid] = $metrics[$uid] ?? $this->empty_row_metrics();
            $metrics[$uid]['sessions']++;
            $metrics[$uid]['duration'] += $seconds;
            $metrics[$uid]['lastactive'] = max($metrics[$uid]['lastactive'], (int) $open->lastmessage);
            $this->mark_active($metrics[$uid], $uid, true, 0);
        }

        $rows = [];
        foreach ($metrics as $uid => $rowmetrics) {
            $user = $users[$uid] ?? null;
            $row = $this->numeric_row((int) $uid, $user ? fullname($user) : (string) $uid, $rowmetrics);
            $row['user'] = $user;
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * User rows: the course page, every course activity and any other module with usage.
     *
     * Activities of a tutor-excluded module type are hidden while they have no messages.
     *
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int $timestart Period start.
     * @param int $timeend Period end.
     * @param int[] $roleids Role filter.
     * @return array[]
     */
    protected function build_user_rows(int $courseid, int $userid, int $timestart, int $timeend, array $roleids): array {
        global $DB;

        $level = self::LEVEL_USER;
        $cms = [];
        $metrics = [0 => $this->empty_row_metrics()];
        try {
            $modinfo = $courseid > 0 ? get_fast_modinfo($courseid) : null;
        } catch (\Throwable $e) {
            $modinfo = null;
        }
        if ($modinfo) {
            foreach ($modinfo->get_cms() as $cm) {
                if (!empty($cm->deletioninprogress)) {
                    continue;
                }
                $cms[(int) $cm->id] = $cm;
                $metrics[(int) $cm->id] = $this->empty_row_metrics();
            }
        }

        $scope = $this->get_scope($level, $courseid, $userid, $roleids);
        [$wheresql, $params] = $scope->where('ev');
        $modesql = [];
        foreach (self::MESSAGE_MODES as $mode) {
            if ($mode === tutor_message::MODE_NORMAL) {
                $condition = 'ev.mode NOT IN (:tusguide, :tusquiz, :tusteach)';
            } else {
                $condition = "ev.mode = :tusmode{$mode}";
                $params['tusmode' . $mode] = $mode;
            }
            $params['tusmsg' . $mode] = tutor_usage_recorder::EVENT_MESSAGE;
            $modesql[] = "SUM(CASE WHEN ev.eventtype = :tusmsg{$mode} AND {$condition} THEN 1 ELSE 0 END) AS {$mode}";
        }
        $params += [
            'tusguide' => tutor_message::MODE_GUIDE,
            'tusquiz' => tutor_message::MODE_QUIZ,
            'tusteach' => tutor_message::MODE_TEACH,
            'tusmsg' => tutor_usage_recorder::EVENT_MESSAGE,
            'tusstart' => $timestart,
            'tusend' => $timeend,
        ];
        $records = $DB->get_records_sql(
            "SELECT ev.cmid,
                    SUM(CASE WHEN ev.eventtype = :tusmsg THEN 1 ELSE 0 END) AS messages,
                    " . implode(",\n                    ", $modesql) . ",
                    MAX(ev.timecreated) AS lastactive
               FROM {" . tutor_usage_recorder::TABLE_EVENT . "} ev
              WHERE ev.timecreated >= :tusstart AND ev.timecreated <= :tusend AND {$wheresql}
           GROUP BY ev.cmid",
            $params
        );
        foreach ($records as $record) {
            $cmid = (int) $record->cmid;
            // Unknown modules (for example a deleted activity) keep their historical usage.
            $metrics[$cmid] = $metrics[$cmid] ?? $this->empty_row_metrics();
            $metrics[$cmid]['messages'] += (int) $record->messages;
            foreach (self::MESSAGE_MODES as $mode) {
                $metrics[$cmid][$mode] += (int) $record->{$mode};
            }
            $metrics[$cmid]['lastactive'] = max($metrics[$cmid]['lastactive'], (int) $record->lastactive);
        }

        // Sessions are course-scoped, so they are credited to the course page row.
        $sessions = $this->get_session_metrics($level, $courseid, $userid, $timestart, $timeend, $roleids);
        $metrics[0]['sessions'] += (int) $sessions['sessions'];
        $metrics[0]['duration'] += (int) $sessions['duration'];

        $excluded = $this->get_tutor_excluded_modules($courseid);
        $rows = [];
        foreach ($metrics as $cmid => $rowmetrics) {
            $cm = $cms[$cmid] ?? null;
            if ($cmid === 0) {
                $namelabel = get_string('tutor_usage_report_course_page', 'local_dixeo');
                $moduletype = 'course';
                $moduletypelabel = get_string('course');
            } else if ($cm) {
                $namelabel = format_string($cm->name);
                $moduletype = (string) $cm->modname;
                $moduletypelabel = get_string('modulename', $cm->modname);
            } else {
                $namelabel = (string) $cmid;
                $moduletype = '';
                $moduletypelabel = '';
            }
            if (
                $rowmetrics['messages'] < 1
                && $moduletype !== ''
                && $moduletype !== 'course'
                && in_array($moduletype, $excluded, true)
            ) {
                continue;
            }

            $row = $this->numeric_row((int) $cmid, $namelabel, $rowmetrics);
            $row['moduletype'] = $moduletype;
            $row['moduletypelabel'] = $moduletypelabel;
            $row['cm'] = $cm;
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Mark a user as active on a row.
     *
     * @param array $metrics Row metrics (by reference).
     * @param int $userid User id.
     * @param bool $incoursescope Whether the user is in scope in the row's course.
     * @param int $activedays Active days of the user in this row (0 for sessions).
     */
    protected function mark_active(array &$metrics, int $userid, bool $incoursescope, int $activedays): void {
        if ($userid < 1) {
            return;
        }
        $metrics['activeuserids'][$userid] = !empty($metrics['activeuserids'][$userid]) || $incoursescope;
        if ($activedays > 0) {
            $metrics['activedaysbyuser'][$userid] = ($metrics['activedaysbyuser'][$userid] ?? 0) + $activedays;
        }
    }

    /**
     * Numeric row fields used for sorting.
     *
     * @param int $key Entity key.
     * @param string $namelabel Display name.
     * @param array $metrics Row metrics.
     * @return array
     */
    protected function numeric_row(int $key, string $namelabel, array $metrics): array {
        // Active days averaged over the users active in this row; a user row holds a single user.
        $activedays = array_values($metrics['activedaysbyuser']);
        return [
            'key' => $key,
            'namelabel' => $namelabel,
            'moduletype' => '',
            'moduletypelabel' => '',
            'engagement' => $activedays !== [] ? array_sum($activedays) / count($activedays) : 0.0,
            'adoption' => 0.0,
            'activeinscope' => 0,
            'totalinscope' => 0,
            'messages' => (int) $metrics['messages'],
            'normal' => (int) $metrics[tutor_message::MODE_NORMAL],
            'guide' => (int) $metrics[tutor_message::MODE_GUIDE],
            'quiz' => (int) $metrics[tutor_message::MODE_QUIZ],
            'teach' => (int) $metrics[tutor_message::MODE_TEACH],
            'sessions' => (int) $metrics['sessions'],
            'duration' => (int) $metrics['duration'],
            'lastactive' => (int) $metrics['lastactive'],
        ];
    }

    /**
     * Format one summary-table row for display or export.
     *
     * @param string $level Report level.
     * @param array $row Numeric row from build_row_metrics().
     * @param int $courseid Course id for user level links.
     * @param array $linkparams Extra URL parameters to carry into the drill-down link.
     * @param int[]|null $sessiondurations Session durations for the tooltip, or null to skip tooltips.
     * @return array
     */
    protected function format_summary_row(
        string $level,
        array $row,
        int $courseid,
        array $linkparams,
        ?array $sessiondurations
    ): array {
        $url = null;
        $adoptionformatted = '';
        $adoptiontooltip = '';
        $withtooltips = $sessiondurations !== null;

        if ($level === self::LEVEL_SITE) {
            if (!empty($row['course'])) {
                $url = self::build_report_url(array_merge($linkparams, [
                    'level' => self::LEVEL_COURSE,
                    'courseid' => (int) $row['course']->id,
                ]));
            }
            $adoptionformatted = number_format($row['adoption'], 1) . '%';
            if ($withtooltips) {
                $adoptiontooltip = get_string('tutor_usage_report_cell_tooltip_adoption', 'local_dixeo', (object) [
                    'active' => number_format($row['activeinscope']),
                    'total' => number_format($row['totalinscope']),
                ]);
            }
        } else if ($level === self::LEVEL_COURSE) {
            if (!empty($row['user'])) {
                $url = self::build_report_url(array_merge($linkparams, [
                    'level' => self::LEVEL_USER,
                    'courseid' => $courseid,
                    'userid' => (int) $row['user']->id,
                ]));
            }
        } else if ((int) $row['key'] === 0) {
            $url = (new \moodle_url('/course/view.php', ['id' => $courseid]))->out(false);
        } else if (!empty($row['cm'])) {
            $url = (new \moodle_url('/mod/' . $row['cm']->modname . '/view.php', ['id' => $row['cm']->id]))->out(false);
        }

        $messages = $row['messages'];
        $engagement = (float) $row['engagement'];
        $formatted = [
            'key' => (int) $row['key'],
            'namelabel' => $row['namelabel'],
            'url' => $url,
            'isuserlevel' => $level === self::LEVEL_USER,
            'issitelevel' => $level === self::LEVEL_SITE,
            'engagement' => $engagement,
            'engagementformatted' => get_string(
                'tutor_usage_report_kpi_engagement_value',
                'local_dixeo',
                $level === self::LEVEL_COURSE
                    ? number_format((int) round($engagement))
                    : number_format($engagement, 1)
            ),
            'moduletype' => $row['moduletype'],
            'moduletypelabel' => $row['moduletypelabel'],
            'adoption' => $row['adoption'],
            'adoptionformatted' => $adoptionformatted,
            'adoptiontooltip' => $adoptiontooltip,
            'messages' => $messages,
            'messagesformatted' => number_format($messages),
            'sessions' => $row['sessions'],
            'duration' => $row['duration'],
            'durationformatted' => self::format_duration($row['duration']),
            'lastactive' => $row['lastactive'],
            'lastactiveformatted' => $row['lastactive'] > 0
                ? site_calendar::format($row['lastactive'], get_string('strftimedatetime', 'langconfig'))
                : get_string('never', 'moodle'),
        ];
        foreach (self::MESSAGE_MODES as $mode) {
            $formatted[$mode] = $row[$mode];
            $formatted[$mode . 'formatted'] = number_format($row[$mode]);
            $formatted[$mode . 'tooltip'] = $this->format_mode_pct($row[$mode], $messages);
        }

        if ($withtooltips) {
            $durations = array_map('intval', $sessiondurations);
            $formatted['durationtooltip'] = get_string(
                'tutor_usage_report_kpi_duration_secondary',
                'local_dixeo',
                (object) [
                    'median' => self::format_duration((int) round($this->median($durations))),
                    'average' => self::format_duration(
                        $durations !== [] ? (int) round(array_sum($durations) / count($durations)) : 0
                    ),
                ]
            );
        } else {
            $formatted['durationtooltip'] = '';
        }

        return $formatted;
    }

    /**
     * Module types listed in the Dixeo Tutor excludedmodules setting.
     *
     * @param int $courseid Course whose override applies. Zero reads the Dixeo value.
     * @return string[]
     */
    protected function get_tutor_excluded_modules(int $courseid = 0): array {
        $context = $courseid > SITEID
            ? \context_course::instance($courseid)
            : \context_system::instance();
        $setting = \local_dixeo\external\service_factory::get_settings_config()
            ->get('block_dixeo_tutor', 'excludedmodules', $context)
            ->get_value();
        $modules = [];
        foreach (explode(',', $setting) as $module) {
            $module = trim($module);
            if ($module !== '') {
                $modules[] = $module;
            }
        }
        return $modules;
    }

    /**
     * Partial field holding the message count of each tutor mode.
     *
     * @return array<string, string> Mode to field name.
     */
    protected static function mode_columns(): array {
        return [
            tutor_message::MODE_NORMAL => 'msgnormal',
            tutor_message::MODE_GUIDE => 'msgguide',
            tutor_message::MODE_QUIZ => 'msgquiz',
            tutor_message::MODE_TEACH => 'msgteach',
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
            tutor_message::MODE_NORMAL => 0,
            tutor_message::MODE_GUIDE => 0,
            tutor_message::MODE_QUIZ => 0,
            tutor_message::MODE_TEACH => 0,
            'sessions' => 0,
            'duration' => 0,
            'lastactive' => 0,
            'activeuserids' => [],
            'activedaysbyuser' => [],
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
        $seconds = max(0, $seconds);
        if ($seconds < MINSECS) {
            return get_string('tutor_usage_duration_seconds', 'local_dixeo', $seconds);
        }

        // Truncating instead of rounding keeps units from overflowing, e.g. no "1h 60m".
        $minutesperhour = intdiv(HOURSECS, MINSECS);
        $minutesperday = intdiv(DAYSECS, MINSECS);
        $totalminutes = intdiv($seconds, MINSECS);
        if ($totalminutes < $minutesperhour) {
            return get_string('tutor_usage_duration_minutes', 'local_dixeo', $totalminutes);
        }

        $days = intdiv($totalminutes, $minutesperday);
        $hours = intdiv($totalminutes % $minutesperday, $minutesperhour);
        $minutes = $totalminutes % $minutesperhour;
        if ($days > 0) {
            return get_string('tutor_usage_duration_days', 'local_dixeo', (object) [
                'days' => $days,
                'hours' => $hours,
                'minutes' => $minutes,
            ]);
        }
        return get_string('tutor_usage_duration_hours', 'local_dixeo', (object) [
            'hours' => $hours,
            'minutes' => $minutes,
        ]);
    }
}
