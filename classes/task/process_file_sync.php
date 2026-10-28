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
 * Adhoc task for processing file synchronization.
 *
 * Handles debounced sync execution, allowing multiple rapid changes
 * to be batched into a single sync operation.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\task;

use core\task\adhoc_task;
use local_dixeo\api\exception\api_exception;
use local_dixeo\external\service_factory;
use local_dixeo\service\file_sync_service;

/**
 * Adhoc task for processing file sync.
 */
class process_file_sync extends adhoc_task {
    /**
     * Get the task name for display.
     *
     * @return string The task name.
     */
    public function get_name(): string {
        return get_string('task_process_file_sync', 'local_dixeo');
    }

    /** @var int Debounce window in seconds - must match file_sync_service::DEBOUNCE_DELAY */
    private const DEBOUNCE_WINDOW = 30;

    /**
     * Execute the file sync task.
     *
     * @return void
     */
    public function execute(): void {
        $data = $this->get_custom_data();

        if (!isset($data->courseid)) {
            mtrace('process_file_sync: No course ID provided');
            return;
        }

        $courseid = (int) $data->courseid;
        if (!service_factory::get_settings_config()->enabled_for(\context_course::instance($courseid))) {
            mtrace("process_file_sync: Dixeo is off for course {$courseid}, skipping");
            return;
        }

        mtrace("process_file_sync: Starting sync for course {$courseid}");

        $service = service_factory::get_file_sync_service();

        // Check if sync is still enabled.
        if (!$service->is_enabled($courseid)) {
            mtrace("process_file_sync: Sync disabled for course {$courseid}, skipping");
            return;
        }

        // Check if a sync was completed recently (within debounce window).
        // This prevents duplicate syncs when user clicks "Sync Now" while a task is pending.
        $status = $service->get_status($courseid);
        if ($status->lastsynccompleted && (time() - $status->lastsynccompleted) < self::DEBOUNCE_WINDOW) {
            mtrace("process_file_sync: Recent sync found for course {$courseid}, skipping");
            return;
        }

        try {
            $service->trigger_sync($courseid);
            mtrace("process_file_sync: Successfully synced course {$courseid}");
        } catch (api_exception $e) {
            mtrace("process_file_sync: Error syncing course {$courseid}: " . $e->getMessage());

            $this->schedule_retry($courseid, $service);
        } catch (\Throwable $e) {
            mtrace("process_file_sync: Unexpected error for course {$courseid}: " . $e->getMessage());
        }
    }

    /**
     * Schedule a retry task with the course backoff, until the retries run out.
     *
     * @param int $courseid The course ID.
     * @param file_sync_service $service The sync service.
     * @return void
     */
    private function schedule_retry(int $courseid, file_sync_service $service): void {
        if (!$service->queue_sync_retry($courseid)) {
            mtrace("process_file_sync: No retry queued for course {$courseid}");
            return;
        }
        mtrace("process_file_sync: Scheduled retry for course {$courseid}");
    }
}
