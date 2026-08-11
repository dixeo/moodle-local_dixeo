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

/**
 * Legacy adhoc task classname for image polls queued before poll_image_job.
 *
 * Queued tasks still reference this classname in task_adhoc. Normalizes the
 * pre-unified custom_data payload (scope/imagejobid) then delegates execution.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class poll_image_generation_job extends poll_image_job {
    /**
     * Execute legacy poll task.
     */
    public function execute(): void {
        $data = $this->get_custom_data();
        if (!is_object($data)) {
            return;
        }

        $courseid = (int) ($data->courseid ?? 0);
        $imagejobid = trim((string) ($data->imagejobid ?? ''));
        $userid = (int) ($data->userid ?? 0);
        $chainseq = (int) ($data->chainseq ?? 0);
        $scope = isset($data->scope) ? (string) $data->scope : '';
        $objectid = (int) ($data->objectid ?? 0);

        if ($courseid < 1 || $imagejobid === '' || $userid < 1) {
            return;
        }

        if (
            $objectid < 1 || !in_array($scope, [
            image_poll_manager::SCOPE_COURSE_OVERVIEW,
            image_poll_manager::SCOPE_FORMAT_SECTION,
            ], true)
        ) {
            return;
        }

        $jobservice = service_factory::get_job_service();

        $deadline = time() + self::POLL_WINDOW_SECONDS;
        while (time() < $deadline) {
            $jobstatus = $jobservice->get_job_status(
                $imagejobid,
                $courseid,
                $userid
            );

            if ($jobstatus->is_completed()) {
                $result = $jobstatus->result;
                if (is_string($result)) {
                    $decoded = json_decode($result, true);
                    $result = is_array($decoded) ? $decoded : [];
                } else if (!is_array($result)) {
                    $result = $result !== null ? (array) $result : [];
                }

                try {
                    course_image_writer::apply_from_job_result($scope, $objectid, $result, $userid);
                } catch (\Throwable $e) {
                    debugging(
                        'poll_image_generation_job: apply failed courseid=' . $courseid . ' ' . $e->getMessage(),
                        DEBUG_DEVELOPER
                    );
                }
                return;
            }
            if (empty($data->jobid) && !empty($data->imagejobid)) {
                $data->jobid = (string) $data->imagejobid;
            }
            $this->set_custom_data($data);
        }
        parent::execute();
    }

    /**
     * Get name.
     */
    public function get_name(): string {
        return get_string('task_poll_image_generation', 'local_dixeo');
    }
}
