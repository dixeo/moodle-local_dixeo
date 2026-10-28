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
        if ($record === null || $record->syncstatus === 'pending_deletion') {
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
        }

        // Clear error state on a successful sync. Files waiting for a retry keep the backoff count.
        if ($status === 'synchronized' && !self::has_retry_files(self::parse_skipped_files($record->skippedfiles ?? null))) {
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

        // Remote deletion must finish before sync can be turned on again, and a
        // disable must not replace that state with paused.
        if ($enabled && $record->syncstatus === 'pending_deletion') {
            return;
        }

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
            if ($record->syncstatus !== 'none' && $record->syncstatus !== 'pending_deletion') {
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
     * Count a sync that left files to send again as a failed attempt.
     *
     * Feeds the same backoff as {@see record_error()} without changing the sync status,
     * so the course still shows as synchronized with files left out.
     *
     * @param int $courseid The course ID.
     * @return void
     */
    public function record_retry_pending(int $courseid): void {
        global $DB;

        $record = $this->get_by_courseid($courseid);
        if ($record === null) {
            return;
        }

        $update = new \stdClass();
        $update->id = $record->id;
        $update->errorcount = (int) $record->errorcount + 1;
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
     * Uses exponential backoff: 5min -> 15min -> 1hour -> stop, so a course
     * gets at most three automatic retries.
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
     * Files the last sync left out.
     *
     * @param int $courseid The course ID.
     * @return array[] Entries with filename, reason, and optional hash, code, and retry.
     */
    public function get_skipped_files(int $courseid): array {
        $record = $this->get_by_courseid($courseid);
        return $record === null ? [] : self::parse_skipped_files($record->skippedfiles ?? null);
    }

    /**
     * Whether some skipped files wait for a later sync.
     *
     * @param array[] $entries Skipped-file entries.
     * @return bool
     */
    public static function has_retry_files(array $entries): bool {
        foreach ($entries as $entry) {
            if (!empty($entry['retry'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Decode a stored skipped-file list.
     *
     * @param string|null $value Column value.
     * @return array[]
     */
    public static function parse_skipped_files(?string $value): array {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }

    /**
     * Replace the list of files the last sync left out.
     *
     * @param int $courseid The course ID.
     * @param array[] $files Entries as returned by {@see get_skipped_files()}.
     * @return void
     */
    public function set_skipped_files(int $courseid, array $files): void {
        global $DB;

        $record = $this->get_by_courseid($courseid);
        if ($record === null) {
            return;
        }

        $update = new \stdClass();
        $update->id = $record->id;
        $update->skippedfiles = $files === [] ? null : json_encode(array_values($files));
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
        $update->skippedfiles = null;
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
