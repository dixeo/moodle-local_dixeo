<?php
// This file is part of Moodle - http://moodle.org/
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

use local_dixeo\external\service_factory;
use local_dixeo\service\course_image_writer;
use local_dixeo\service\image_poll_manager;

/**
 * Polls a remote Dixeo image job for up to ~60s, applies the image when complete, or re-queues itself.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class poll_image_generation_job extends \core\task\adhoc_task {
    /** @var int Wall-clock seconds to poll inside one task run before chaining. */
    private const POLL_WINDOW_SECONDS = 60;

    /** @var int Seconds between remote status checks. */
    private const POLL_INTERVAL_SECONDS = 4;

    /**
     * Return the component name for this task.
     *
     * @return string
     */
    public function get_component(): string {
        return 'local_dixeo';
    }

    /**
     * Poll a remote image job and apply the result when complete.
     *
     * @return void
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

            if ($jobstatus->is_failed()) {
                debugging(
                    'poll_image_generation_job: job failed courseid=' . $courseid . ' job=' . $imagejobid,
                    DEBUG_DEVELOPER
                );
                return;
            }

            sleep(self::POLL_INTERVAL_SECONDS);
        }

        if ($chainseq + 1 >= image_poll_manager::MAX_CHAIN_SEGMENTS) {
            debugging('poll_image_generation_job: chain limit reached courseid=' . $courseid, DEBUG_DEVELOPER);
            return;
        }

        image_poll_manager::queue_poll_task($courseid, $imagejobid, $userid, $chainseq + 1, $scope, $objectid);
    }

    /**
     * Return the human-readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_poll_image_generation', 'local_dixeo');
    }
}
