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
 * Web service to create a module from a completed job.
 *
 * Retrieves job result and executes DSL to create the Moodle module.
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
use local_dixeo\dsl\interpreter;
use local_dixeo\api\exception\api_exception;
use local_dixeo\dto\job_binding_metadata;
use local_dixeo\service\course_completion_sync_service;
use local_dixeo\external\service_factory;

/**
 * External function to create a module from a completed job.
 */
class create_module_from_job extends external_api {
    use capability_check;

    /**
     * Define parameters for the web service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'jobid' => new external_value(PARAM_RAW, 'The completed job UUID'),
            'courseid' => new external_value(PARAM_INT, 'The course ID'),
            'sectionnumber' => new external_value(PARAM_INT, 'The section number', VALUE_DEFAULT, 0),
            'beforemod' => new external_value(PARAM_INT, 'Course module ID to insert before', VALUE_DEFAULT, 0),
            // Allow callers (e.g. the course structure flow) to override AI-generated name/intro
            // with values from the structure definition rather than re-querying the job.
            'name' => new external_value(PARAM_TEXT, 'Override module name from course structure', VALUE_OPTIONAL),
            'intro' => new external_value(PARAM_RAW, 'Override module intro from course structure', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Create a module from a completed job.
     *
     * Fetches the job result and runs the DSL interpreter to create the module.
     * Optional name/intro allow the course structure flow to override AI-generated
     * values with those defined in the structure template.
     *
     * @param string $jobid The completed job UUID.
     * @param int $courseid The course ID.
     * @param int $sectionnumber The section number.
     * @param int|null $beforemod Course module ID to insert before (null or 0 = append).
     * @param string|null $name Override module name.
     * @param string|null $intro Override module intro HTML.
     * @return array Result with cmid on success.
     */
    public static function execute(
        string $jobid,
        int $courseid,
        int $sectionnumber = 0,
        ?int $beforemod = null,
        ?string $name = null,
        ?string $intro = null
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'jobid' => $jobid,
            'courseid' => $courseid,
            'sectionnumber' => $sectionnumber,
            'beforemod' => $beforemod ?? 0,
            'name' => $name,
            'intro' => $intro,
        ]);

        $coursecontext = self::validate_course_capability($params['courseid'], true);

        try {
            $jobservice = service_factory::get_job_service();
            $status = $jobservice->get_job_status($params['jobid'], $params['courseid']);

            if (!$status->is_completed()) {
                return response_factory::module_creation_result(
                    false,
                    0,
                    'Job is not completed. Status: ' . $status->status,
                    'job_not_completed'
                );
            }

            $result = $status->result;

            // Result may be JSON encoded string from get_job_status, decode if needed.
            if (is_string($result)) {
                $result = json_decode($result, true);
            }

            if (empty($result['creation'])) {
                return response_factory::module_creation_result(
                    false,
                    0,
                    'No creation instructions in job result',
                    'no_creation_instructions'
                );
            }

            $context = interpreter::build_context(
                $params['courseid'],
                $params['sectionnumber'],
                $result['moduleType'] ?? 'page',
                !empty($params['beforemod']) ? (int) $params['beforemod'] : null
            );

            // Allow callers to override AI-generated name/intro with structure-defined values.
            // This lets the course structure flow inject pre-determined titles without
            // needing a second AI call or a separate job.
            // Skip intro override for labels — intro IS the content, not a description.
            $data = $result['data'] ?? [];
            $moduletype = $result['moduleType'] ?? 'page';
            if ($params['name'] !== null && $params['name'] !== '') {
                $data['name'] = $params['name'];
            }
            if ($moduletype !== 'label' && $params['intro'] !== null) {
                $data['intro'] = $params['intro'];
            }
            // Fill jobs return content-only data; creation DSL still references $.name/$.intro.
            if (!array_key_exists('name', $data)) {
                $data['name'] = ($params['name'] !== null && $params['name'] !== '') ? $params['name'] : '';
            }
            if ($moduletype !== 'label' && !array_key_exists('intro', $data)) {
                $data['intro'] = $params['intro'] ?? '';
            }

            $interpreter = new interpreter();
            $cmid = $interpreter->execute($result['creation'], $data, $context);

            if ($cmid > 0) {
                $jobservice->enrich_job_metadata(
                    $params['jobid'],
                    job_binding_metadata::for_module($moduletype, (int) $cmid)
                );
            }

            // Add activity criteria to course completion requirements.
            $completionsync = new course_completion_sync_service();
            $completionsync->sync_activity_criteria_from_modules((int) $params['courseid']);

            // Enable file sync on successful AI module creation when the actor holds syncfiles.
            if (has_capability('local/dixeo:syncfiles', $coursecontext)) {
                service_factory::get_file_sync_service()
                    ->enable_and_queue_sync_after_module_creation((int) $params['courseid']);
            }

            return response_factory::module_creation_result(true, $cmid);
        } catch (api_exception $e) {
            return response_factory::module_creation_result(
                false,
                0,
                $e->getMessage(),
                $e->get_error_code()
            );
        } catch (\moodle_exception $e) {
            if ($e->errorcode === 'error:job_not_found') {
                return response_factory::module_creation_result(
                    false,
                    0,
                    get_string('error:job_not_found', 'local_dixeo'),
                    'job_not_found'
                );
            }
            // For moodle_exception (including dsl_exception), getMessage() returns the
            // language string key, not the detailed error. Extract debuginfo for details.
            $message = $e->getMessage();
            if (!empty($e->debuginfo)) {
                $message = $e->debuginfo;
            }

            return response_factory::module_creation_result(
                false,
                0,
                $message,
                'dsl_execution_error'
            );
        } catch (\Exception $e) {
            return response_factory::module_creation_result(
                false,
                0,
                $e->getMessage(),
                'dsl_execution_error'
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
            'success' => new external_value(PARAM_BOOL, 'Whether the module was created successfully'),
            'cmid' => new external_value(PARAM_INT, 'The created course module ID (0 if failed)'),
            'errormessage' => new external_value(PARAM_RAW, 'Error message if failed', VALUE_OPTIONAL),
            'errorcode' => new external_value(PARAM_ALPHANUMEXT, 'Error code if failed', VALUE_OPTIONAL),
        ]);
    }
}
