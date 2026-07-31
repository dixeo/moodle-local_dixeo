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
use local_dixeo\service\image\image_target;
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
        self::delete_queued($target);

        $task = new poll_image_job();
        $task->set_custom_data((object) array_merge($target->to_poll_custom_data(), [
            'jobid' => $remotejobid,
            'imagejobid' => $remotejobid,
            'userid' => $userid,
            'chainseq' => $chainseq,
            'source' => $source,
        ]));
        $task->set_userid($userid);
        if ($delayseconds > 0) {
            $task->set_next_run_time(time() + $delayseconds);
        }
        core_task_manager::queue_adhoc_task($task);
    }
}
