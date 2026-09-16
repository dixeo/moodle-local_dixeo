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
 * Web service to trigger immediate file sync for a course.
 *
 * Unlike queue_sync which uses debouncing via adhoc tasks, this executes
 * the sync immediately for when the user explicitly requests it.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_dixeo\external\traits\capability_check;
use local_dixeo\service\file_sync_service;

/**
 * External function to trigger immediate file sync.
 */
class trigger_file_sync extends external_api {
    use capability_check;

    /**
     * Define parameters for the web service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'The course ID'),
        ]);
    }

    /**
     * Trigger immediate file sync for a course.
     *
     * @param int $courseid The course ID.
     * @return array The result with sync status.
     */
    public static function execute(int $courseid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
        ]);

        self::validate_course_sync_capability($params['courseid']);

        $service = service_factory::get_file_sync_service();

        try {
            $service->enable_sync($params['courseid'], (int) $USER->id);
            $service->trigger_sync($params['courseid']);

            $status = $service->get_status($params['courseid']);

            return [
                'success' => true,
                'status' => $status->status,
                'filestotal' => $status->filestotal,
                'filescompleted' => $status->filescompleted,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status' => 'error',
                'error' => response_factory::safe_message($e),
            ];
        }
    }

    /**
     * Define the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the sync succeeded'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Current sync status'),
            'filestotal' => new external_value(PARAM_INT, 'Total files synced', VALUE_OPTIONAL),
            'filescompleted' => new external_value(PARAM_INT, 'Files successfully synced', VALUE_OPTIONAL),
            'error' => new external_value(PARAM_RAW, 'Error message if failed', VALUE_OPTIONAL),
        ]);
    }
}
