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
 * Repository for managing course AI sync records.
 *
 * Handles all database operations for the local_dixeo_course_ai table,
 * including CRUD operations, status updates, and retry backoff logic.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\repository;

/**
 * Repository class for course AI sync records.
 */
class course_ai_repository {
    /** @var string Database table name. */
    private const TABLE = 'local_dixeo_course_ai';

    /** @var int Retry delay in seconds after first error (5 minutes). */
    private const RETRY_DELAY_FIRST = 300;

    /** @var int Retry delay in seconds after second error (15 minutes). */
    private const RETRY_DELAY_SECOND = 900;

    /** @var int Retry delay in seconds after third error (1 hour). */
    private const RETRY_DELAY_THIRD = 3600;

    /** @var int Maximum number of retries before stopping auto-retry. */
    private const MAX_RETRY_COUNT = 3;

    /**
     * Get the course AI record by course ID.
     *
     * @param int $courseid The course ID.
     * @return \stdClass|null The record or null if not found.
     */
    public function get_by_courseid(int $courseid): ?\stdClass {
        global $DB;

        return $DB->get_record(self::TABLE, ['courseid' => $courseid]) ?: null;
    }

    /**
     * Create a new course AI record.
     *
     * @param int $courseid The course ID.
     * @param int $userid The user ID who initiated the creation.
     * @return \stdClass The created record.
     */
    public function create(int $courseid, int $userid): \stdClass {
        global $DB;

        $now = time();
        $record = new \stdClass();
        $record->courseid = $courseid;
        $record->enabled = 0;
        $record->syncstatus = 'none';
        $record->errorcount = 0;
        $record->timecreated = $now;
        $record->timemodified = $now;

        $record->id = $DB->insert_record(self::TABLE, $record);

        return $record;
    }

    /**
     * Get or create a course AI record.
     *
     * @param int $courseid The course ID.
     * @param int $userid The user ID (for creation if needed).
     * @return \stdClass The record.
     */
    public function get_or_create(int $courseid, int $userid): \stdClass {
        $record = $this->get_by_courseid($courseid);

        if ($record === null) {
            $record = $this->create($courseid, $userid);
        }

        return $record;
    }

    /**
     * Update the sync status and optionally progress information.
     *
     * @param int $courseid The course ID.
     * @param string $status The new status (none, syncing, synchronized, error, paused).
     * @param array|null $progress Optional progress data with keys: filestotal, filescompleted, progresspercent.
     * @return void
     */
    public function update_sync_status(int $courseid, string $status, ?array $progress = null): void {
        global $DB;

        $record = $this->get_by_courseid($courseid);
        if ($record === null) {
            return;
        }

        $update = new \stdClass();
        $update->id = $record->id;
        $update->syncstatus = $status;
        $update->timemodified = time();

        if ($progress !== null) {
            if (isset($progress['filestotal'])) {
                $update->filestotal = $progress['filestotal'];
            }
            if (isset($progress['filescompleted'])) {
                $update->filescompleted = $progress['filescompleted'];
            }
            if (isset($progress['progresspercent'])) {
                $update->progresspercent = $progress['progresspercent'];
            }
        }

        // Track sync timing based on status transitions.
        if ($status === 'syncing' && $record->syncstatus !== 'syncing') {
            $update->lastsyncstarted = time();
        }

        if ($status === 'synchronized') {
            $update->lastsynccompleted = time();
            // Clear error state on successful sync.
            $update->errorcount = 0;
            $update->errormessage = null;
            $update->lasterrorat = null;
        }

        $DB->update_record(self::TABLE, $update);
    }

    /**
     * Set the enabled state for a course.
     *
     * @param int $courseid The course ID.
     * @param bool $enabled Whether to enable or disable.
     * @param int $userid The user ID making the change.
     * @return void
     */
    public function set_enabled(int $courseid, bool $enabled, int $userid): void {
        global $DB;

        $record = $this->get_or_create($courseid, $userid);

        $update = new \stdClass();
        $update->id = $record->id;
        $update->enabled = $enabled ? 1 : 0;
        $update->timemodified = time();

        if ($enabled) {
            $update->enabledby = $userid;
            $update->enabledat = time();
            // Reset error state when re-enabling.
            $update->errorcount = 0;
            $update->errormessage = null;
            $update->lasterrorat = null;
        } else {
            $update->disabledby = $userid;
            $update->disabledat = time();
            // Set status to paused when disabled but keeping files.
            if ($record->syncstatus !== 'none') {
                $update->syncstatus = 'paused';
            }
        }

        $DB->update_record(self::TABLE, $update);
    }

    /**
     * Record an error for a course.
     *
     * Increments the error count and stores the error message.
     *
     * @param int $courseid The course ID.
     * @param string $message The error message.
     * @return void
     */
    public function record_error(int $courseid, string $message): void {
        global $DB;

        $record = $this->get_by_courseid($courseid);
        if ($record === null) {
            return;
        }

        $update = new \stdClass();
        $update->id = $record->id;
        $update->syncstatus = 'error';
        $update->errormessage = $message;
        $update->errorcount = $record->errorcount + 1;
        $update->lasterrorat = time();
        $update->timemodified = time();

        $DB->update_record(self::TABLE, $update);
    }

    /**
     * Clear the error state for a course.
     *
     * @param int $courseid The course ID.
     * @return void
     */
    public function clear_error(int $courseid): void {
        global $DB;

        $record = $this->get_by_courseid($courseid);
        if ($record === null) {
            return;
        }

        $update = new \stdClass();
        $update->id = $record->id;
        $update->errorcount = 0;
        $update->errormessage = null;
        $update->lasterrorat = null;
        $update->timemodified = time();

        // Revert to a sensible status.
        if ($record->syncstatus === 'error') {
            $update->syncstatus = $record->lastsynccompleted ? 'synchronized' : 'none';
        }

        $DB->update_record(self::TABLE, $update);
    }

    /**
     * Get the retry delay in seconds based on error count.
     *
     * Uses exponential backoff: 5min -> 15min -> 1hour -> stop.
     *
     * @param int $courseid The course ID.
     * @return int The delay in seconds, or 0 if no retry should occur.
     */
    public function get_retry_delay(int $courseid): int {
        $record = $this->get_by_courseid($courseid);
        if ($record === null) {
            return 0;
        }

        return match ((int) $record->errorcount) {
            1 => self::RETRY_DELAY_FIRST,
            2 => self::RETRY_DELAY_SECOND,
            3 => self::RETRY_DELAY_THIRD,
            default => 0,
        };
    }

    /**
     * Check if the course should auto-retry after an error.
     *
     * Returns true if the error count is within limits and enough time has passed.
     *
     * @param int $courseid The course ID.
     * @return bool True if auto-retry should proceed.
     */
    public function should_auto_retry(int $courseid): bool {
        $record = $this->get_by_courseid($courseid);
        if ($record === null) {
            return false;
        }

        if ((int) $record->errorcount >= self::MAX_RETRY_COUNT) {
            return false;
        }

        if ($record->lasterrorat === null) {
            return true;
        }

        $delay = $this->get_retry_delay($courseid);
        if ($delay === 0) {
            return false;
        }

        return (time() - $record->lasterrorat) >= $delay;
    }

    /**
     * Update the file manifest hash for a course.
     *
     * @param int $courseid The course ID.
     * @param string $filehash The SHA-256 hash of the file manifest.
     * @return void
     */
    public function update_filehash(int $courseid, string $filehash): void {
        global $DB;

        $record = $this->get_by_courseid($courseid);
        if ($record === null) {
            return;
        }

        $update = new \stdClass();
        $update->id = $record->id;
        $update->filehash = $filehash;
        $update->timemodified = time();

        $DB->update_record(self::TABLE, $update);
    }

    /**
     * Reset the sync state for a course (used when deleting files).
     *
     * @param int $courseid The course ID.
     * @return void
     */
    public function reset_sync_state(int $courseid): void {
        global $DB;

        $record = $this->get_by_courseid($courseid);
        if ($record === null) {
            return;
        }

        $update = new \stdClass();
        $update->id = $record->id;
        $update->syncstatus = 'none';
        $update->filestotal = null;
        $update->filescompleted = null;
        $update->progresspercent = null;
        $update->errorcount = 0;
        $update->errormessage = null;
        $update->lasterrorat = null;
        $update->filehash = null;
        $update->lastsyncstarted = null;
        $update->lastsynccompleted = null;
        $update->timemodified = time();

        $DB->update_record(self::TABLE, $update);
    }

    /**
     * Mark a course as awaiting confirmed remote file deletion.
     *
     * Keeps enough identity for idempotent DELETE retries. Does not clear filehash
     * until {@see reset_sync_state()} runs after a successful remote delete.
     *
     * @param int $courseid The course ID.
     * @return void
     */
    public function mark_pending_deletion(int $courseid): void {
        global $DB;

        $record = $this->get_by_courseid($courseid);
        if ($record === null) {
            $record = $this->create($courseid, 0);
        }

        $update = new \stdClass();
        $update->id = $record->id;
        $update->enabled = 0;
        $update->syncstatus = 'pending_deletion';
        $update->timemodified = time();

        $DB->update_record(self::TABLE, $update);
    }

    /**
     * Record a failed remote deletion while keeping pending_deletion status.
     *
     * @param int $courseid The course ID.
     * @param string $message Last error message (may be truncated for storage).
     * @return void
     */
    public function record_pending_deletion_error(int $courseid, string $message): void {
        global $DB;

        $record = $this->get_by_courseid($courseid);
        if ($record === null) {
            return;
        }

        $update = new \stdClass();
        $update->id = $record->id;
        $update->syncstatus = 'pending_deletion';
        $update->enabled = 0;
        $update->errormessage = \core_text::substr($message, 0, 1000);
        $update->errorcount = (int) $record->errorcount + 1;
        $update->lasterrorat = time();
        $update->timemodified = time();

        $DB->update_record(self::TABLE, $update);
    }

    /**
     * Whether another remote-deletion retry should be queued.
     *
     * @param int $courseid The course ID.
     * @return bool
     */
    public function should_retry_pending_deletion(int $courseid): bool {
        $record = $this->get_by_courseid($courseid);
        if ($record === null || $record->syncstatus !== 'pending_deletion') {
            return false;
        }

        return $this->get_retry_delay($courseid) > 0;
    }

    /**
     * Find courses with pending remote file deletion.
     *
     * @return array<int, \stdClass>
     */
    public function get_pending_deletion_courses(): array {
        global $DB;

        return $DB->get_records(self::TABLE, ['syncstatus' => 'pending_deletion'], 'id ASC');
    }

    /**
     * Delete the course AI record.
     *
     * @param int $courseid The course ID.
     * @return void
     */
    public function delete(int $courseid): void {
        global $DB;

        $DB->delete_records(self::TABLE, ['courseid' => $courseid]);
    }

    /**
     * Get all courses with a specific sync status.
     *
     * @param string $status The status to filter by.
     * @return array Array of records.
     */
    public function get_by_status(string $status): array {
        global $DB;

        return $DB->get_records(self::TABLE, ['syncstatus' => $status]);
    }

    /**
     * Get all enabled courses.
     *
     * @return array Array of records.
     */
    public function get_enabled(): array {
        global $DB;

        return $DB->get_records(self::TABLE, ['enabled' => 1]);
    }
}
