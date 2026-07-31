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
 * Service for managing AI file synchronization with Dixeo.
 *
 * Orchestrates file collection from course modules, uploading to the API,
 * and tracking sync status. Supports debounced sync via adhoc tasks.
 * Local SCORM packages contribute a generated plain-text extract per activity (SCO-only).
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service;

use local_dixeo\api\client;
use local_dixeo\api\exception\api_exception;
use local_dixeo\dto\file_upload_part;
use local_dixeo\event\file_sync_disabled;
use local_dixeo\event\file_sync_enabled;
use local_dixeo\event\file_sync_triggered;
use local_dixeo\repository\course_ai_repository;

/**
 * Service for file synchronization with Dixeo.
 */
class file_sync_service {
    /** @var int Debounce delay in seconds for queued syncs. */
    private const DEBOUNCE_DELAY = 30;

    /** @var array Supported file extensions for sync. */
    private const SUPPORTED_EXTENSIONS = ['pdf', 'docx', 'txt', 'pptx'];

    /**
     * File extensions indexed for tutor and AI content generation (RAG).
     *
     * @return string[] Lowercase extensions without leading dot.
     */
    public static function get_rag_indexed_extensions(): array {
        return self::SUPPORTED_EXTENSIONS;
    }

    /**
     * Whether a filename uses a RAG-indexed extension.
     *
     * @param string $filename Original upload filename.
     * @return bool
     */
    public static function is_rag_indexed_filename(string $filename): bool {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($extension === '') {
            return false;
        }
        return in_array($extension, self::get_rag_indexed_extensions(), true);
    }

    /**
     * Human-readable RAG extension list for UI and error messages.
     *
     * @return string e.g. "PDF, DOCX, TXT, and PPTX"
     */
    public static function format_rag_indexed_extensions_label(): string {
        $labels = array_map('strtoupper', self::get_rag_indexed_extensions());
        if (count($labels) <= 1) {
            return implode('', $labels);
        }
        $last = array_pop($labels);
        return implode(', ', $labels) . ', and ' . $last;
    }

    /** @var array Module types that contain syncable files. */
    private const FILE_MODULE_TYPES = ['resource', 'folder'];

    /** @var int Max files per outbound HTTP chunk. */
    private const CHUNK_MAX_FILES = 10;

    /** @var int Max total bytes per outbound HTTP chunk (8 MB). */
    private const CHUNK_MAX_BYTES = 8388608;

    /** @var course_ai_repository Repository for course AI records. */
    private course_ai_repository $repository;

    /** @var client API client for Dixeo communication. */
    private client $client;

    /** @var scorm_vector_extract_service SCORM package text extraction for sync. */
    private scorm_vector_extract_service $scormextract;

    /**
     * Constructor.
     *
     * @param course_ai_repository|null $repository Optional repository instance.
     * @param client|null $client Optional API client instance.
     * @param scorm_vector_extract_service|null $scormextract Optional SCORM extract service.
     */
    public function __construct(
        ?course_ai_repository $repository = null,
        ?client $client = null,
        ?scorm_vector_extract_service $scormextract = null
    ) {
        $this->repository = $repository ?? new course_ai_repository();
        $this->client = $client ?? new client();
        $this->scormextract = $scormextract ?? new scorm_vector_extract_service();
    }

    /**
     * Enable file sync for a course.
     *
     * Creates the course AI record if it doesn't exist and marks it as enabled.
     *
     * @param int $courseid The course ID.
     * @param int $userid The user enabling sync.
     * @return void
     */
    public function enable_sync(int $courseid, int $userid): void {
        $wasenabled = $this->is_enabled($courseid);

        if ($this->repository->get_by_courseid($courseid) === null) {
            $this->repository->create($courseid, $userid);
        }

        $this->repository->set_enabled($courseid, true, $userid);

        if (!$wasenabled) {
            $record = $this->repository->get_by_courseid($courseid);
            if ($record !== null) {
                file_sync_enabled::create_for_course($courseid, $userid, (int) $record->id)->trigger();
            }
        }
    }

    /**
     * Implicit opt-in when a tutor or modulegen block is added to a course.
     *
     * Failures are logged only so block creation is never blocked.
     *
     * @param int $courseid The course ID.
     * @param int $userid The user adding the block.
     * @return void
     */
    public function opt_in_on_block_added(int $courseid, int $userid): void {
        if ($courseid <= SITEID || $userid <= 0) {
            return;
        }

        try {
            $this->enable_sync($courseid, $userid);
            $this->queue_sync($courseid);
        } catch (\Throwable $e) {
            debugging(
                'Failed to opt in file sync on block add: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }

    /**
     * Enable sync, run an immediate upload, and wait until the course is indexed.
     *
     * Used before RAG-backed API jobs (tutor messages, module generation).
     *
     * @param int $courseid The course ID.
     * @param int $userid The user initiating the operation.
     * @param int $timeoutseconds Maximum seconds to wait for synchronization.
     * @return void
     * @throws \moodle_exception When sync fails or times out.
     */
    public function ensure_enabled_and_synchronized(int $courseid, int $userid, int $timeoutseconds = 120): void {
        $this->enable_sync($courseid, $userid);
        $this->trigger_sync($courseid);

        $deadline = time() + $timeoutseconds;
        while (time() < $deadline) {
            $status = $this->poll_status($courseid);
            if ($status->status === 'synchronized' || $status->status === 'none') {
                return;
            }
            if ($status->status === 'error') {
                throw new \moodle_exception('filesync_failed', 'local_dixeo', '', $status->errormessage ?? '');
            }
            sleep(2);
        }

        throw new \moodle_exception('filesync_timeout', 'local_dixeo');
    }

    /**
     * Enable file sync for a course and queue a debounced sync after module creation.
     *
     * Used after AI-generated and manual-upload activities so new resources/SCORM
     * are picked up for the tutor and content generation. Failures are logged only.
     *
     * @param int $courseid The course ID.
     * @param int|null $userid User enabling sync; defaults to current user.
     * @return void
     */
    public function enable_and_queue_sync_after_module_creation(int $courseid, ?int $userid = null): void {
        global $USER;

        try {
            $userid = $userid ?? (int) ($USER->id ?? 0);
            if (!$this->user_can_sync_files_in_course($courseid, $userid)) {
                return;
            }

            $this->enable_sync($courseid, $userid);
            $this->queue_sync($courseid);
        } catch (\Throwable $e) {
            debugging(
                'Failed to enable file sync after module creation: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }

    /**
     * Enable file sync for a course and run sync immediately after module creation.
     *
     * Used after manual uploads so new resources/SCORM are indexed without debounce delay.
     * Failures are logged only.
     *
     * @param int $courseid The course ID.
     * @param int|null $userid User enabling sync; defaults to current user.
     * @return void
     */
    public function enable_and_trigger_sync_after_module_creation(int $courseid, ?int $userid = null): void {
        global $USER;

        try {
            $userid = $userid ?? (int) ($USER->id ?? 0);
            if (!$this->user_can_sync_files_in_course($courseid, $userid)) {
                return;
            }

            $this->enable_sync($courseid, $userid);
            $this->trigger_sync($courseid);
        } catch (\Throwable $e) {
            debugging(
                'Failed to trigger file sync after module creation: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }

    /**
     * Disable file sync for a course.
     *
     * Optionally removes all files from Dixeo.
     *
     * @param int $courseid The course ID.
     * @param int $userid The user disabling sync.
     * @param bool $removefiles Whether to delete files from Dixeo.
     * @return void
     */
    public function disable_sync(int $courseid, int $userid, bool $removefiles = false): void {
        $wasenabled = $this->is_enabled($courseid);
        $record = $this->repository->get_by_courseid($courseid);
        $objectid = $record !== null ? (int) $record->id : 0;

        if ($removefiles) {
            // Always reset local state when user wants to clear data.
            $this->repository->reset_sync_state($courseid);

            // Try to delete files from API, but don't fail if it doesn't work.
            try {
                $this->client->delete_files((string) $courseid);
            } catch (api_exception $e) {
                // Log but don't fail - user wants to disable regardless.
                // Local state is already reset, API files will be orphaned but harmless.
                debugging('Failed to delete files from Dixeo: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        $this->repository->set_enabled($courseid, false, $userid);

        if ($wasenabled && $objectid > 0) {
            file_sync_disabled::create_for_course($courseid, $userid, $objectid, $removefiles)->trigger();
        }
    }

    /**
     * Check if file sync is enabled for a course.
     *
     * Missing rows are treated as disabled until explicit opt-in.
     *
     * @param int $courseid The course ID.
     * @return bool True if sync is enabled.
     */
    public function is_enabled(int $courseid): bool {
        $record = $this->repository->get_by_courseid($courseid);

        if ($record === null) {
            return false;
        }

        return (bool) $record->enabled;
    }

    /**
     * Get the current sync status for a course.
     *
     * Returns a status object with all relevant fields for UI display.
     *
     * @param int $courseid The course ID.
     * @return \stdClass Status object with: enabled, status, filestotal, filescompleted,
     *                   progresspercent, errormessage, lastsyncstarted, lastsynccompleted.
     */
    public function get_status(int $courseid): \stdClass {
        $record = $this->repository->get_by_courseid($courseid);

        $status = new \stdClass();

        if ($record === null) {
            $status->enabled = false;
            $status->status = 'none';
            $status->filestotal = null;
            $status->filescompleted = null;
            $status->progresspercent = null;
            $status->errormessage = null;
            $status->lastsyncstarted = null;
            $status->lastsynccompleted = null;
            return $status;
        }

        $status->enabled = (bool) $record->enabled;
        $status->status = $record->syncstatus;
        $status->filestotal = $record->filestotal;
        $status->filescompleted = $record->filescompleted;
        $status->progresspercent = $record->progresspercent;
        $status->errormessage = $record->errormessage;
        $status->lastsyncstarted = $record->lastsyncstarted;
        $status->lastsynccompleted = $record->lastsynccompleted;

        return $status;
    }

    /**
     * Trigger an immediate file sync for a course.
     *
     * Collects all syncable files from the course and uploads them to the API
     * in chunks of at most {@see self::CHUNK_MAX_FILES} files or
     * {@see self::CHUNK_MAX_BYTES} bytes (first limit reached closes the chunk).
     *
     * All chunks but the last are sent as append-only (finalChunk=false).
     * The last chunk carries the full manifest of expected file hashes and
     * filenames so the remote store knows which older files to drop.
     *
     * @param int $courseid The course ID.
     * @return void
     * @throws api_exception If the API request fails.
     */
    public function trigger_sync(int $courseid): void {
        global $USER;

        $userid = $USER->id ?? 0;

        // Ensure record exists.
        $record = $this->repository->get_or_create($courseid, $userid);

        if (!$record->enabled) {
            return;
        }

        // Hash from stored packages only (no SCORM temp extraction until we know sync is needed).
        $filehash = $this->compute_sync_manifest_hash($courseid);

        if (
            $filehash === ($record->filehash ?? null)
            && ($record->syncstatus ?? null) === 'synchronized'
        ) {
            return;
        }

        $uploaditems = $this->collect_upload_payload($courseid);

        // Release the session lock for this request so other requests (e.g. file-sync status polls)
        // can run while the outbound upload is in flight.
        \core\session\manager::write_close();

        // Flip the local sync status; per-chunk progress counters are not
        // tracked locally — poll_status() refreshes them from the server.
        $this->repository->update_sync_status($courseid, 'syncing');
        file_sync_triggered::create_for_course($courseid, (int) $userid, (int) $record->id)->trigger();

        try {
            $filecount = count($uploaditems);

            if ($filecount === 0) {
                // No files remain in Moodle. Clear the remote store as well;
                // POST /v1/files intentionally rejects empty multipart payloads.
                $this->client->delete_files((string) $courseid);
                $this->repository->update_sync_status($courseid, 'synchronized', [
                    'filestotal' => 0,
                    'filescompleted' => 0,
                    'progresspercent' => 100,
                ]);
                $this->repository->update_filehash($courseid, $filehash);
                return;
            }

            // Pre-compute the full manifest. It is only needed on the final
            // chunk (so the API knows what to keep) but we build it up front
            // so any hashing error fails the whole sync before we start
            // sending partial data over the wire.
            $expectedfiles = $this->compute_expected_files($uploaditems);

            $chunks = $this->build_chunks($uploaditems);
            $totalchunks = count($chunks);

            $lastresult = null;
            $failedfiles = [];
            foreach ($chunks as $idx => $chunk) {
                $isfinal = ($idx === $totalchunks - 1);
                $chunkmanifest = $isfinal ? $expectedfiles : null;

                $lastresult = $this->client->upload_files(
                    (string) $courseid,
                    $chunk,
                    null,
                    null,
                    $isfinal,
                    $chunkmanifest,
                    $filecount
                );
                $this->log_api_rejections($lastresult);
                $failedfiles = array_merge($failedfiles, $this->extract_api_rejections($lastresult));
            }

            if ($failedfiles !== []) {
                $message = count($failedfiles) . ' file(s) were rejected by the Dixeo API';
                throw new api_exception('file_sync_rejections', $message, 422, ['failedFiles' => $failedfiles]);
            }

            $this->poll_status($courseid);

            $this->repository->clear_error($courseid);
            $this->repository->update_filehash($courseid, $filehash);
        } catch (api_exception $e) {
            // Persist a short UI-safe message only — never raw API response bodies.
            $errormessage = $e->get_error_code() . ': ' . $e->getMessage();
            if (\core_text::strlen($errormessage) > 500) {
                $errormessage = \core_text::substr($errormessage, 0, 497) . '...';
            }
            $this->repository->record_error($courseid, $errormessage);
            throw $e;
        }
    }

    /**
     * Split the upload payload into HTTP-friendly batches.
     *
     * A batch is closed as soon as adding the next item would exceed either
     * {@see self::CHUNK_MAX_FILES} files or {@see self::CHUNK_MAX_BYTES}
     * bytes. A single oversized file always gets its own batch — the byte
     * budget is an upper bound per item, not a hard rejection.
     *
     * @param array $items Upload items (\stored_file|file_upload_part).
     * @return array[] Array of batches, each a subset of $items preserving order.
     */
    public function build_chunks(array $items): array {
        $chunks = [];
        $current = [];
        $currentbytes = 0;

        foreach ($items as $item) {
            $size = $this->item_size($item);
            $currentcount = count($current);
            $wouldexceedcount = $currentcount >= self::CHUNK_MAX_FILES;
            $wouldexceedbytes = $currentcount > 0
                && ($currentbytes + $size) > self::CHUNK_MAX_BYTES;

            if ($wouldexceedcount || $wouldexceedbytes) {
                $chunks[] = $current;
                $current = [];
                $currentbytes = 0;
            }

            $current[] = $item;
            $currentbytes += $size;
        }

        if ($current !== []) {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * Compute the SHA-256 content hash of every upload item.
     *
     * Stored files are hashed via a streaming handle to keep memory bounded;
     * local upload parts are hashed directly from their on-disk path.
     *
     * @param array $items Upload items.
     * @return array[] File manifest entries in the same order as $items.
     */
    public function compute_expected_files(array $items): array {
        $files = [];
        foreach ($items as $item) {
            $files[] = [
                'hash' => $this->hash_item($item),
                'filename' => $this->item_filename($item),
            ];
        }
        return $files;
    }

    /**
     * Byte size of a single upload item.
     *
     * @param \stored_file|file_upload_part $item
     * @return int
     */
    private function item_size($item): int {
        if ($item instanceof \stored_file) {
            return (int) $item->get_filesize();
        }
        if ($item instanceof file_upload_part) {
            $size = @filesize($item->path);
            return $size !== false ? (int) $size : 0;
        }
        return 0;
    }

    /**
     * Logical filename sent to the API for a single upload item.
     *
     * @param \stored_file|file_upload_part $item
     * @return string
     */
    private function item_filename($item): string {
        if ($item instanceof \stored_file) {
            return $item->get_filename();
        }
        if ($item instanceof file_upload_part) {
            return $item->filename;
        }
        throw new \RuntimeException('Unsupported upload item type: ' . get_debug_type($item));
    }

    /**
     * Streaming SHA-256 of a single upload item.
     *
     * @param \stored_file|file_upload_part $item
     * @return string
     */
    private function hash_item($item): string {
        if ($item instanceof \stored_file) {
            $fh = $item->get_content_file_handle();
            if ($fh === false) {
                throw new \RuntimeException('Failed to open stored_file for hashing');
            }
            $ctx = hash_init('sha256');
            hash_update_stream($ctx, $fh);
            fclose($fh);
            return hash_final($ctx);
        }
        if ($item instanceof file_upload_part) {
            $hash = hash_file('sha256', $item->path);
            if ($hash === false) {
                throw new \RuntimeException('Failed to hash file_upload_part: ' . $item->path);
            }
            return $hash;
        }
        throw new \RuntimeException('Unsupported upload item type: ' . get_debug_type($item));
    }

    /**
     * Log one debug line per file the API rejected in the given response.
     *
     * @param array $response Decoded API response for a chunk upload.
     * @return void
     */
    private function log_api_rejections(array $response): void {
        $failed = $this->extract_api_rejections($response);
        if ($failed === []) {
            return;
        }

        foreach ($failed as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            debugging(sprintf(
                'local_dixeo: API rejected "%s" [%s]: %s',
                (string) ($entry['filename'] ?? '(unknown)'),
                (string) ($entry['code'] ?? 'UNKNOWN'),
                (string) ($entry['reason'] ?? '')
            ), DEBUG_DEVELOPER);
        }
    }

    /**
     * Extract failed file entries from a chunk upload API response.
     *
     * @param array $response Decoded API response for a chunk upload.
     * @return array[]
     */
    private function extract_api_rejections(array $response): array {
        $failed = $response['failedFiles'] ?? null;
        if (!is_array($failed) || $failed === []) {
            return [];
        }

        return array_values(array_filter($failed, 'is_array'));
    }

    /**
     * Mark a course as outdated (content changed, sync needed).
     *
     * Sets the sync status to 'outdated' to signal that course content
     * has changed since the last synchronization. Does not override
     * an active sync in progress.
     *
     * @param int $courseid The course ID.
     * @return void
     */
    public function mark_outdated(int $courseid): void {
        $record = $this->repository->get_by_courseid($courseid);

        if ($record === null || !$record->enabled) {
            return;
        }

        // Don't override an active sync.
        if ($record->syncstatus === 'syncing') {
            return;
        }

        $this->repository->update_sync_status($courseid, 'outdated');
    }

    /**
     * Queue a debounced sync for a course.
     *
     * Creates an adhoc task that will execute after a delay, allowing
     * multiple rapid changes to be batched into a single sync.
     *
     * Uses database status check to prevent race conditions between
     * concurrent requests checking for existing tasks.
     *
     * @param int $courseid The course ID.
     * @return void
     */
    public function queue_sync(int $courseid): void {
        if (!$this->is_enabled($courseid)) {
            return;
        }

        // Check current status - if already syncing, skip to avoid duplicate work.
        $record = $this->repository->get_by_courseid($courseid);
        if ($record !== null && $record->syncstatus === 'syncing') {
            return;
        }

        // Check for existing pending task.
        // Note: This check has a small race window, but the status check above
        // prevents the more common duplicate sync scenario.
        $existingtasks = \core\task\manager::get_adhoc_tasks('\\local_dixeo\\task\\process_file_sync');
        foreach ($existingtasks as $task) {
            $data = $task->get_custom_data();
            if (isset($data->courseid) && (int) $data->courseid === $courseid) {
                // Task already queued for this course - skip.
                return;
            }
        }

        // Create new adhoc task with delay.
        // The 'true' parameter ensures only one task per course is queued.
        $task = new \local_dixeo\task\process_file_sync();
        $task->set_custom_data((object) ['courseid' => $courseid]);
        $task->set_next_run_time(time() + self::DEBOUNCE_DELAY);

        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Collect all syncable files from a course.
     *
     * Iterates through resource and folder modules, extracting files
     * with supported extensions. SCORM extracts are not included here
     * (they are built during {@see trigger_sync()} when the manifest hash changes).
     *
     * @param int $courseid The course ID.
     * @return array Array of stored_file objects.
     */
    public function collect_course_files(int $courseid): array {
        $modinfo = get_fast_modinfo($courseid);
        $files = [];
        $fs = get_file_storage();

        foreach ($modinfo->get_cms() as $cm) {
            if (!in_array($cm->modname, self::FILE_MODULE_TYPES, true)) {
                continue;
            }

            if (!$cm->visible) {
                continue;
            }

            $context = \context_module::instance($cm->id);
            $modulefiles = $this->get_module_files($cm->modname, $context, $fs);
            $files = array_merge($files, $modulefiles);
        }

        return $files;
    }

    /**
     * Compute manifest hash from resource/folder files and SCORM package content hashes (no extraction).
     *
     * @param int $courseid The course ID.
     * @return string SHA-256 hex digest.
     */
    private function compute_sync_manifest_hash(int $courseid): string {
        $lines = $this->collect_sync_manifest_lines($courseid);
        sort($lines, SORT_STRING);

        return hash('sha256', implode("\n", $lines));
    }

    /**
     * Sorted manifest lines for hashing.
     *
     * @param int $courseid The course ID.
     * @return string[]
     */
    private function collect_sync_manifest_lines(int $courseid): array {
        $modinfo = get_fast_modinfo($courseid);
        $fs = get_file_storage();
        $lines = [];

        foreach ($modinfo->get_cms() as $cm) {
            if (in_array($cm->modname, self::FILE_MODULE_TYPES, true)) {
                if (!$cm->visible) {
                    continue;
                }
                $context = \context_module::instance($cm->id);
                $modulefiles = $this->get_module_files($cm->modname, $context, $fs);
                foreach ($modulefiles as $file) {
                    $lines[] = $file->get_contenthash() . '|' . $file->get_filename();
                }
            } else if ($cm->modname === 'scorm') {
                if (!$cm->visible) {
                    continue;
                }
                $package = $this->scormextract->get_package_file($cm);
                if ($package) {
                    $lines[] = 'scorm_cm' . $cm->id . '|' . $package->get_contenthash();
                }
            }
        }

        return $lines;
    }

    /**
     * Build upload payload: resource/folder stored_files plus SCORM text extracts.
     *
     * Empty SCORM extracts are omitted; debugging() records the skip (see scorm_vector_extract_service).
     *
     * @param int $courseid The course ID.
     * @return array Array of \stored_file|file_upload_part.
     */
    private function collect_upload_payload(int $courseid): array {
        $modinfo = get_fast_modinfo($courseid);
        $fs = get_file_storage();
        $payload = [];

        foreach ($modinfo->get_cms() as $cm) {
            if (in_array($cm->modname, self::FILE_MODULE_TYPES, true)) {
                if (!$cm->visible) {
                    continue;
                }
                $context = \context_module::instance($cm->id);
                $modulefiles = $this->get_module_files($cm->modname, $context, $fs);
                foreach ($modulefiles as $file) {
                    $payload[] = $file;
                }
            } else if ($cm->modname === 'scorm') {
                if (!$cm->visible) {
                    continue;
                }
                $part = $this->scormextract->try_build_upload_part($cm);
                if ($part !== null) {
                    $payload[] = $part;
                }
            }
        }

        return $payload;
    }

    /**
     * Check if auto-retry should proceed for a course.
     *
     * @param int $courseid The course ID.
     * @return bool True if auto-retry should proceed.
     */
    public function should_retry_on_error(int $courseid): bool {
        return $this->repository->should_auto_retry($courseid);
    }

    /**
     * Poll the API for current sync status and update local record.
     *
     * @param int $courseid The course ID.
     * @return \stdClass Updated status object.
     */
    public function poll_status(int $courseid): \stdClass {
        if (!$this->client->is_configured()) {
            return $this->get_status($courseid);
        }

        $apistatus = $this->fetch_remote_sync_status($courseid);
        if ($apistatus !== null) {
            $apiprogress = $apistatus['progress'] ?? null;

            $progress = [
                'filestotal' => is_array($apiprogress) && isset($apiprogress['filesTotal'])
                    ? (int) $apiprogress['filesTotal']
                    : (int) ($apistatus['fileCount'] ?? 0),
                'filescompleted' => is_array($apiprogress) && isset($apiprogress['filesCompleted'])
                    ? (int) $apiprogress['filesCompleted']
                    : (int) ($apistatus['syncedCount'] ?? 0),
                'progresspercent' => is_array($apiprogress) && isset($apiprogress['percent'])
                    ? (int) $apiprogress['percent']
                    : 0,
            ];

            $status = $apistatus['status'] ?? 'none';
            $this->repository->update_sync_status($courseid, $status, $progress);
        }

        return $this->get_status($courseid);
    }

    /**
     * Fetch remote sync status, or null when the API is unavailable.
     *
     * @param int $courseid The course ID.
     * @return array|null Decoded API status payload.
     */
    private function fetch_remote_sync_status(int $courseid): ?array {
        try {
            return $this->client->get_files_status((string) $courseid);
        } catch (api_exception $e) {
            return null;
        }
    }

    /**
     * Whether the user may enable or trigger outbound file sync in a course.
     *
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @return bool
     */
    private function user_can_sync_files_in_course(int $courseid, int $userid): bool {
        if ($courseid <= SITEID || $userid <= 0) {
            return false;
        }

        try {
            $context = \context_course::instance($courseid);
            return has_capability('local/dixeo:syncfiles', $context, $userid);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get files from a specific module.
     *
     * @param string $modname The module type (resource, folder).
     * @param \context_module $context The module context.
     * @param \file_storage $fs The file storage instance.
     * @return array Array of stored_file objects.
     */
    private function get_module_files(string $modname, \context_module $context, \file_storage $fs): array {
        $files = [];

        // Component and filearea depend on module type.
        $component = 'mod_' . $modname;

        // Get files based on module type.
        if ($modname === 'resource') {
            $areafiles = $fs->get_area_files($context->id, $component, 'content', 0, 'sortorder', false);
        } else if ($modname === 'folder') {
            $areafiles = $fs->get_area_files($context->id, $component, 'content', 0, 'sortorder', false);
        } else {
            return $files;
        }

        foreach ($areafiles as $file) {
            if ($file->is_directory()) {
                continue;
            }

            $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
            if (in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
                $files[] = $file;
            }
        }

        return $files;
    }
}
