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


use local_dixeo\repository\image\job_repository;
use local_dixeo\service\image\apply\content_handler as apply_content_handler;
use local_dixeo\service\image\apply\registry as apply_registry;
use local_dixeo\service\image\content_target;
use local_dixeo\service\image\image_target;
use local_dixeo\service\image\poll\engine as poll_engine;
use local_dixeo\service\image\poll\manager as poll_manager;
use local_dixeo\service\image\target_factory;

/**
 * Unified adhoc task: poll remote image job and apply result.
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

        if ($target->get_courseid() < 1 || $jobid === '' || $userid < 1) {
            return;
        }

        $job = job_repository::get_by_target($target);
        if ($job) {
            if ($job->status === job_repository::STATUS_APPLIED || $job->status === job_repository::STATUS_FAILED) {
                return;
            }
            job_repository::update_status((int) $job->id, job_repository::STATUS_PROCESSING);
        }

        $outcome = poll_engine::poll_once($jobid);

        if ($outcome['completed']) {
            try {
                if ($job) {
                    $freshjob = $DB->get_record(job_repository::TABLE, ['id' => $job->id], '*', MUST_EXIST);
                    if ($freshjob->status === job_repository::STATUS_APPLIED) {
                        return;
                    }
                }
                apply_registry::apply($target, $outcome['result'], $userid, $source, $job);
                if ($job) {
                    job_repository::update_status((int) $job->id, job_repository::STATUS_APPLIED);
                }
            } catch (\Throwable $e) {
                $this->mark_failed($target, $job, $userid, $e->getMessage());
            }
            return;
        }

        if ($outcome['failed']) {
            // Outcome message is already a generic lang string from the poll engine.
            $this->mark_failed($target, $job, $userid, (string) ($outcome['errormessage'] ?? ''));
            return;
        }

        if ($chainseq + 1 >= poll_engine::MAX_POLL_ATTEMPTS) {
            $this->mark_failed(
                $target,
                $job,
                $userid,
                get_string('dixeo_image_job_failed', 'local_dixeo')
            );
            return;
        }

        // Still pending: requeue a delayed poll instead of blocking the cron worker.
        poll_manager::queue_poll_task($target, $jobid, $userid, $chainseq + 1, $source, poll_engine::POLL_INTERVAL_SECONDS);
    }

    /**
     * Mark failed.
     * @param image_target $target
     * @param \stdClass|null $job
     * @param int $userid
     * @param string $message
     * @return void
     */
    private function mark_failed(image_target $target, ?\stdClass $job, int $userid, string $message): void {
        if ($message !== '' && $message !== get_string('dixeo_image_job_failed', 'local_dixeo')) {
            debugging('Dixeo image job failed: ' . $message, DEBUG_DEVELOPER);
        }
        $safe = get_string('dixeo_image_job_failed', 'local_dixeo');
        if ($job) {
            job_repository::mark_failed((int) $job->id, $safe);
        }
        if ($target instanceof content_target) {
            apply_content_handler::apply_failure($target, $userid, $job);
        }
    }

    /**
     * Get name.
     */
    public function get_name(): string {
        return get_string('task_poll_image', 'local_dixeo');
    }
}
