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

/**
 * Courses and users a tutor usage report covers, as SQL fragments.
 *
 * The user part is a semi-join on active enrolments (and role assignments when a role
 * scope is set), so no list of user ids is ever bound. At site level, a user who is in
 * scope in any covered course counts in every covered course; at course and user levels
 * the enrolment and role must be in that course.
 *
 * Aliases passed to where() and pair_in_scope() are internal constants. Parameter names
 * carry an internal counter, so fragments can be combined in one statement.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tutor_usage_scope {
    /** @var int Counter that keeps parameter names unique. */
    private static int $counter = 0;

    /** @var string Report level. */
    private string $level;

    /** @var int Course id (course and user levels). */
    private int $courseid;

    /** @var int User id (user level). */
    private int $userid;

    /** @var int[] Role ids; empty means any active enrolment. */
    private array $roleids;

    /** @var bool Whether the viewer sees every course at site level. */
    private bool $siteviewer = false;

    /** @var int[] Courses the viewer may open at site level, when not a site viewer. */
    private array $courseids = [];

    /** @var int|null Memoised count_users() result. */
    private ?int $usercount = null;

    /**
     * Private constructor; use for_viewer().
     */
    private function __construct() {
    }

    /**
     * Scope of a report request for a viewer.
     *
     * @param string $level Report level (site|course|user).
     * @param int $courseid Course id for course and user levels.
     * @param int $userid User id for the user level.
     * @param int[] $roleids Role ids; empty means any active enrolment.
     * @param int|null $viewerid Viewer user id (defaults to the current user).
     * @return self
     */
    public static function for_viewer(string $level, int $courseid, int $userid, array $roleids, ?int $viewerid = null): self {
        $scope = new self();
        $scope->level = $level;
        $scope->courseid = $courseid;
        $scope->userid = $userid;
        $scope->roleids = array_values(array_unique(array_map('intval', $roleids)));
        if ($level === tutor_usage_report_service::LEVEL_SITE) {
            $scope->siteviewer = tutor_usage_report_service::can_view_site($viewerid);
            if (!$scope->siteviewer) {
                $scope->courseids = tutor_usage_report_service::get_accessible_courseids($viewerid);
            }
        }
        return $scope;
    }

    /**
     * Report level of this scope.
     *
     * @return string
     */
    public function get_level(): string {
        return $this->level;
    }

    /**
     * Restrict $alias.courseid and $alias.userid to the scope.
     *
     * @param string $alias Table alias (internal constant).
     * @return array{0: string, 1: array} SQL condition and parameters.
     */
    public function where(string $alias): array {
        $prefix = $this->prefix();
        [$coursesql, $params] = $this->course_condition($alias . '.courseid', $prefix . 'w', false);
        $conditions = [$coursesql];
        if ($this->level === tutor_usage_report_service::LEVEL_USER) {
            $conditions[] = "{$alias}.userid = :{$prefix}u";
            $params[$prefix . 'u'] = $this->userid;
        }
        [$usersql, $userparams] = $this->users_sql();
        $conditions[] = "{$alias}.userid IN ({$usersql})";
        return [implode(' AND ', $conditions), $params + $userparams];
    }

    /**
     * Condition: user $alias.userid is actively enrolled in $alias.courseid with a role in scope.
     *
     * @param string $alias Table alias (internal constant).
     * @return array{0: string, 1: array} SQL condition and parameters.
     */
    public function pair_in_scope(string $alias): array {
        global $DB;

        $prefix = $this->prefix();
        $params = [$prefix . 'active' => ENROL_USER_ACTIVE];
        $rolejoin = '';
        $rolewhere = '';
        if ($this->roleids !== []) {
            [$rolesql, $roleparams] = $DB->get_in_or_equal($this->roleids, SQL_PARAMS_NAMED, $prefix . 'r');
            $rolejoin = "JOIN {role_assignments} pra ON pra.userid = pue.userid
                         JOIN {context} pctx ON pctx.id = pra.contextid
                              AND pctx.contextlevel = :{$prefix}ctxlevel AND pctx.instanceid = pe.courseid";
            $rolewhere = "AND pra.roleid {$rolesql}";
            $params += $roleparams + [$prefix . 'ctxlevel' => CONTEXT_COURSE];
        }

        $sql = "EXISTS (SELECT 1
                          FROM {user_enrolments} pue
                          JOIN {enrol} pe ON pe.id = pue.enrolid
                          {$rolejoin}
                         WHERE pue.userid = {$alias}.userid
                           AND pe.courseid = {$alias}.courseid
                           AND pue.status = :{$prefix}active
                           {$rolewhere})";
        return [$sql, $params];
    }

    /**
     * SELECT of the user ids in scope (may repeat a user).
     *
     * @return array{0: string, 1: array} SQL and parameters.
     */
    public function users_sql(): array {
        global $DB;

        $prefix = $this->prefix();
        [$coursesql, $params] = $this->course_condition('e.courseid', $prefix . 'e', true);
        $params[$prefix . 'active'] = ENROL_USER_ACTIVE;
        $rolejoin = '';
        $rolewhere = '';
        if ($this->roleids !== []) {
            [$rolesql, $roleparams] = $DB->get_in_or_equal($this->roleids, SQL_PARAMS_NAMED, $prefix . 'r');
            $rolejoin = "JOIN {role_assignments} ra ON ra.userid = ue.userid
                         JOIN {context} ctx ON ctx.id = ra.contextid
                              AND ctx.contextlevel = :{$prefix}ctxlevel AND ctx.instanceid = e.courseid";
            $rolewhere = "AND ra.roleid {$rolesql}";
            $params += $roleparams + [$prefix . 'ctxlevel' => CONTEXT_COURSE];
        }

        $sql = "SELECT ue.userid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  {$rolejoin}
                 WHERE ue.status = :{$prefix}active
                   AND {$coursesql}
                   {$rolewhere}";
        return [$sql, $params];
    }

    /**
     * Distinct users in scope: across the covered courses (site), in the course (course), or 0/1 (user).
     *
     * @return int
     */
    public function count_users(): int {
        global $DB;

        if ($this->usercount !== null) {
            return $this->usercount;
        }

        [$usersql, $params] = $this->users_sql();
        if ($this->level === tutor_usage_report_service::LEVEL_USER) {
            $prefix = $this->prefix();
            $params[$prefix . 'u'] = $this->userid;
            $exists = $this->userid > 0
                && $DB->record_exists_sql("SELECT 1 FROM {user} u WHERE u.id = :{$prefix}u AND u.id IN ({$usersql})", $params);
            $this->usercount = $exists ? 1 : 0;
            return $this->usercount;
        }

        $this->usercount = (int) $DB->count_records_sql("SELECT COUNT(DISTINCT s.userid) FROM ({$usersql}) s", $params);
        return $this->usercount;
    }

    /**
     * Users in scope per course, for a list of courses.
     *
     * Each course is counted with course semantics (enrolment and role in that course).
     *
     * @param int[] $courseids Course ids.
     * @return array<int, int> Course id to number of users in scope.
     */
    public function count_users_per_course(array $courseids): array {
        global $DB;

        $courseids = array_values(array_unique(array_map('intval', $courseids)));
        if ($courseids === []) {
            return [];
        }

        $prefix = $this->prefix();
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, $prefix . 'c');
        $params[$prefix . 'active'] = ENROL_USER_ACTIVE;
        $rolejoin = '';
        $rolewhere = '';
        if ($this->roleids !== []) {
            [$rolesql, $roleparams] = $DB->get_in_or_equal($this->roleids, SQL_PARAMS_NAMED, $prefix . 'r');
            $rolejoin = "JOIN {role_assignments} ra ON ra.userid = ue.userid
                         JOIN {context} ctx ON ctx.id = ra.contextid
                              AND ctx.contextlevel = :{$prefix}ctxlevel AND ctx.instanceid = e.courseid";
            $rolewhere = "AND ra.roleid {$rolesql}";
            $params += $roleparams + [$prefix . 'ctxlevel' => CONTEXT_COURSE];
        }

        $records = $DB->get_records_sql(
            "SELECT e.courseid, COUNT(DISTINCT ue.userid) AS total
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
               {$rolejoin}
              WHERE ue.status = :{$prefix}active
                AND e.courseid {$insql}
                {$rolewhere}
           GROUP BY e.courseid",
            $params
        );

        $totals = [];
        foreach ($records as $record) {
            $totals[(int) $record->courseid] = (int) $record->total;
        }
        return $totals;
    }

    /**
     * Condition on a course id column for this scope.
     *
     * @param string $column Qualified course id column.
     * @param string $prefix Parameter name prefix.
     * @param bool $enrolment True for the enrolment subquery, where the course row always exists.
     * @return array{0: string, 1: array}
     */
    private function course_condition(string $column, string $prefix, bool $enrolment): array {
        global $DB;

        if ($this->level !== tutor_usage_report_service::LEVEL_SITE) {
            return ["{$column} = :{$prefix}course", [$prefix . 'course' => $this->courseid]];
        }

        if ($this->siteviewer) {
            if ($enrolment) {
                return ["{$column} <> :{$prefix}site", [$prefix . 'site' => (int) SITEID]];
            }
            // Only existing courses are reported, as their usage rows outlive a deleted course.
            return [
                "{$column} IN (SELECT sc.id FROM {course} sc WHERE sc.id <> :{$prefix}site)",
                [$prefix . 'site' => (int) SITEID],
            ];
        }

        if ($this->courseids === []) {
            return ['1 = 0', []];
        }
        [$insql, $params] = $DB->get_in_or_equal($this->courseids, SQL_PARAMS_NAMED, $prefix . 'c');
        return ["{$column} {$insql}", $params];
    }

    /**
     * Unique parameter name prefix for one fragment.
     *
     * @return string
     */
    private function prefix(): string {
        return 'tus' . (++self::$counter);
    }
}
