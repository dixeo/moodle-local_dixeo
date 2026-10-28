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
 * Web service to get file sync status for a course.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_dixeo\external\traits\capability_check;
use local_dixeo\service\file_sync_service;

/**
 * External function to get file sync status.
 */
class get_file_sync_status extends external_api {
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
     * Get the file sync status for a course.
     *
     * @param int $courseid The course ID.
     * @return array The sync status.
     */
    public static function execute(int $courseid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
        ]);

        self::validate_course_capability($params['courseid']);

        $service = service_factory::get_file_sync_service();

        // If currently syncing, poll API for real-time status update.
        $localstatus = $service->get_status($params['courseid']);
        if ($localstatus->status === 'syncing') {
            $status = $service->poll_status($params['courseid']);
        } else {
            $status = $localstatus;
        }

        return [
            'enabled' => $status->enabled,
            'status' => $status->status,
            'filestotal' => $status->filestotal ?? 0,
            'filescompleted' => $status->filescompleted ?? 0,
            'progresspercent' => $status->progresspercent ?? 0,
            'errormessage' => $status->errormessage ?? '',
            'lastsyncstarted' => $status->lastsyncstarted ?? 0,
            'lastsynccompleted' => $status->lastsynccompleted ?? 0,
            'filesskipped' => $status->filesskipped ?? 0,
            'files' => $service->list_status_files($params['courseid'], (string) $status->status),
        ];
    }

    /**
     * Define the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'enabled' => new external_value(PARAM_BOOL, 'Whether sync is enabled'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Sync status (none, syncing, synchronized, outdated, error, paused)'),
            'filestotal' => new external_value(PARAM_INT, 'Total number of files', VALUE_OPTIONAL),
            'filescompleted' => new external_value(PARAM_INT, 'Number of files synced', VALUE_OPTIONAL),
            'progresspercent' => new external_value(PARAM_INT, 'Progress percentage 0-100', VALUE_OPTIONAL),
            'errormessage' => new external_value(PARAM_RAW, 'Error message if status is error', VALUE_OPTIONAL),
            'lastsyncstarted' => new external_value(PARAM_INT, 'Timestamp of last sync start', VALUE_OPTIONAL),
            'lastsynccompleted' => new external_value(PARAM_INT, 'Timestamp of last successful sync', VALUE_OPTIONAL),
            'filesskipped' => new external_value(PARAM_INT, 'Number of files the last sync left out', VALUE_OPTIONAL),
            'files' => new external_multiple_structure(
                new external_single_structure([
                    'filename' => new external_value(PARAM_TEXT, 'File name'),
                    'state' => new external_value(PARAM_ALPHA, 'synced, syncing, or problem'),
                    'reason' => new external_value(
                        PARAM_ALPHANUMEXT,
                        'toolarge, unsupported, empty, error, retry, or blank when the file is synced or syncing',
                        VALUE_OPTIONAL
                    ),
                ])
            ),
        ]);
    }
}
