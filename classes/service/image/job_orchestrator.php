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

namespace local_dixeo\service\image;


use local_dixeo\repository\image\job_repository;
use local_dixeo\service\image\apply\content_handler as apply_content_handler;
use local_dixeo\service\image\poll\manager as poll_manager;

/**
 * Upsert DB job row and queue unified poll adhoc task.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class job_orchestrator {
    /**
     * Submit and queue.
     * @param image_target $target
     * @param string $remotejobid
     * @param int $userid
     * @param array $metadata Extra job row fields.
     * @param string $source Poll apply source label.
     * @return void
     */
    public static function submit_and_queue(
        image_target $target,
        string $remotejobid,
        int $userid,
        array $metadata = [],
        string $source = 'generated'
    ): void {
        job_repository::upsert($target, $remotejobid, $userid, $metadata);
        poll_manager::queue_poll_task($target, $remotejobid, $userid, 0, $source);
    }

    /**
     * Queue a generation: the poll task submits it, retrying while the API is busy.
     *
     * The row has no remote jobid until the API accepts the request, and shows as pending.
     *
     * @param image_target $target
     * @param int $userid
     * @param generation_request $request
     * @param array $metadata Extra job row fields.
     * @param string $source Poll apply source label.
     * @return void
     */
    public static function queue_generation(
        image_target $target,
        int $userid,
        generation_request $request,
        array $metadata = [],
        string $source = 'generated'
    ): void {
        job_repository::upsert($target, '', $userid, $metadata);
        poll_manager::queue_submit_task($target, $request, $userid, $source, time());
    }

    /**
     * Mark a job failed and show the failure in place of a content placeholder.
     *
     * @param \stdClass $job Image job row.
     * @param string $code Failure code, see {@see failure_message::for_storage()}.
     * @return void
     */
    public static function fail_job(\stdClass $job, string $code): void {
        job_repository::mark_failed((int) $job->id, failure_message::for_storage($code));

        $target = target_factory::from_job_record($job);
        if (!$target instanceof content_target) {
            return;
        }
        try {
            apply_content_handler::apply_failure($target, (int) $job->userid, $job);
        } catch (\Throwable $e) {
            debugging('Dixeo image apply_failure failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Best-effort remote cancel for a job that is no longer wanted locally.
     *
     * Never throws: the local job row is already terminal and the poll chain
     * stops on its own, so a failed cancel only wastes remote credits.
     *
     * @param string $remotejobid
     * @return void
     */
    public static function cancel_remote(string $remotejobid): void {
        $remotejobid = trim($remotejobid);
        if ($remotejobid === '') {
            return;
        }
        try {
            \local_dixeo\external\service_factory::get_job_service()->cancel_job($remotejobid);
        } catch (\Throwable $e) {
            debugging('Remote image job cancel failed for ' . $remotejobid . ': ' .
                $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
