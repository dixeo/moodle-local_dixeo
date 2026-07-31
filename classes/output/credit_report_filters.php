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

namespace local_dixeo\output;

use local_dixeo\dto\credit_transaction;
use local_dixeo\service\credit_usage_report_service;
use local_dixeo\util\credit_component_mapper;

/**
 * Validated credit report filter state.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class credit_report_filters {
    /** @var string[] */
    public array $components;

    /** @var string[] */
    public array $jobtypes;

    /** @var string[] */
    public array $moduletypes;

    /** @var int[] */
    public array $userids;

    /** @var int[] */
    public array $courseids;

    /**
     * Build validated filters from raw request values.
     *
     * @param array $raw Raw filter values.
     * @return self
     */
    public static function from_raw(array $raw): self {
        $service = new credit_usage_report_service();
        $filters = new self();
        $filters->components = array_values(array_filter($raw['components'] ?? []));
        $filters->jobtypes = credit_component_mapper::normalize_action_list($raw['jobtypes'] ?? []);
        $filters->moduletypes = array_values(array_filter($raw['moduletypes'] ?? []));
        $filters->userids = $service->filter_valid_user_ids(array_map('intval', $raw['userids'] ?? []));
        $filters->courseids = $service->filter_valid_course_ids(array_map('intval', $raw['courseids'] ?? []));
        return $filters;
    }

    /**
     * Build report service filters for the active period.
     *
     * @param array $period Resolved period data.
     * @return array
     */
    public function to_service_filters(array $period): array {
        return [
            'type' => credit_transaction::TYPE_DEDUCTION,
            'timestart' => (int) $period['timestart'],
            'timeend' => (int) $period['timeend'],
            'components' => $this->components,
            'jobtypes' => $this->jobtypes,
            'moduletypes' => $this->moduletypes,
            'userids' => $this->userids,
            'courseids' => $this->courseids,
        ];
    }

    /**
     * Build period-scoped filters for enum option discovery.
     *
     * @param array $period Resolved period data.
     * @return array
     */
    public function to_period_filters(array $period): array {
        return [
            'type' => credit_transaction::TYPE_DEDUCTION,
            'timestart' => (int) $period['timestart'],
            'timeend' => (int) $period['timeend'],
        ];
    }

    /**
     * Serialize active filters for report URLs and hidden form fields.
     *
     * @return array
     */
    public function to_query_params(): array {
        $params = [];

        foreach ($this->components as $value) {
            $params['component'][] = $value;
        }
        foreach ($this->jobtypes as $value) {
            $params['jobtype'][] = $value;
        }
        foreach ($this->moduletypes as $value) {
            $params['moduletype'][] = $value;
        }
        foreach ($this->userids as $value) {
            $params['userid'][] = $value;
        }
        foreach ($this->courseids as $value) {
            $params['courseid'][] = $value;
        }

        return $params;
    }

    /**
     * Build hidden form fields for export and other forms.
     *
     * @return array<int, array{name: string, value: string}>
     */
    public function to_hidden_fields(): array {
        $hidden = [];
        foreach ($this->to_query_params() as $key => $values) {
            foreach ($values as $value) {
                $hidden[] = [
                    'name' => $key . '[]',
                    'value' => (string) $value,
                ];
            }
        }
        return $hidden;
    }

    /**
     * Load labels for applied entity filters.
     *
     * @param credit_usage_report_service $service Report service.
     * @return array{users: array, courses: array}
     */
    public function get_applied_entity_options(credit_usage_report_service $service): array {
        $labels = $service->get_entity_labels($this->userids, $this->courseids);

        $users = [];
        foreach ($this->userids as $userid) {
            $users[] = [
                'value' => (string) $userid,
                'label' => $labels['users'][$userid] ?? (string) $userid,
                'selected' => true,
            ];
        }

        $courses = [];
        foreach ($this->courseids as $courseid) {
            $courses[] = [
                'value' => (string) $courseid,
                'label' => $labels['courses'][$courseid] ?? (string) $courseid,
                'selected' => true,
            ];
        }

        return [
            'users' => $users,
            'courses' => $courses,
        ];
    }
}
