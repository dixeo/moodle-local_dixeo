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
 * Adhoc task to retry remote Dixeo file deletion after a failed DELETE.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\task;

use core\task\adhoc_task;
use local_dixeo\external\service_factory;

/**
 * Retries DELETE /v1/files for courses in pending_deletion state.
 */
class process_remote_file_deletion extends adhoc_task {
    /**
     * Get the task name for display.
     *
     * @return string The task name.
     */
    public function get_name(): string {
        return get_string('task_process_remote_file_deletion', 'local_dixeo');
    }

    /**
     * Execute the remote deletion retry.
     *
     * @return void
     */
    public function execute(): void {
        $data = $this->get_custom_data();

        if (!isset($data->courseid)) {
            mtrace('process_remote_file_deletion: No course ID provided');
            return;
        }

        $courseid = (int) $data->courseid;
        mtrace("process_remote_file_deletion: Retrying remote delete for course {$courseid}");

        $service = service_factory::get_file_sync_service();
        $completed = $service->retry_pending_deletion($courseid);

        if ($completed) {
            mtrace("process_remote_file_deletion: Remote delete completed for course {$courseid}");
        } else {
            mtrace("process_remote_file_deletion: Remote delete still pending for course {$courseid}");
        }
    }
}
