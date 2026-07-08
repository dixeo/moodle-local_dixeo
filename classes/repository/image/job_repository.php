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

namespace local_dixeo\repository\image;


use local_dixeo\service\image\content\location;
use local_dixeo\service\image\content\url_helper;
use local_dixeo\service\image\content_target;
use local_dixeo\service\image\image_target;

/**
 * CRUD and status for unified image jobs (content + structure).
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class job_repository {
    /** @var string Constant TABLE. */
    public const TABLE = 'local_dixeo_image_job';

    /** @var string Constant STATUS_PENDING. */
    public const STATUS_PENDING = 'pending';
    /** @var string Constant STATUS_PROCESSING. */
    public const STATUS_PROCESSING = 'processing';
    /** @var string Constant STATUS_APPLIED. */
    public const STATUS_APPLIED = 'applied';
    /** @var string Constant STATUS_FAILED. */
    public const STATUS_FAILED = 'failed';

    /** @var string Constant ORIGIN_SHORTCODE. */
    public const ORIGIN_SHORTCODE = 'shortcode';
    /** @var string Constant ORIGIN_MODAL. */
    public const ORIGIN_MODAL = 'modal';
    /** @var string Constant ORIGIN_STRUCTURE. */
    public const ORIGIN_STRUCTURE = 'structure';
    /** @var string Constant ORIGIN_EDITOR_DRAFT. */
    public const ORIGIN_EDITOR_DRAFT = 'editor_draft';

    /** @var string Editor session table name for draft image jobs. */
    public const TARGETTABLE_EDITOR_SESSION = 'local_dixeo_editor_session';

    /** @var int Seconds before a pending lock is marked failed (~1h). */
    public const TIMEOUT_SECONDS = 3600;

    /**
     * Get by target.
     * @param image_target $target
     * @return \stdClass|null
     */
    public static function get_by_target(image_target $target): ?\stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['locationhash' => $target->get_location_hash()], '*', IGNORE_MISSING);
        return $record ?: null;
    }

    /**
     * Get by locationhash.
     * @param location $location
     * @return \stdClass|null
     */
    public static function get_by_locationhash(location $location): ?\stdClass {
        return self::get_by_target(content_target::from_location($location));
    }

    /**
     * Get by placeholderid.
     * @param string $placeholderid
     * @return \stdClass|null
     */
    public static function get_by_placeholderid(string $placeholderid): ?\stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['placeholderid' => $placeholderid], '*', IGNORE_MISSING);
        return $record ?: null;
    }

    /**
     * Read-only view of the job for a target; timed-out rows are presented as
     * failed without writing (the cleanup_image_jobs task persists that state).
     *
     * @param image_target $target
     * @return \stdClass|null
     */
    public static function get_active_job(image_target $target): ?\stdClass {
        $record = self::get_by_target($target);
        if (!$record) {
            return null;
        }

        if (in_array($record->status, [self::STATUS_APPLIED, self::STATUS_FAILED], true)) {
            return $record;
        }

        if ((time() - (int) $record->timecreated) > self::TIMEOUT_SECONDS) {
            $record->status = self::STATUS_FAILED;
            $record->errormessage = get_string('dixeo_image_job_failed', 'local_dixeo');
        }

        return $record;
    }

    /**
     * Subset of the given hashes that currently have a pending or processing job.
     *
     * Timed-out rows are excluded, matching get_active_job(). Single query, intended
     * for render-time checks over many locations (e.g. the image editor filter).
     *
     * @param string[] $hashes Location hashes.
     * @return string[] Hashes with an in-flight job.
     */
    public static function get_pending_locationhashes(array $hashes): array {
        global $DB;

        $hashes = array_values(array_unique(array_filter($hashes)));
        if ($hashes === []) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($hashes, SQL_PARAMS_NAMED);
        $params['pending'] = self::STATUS_PENDING;
        $params['processing'] = self::STATUS_PROCESSING;
        $params['cutoff'] = time() - self::TIMEOUT_SECONDS;

        return $DB->get_fieldset_select(
            self::TABLE,
            'locationhash',
            "locationhash {$insql} AND status IN (:pending, :processing) AND timecreated > :cutoff",
            $params
        );
    }

    /**
     * Has blocking job.
     * @param location $location
     * @return bool
     */
    public static function has_blocking_job(location $location): bool {
        $job = self::get_active_job(content_target::from_location($location));
        if (!$job) {
            return false;
        }
        return in_array($job->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true);
    }

    /**
     * Get active job for location.
     * @param location $location
     * @return \stdClass|null
     */
    public static function get_active_job_for_location(location $location): ?\stdClass {
        return self::get_active_job(content_target::from_location($location));
    }

    /**
     * Create job.
     * @param location $location
     * @param string $jobid
     * @param int $userid
     * @param string|null $prompt
     * @param string|null $quality
     * @param string|null $mode
     * @return \stdClass
     */
    public static function create_job(
        location $location,
        string $jobid,
        int $userid,
        ?string $prompt = null,
        ?string $quality = null,
        ?string $mode = null
    ): \stdClass {
        return self::upsert(content_target::from_location($location), $jobid, $userid, [
            'placeholderid' => null,
            'targettable' => null,
            'targetfield' => null,
            'targetid' => null,
            'cmid' => null,
            'origin' => self::ORIGIN_MODAL,
            'prompt' => $prompt,
            'quality' => $quality,
            'mode' => $mode,
        ]);
    }

    /**
     * Upsert.
     * @param image_target $target
     * @param string $remotejobid
     * @param int $userid
     * @param array $metadata Extra row fields (prompt, origin, placeholderid, etc.).
     * @return \stdClass
     */
    public static function upsert(image_target $target, string $remotejobid, int $userid, array $metadata = []): \stdClass {
        $fields = array_merge($target->to_job_fields(), $metadata, [
            'jobid' => $remotejobid,
            'status' => self::STATUS_PENDING,
            'errormessage' => null,
            'userid' => $userid,
        ]);
        return self::upsert_job($fields);
    }

    /**
     * Upsert job.
     * @param array $fields Full job row fields.
     * @return \stdClass
     */
    public static function upsert_job(array $fields): \stdClass {
        global $DB;

        $locationhash = (string) ($fields['locationhash'] ?? '');
        if ($locationhash === '') {
            throw new \invalid_parameter_exception('locationhash is required');
        }

        $existing = $DB->get_record(self::TABLE, ['locationhash' => $locationhash], '*', IGNORE_MISSING);
        if (
            $existing && in_array($existing->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true)
                && (time() - (int) $existing->timecreated) <= self::TIMEOUT_SECONDS
        ) {
            throw new \moodle_exception('dixeo_image_job_locked', 'local_dixeo');
        }

        $now = time();
        $record = (object) $fields;
        $record->timemodified = $now;

        if ($existing) {
            $record->id = $existing->id;
            if (!isset($record->timecreated)) {
                $record->timecreated = $existing->timecreated;
            }
            $DB->update_record(self::TABLE, $record);
            return $DB->get_record(self::TABLE, ['id' => $record->id], '*', MUST_EXIST);
        }

        $record->timecreated = $now;
        if (!isset($record->target_kind)) {
            $record->target_kind = image_target::KIND_CONTENT;
        }
        $record->id = $DB->insert_record(self::TABLE, $record);
        return $DB->get_record(self::TABLE, ['id' => $record->id], '*', MUST_EXIST);
    }

    /**
     * Update status.
     * @param int $jobid
     * @param string $status
     * @param string|null $errormessage
     * @return void
     */
    public static function update_status(int $jobid, string $status, ?string $errormessage = null): void {
        global $DB;

        $DB->update_record(self::TABLE, (object) [
            'id' => $jobid,
            'status' => $status,
            'errormessage' => $errormessage,
            'timemodified' => time(),
        ]);
    }

    /**
     * Mark failed.
     * @param int $jobid
     * @param string $message
     * @return void
     */
    public static function mark_failed(int $jobid, string $message): void {
        self::update_status($jobid, self::STATUS_FAILED, $message);
    }

    /**
     * Delete job.
     * @param int $jobid
     * @return void
     */
    public static function delete_job(int $jobid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['id' => $jobid]);
    }

    /**
     * Get status for target.
     * @param image_target $target
     * @param bool $acknowledged When true, applied jobs are deleted after read.
     * @return array{
     *     status: string,
     *     imageurl?: string,
     *     errormessage?: string,
     *     lockid?: int,
     *     prefill_prompt?: string,
     *     prefill_quality?: string,
     *     prefill_mode?: string,
     *     current_contenthash?: string
     * }
     */
    public static function get_status_for_target(image_target $target, bool $acknowledged = false): array {
        $job = self::get_active_job($target);
        if (!$job) {
            return ['status' => 'idle'];
        }

        $payload = [
            'status' => (string) $job->status,
            'lockid' => (int) $job->id,
        ];

        if (!empty($job->errormessage)) {
            $payload['errormessage'] = (string) $job->errormessage;
        }

        if ($job->status === self::STATUS_APPLIED) {
            if ($target->get_target_kind() === image_target::KIND_CONTENT) {
                $location = null;
                if ($target instanceof content_target) {
                    $location = $target->get_location();
                }
                if ($location) {
                    $payload['imageurl'] = url_helper::get_current_image_url($location);
                    $file = $location->get_stored_file();
                    if ($file) {
                        $payload['current_contenthash'] = $file->get_contenthash();
                    }
                }
            }
        }

        if ($job->status === self::STATUS_FAILED) {
            $payload['prefill_prompt'] = (string) ($job->prompt ?? '');
            $payload['prefill_quality'] = (string) ($job->quality ?? '');
            $payload['prefill_mode'] = (string) ($job->mode ?? '');
        }

        if ($acknowledged && $job->status === self::STATUS_APPLIED) {
            self::delete_job((int) $job->id);
        }

        return $payload;
    }

    /**
     * Get location status.
     * @param location $location
     * @param bool $acknowledged
     * @return array{
     *     status: string,
     *     imageurl?: string,
     *     errormessage?: string,
     *     lockid?: int,
     *     prefill_prompt?: string,
     *     prefill_quality?: string,
     *     prefill_mode?: string,
     *     current_contenthash?: string
     * }
     */
    public static function get_location_status(location $location, bool $acknowledged = false): array {
        return self::get_status_for_target(content_target::from_location($location), $acknowledged);
    }

    /**
     * Get jobs for session.
     * @param int $sessionid
     * @return \stdClass[]
     */
    public static function get_jobs_for_session(int $sessionid): array {
        global $DB;
        return $DB->get_records(self::TABLE, [
            'targettable' => self::TARGETTABLE_EDITOR_SESSION,
            'targetid' => $sessionid,
        ]);
    }

    /**
     * Mark pending/processing editor draft jobs for a session as failed.
     *
     * @param int $sessionid
     * @param string $message
     * @return \stdClass[] The job rows that were marked failed.
     */
    public static function fail_session_jobs(int $sessionid, string $message): array {
        $failed = [];
        foreach (self::get_jobs_for_session($sessionid) as $job) {
            if (!in_array($job->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true)) {
                continue;
            }
            self::mark_failed((int) $job->id, $message);
            $failed[] = $job;
        }
        return $failed;
    }

    /**
     * Fail draft jobs whose placeholder id is no longer present in HTML.
     *
     * @param int $sessionid
     * @param string[] $activeplaceholderids
     * @param string $message
     * @return \stdClass[] The job rows that were marked failed.
     */
    public static function fail_orphan_session_jobs(int $sessionid, array $activeplaceholderids, string $message): array {
        $active = array_flip($activeplaceholderids);
        $failed = [];
        foreach (self::get_jobs_for_session($sessionid) as $job) {
            if (empty($job->placeholderid)) {
                continue;
            }
            if (isset($active[(string) $job->placeholderid])) {
                continue;
            }
            if (!in_array($job->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true)) {
                continue;
            }
            self::mark_failed((int) $job->id, $message);
            $failed[] = $job;
        }
        return $failed;
    }

    /**
     * Is editor draft job.
     * @param \stdClass|null $jobrow
     * @return bool
     */
    public static function is_editor_draft_job(?\stdClass $jobrow): bool {
        return $jobrow !== null
            && (string) ($jobrow->targettable ?? '') === self::TARGETTABLE_EDITOR_SESSION;
    }
}
