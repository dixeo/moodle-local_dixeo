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
 * Web service to get the status of a job.
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
use local_dixeo\api\exception\api_exception;

/**
 * External function to get the status of a job.
 *
 * Access follows the mode persisted at job registration (course_shared for
 * collaborative modulegen jobs; initiator_scoped by default).
 */
class get_job_status extends external_api {
    use capability_check;

    /**
     * Define parameters for the web service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'jobid' => new external_value(PARAM_RAW, 'The job UUID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID the job belongs to'),
        ]);
    }

    /**
     * Get the status of a job.
     *
     * @param string $jobid The job UUID.
     * @param int $courseid Course ID the job belongs to.
     * @return array The job status.
     */
    public static function execute(string $jobid, int $courseid): array {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/local/dixeo/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'jobid' => $jobid,
            'courseid' => $courseid,
        ]);

        self::validate_course_capability($params['courseid']);

        if (!\local_dixeo_is_valid_job_uuid($params['jobid'])) {
            throw new \moodle_exception('error:job_not_found', 'local_dixeo');
        }

        try {
            $service = service_factory::get_job_service();
            $status = $service->get_job_status(
                $params['jobid'],
                $params['courseid'],
                (int) $USER->id
            );

            $data = $status->to_array();
            // Encode result as JSON since it has dynamic structure.
            if (isset($data['result']) && is_array($data['result'])) {
                $data['result'] = json_encode($data['result']);
            }

            return $data;
        } catch (api_exception $e) {
            return response_factory::job_status_error($params['jobid'], $e);
        }
    }

    /**
     * Define the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'jobid' => new external_value(PARAM_RAW, 'The job UUID'),
            'type' => new external_value(PARAM_ALPHANUMEXT, 'The job type'),
            'status' => new external_value(PARAM_ALPHA, 'Current status (pending, processing, completed, failed)'),
            'progress' => new external_value(PARAM_INT, 'Progress percentage (0-100)'),
            'createdat' => new external_value(PARAM_INT, 'Unix timestamp when created'),
            'updatedat' => new external_value(PARAM_INT, 'Unix timestamp when last updated', VALUE_OPTIONAL),
            'completedat' => new external_value(PARAM_INT, 'Unix timestamp when completed', VALUE_OPTIONAL),
            'result' => new external_value(PARAM_RAW, 'The result data as JSON', VALUE_OPTIONAL),
            'creditsused' => new external_value(PARAM_INT, 'Credits consumed', VALUE_OPTIONAL),
            // RFC 7807 Problem Details format.
            'error' => new external_single_structure([
                'type' => new external_value(PARAM_ALPHANUMEXT, 'Error type identifier', VALUE_OPTIONAL),
                'title' => new external_value(PARAM_TEXT, 'Human-readable error title', VALUE_OPTIONAL),
                'status' => new external_value(PARAM_INT, 'HTTP status code', VALUE_OPTIONAL),
                'detail' => new external_value(PARAM_TEXT, 'Detailed error description', VALUE_OPTIONAL),
            ], 'Error information (RFC 7807)', VALUE_OPTIONAL),
            'processingtimeseconds' => new external_value(PARAM_FLOAT, 'Processing time in seconds', VALUE_OPTIONAL),
            'namespace' => new external_value(PARAM_RAW, 'The namespace', VALUE_OPTIONAL),
        ]);
    }
}
