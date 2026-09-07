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

/**
 * Scheduled retention for unified image job records.
 *
 * Marks timed-out pending/processing rows as failed and deletes terminal
 * (applied/failed) rows older than the retention period.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_image_jobs extends \core\task\scheduled_task {
    /** @var int Seconds terminal rows are kept for status reads before deletion (7 days). */
    public const RETENTION_SECONDS = 7 * DAYSECS;

    /**
     * Get name.
     */
    public function get_name(): string {
        return get_string('task_cleanup_image_jobs', 'local_dixeo');
    }

    /**
     * Execute.
     */
    public function execute(): void {
        global $DB;

        $now = time();

        // Persist the failed state for jobs stuck in pending/processing beyond the timeout.
        $DB->execute(
            'UPDATE {' . job_repository::TABLE . '}
                SET status = :failed, errormessage = :message, timemodified = :now
              WHERE status IN (:pending, :processing) AND timecreated < :cutoff',
            [
                'failed' => job_repository::STATUS_FAILED,
                'message' => get_string('dixeo_image_job_failed', 'local_dixeo'),
                'now' => $now,
                'pending' => job_repository::STATUS_PENDING,
                'processing' => job_repository::STATUS_PROCESSING,
                'cutoff' => $now - job_repository::TIMEOUT_SECONDS,
            ]
        );

        // Delete terminal rows past the retention period.
        $DB->delete_records_select(
            job_repository::TABLE,
            'status IN (:applied, :failed) AND timemodified < :cutoff',
            [
                'applied' => job_repository::STATUS_APPLIED,
                'failed' => job_repository::STATUS_FAILED,
                'cutoff' => $now - self::RETENTION_SECONDS,
            ]
        );
    }
}
