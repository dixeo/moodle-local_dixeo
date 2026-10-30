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

namespace local_dixeo\task;


use local_dixeo\api\exception\api_exception;
use local_dixeo\api\exception\rate_limit_exception;
use local_dixeo\external\service_factory;
use local_dixeo\dto\job_binding_metadata;
use local_dixeo\repository\image\job_repository;
use local_dixeo\service\image\apply\registry as apply_registry;
use local_dixeo\service\image\failure_message;
use local_dixeo\service\image\generation_request;
use local_dixeo\service\image\image_target;
use local_dixeo\service\image\job_orchestrator;
use local_dixeo\service\image\poll\engine as poll_engine;
use local_dixeo\service\image\poll\manager as poll_manager;
use local_dixeo\service\image\target_factory;

/**
 * Unified adhoc task: submit a queued image job, poll it and apply the result.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class poll_image_job extends \core\task\adhoc_task {
    /**
     * Get component.
     */
    public function get_component(): string {
        return 'local_dixeo';
    }

    /**
     * Execute.
     */
    public function execute(): void {
        global $DB;

        $data = $this->get_custom_data();
        if (!is_object($data)) {
            return;
        }

        $target = target_factory::from_poll_data($data);
        $jobid = trim((string) ($data->jobid ?? $data->imagejobid ?? ''));
        $userid = (int) ($data->userid ?? 0);
        $chainseq = (int) ($data->chainseq ?? 0);
        $source = trim((string) ($data->source ?? 'generated'));
        if ($source === '') {
            $source = 'generated';
        }

        $request = isset($data->request) ? generation_request::from_array($data->request) : null;
        if ($target->get_courseid() < 1 || ($jobid === '' && $request === null) || $userid < 1) {
            return;
        }
        $coursecontext = \context_course::instance($target->get_courseid(), IGNORE_MISSING);
        if (!$coursecontext || !service_factory::get_settings_config()->enabled_for($coursecontext)) {
            return;
        }

        $job = job_repository::get_by_target($target);
        if (!$job) {
            if ($jobid === '') {
                return;
            }
            $byremote = $DB->get_record(job_repository::TABLE, ['jobid' => $jobid], '*', IGNORE_MISSING);

            // Save may have promoted the job to a new locationhash while this draft-hash
            // poll was already queued. Continue against the live row when possible.
            if (
                $byremote
                && in_array(
                    (string) $byremote->status,
                    [job_repository::STATUS_PENDING, job_repository::STATUS_PROCESSING],
                    true
                )
            ) {
                $job = $byremote;
                $target = target_factory::from_job_record($job);
            } else {
                return;
            }
        }
        if ($job->status === job_repository::STATUS_APPLIED || $job->status === job_repository::STATUS_FAILED) {
            return;
        }

        if ($jobid === '') {
            // A row that already has a remote job belongs to a newer submission.
            if (trim((string) $job->jobid) === '') {
                $this->submit($target, $job, $userid, $source, $request, (int) ($data->queuedat ?? time()));
            }
            return;
        }

        try {
            job_repository::update_status((int) $job->id, job_repository::STATUS_PROCESSING);

            $outcome = poll_engine::poll_once($jobid);
        } catch (\Throwable $e) {
            debugging('Dixeo image poll failed: ' . $e->getMessage(), DEBUG_DEVELOPER);

            if ($chainseq + 1 >= poll_engine::MAX_POLL_ATTEMPTS) {
                $this->mark_failed($job, $e->getMessage());
                return;
            }
            poll_manager::queue_poll_task(
                $target,
                $jobid,
                $userid,
                $chainseq + 1,
                $source,
                poll_engine::POLL_INTERVAL_SECONDS
            );
            return;
        }

        if ($outcome['completed']) {
            try {
                $freshjob = $DB->get_record(job_repository::TABLE, ['id' => $job->id], '*', MUST_EXIST);
                if ($freshjob->status === job_repository::STATUS_APPLIED) {
                    return;
                }
                apply_registry::apply($target, $outcome['result'], $userid, $source, $job);
                job_repository::update_status((int) $job->id, job_repository::STATUS_APPLIED);
                $this->maybe_backfill_job_metadata($job);
            } catch (\Throwable $e) {
                $this->mark_failed($job, $e->getMessage());
            }
            return;
        }

        if ($outcome['failed']) {
            $this->mark_failed(
                $job,
                (string) ($outcome['errormessage'] ?? ''),
                (string) ($outcome['errorcode'] ?? '')
            );
            return;
        }

        if ($chainseq + 1 >= poll_engine::MAX_POLL_ATTEMPTS) {
            $this->mark_failed($job, 'Image job still running after the last poll attempt');
            return;
        }

        poll_manager::queue_poll_task(
            $target,
            $jobid,
            $userid,
            $chainseq + 1,
            $source,
            poll_engine::POLL_INTERVAL_SECONDS
        );
    }

    /**
     * Submit a queued request, then hand over to polling.
     *
     * A busy or unreachable API reschedules the submission until the submit window
     * ends. Any other refusal fails the job at once.
     *
     * @param image_target $target
     * @param \stdClass $job Local image job row.
     * @param int $userid
     * @param string $source
     * @param generation_request $request
     * @param int $queuedat When the generation was first queued.
     * @return void
     */
    private function submit(
        image_target $target,
        \stdClass $job,
        int $userid,
        string $source,
        generation_request $request,
        int $queuedat
    ): void {
        try {
            $remotejobid = $request->submit();
            job_repository::set_remote_jobid((int) $job->id, $remotejobid);
        } catch (api_exception $e) {
            $code = self::retry_code($e);
            if ($code === null) {
                $this->mark_failed($job, $e->getMessage(), $e->get_error_code());
                return;
            }
            $retryafter = $e instanceof rate_limit_exception ? $e->get_retry_after() : null;
            $this->retry_submit($target, $job, $userid, $source, $request, $queuedat, $code, $retryafter);
            return;
        } catch (\Throwable $e) {
            $this->mark_failed($job, $e->getMessage());
            return;
        }

        poll_manager::queue_poll_task($target, $remotejobid, $userid, 0, $source, poll_engine::POLL_INTERVAL_SECONDS);
    }

    /**
     * Failure code of a refusal worth retrying, or null when retrying cannot help.
     *
     * @param api_exception $e
     * @return string|null
     */
    private static function retry_code(api_exception $e): ?string {
        if ($e instanceof rate_limit_exception || $e->get_http_status() === 429) {
            return failure_message::CODE_RATE_LIMITED;
        }
        if ($e->get_error_type() === 'connection_error' || $e->get_http_status() >= 500) {
            return failure_message::CODE_AI_UNAVAILABLE;
        }
        return null;
    }

    /**
     * Reschedule a refused submission, or fail it once the submit window is over.
     *
     * @param image_target $target
     * @param \stdClass $job
     * @param int $userid
     * @param string $source
     * @param generation_request $request
     * @param int $queuedat
     * @param string $code Failure code stored when giving up.
     * @param int|null $retryafter Seconds the API asked to wait, if any.
     * @return void
     */
    private function retry_submit(
        image_target $target,
        \stdClass $job,
        int $userid,
        string $source,
        generation_request $request,
        int $queuedat,
        string $code,
        ?int $retryafter = null
    ): void {
        $delay = poll_engine::submit_retry_delay($retryafter);
        if (time() + $delay > $queuedat + job_repository::SUBMIT_TIMEOUT_SECONDS) {
            $this->mark_failed($job, 'Image job not accepted before the submit window ended', $code);
            return;
        }
        poll_manager::queue_submit_task($target, $request, $userid, $source, $queuedat, $delay);
    }

    /**
     * Backfill remote job binding metadata from the local image job row when submit missed it.
     *
     * @param \stdClass|null $job Local image job row.
     * @return void
     */
    private function maybe_backfill_job_metadata(?\stdClass $job): void {
        if ($job === null) {
            return;
        }

        $remotejobid = trim((string) ($job->jobid ?? ''));
        $cmid = (int) ($job->cmid ?? 0);
        if ($remotejobid === '' || $cmid <= 0) {
            return;
        }

        $cm = get_coursemodule_from_id(null, $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        service_factory::get_job_service()->enrich_job_metadata(
            $remotejobid,
            job_binding_metadata::for_module($cm->modname, $cmid)
        );
    }

    /**
     * Mark failed.
     * @param \stdClass $job
     * @param string $message Diagnostic text. Not shown to the user.
     * @param string $errorcode API failure code, empty for a local failure.
     * @return void
     */
    private function mark_failed(\stdClass $job, string $message, string $errorcode = ''): void {
        debugging('Dixeo image job failed: ' . ($errorcode !== '' ? $errorcode : $message), DEBUG_DEVELOPER);
        job_orchestrator::fail_job($job, $errorcode);
    }

    /**
     * Get name.
     */
    public function get_name(): string {
        return get_string('task_poll_image', 'local_dixeo');
    }
}
