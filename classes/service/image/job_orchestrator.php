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
