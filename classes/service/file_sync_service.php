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
use local_dixeo\settings\config;

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

    /** @var int Maximum file size accepted by the Dixeo API (20 MB). */
    private const MAX_FILE_SIZE = 20971520;

    /**
     * Validation codes that do not fail the course sync.
     *
     * An entry with no code is treated the same way, so a production API
     * that omits the machine-readable code still does not fail the course.
     *
     * @var string[]
     */
    private const IGNORABLE_REJECTION_CODES = [
        'FILE_EMPTY',
        'FILE_TOO_LARGE',
        'FILE_UNREADABLE',
        'FILENAME_MISSING_EXTENSION',
        'EXTENSION_NOT_ALLOWED',
        'MIME_TYPE_MISMATCH',
        'MIME_TYPE_UNDETECTABLE',
    ];

    /** @var course_ai_repository Repository for course AI records. */
    private course_ai_repository $repository;

    /** @var client API client for Dixeo communication. */
    private client $client;

    /** @var scorm_vector_extract_service SCORM package text extraction for sync. */
    private scorm_vector_extract_service $scormextract;

    /** @var bool True while a status or new-course write records its own pause event. */
    private static bool $deferfilesyncevent = false;

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
     * Requires local/dixeo:syncfiles for the acting user in the course context.
     * Does nothing when Dixeo is off for the course.
     *
     * @param int $courseid The course ID.
     * @param int $userid The user enabling sync.
     * @return void
     */
    public function enable_sync(int $courseid, int $userid): void {
        if (!$this->dixeo_enabled($courseid)) {
            return;
        }

        if (!$this->user_can_sync_files_in_course($courseid, $userid)) {
            return;
        }

        $this->write_filesync_setting($courseid, '1');
        $this->apply_course_filesync($courseid, $userid, true);
    }

    /**
     * Turn file sync on or leave it paused for a course that has just been created.
     *
     * Always stores the course setting from the Dixeo default for new courses, including Off.
     * The operational row is created only when that value is On. The site setting authorises
     * the change, so the course creator does not need local/dixeo:syncfiles or moodle/course:update.
     * Does nothing when Dixeo is off for the course.
     *
     * @param int $courseid The new course ID.
     * @param int $userid The user who created the course.
     * @return void
     */
    public function enable_new_course(int $courseid, int $userid): void {
        if (!$this->dixeo_enabled($courseid)) {
            return;
        }

        $default = (new config())->get(
            'local_dixeo',
            'filesync_enabled_default',
            \context_system::instance()
        )->get_value();
        $enabled = $default === '1';
        $this->write_filesync_setting($courseid, $enabled ? '1' : '0');
        $this->apply_course_filesync($courseid, $userid, $enabled);
    }

    /**
     * Apply the new-course file sync default after generation has indexed the source files.
     *
     * The Course Designer turns sync on while it generates. On leaves that sync running.
     * Off pauses it and keeps the files already indexed.
     *
     * @param int $courseid The generated course ID.
     * @param int $userid The user recorded on the pause.
     * @return void
     */
    public function apply_new_course_default(int $courseid, int $userid): void {
        $default = (new config())->get(
            'local_dixeo',
            'filesync_enabled_default',
            \context_system::instance()
        )->get_value();
        if ($default === '1') {
            return;
        }

        $this->disable_sync($courseid, $userid, false);
    }

    /**
     * Wait until course files are indexed when file sync is already on.
     *
     * Does not turn sync on. When sync is off, the caller continues without an index.
     * When sync is on, refreshes and waits for a synchronized (or empty) status.
     *
     * Used before RAG-backed API jobs (tutor messages, module generation).
     * Does nothing when Dixeo is off for the course.
     *
     * @param int $courseid The course ID.
     * @param int $userid Unused. Sync is not turned on for this user.
     * @param int $timeoutseconds Maximum seconds to wait for synchronization.
     * @return void
     * @throws \moodle_exception When sync fails or times out.
     */
    public function ensure_enabled_and_synchronized(int $courseid, int $userid, int $timeoutseconds = 120): void {
        if (!$this->dixeo_enabled($courseid)) {
            return;
        }

        if ($this->is_pending_deletion($courseid)) {
            throw new \moodle_exception('filesync_pending_deletion', 'local_dixeo');
        }

        if (!$this->is_enabled($courseid)) {
            return;
        }

        // A confirmed-clean course stays clean until content changes, and content changes
        // queue their own sync. Re-confirming on every message cost two hub round trips.
        if (self::recently_verified($courseid)) {
            return;
        }

        $this->trigger_sync($courseid);

        $deadline = time() + $timeoutseconds;
        while (time() < $deadline) {
            $status = $this->poll_status($courseid);
            if ($status->status === 'synchronized' || $status->status === 'none') {
                self::mark_verified($courseid);
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
     * Whether remote file deletion for this course is still outstanding.
     *
     * @param int $courseid The course ID.
     * @return bool
     */
    private function is_pending_deletion(int $courseid): bool {
        $record = $this->repository->get_by_courseid($courseid);
        return $record !== null && $record->syncstatus === 'pending_deletion';
    }

    /**
     * Whether this course was confirmed in sync recently enough to skip the blocking check.
     *
     * @param int $courseid The course ID.
     * @return bool
     */
    private static function recently_verified(int $courseid): bool {
        return \cache::make('local_dixeo', 'filesyncverified')->get((string) $courseid) !== false;
    }

    /**
     * Record that this course was confirmed in sync.
     *
     * @param int $courseid The course ID.
     * @return void
     */
    private static function mark_verified(int $courseid): void {
        \cache::make('local_dixeo', 'filesyncverified')->set((string) $courseid, time());
    }

    /**
     * Drop the verified marker so the next RAG-backed job re-checks synchronization.
     *
     * Called when course content changes (new or updated activities and files).
     *
     * @param int $courseid The course ID.
     * @return void
     */
    public static function invalidate_verified(int $courseid): void {
        \cache::make('local_dixeo', 'filesyncverified')->delete((string) $courseid);
    }

    /**
     * Queue a debounced sync after module creation when file sync is already on.
     *
     * Does not turn sync on. Used after AI-generated and manual-upload activities so
     * new resources are picked up for the tutor and content generation. Failures are logged only.
     *
     * @param int $courseid The course ID.
     * @param int|null $userid Unused. The stored course setting authorises the refresh.
     * @return void
     */
    public function enable_and_queue_sync_after_module_creation(int $courseid, ?int $userid = null): void {
        if (!$this->dixeo_enabled($courseid)) {
            return;
        }

        try {
            self::invalidate_verified($courseid);
            if (!$this->is_enabled($courseid)) {
                return;
            }

            $this->queue_sync($courseid);
        } catch (\Throwable $e) {
            debugging(
                'Failed to queue file sync after module creation: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }

    /**
     * Run sync immediately after module creation when file sync is already on.
     *
     * Does not turn sync on. Used after manual uploads so new resources/SCORM are
     * indexed without debounce delay. Failures are logged only.
     *
     * @param int $courseid The course ID.
     * @param int|null $userid Unused. The stored course setting authorises the refresh.
     * @return void
     */
    public function enable_and_trigger_sync_after_module_creation(int $courseid, ?int $userid = null): void {
        if (!$this->dixeo_enabled($courseid)) {
            return;
        }

        try {
            self::invalidate_verified($courseid);
            if (!$this->is_enabled($courseid)) {
                return;
            }

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
     * When $removefiles is true, marks pending_deletion and calls DELETE /v1/files.
     * Local sync evidence is cleared only after a successful remote delete. Failures
     * keep pending_deletion and queue an idempotent retry task.
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

        $this->write_filesync_setting($courseid, '0');

        if (!$removefiles) {
            $record = $this->repository->get_by_courseid($courseid);
            if ($record !== null && (bool) $record->enabled) {
                $this->repository->set_enabled($courseid, false, $userid);
            }
            if ($wasenabled && $objectid > 0) {
                file_sync_disabled::create_for_course(
                    $courseid,
                    $userid,
                    $objectid,
                    false,
                    'not_requested'
                )->trigger();
            }
            return;
        }

        $this->repository->set_enabled($courseid, false, $userid);
        $this->repository->mark_pending_deletion($courseid);

        $remotedeletestatus = 'pending';
        try {
            $this->client->delete_files((string) $courseid);
            $this->repository->reset_sync_state($courseid);
            $remotedeletestatus = 'completed';
        } catch (api_exception $e) {
            $this->repository->record_pending_deletion_error($courseid, $e->getMessage());
            $this->queue_remote_deletion_retry($courseid);
        }

        $record = $this->repository->get_by_courseid($courseid);
        $objectid = $record !== null ? (int) $record->id : $objectid;
        if ($wasenabled && $objectid > 0) {
            file_sync_disabled::create_for_course(
                $courseid,
                $userid,
                $objectid,
                true,
                $remotedeletestatus
            )->trigger();
        }
    }

    /**
     * Retry a pending remote file deletion for a course.
     *
     * Idempotent: no-ops unless syncstatus is pending_deletion.
     *
     * @param int $courseid The course ID.
     * @return bool True when remote deletion completed (or nothing pending).
     */
    public function retry_pending_deletion(int $courseid): bool {
        $record = $this->repository->get_by_courseid($courseid);
        if ($record === null || $record->syncstatus !== 'pending_deletion') {
            return true;
        }

        try {
            $this->client->delete_files((string) $courseid);
            $this->repository->reset_sync_state($courseid);
            return true;
        } catch (api_exception $e) {
            $this->repository->record_pending_deletion_error($courseid, $e->getMessage());
            if ($this->repository->should_retry_pending_deletion($courseid)) {
                $this->queue_remote_deletion_retry($courseid);
            }
            return false;
        }
    }

    /**
     * Queue an adhoc task to retry remote file deletion.
     *
     * @param int $courseid The course ID.
     * @return void
     */
    public function queue_remote_deletion_retry(int $courseid): void {
        $delay = $this->repository->get_retry_delay($courseid);
        if ($delay < 1) {
            // First failure may not yet have an errorcount mapped delay in edge cases.
            $delay = 60;
        }

        $classname = '\\local_dixeo\\task\\process_remote_file_deletion';
        foreach (\core\task\manager::get_adhoc_tasks($classname) as $existing) {
            $data = $existing->get_custom_data();
            if (isset($data->courseid) && (int) $data->courseid === $courseid) {
                return;
            }
        }

        $task = new \local_dixeo\task\process_remote_file_deletion();
        $task->set_custom_data((object) ['courseid' => $courseid]);
        $task->set_next_run_time(time() + $delay);
        \core\task\manager::queue_adhoc_task($task, true);
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
     * Per-file rows for the status tab.
     *
     * Resource and folder files are classified locally. A remote file list is
     * applied when the API returns one. The remote error text is never copied
     * onto the row: a failed remote file uses the generic reason "error".
     *
     * While a sync is in progress, a managed file that the remote list has not
     * confirmed is omitted. Files that are too large, empty, or an unmanaged
     * type stay visible as problems.
     *
     * @param int $courseid The course ID.
     * @param string|null $syncstatus Course sync status. Read from the local row when omitted.
     * @return array[] Each row has filename, state (synced|problem), and reason.
     */
    public function list_status_files(int $courseid, ?string $syncstatus = null): array {
        if ($syncstatus === null) {
            $syncstatus = (string) $this->get_status($courseid)->status;
        }

        return $this->classify_sync_files(
            $this->collect_status_file_descriptors($courseid),
            $this->fetch_remote_file_list($courseid),
            $syncstatus
        );
    }

    /**
     * Classify local files, then overlay a remote file list when its shape is recognised.
     *
     * $remotefiles may be a list, a hydra:member collection, or a member collection.
     * Filename is read from fileName or filename. An unusable payload is ignored.
     *
     * @param array $localfiles Rows with filename and size.
     * @param array|null $remotefiles Decoded GET /v1/files body, or null when the API was not called.
     * @param string $syncstatus Course sync status.
     * @return array[]
     */
    public function classify_sync_files(array $localfiles, ?array $remotefiles, string $syncstatus): array {
        $remote = $this->index_remote_files($remotefiles);
        $rows = [];
        foreach ($localfiles as $index => $file) {
            if (!is_array($file)) {
                continue;
            }
            $filename = (string) ($file['filename'] ?? '');
            if ($filename === '') {
                continue;
            }
            $row = $this->classify_one_status_file($filename, (int) ($file['size'] ?? 0), $remote, $syncstatus);
            if ($row === null) {
                continue;
            }
            $row['sort'] = $index;
            $rows[] = $row;
        }

        usort($rows, static function (array $a, array $b): int {
            $byname = strcasecmp($a['filename'], $b['filename']);
            if ($byname !== 0) {
                return $byname;
            }
            return $a['sort'] <=> $b['sort'];
        });

        $sorted = [];
        foreach ($rows as $row) {
            unset($row['sort']);
            $sorted[] = $row;
        }
        return $sorted;
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
     * Files over 20 MB and empty files are omitted and do not fail the sync.
     * A validation rejection from the API does not fail the course either.
     * UPLOAD_FAILED, and a transport or HTTP failure, still do.
     *
     * @param int $courseid The course ID.
     * @return void
     * @throws api_exception If the API request fails.
     */
    public function trigger_sync(int $courseid): void {
        global $USER;

        if (!$this->dixeo_enabled($courseid)) {
            return;
        }

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

        $uploaditems = $this->select_managed_files($this->collect_upload_payload($courseid));

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
                // Nothing left to index (no files, or only files the API does not manage).
                // Clear the remote store as well; POST /v1/files rejects an empty payload.
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

            $fatalfiles = $this->fatal_api_rejections($failedfiles);
            if ($fatalfiles !== []) {
                $message = count($fatalfiles) . ' file(s) were rejected by the Dixeo API';
                throw new api_exception('file_sync_rejections', $message, 422, ['failedFiles' => $fatalfiles]);
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
     * Drop files the API does not index.
     *
     * Files larger than {@see self::MAX_FILE_SIZE} and empty files are omitted.
     * Unsupported extensions are already excluded by {@see self::get_module_files()}.
     * SCORM extract parts go through this same size check.
     *
     * @param array $items Upload items (\stored_file|file_upload_part).
     * @return array Items to upload, in the original order.
     */
    public function select_managed_files(array $items): array {
        $managed = [];
        foreach ($items as $item) {
            $reason = $this->ignored_file_reason($item);
            if ($reason === null) {
                $managed[] = $item;
                continue;
            }
            debugging(sprintf(
                'local_dixeo: ignoring "%s" (%s)',
                $this->item_filename($item),
                $reason
            ), DEBUG_DEVELOPER);
        }
        return $managed;
    }

    /**
     * Per-file API rejections that fail the course sync.
     *
     * Size, type, empty, and unreadable files are ignored, as is an entry
     * with no machine-readable code. UPLOAD_FAILED still fails the sync.
     *
     * @param array $failedfiles Entries from a chunk upload failedFiles list.
     * @return array[]
     */
    public function fatal_api_rejections(array $failedfiles): array {
        $fatal = [];
        foreach ($failedfiles as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $code = (string) ($entry['code'] ?? '');
            if ($code === '' || in_array($code, self::IGNORABLE_REJECTION_CODES, true)) {
                continue;
            }
            $fatal[] = $entry;
        }
        return $fatal;
    }

    /**
     * Why an upload item is left out of the sync, or null when it should be sent.
     *
     * @param \stored_file|file_upload_part $item
     * @return string|null
     */
    private function ignored_file_reason($item): ?string {
        $size = $this->item_size($item);
        if ($size <= 0) {
            return 'empty';
        }
        if ($size > self::MAX_FILE_SIZE) {
            return 'too large';
        }
        return null;
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
        if (!$this->dixeo_enabled($courseid)) {
            return;
        }

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
        if (!$this->dixeo_enabled($courseid) || !$this->is_enabled($courseid)) {
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
     * Remote file list, or null when the API is not configured or the call fails.
     *
     * @param int $courseid The course ID.
     * @return array|null
     */
    private function fetch_remote_file_list(int $courseid): ?array {
        if (!$this->client->is_configured()) {
            return null;
        }

        try {
            $response = $this->client->list_files((string) $courseid);
        } catch (api_exception $e) {
            return null;
        }

        return is_array($response) ? $response : null;
    }

    /**
     * Index remote files by filename.
     *
     * Null means the payload was missing or not a file list. An empty array
     * means the API returned no files.
     *
     * @param array|null $response Decoded file-list payload.
     * @return array|null Filename to remote entry.
     */
    private function index_remote_files(?array $response): ?array {
        $entries = $this->remote_file_entries($response);
        if ($entries === null) {
            return null;
        }

        $indexed = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $filename = '';
            if (isset($entry['fileName']) && is_string($entry['fileName'])) {
                $filename = $entry['fileName'];
            } else if (isset($entry['filename']) && is_string($entry['filename'])) {
                $filename = $entry['filename'];
            }
            if ($filename === '') {
                continue;
            }
            if (!isset($indexed[$filename]) || $this->remote_entry_failed($entry)) {
                $indexed[$filename] = $entry;
            }
        }
        return $indexed;
    }

    /**
     * File entries from a bare list or a member collection.
     *
     * @param array|null $response Decoded file-list payload.
     * @return array|null Null when the shape is not a file list.
     */
    private function remote_file_entries(?array $response): ?array {
        if ($response === null) {
            return null;
        }
        if (array_is_list($response)) {
            return $response;
        }
        foreach (['hydra:member', 'member'] as $key) {
            if (isset($response[$key]) && is_array($response[$key]) && array_is_list($response[$key])) {
                return $response[$key];
            }
        }
        return null;
    }

    /**
     * Whether a remote file entry reports a failure.
     *
     * The message text is not used. Production may omit it, and the new API
     * may replace it with a generic sentence.
     *
     * @param array $entry One remote file.
     * @return bool
     */
    private function remote_entry_failed(array $entry): bool {
        $status = strtolower((string) ($entry['status'] ?? ''));
        if ($status === 'error' || $status === 'failed') {
            return true;
        }
        return isset($entry['errorMessage']) && is_string($entry['errorMessage']) && $entry['errorMessage'] !== '';
    }

    /**
     * Whether a remote file entry is known to be indexed.
     *
     * @param array $entry One remote file.
     * @return bool
     */
    private function remote_entry_synced(array $entry): bool {
        if ($this->remote_entry_failed($entry)) {
            return false;
        }
        $status = strtolower((string) ($entry['status'] ?? ''));
        return in_array($status, ['uploaded', 'completed', 'synchronized'], true);
    }

    /**
     * One status-tab row, or null while an unconfirmed file is still syncing.
     *
     * @param string $filename File name.
     * @param int $size Size in bytes.
     * @param array|null $remote Remote files indexed by filename, or null when unavailable.
     * @param string $syncstatus Course sync status.
     * @return array|null
     */
    private function classify_one_status_file(string $filename, int $size, ?array $remote, string $syncstatus): ?array {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $supported = in_array($extension, self::SUPPORTED_EXTENSIONS, true);
        if ($supported && $size > self::MAX_FILE_SIZE) {
            return [
                'filename' => $filename,
                'state' => 'problem',
                'reason' => 'toolarge',
            ];
        }
        if (!$supported || $size <= 0) {
            return [
                'filename' => $filename,
                'state' => 'problem',
                'reason' => 'unmanaged',
            ];
        }

        $entry = is_array($remote) ? ($remote[$filename] ?? null) : null;
        if (is_array($entry) && $this->remote_entry_failed($entry)) {
            return [
                'filename' => $filename,
                'state' => 'problem',
                'reason' => 'error',
            ];
        }
        if ($syncstatus === 'syncing' && !(is_array($entry) && $this->remote_entry_synced($entry))) {
            return null;
        }

        return [
            'filename' => $filename,
            'state' => 'synced',
            'reason' => '',
        ];
    }

    /**
     * Filename and size of every visible resource and folder file.
     *
     * @param int $courseid The course ID.
     * @return array[]
     */
    private function collect_status_file_descriptors(int $courseid): array {
        $modinfo = get_fast_modinfo($courseid);
        $fs = get_file_storage();
        $files = [];

        foreach ($modinfo->get_cms() as $cm) {
            if (!in_array($cm->modname, self::FILE_MODULE_TYPES, true) || !$cm->visible) {
                continue;
            }
            $context = \context_module::instance($cm->id);
            foreach ($this->list_module_files($cm->modname, $context, $fs) as $file) {
                $files[] = [
                    'filename' => $file->get_filename(),
                    'size' => (int) $file->get_filesize(),
                ];
            }
        }

        return $files;
    }

    /**
     * Match the operational enabled flag to the stored course setting.
     *
     * On creates the operational row. Off updates an existing row and does not create one,
     * and does not delete files. A plain pause event is recorded when this call is not
     * inside {@see self::write_filesync_setting()}, which records its own event.
     *
     * @param int $courseid The course ID.
     * @param int $userid The user recorded on the operational row.
     * @param bool $enabled Whether file sync is on.
     * @return void
     */
    public function apply_course_filesync(int $courseid, int $userid, bool $enabled): void {
        if ($courseid <= SITEID) {
            return;
        }
        if ($enabled) {
            if ($userid <= 0) {
                $admin = get_admin();
                $userid = $admin ? (int) $admin->id : 0;
            }
            if ($userid <= 0) {
                return;
            }
            $this->store_enabled($courseid, $userid);
            return;
        }

        $record = $this->repository->get_by_courseid($courseid);
        if ($record === null) {
            return;
        }
        $wasenabled = (bool) $record->enabled;
        $this->repository->set_enabled($courseid, false, $userid > 0 ? $userid : 0);
        if ($wasenabled && !self::$deferfilesyncevent) {
            file_sync_disabled::create_for_course(
                $courseid,
                $userid,
                (int) $record->id,
                false,
                'not_requested'
            )->trigger();
        }
    }

    /**
     * Store the course file sync setting without the course-update capability check.
     *
     * The setting observer applies the operational flag. While this write is in progress
     * that observer does not also record a pause event.
     *
     * @param int $courseid The course ID.
     * @param string $value Stored bool, "1" or "0".
     * @return void
     */
    private function write_filesync_setting(int $courseid, string $value): void {
        if ($courseid <= SITEID) {
            return;
        }
        self::$deferfilesyncevent = true;
        try {
            (new config())->set(
                'local_dixeo',
                'filesync_enabled',
                \context_course::instance($courseid),
                $value === '1' ? '1' : '0',
                false
            );
        } finally {
            self::$deferfilesyncevent = false;
        }
    }

    /**
     * Write the enabled row and emit the enabled event when it newly turns on.
     *
     * @param int $courseid The course ID.
     * @param int $userid The user recorded as enabling sync.
     * @return void
     */
    private function store_enabled(int $courseid, int $userid): void {
        if ($this->is_pending_deletion($courseid)) {
            return;
        }

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
     * Whether the Dixeo master switch allows file sync in this course.
     *
     * Off at the Dixeo layer, or off on this course, stops every sync entry point.
     *
     * @param int $courseid The course ID.
     * @return bool
     */
    private function dixeo_enabled(int $courseid): bool {
        if ($courseid <= SITEID) {
            return false;
        }

        try {
            $context = \context_course::instance($courseid);
        } catch (\Throwable $e) {
            return false;
        }

        return (new config())->enabled_for($context);
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
        foreach ($this->list_module_files($modname, $context, $fs) as $file) {
            $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
            if (in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
                $files[] = $file;
            }
        }
        return $files;
    }

    /**
     * Every non-directory file in a resource or folder.
     *
     * Includes types and sizes the sync does not upload. Callers that build
     * an upload payload still filter through {@see self::get_module_files()}.
     *
     * @param string $modname The module type (resource, folder).
     * @param \context_module $context The module context.
     * @param \file_storage $fs The file storage instance.
     * @return \stored_file[]
     */
    private function list_module_files(string $modname, \context_module $context, \file_storage $fs): array {
        if ($modname !== 'resource' && $modname !== 'folder') {
            return [];
        }

        $areafiles = $fs->get_area_files($context->id, 'mod_' . $modname, 'content', 0, 'sortorder', false);
        $files = [];
        foreach ($areafiles as $file) {
            if ($file->is_directory()) {
                continue;
            }
            $files[] = $file;
        }
        return $files;
    }
}
