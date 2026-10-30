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

namespace local_dixeo\service\image\poll;


use core\task\manager as core_task_manager;
use local_dixeo\repository\image\job_repository;
use local_dixeo\service\image\failure_message;
use local_dixeo\service\image\generation_request;
use local_dixeo\service\image\image_target;
use local_dixeo\service\image\job_orchestrator;
use local_dixeo\service\image\target_factory;
use local_dixeo\task\poll_image_job;

/**
 * Queue and delete unified image poll adhoc tasks.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class manager {
    /**
     * Task classname.
     * @return string
     */
    public static function task_classname(): string {
        return poll_image_job::class;
    }

    /**
     * Delete queued.
     * @param image_target $target
     * @return int
     */
    public static function delete_queued(image_target $target): int {
        global $DB;

        $hash = $target->get_location_hash();
        $tasks = core_task_manager::get_adhoc_tasks(self::task_classname(), false, true);
        $deleted = 0;
        foreach ($tasks as $task) {
            $data = $task->get_custom_data();
            if (!is_object($data)) {
                continue;
            }
            if ((string) ($data->locationhash ?? '') !== $hash) {
                continue;
            }
            $id = $task->get_id();
            if (!$id) {
                continue;
            }
            $record = $DB->get_record('task_adhoc', ['id' => $id], 'id, timestarted', IGNORE_MISSING);
            if (!$record || (int) $record->timestarted > 0) {
                continue;
            }
            $DB->delete_records('task_adhoc', ['id' => $id]);
            $deleted++;
        }
        return $deleted;
    }

    /**
     * Whether a poll adhoc exists for this target.
     *
     * @param image_target $target
     * @param bool $includerunning Also count a task that is running now.
     * @return bool
     */
    public static function has_poll_task(image_target $target, bool $includerunning = false): bool {
        $hash = $target->get_location_hash();
        $tasks = core_task_manager::get_adhoc_tasks(self::task_classname(), false, !$includerunning);
        foreach ($tasks as $task) {
            $data = $task->get_custom_data();
            if (!is_object($data)) {
                continue;
            }
            if ((string) ($data->locationhash ?? '') === $hash) {
                return true;
            }
        }
        return false;
    }

    /**
     * Requeue a poll when a pending/processing job has no adhoc task left.
     *
     * Adhoc runners can die after marking the row processing (OOM, timeout, kill)
     * without requeueing, which leaves the UI stuck on generating forever.
     * A queued job cannot be requeued (its request lived in the task), so it fails.
     *
     * @param \stdClass $job Image job row.
     * @return bool True when a new poll task was queued.
     */
    public static function ensure_poll_for_job(\stdClass $job): bool {
        if (
            !in_array(
                (string) ($job->status ?? ''),
                [
                    job_repository::STATUS_PENDING,
                    job_repository::STATUS_PROCESSING,
                ],
                true
            )
        ) {
            return false;
        }

        $remotejobid = trim((string) ($job->jobid ?? ''));
        $userid = (int) ($job->userid ?? 0);
        $target = target_factory::from_job_record($job);
        if ($remotejobid === '') {
            if (!self::has_poll_task($target, true)) {
                job_orchestrator::fail_job($job, failure_message::CODE_FAILED);
            }
            return false;
        }
        if ($userid < 1 || self::has_poll_task($target)) {
            return false;
        }

        self::queue_poll_task($target, $remotejobid, $userid, 0, 'generated', 0);
        return true;
    }

    /**
     * Queue poll task.
     * @param image_target $target
     * @param string $remotejobid
     * @param int $userid
     * @param int $chainseq
     * @param string $source
     * @param int $delayseconds Run the task no earlier than now + delay (0 = as soon as possible).
     * @return void
     */
    public static function queue_poll_task(
        image_target $target,
        string $remotejobid,
        int $userid,
        int $chainseq = 0,
        string $source = 'generated',
        int $delayseconds = 0
    ): void {
        self::queue_task($target, $userid, [
            'jobid' => $remotejobid,
            'imagejobid' => $remotejobid,
            'userid' => $userid,
            'chainseq' => $chainseq,
            'source' => $source,
        ], $delayseconds);
    }

    /**
     * Queue the task that submits a generation request, then polls it.
     *
     * @param image_target $target
     * @param generation_request $request
     * @param int $userid
     * @param string $source
     * @param int $queuedat When the generation was first queued; bounds the retries.
     * @param int $delayseconds Run the task no earlier than now + delay (0 = as soon as possible).
     * @return void
     */
    public static function queue_submit_task(
        image_target $target,
        generation_request $request,
        int $userid,
        string $source,
        int $queuedat,
        int $delayseconds = 0
    ): void {
        self::queue_task($target, $userid, [
            'jobid' => '',
            'imagejobid' => '',
            'userid' => $userid,
            'chainseq' => 0,
            'source' => $source,
            'request' => $request->to_array(),
            'queuedat' => $queuedat,
        ], $delayseconds);
    }

    /**
     * Move queued tasks to a new target, keeping their state and schedule.
     *
     * @param image_target $from Target the tasks were queued for.
     * @param image_target $to Target they must now apply to.
     * @return int Number of tasks moved.
     */
    public static function retarget_queued(image_target $from, image_target $to): int {
        global $DB;

        $hash = $from->get_location_hash();
        $moved = 0;
        foreach (core_task_manager::get_adhoc_tasks(self::task_classname(), false, true) as $task) {
            $data = $task->get_custom_data();
            if (!is_object($data) || (string) ($data->locationhash ?? '') !== $hash || !$task->get_id()) {
                continue;
            }

            $copy = new poll_image_job();
            $copy->set_custom_data((object) array_merge((array) $data, $to->to_poll_custom_data()));
            $copy->set_userid((int) $task->get_userid());
            $copy->set_next_run_time((int) $task->get_next_run_time());
            $DB->delete_records('task_adhoc', ['id' => $task->get_id()]);
            core_task_manager::queue_adhoc_task($copy);
            $moved++;
        }
        return $moved;
    }

    /**
     * Replace any queued task for the target with a new one.
     *
     * @param image_target $target
     * @param int $userid
     * @param array $data Custom data on top of the target fields.
     * @param int $delayseconds
     * @return void
     */
    private static function queue_task(image_target $target, int $userid, array $data, int $delayseconds): void {
        self::delete_queued($target);

        $task = new poll_image_job();
        $task->set_custom_data((object) array_merge($target->to_poll_custom_data(), $data));
        $task->set_userid($userid);
        if ($delayseconds > 0) {
            $task->set_next_run_time(time() + $delayseconds);
        }
        core_task_manager::queue_adhoc_task($task);
    }
}
