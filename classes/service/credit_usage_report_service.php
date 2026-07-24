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

use local_dixeo\dto\credit_transaction;
use local_dixeo\repository\credit_usage_repository;
use local_dixeo\util\credit_component_mapper;

/**
 * Builds credit usage report data from the local usage table.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credit_usage_report_service {
    /** @var string Week view mode. */
    public const VIEW_WEEK = 'week';

    /** @var string Month view mode. */
    public const VIEW_MONTH = 'month';

    /** @var string Custom date range view mode. */
    public const VIEW_CUSTOM = 'custom';

    /**
     * Build SQL filter conditions from report filters.
     *
     * @param array $filters Report filters.
     * @return array{sql: string, params: array}
     */
    public function build_conditions(array $filters): array {
        global $DB;

        $conditions = ['1 = 1'];
        $params = [];

        $type = $filters['type'] ?? credit_transaction::TYPE_DEDUCTION;
        if (!empty($type)) {
            $conditions[] = 'cu.type = :type';
            $params['type'] = $type;
        }

        if (!empty($filters['timestart'])) {
            $conditions[] = 'cu.timecreated >= :timestart';
            $params['timestart'] = (int) $filters['timestart'];
        }

        if (!empty($filters['timeend'])) {
            $conditions[] = 'cu.timecreated <= :timeend';
            $params['timeend'] = (int) $filters['timeend'];
        }

        if (!empty($filters['components']) && is_array($filters['components'])) {
            [$insql, $inparams] = $DB->get_in_or_equal($filters['components'], SQL_PARAMS_NAMED, 'cmp');
            $conditions[] = "cu.component {$insql}";
            $params = array_merge($params, $inparams);
        }

        if (!empty($filters['jobtypes']) && is_array($filters['jobtypes'])) {
            [$insql, $inparams] = $DB->get_in_or_equal($filters['jobtypes'], SQL_PARAMS_NAMED, 'jt');
            $conditions[] = "cu.jobtype {$insql}";
            $params = array_merge($params, $inparams);
        }

        if (!empty($filters['moduletypes']) && is_array($filters['moduletypes'])) {
            [$insql, $inparams] = $DB->get_in_or_equal($filters['moduletypes'], SQL_PARAMS_NAMED, 'mt');
            $conditions[] = "cu.moduletype {$insql}";
            $params = array_merge($params, $inparams);
        }

        if (!empty($filters['userid'])) {
            $conditions[] = 'cu.userid = :userid';
            $params['userid'] = (int) $filters['userid'];
        }

        if (!empty($filters['courseid'])) {
            $conditions[] = 'cu.courseid = :courseid';
            $params['courseid'] = (int) $filters['courseid'];
        }

        if (isset($filters['creditsmin']) && $filters['creditsmin'] !== '' && $filters['creditsmin'] !== null) {
            $conditions[] = 'cu.credits >= :creditsmin';
            $params['creditsmin'] = (int) $filters['creditsmin'];
        }

        if (isset($filters['creditsmax']) && $filters['creditsmax'] !== '' && $filters['creditsmax'] !== null) {
            $conditions[] = 'cu.credits <= :creditsmax';
            $params['creditsmax'] = (int) $filters['creditsmax'];
        }

        return [
            'sql' => implode(' AND ', $conditions),
            'params' => $params,
        ];
    }

    /**
     * Resolve period boundaries from view parameters.
     *
     * @param string $view View mode.
     * @param string|null $anchor Anchor date Y-m-d.
     * @param int|null $datefrom Custom start timestamp.
     * @param int|null $dateto Custom end timestamp.
     * @return array{timestart: int, timeend: int, label: string, prevanchor: string|null, nextanchor: string|null}
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
            $prev = (clone $anchordate)->modify('-1 month')->format('Y-m-d');
            $next = (clone $anchordate)->modify('+1 month')->format('Y-m-d');
            $label = userdate($start->getTimestamp(), get_string('strftimemonthyear', 'langconfig'));
        } else if ($view === self::VIEW_CUSTOM) {
            $start = (new \DateTime())->setTimestamp($datefrom ?: strtotime('today'));
            $start->setTime(0, 0, 0);
            $end = (new \DateTime())->setTimestamp($dateto ?: strtotime('today'));
            $end->setTime(23, 59, 59);
            $prev = null;
            $next = null;
            $label = userdate($start->getTimestamp(), '%d %b %Y') . ' - ' . userdate($end->getTimestamp(), '%d %b %Y');
        } else {
            $dayofweek = (int) $anchordate->format('N');
            $start = (clone $anchordate)->modify('-' . ($dayofweek - 1) . ' days')->setTime(0, 0, 0);
            $end = (clone $start)->modify('+6 days')->setTime(23, 59, 59);
            $prev = (clone $start)->modify('-7 days')->format('Y-m-d');
            $next = (clone $start)->modify('+7 days')->format('Y-m-d');
            $label = userdate($start->getTimestamp(), '%d %b') . ' - ' . userdate($end->getTimestamp(), '%d %b %Y');
        }

        return [
            'timestart' => $start->getTimestamp(),
            'timeend' => $end->getTimestamp(),
            'label' => $label,
            'prevanchor' => $prev,
            'nextanchor' => $next,
        ];
    }

    /**
     * Get KPI aggregates for the active filters.
     *
     * @param array $filters Report filters.
     * @return array{totalcredits: int, totalusers: int, totalcourses: int, totalrows: int}
     */
    public function get_kpis(array $filters): array {
        global $DB;

        $built = $this->build_conditions($filters);
        $sql = "SELECT COALESCE(SUM(cu.credits), 0) AS totalcredits,
                       COUNT(DISTINCT CASE WHEN cu.userid > 0 THEN cu.userid END) AS totalusers,
                       COUNT(DISTINCT CASE WHEN cu.courseid > 0 THEN cu.courseid END) AS totalcourses,
                       COUNT(1) AS totalrows
                  FROM {" . credit_usage_repository::TABLE . "} cu
                 WHERE {$built['sql']}";

        $row = $DB->get_record_sql($sql, $built['params']);
        return [
            'totalcredits' => (int) ($row->totalcredits ?? 0),
            'totalusers' => (int) ($row->totalusers ?? 0),
            'totalcourses' => (int) ($row->totalcourses ?? 0),
            'totalrows' => (int) ($row->totalrows ?? 0),
        ];
    }

    /**
     * Get histogram data bucketed by day.
     *
     * @param array $filters Report filters.
     * @return array{labels: string[], values: int[]}
     */
    public function get_histogram(array $filters): array {
        global $DB;

        $built = $this->build_conditions($filters);
        $sql = "SELECT cu.timecreated, cu.credits
                  FROM {" . credit_usage_repository::TABLE . "} cu
                 WHERE {$built['sql']}
              ORDER BY cu.timecreated ASC";

        $records = $DB->get_records_sql($sql, $built['params']);
        $buckets = [];

        foreach ($records as $record) {
            $day = userdate((int) $record->timecreated, '%Y-%m-%d');
            if (!isset($buckets[$day])) {
                $buckets[$day] = 0;
            }
            $buckets[$day] += (int) $record->credits;
        }

        $labels = [];
        $values = [];
        foreach ($buckets as $day => $total) {
            $labels[] = userdate(strtotime($day . ' UTC'), '%d %b');
            $values[] = $total;
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    /**
     * Get donut breakdown by component.
     *
     * @param array $filters Report filters.
     * @return array{labels: string[], values: int[]}
     */
    public function get_breakdown(array $filters): array {
        global $DB;

        $built = $this->build_conditions($filters);
        $sql = "SELECT cu.component, SUM(cu.credits) AS totalcredits
                  FROM {" . credit_usage_repository::TABLE . "} cu
                 WHERE {$built['sql']}
              GROUP BY cu.component
              ORDER BY totalcredits DESC";

        $records = $DB->get_records_sql($sql, $built['params']);
        $labels = [];
        $values = [];
        foreach ($records as $record) {
            $labels[] = credit_component_mapper::get_label($record->component);
            $values[] = (int) $record->totalcredits;
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    /**
     * Get distinct filter option values.
     *
     * @return array{components: array, jobtypes: array, moduletypes: array}
     */
    public function get_filter_options(): array {
        global $DB;

        $components = $DB->get_fieldset_select(
            credit_usage_repository::TABLE,
            'DISTINCT component',
            "component IS NOT NULL AND component <> ''",
            []
        );
        $jobtypes = $DB->get_fieldset_select(
            credit_usage_repository::TABLE,
            'DISTINCT jobtype',
            "jobtype IS NOT NULL AND jobtype <> ''",
            []
        );
        $moduletypes = $DB->get_fieldset_select(
            credit_usage_repository::TABLE,
            'DISTINCT moduletype',
            "moduletype IS NOT NULL AND moduletype <> ''",
            []
        );

        return [
            'components' => array_values(array_filter($components)),
            'jobtypes' => array_values(array_filter($jobtypes)),
            'moduletypes' => array_values(array_filter($moduletypes)),
        ];
    }

    /**
     * Get paginated table rows.
     *
     * @param array $filters Report filters.
     * @param int $page Page number (0-based).
     * @param int $perpage Rows per page.
     * @return array{rows: array, total: int}
     */
    public function get_rows(array $filters, int $page = 0, int $perpage = 50): array {
        global $DB;

        $built = $this->build_conditions($filters);
        $countsql = "SELECT COUNT(1)
                       FROM {" . credit_usage_repository::TABLE . "} cu
                      WHERE {$built['sql']}";
        $total = (int) $DB->get_field_sql($countsql, $built['params']);

        $sql = "SELECT cu.*
                  FROM {" . credit_usage_repository::TABLE . "} cu
                 WHERE {$built['sql']}
              ORDER BY cu.timecreated DESC";

        $records = $DB->get_records_sql($sql, $built['params'], $page * $perpage, $perpage);
        $rows = [];

        foreach ($records as $record) {
            $rows[] = $this->format_row($record);
        }

        return [
            'rows' => $rows,
            'total' => $total,
        ];
    }

    /**
     * Format a database row for template output.
     *
     * @param \stdClass $record Usage record.
     * @return array
     */
    private function format_row(\stdClass $record): array {
        $userlabel = get_string('credit_user_unknown', 'local_dixeo');
        $userurl = null;
        if (!empty($record->userid)) {
            $user = \core_user::get_user((int) $record->userid, '*', IGNORE_MISSING);
            if ($user) {
                $userlabel = fullname($user);
                $userurl = (new \moodle_url('/user/profile.php', ['id' => $user->id]))->out(false);
            }
        }

        $courselabel = get_string('credit_context_site', 'local_dixeo');
        $courseurl = null;
        if (!empty($record->courseid)) {
            $course = get_course((int) $record->courseid, IGNORE_MISSING);
            if ($course && !empty($course->id)) {
                $courselabel = format_string($course->fullname);
                $courseurl = (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
            }
        }

        return [
            'credits' => (int) $record->credits,
            'creditsformatted' => credit_service::format_credits((int) $record->credits),
            'component' => (string) $record->component,
            'componentlabel' => credit_component_mapper::get_label($record->component),
            'actioncode' => (string) ($record->jobtype ?: $record->operation),
            'actionlabel' => credit_component_mapper::get_action_label($record->jobtype, $record->operation),
            'moduletype' => (string) ($record->moduletype ?? ''),
            'dateformatted' => userdate((int) $record->timecreated, get_string('strftimedatetime', 'langconfig')),
            'userlabel' => $userlabel,
            'userurl' => $userurl,
            'courselabel' => $courselabel,
            'courseurl' => $courseurl,
            'description' => (string) ($record->description ?? ''),
        ];
    }
}
