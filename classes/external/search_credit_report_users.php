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

namespace local_dixeo\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_dixeo\external\traits\credit_report_access;
use local_dixeo\service\credit_usage_report_service;

/**
 * Search users with credit usage in a report period.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_credit_report_users extends external_api {
    use credit_report_access;

    /**
     * Define parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query' => new external_value(PARAM_RAW_TRIMMED, 'Search query', VALUE_DEFAULT, ''),
            'timestart' => new external_value(PARAM_INT, 'Period start timestamp'),
            'timeend' => new external_value(PARAM_INT, 'Period end timestamp'),
        ]);
    }

    /**
     * Execute the search.
     *
     * @param string $query Search query.
     * @param int $timestart Period start timestamp.
     * @param int $timeend Period end timestamp.
     * @return array
     */
    public static function execute(string $query, int $timestart, int $timeend): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'query' => $query,
            'timestart' => $timestart,
            'timeend' => $timeend,
        ]);
        self::validate_credit_report_access();

        $service = new credit_usage_report_service();
        $results = $service->search_filter_users(
            $params['query'],
            (int) $params['timestart'],
            (int) $params['timeend']
        );

        return [
            'list' => array_map(static fn(array $item): array => [
                'id' => $item['id'],
                'label' => $item['label'],
            ], $results),
        ];
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'list' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'User ID'),
                    'label' => new external_value(PARAM_TEXT, 'Display label'),
                ])
            ),
        ]);
    }
}
