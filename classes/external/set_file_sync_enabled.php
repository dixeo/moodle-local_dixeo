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
 * Web service to enable or disable file sync for a course.
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
 * External function to set file sync enabled state.
 */
class set_file_sync_enabled extends external_api {
    use capability_check;

    /**
     * Define parameters for the web service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'The course ID'),
            'enabled' => new external_value(PARAM_BOOL, 'Whether to enable sync'),
            'removefiles' => new external_value(PARAM_BOOL, 'Whether to remove files when disabling', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Enable or disable file sync for a course.
     *
     * @param int $courseid The course ID.
     * @param bool $enabled Whether to enable sync.
     * @param bool $removefiles Whether to remove files when disabling.
     * @return array The result with success flag and status.
     */
    public static function execute(int $courseid, bool $enabled, bool $removefiles = false): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'enabled' => $enabled,
            'removefiles' => $removefiles,
        ]);

        self::validate_course_sync_capability($params['courseid']);

        $service = service_factory::get_file_sync_service();

        try {
            if ($params['enabled']) {
                $service->enable_sync($params['courseid'], $USER->id);
                // Note: Sync is triggered separately via trigger_file_sync web service.
            } else {
                $service->disable_sync($params['courseid'], $USER->id, $params['removefiles']);
            }

            $status = $service->get_status($params['courseid']);

            return [
                'success' => true,
                'status' => $status->status,
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
            'success' => new external_value(PARAM_BOOL, 'Whether the operation succeeded'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Current sync status'),
            'error' => new external_value(PARAM_RAW, 'Error message if failed', VALUE_OPTIONAL),
        ]);
    }
}
