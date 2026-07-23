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
 * Service for managing AI job operations.
 *
 * Handles job submission, status polling, and cancellation for
 * async AI operations via the Dixeo API. Newly created jobs are bound
 * locally to course/user for subsequent access checks.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service;

use local_dixeo\api\client;
use local_dixeo\api\job_poller;
use local_dixeo\api\polling_config;
use local_dixeo\api\exception\api_exception;
use local_dixeo\dto\operation_result;
use local_dixeo\dto\job_status;
use local_dixeo\event\job_cancelled;
use local_dixeo\repository\job_repository;

/**
 * Service for job management operations.
 */
class job_service {
    /** @var client The API client. */
    private client $client;

    /** @var job_poller The job poller. */
    private job_poller $poller;

    /** @var job_repository Local job ownership store. */
    private job_repository $jobrepository;

    /**
     * Constructor.
     *
     * @param client|null $client Optional API client.
     * @param job_poller|null $poller Optional job poller.
     * @param job_repository|null $jobrepository Optional job repository.
     */
    public function __construct(
        ?client $client = null,
        ?job_poller $poller = null,
        ?job_repository $jobrepository = null
    ) {
        $this->client = $client ?? new client();
        $this->poller = $poller ?? new job_poller($this->client);
        $this->jobrepository = $jobrepository ?? new job_repository();
    }

    /**
     * Submit a job to the API without waiting for completion.
     *
     * Returns immediately with jobid. Use get_job_status() to poll.
     * Registers a local course/user binding for the returned job.
     *
     * @param string $endpoint The API endpoint to submit to.
     * @param array $payload The request payload.
     * @return operation_result Pending operation result with jobid.
     * @throws api_exception If the API request fails.
     */
    public function submit_job(string $endpoint, array $payload): operation_result {
        $response = $this->client->post($endpoint, $payload);
        $jobid = (string) ($response['id'] ?? '');
        $this->register_job($jobid, $endpoint, $payload);

        return operation_result::pending($jobid, 'pending', 0);
    }

    /**
     * Submit a job and poll for completion.
     *
     * Blocks until the job completes, fails, or times out.
     * Registers a local course/user binding for the returned job.
     *
     * @param string $endpoint The API endpoint to submit to.
     * @param array $payload The request payload.
     * @param string $jobtype The job type for polling configuration.
     * @return operation_result The completed operation result.
     * @throws api_exception If an API error occurs.
     */
    public function submit_and_wait(string $endpoint, array $payload, string $jobtype): operation_result {
        $response = $this->client->post($endpoint, $payload);
        $jobid = (string) ($response['id'] ?? '');
        $this->register_job($jobid, $endpoint, $payload);
        $config = polling_config::for_job_type($jobtype);

        return $this->poller->poll($jobid, $config);
    }

    /**
     * Get the status of a job.
     *
     * When both $courseid and $userid are provided, the job must match that
     * course and initiator (editor-style non-shared jobs). When only $courseid
     * is provided, any caller in that course may access the job (shared course
     * work such as modulegen). Personal surfaces may also enforce ownership
     * in their own layer.
     *
     * @param string $jobid The job UUID.
     * @param int|null $courseid Course ID to enforce ownership for (required for AJAX paths).
     * @param int|null $userid Initiating user ID for initiator-scoped jobs (optional).
     * @return job_status The current job status.
     * @throws api_exception If an API error occurs.
     * @throws \moodle_exception If the job is not bound to the given course and/or user.
     */
    public function get_job_status(string $jobid, ?int $courseid = null, ?int $userid = null): job_status {
        $this->require_job_access($jobid, $courseid, $userid);

        return $this->poller->get_job_status($jobid);
    }

    /**
     * Cancel a running job.
     *
     * Access rules match {@see get_job_status()}.
     *
     * @param string $jobid The job UUID to cancel.
     * @param int|null $courseid Course ID to enforce ownership for (required for AJAX paths).
     * @param int|null $userid Initiating user ID for initiator-scoped jobs (optional).
     * @return array The cancellation response from the API.
     * @throws api_exception If an API error occurs.
     * @throws \moodle_exception If the job is not bound to the given course and/or user.
     */
    public function cancel_job(string $jobid, ?int $courseid = null, ?int $userid = null): array {
        global $USER;

        $this->require_job_access($jobid, $courseid, $userid);

        $result = $this->client->post('/v1/jobs/' . $jobid . '/cancel', []);

        $boundcourseid = $courseid;
        if ($boundcourseid === null) {
            $record = $this->jobrepository->get_by_jobid($jobid);
            $boundcourseid = $record !== null ? (int) $record->courseid : 0;
        }
        job_cancelled::create_for_job(
            $jobid,
            (int) $boundcourseid,
            (int) ($USER->id ?? 0)
        )->trigger();

        return $result;
    }

    /**
     * Wait for a pending job to complete.
     *
     * Use this to resume polling for a job that timed out earlier.
     *
     * @param string $jobid The job UUID.
     * @param string $jobtype The job type for polling configuration.
     * @param int|null $courseid Optional course ownership check.
     * @param int|null $userid Initiating user ID for initiator-scoped jobs (optional).
     * @return operation_result The operation result.
     * @throws api_exception If an API error occurs.
     * @throws \moodle_exception If the job is not bound to the given course and/or user.
     */
    public function wait_for_job(
        string $jobid,
        string $jobtype,
        ?int $courseid = null,
        ?int $userid = null
    ): operation_result {
        $this->require_job_access($jobid, $courseid, $userid);

        return $this->poller->poll_for_type($jobid, $jobtype);
    }

    /**
     * Apply course-only or course+user binding checks.
     *
     * @param string $jobid Remote job UUID.
     * @param int|null $courseid Course ID when binding is required.
     * @param int|null $userid Initiating user when initiator-scoped access is required.
     * @throws \moodle_exception When the binding is missing or mismatched.
     */
    private function require_job_access(string $jobid, ?int $courseid, ?int $userid): void {
        if ($courseid === null) {
            return;
        }

        if ($userid !== null && $userid > 0) {
            $this->require_job_for_user_and_course($jobid, $courseid, $userid);
            return;
        }

        $this->require_job_for_course($jobid, $courseid);
    }

    /**
     * Ensure the job is registered to the given course.
     *
     * Uses a single error string for missing and mismatched jobs to avoid leaking existence.
     *
     * @param string $jobid Remote job UUID.
     * @param int $courseid Expected course ID.
     * @throws \moodle_exception When the binding is missing or mismatched.
     */
    public function require_job_for_course(string $jobid, int $courseid): void {
        if (!$this->jobrepository->belongs_to_course($jobid, $courseid)) {
            throw new \moodle_exception('error:job_not_found', 'local_dixeo');
        }
    }

    /**
     * Ensure the job is registered to the given course and initiating user.
     *
     * Uses a single error string for missing and mismatched jobs to avoid leaking existence.
     *
     * @param string $jobid Remote job UUID.
     * @param int $courseid Expected course ID.
     * @param int $userid Expected initiating user ID.
     * @throws \moodle_exception When the binding is missing or mismatched.
     */
    public function require_job_for_user_and_course(string $jobid, int $courseid, int $userid): void {
        if (!$this->jobrepository->belongs_to_user_and_course($jobid, $courseid, $userid)) {
            throw new \moodle_exception('error:job_not_found', 'local_dixeo');
        }
    }

    /**
     * Get the underlying API client.
     *
     * @return client The API client.
     */
    public function get_client(): client {
        return $this->client;
    }

    /**
     * Get the job poller.
     *
     * @return job_poller The job poller.
     */
    public function get_poller(): job_poller {
        return $this->poller;
    }

    /**
     * Get the job repository.
     *
     * @return job_repository
     */
    public function get_job_repository(): job_repository {
        return $this->jobrepository;
    }

    /**
     * Register a local binding for a remote job using the request payload.
     *
     * @param string $jobid Remote job UUID.
     * @param string $endpoint API endpoint used to create the job.
     * @param array $payload Request payload.
     */
    private function register_job(string $jobid, string $endpoint, array $payload): void {
        global $USER, $CFG;

        if (trim($jobid) === '') {
            return;
        }

        require_once($CFG->dirroot . '/local/dixeo/lib.php');

        $courseid = (int) ($payload['courseId'] ?? $payload['courseid'] ?? 0);
        $userid = (int) ($payload['userId'] ?? $payload['userid'] ?? 0);
        if ($userid < 1 && !empty($USER->id)) {
            $userid = (int) $USER->id;
        }

        $namespace = (string) ($payload['namespace'] ?? \local_dixeo_get_configured_namespace());
        $operation = $this->operation_from_endpoint($endpoint);

        $this->jobrepository->register($jobid, $courseid, $userid, $namespace, $operation);
    }

    /**
     * Map an API endpoint to a short operation label.
     *
     * @param string $endpoint API path.
     * @return string
     */
    private function operation_from_endpoint(string $endpoint): string {
        $map = [
            '/v1/modules/generate' => 'module_generate',
            '/v1/modules/fill' => 'module_fill',
            '/v1/modules/edit' => 'module_edit',
            '/v1/tutor/messages' => 'tutor_message',
            '/v1/images/generate' => 'image_generate',
            '/v1/images/edit' => 'image_edit',
            '/v1/courses/structure' => 'course_structure',
        ];

        $normalized = rtrim($endpoint, '/');
        return $map[$normalized] ?? 'unknown';
    }
}
