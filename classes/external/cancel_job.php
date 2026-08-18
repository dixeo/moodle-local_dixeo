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
 * Web service to cancel a running job.
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
 * External function to cancel a running job.
 *
 * Access follows the mode persisted at job registration.
 */
class cancel_job extends external_api {
    use capability_check;

    /**
     * Define parameters for the web service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'jobid' => new external_value(PARAM_RAW, 'The job UUID to cancel'),
            'courseid' => new external_value(PARAM_INT, 'Course ID the job belongs to'),
        ]);
    }

    /**
     * Cancel a running job.
     *
     * @param string $jobid The job UUID.
     * @param int $courseid Course ID the job belongs to.
     * @return array The cancellation result.
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
            $result = $service->cancel_job(
                $params['jobid'],
                $params['courseid'],
                (int) $USER->id
            );

            $status = $result['status'] ?? 'cancelled';
            return response_factory::cancellation_result(
                $params['jobid'],
                true,
                'Job ' . $status . ' successfully'
            );
        } catch (api_exception $e) {
            return response_factory::cancellation_result(
                $params['jobid'],
                false,
                $e->getMessage(),
                $e->get_error_code()
            );
        }
    }

    /**
     * Define the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the cancellation was successful'),
            'jobid' => new external_value(PARAM_RAW, 'The job UUID'),
            'message' => new external_value(PARAM_RAW, 'Status message'),
            'errorcode' => new external_value(PARAM_ALPHANUMEXT, 'Error code if failed', VALUE_OPTIONAL),
        ]);
    }
}
