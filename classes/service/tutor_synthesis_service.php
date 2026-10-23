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

namespace local_dixeo\service;

use local_dixeo\api\client;
use local_dixeo\api\exception\api_exception;
use local_dixeo\dto\job_status;
use local_dixeo\job_access_mode;

/**
 * Requests and stores one synthesis of student tutor conversations per course.
 *
 * The window is the past three months at the moment of the request. The Dixeo API
 * reads the messages. This service stops a second request while one is running,
 * and keeps the stored text when the API reports that no new messages arrived.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tutor_synthesis_service {
    /** @var string Stored synthesis table. */
    public const TABLE = 'local_dixeo_tutor_synthesis';

    /** @var string API endpoint. */
    public const ENDPOINT = '/v1/tutor/synthesis';

    /** @var string No synthesis has been stored for the course. */
    public const STATE_EMPTY = 'empty';

    /** @var string A job is still running. */
    public const STATE_PENDING = 'pending';

    /** @var string A synthesis is stored. */
    public const STATE_READY = 'ready';

    /** @var string The latest attempt failed. A previous synthesis may still be stored. */
    public const STATE_FAILED = 'failed';

    /** @var int Matches the API limit on student ids in one request. */
    private const MAX_STUDENT_IDS = 2000;

    /** @var string How far back a synthesis reads, measured from the request. */
    private const WINDOW = '-3 months';

    /** @var client API client. */
    private client $client;

    /** @var job_service Job binding and status. */
    private job_service $jobs;

    /** @var tutor_usage_report_service Report helpers for the student list. */
    private tutor_usage_report_service $report;

    /**
     * Wire the API client, job service, and report helper.
     *
     * @param client|null $client API client.
     * @param job_service|null $jobs Job service.
     * @param tutor_usage_report_service|null $report Report service.
     */
    public function __construct(
        ?client $client = null,
        ?job_service $jobs = null,
        ?tutor_usage_report_service $report = null
    ) {
        $this->client = $client ?? new client();
        $this->jobs = $jobs ?? new job_service($this->client);
        $this->report = $report ?? new tutor_usage_report_service();
    }

    /**
     * The past three months, ending now.
     *
     * @return array{timestart: int, timeend: int}
     */
    public function current_window(): array {
        $end = time();
        $start = (new \DateTimeImmutable('@' . $end))->modify(self::WINDOW)->getTimestamp();
        return ['timestart' => $start, 'timeend' => $end];
    }

    /**
     * Sentence describing the window a synthesis covers.
     *
     * @param int $timestart Window start.
     * @param int $timeend Window end.
     * @return string
     */
    public function window_label(int $timestart, int $timeend): string {
        $format = get_string('strftimedatefullshort', 'langconfig');
        return get_string('tutor_usage_report_synthesis_window', 'local_dixeo', (object) [
            'from' => userdate($timestart, $format),
            'to' => userdate($timeend, $format),
        ]);
    }

    /**
     * Synthesis stored for a course, reconciling an in-flight job first.
     *
     * @param int $courseid Course id.
     * @return array View model for the report block.
     */
    public function get_for_course(int $courseid): array {
        $record = $this->get_record($courseid);
        if ($record && $record->status === self::STATE_PENDING) {
            $record = $this->reconcile($record);
        }
        return $this->present($record, false);
    }

    /**
     * Start a synthesis, or report that the stored one is already up to date.
     *
     * A course that already has a running job returns that job. A second click
     * does not create another one.
     *
     * @param int $courseid Course id.
     * @return array View model. unchanged is true when no new messages existed.
     */
    public function request(int $courseid): array {
        $lock = $this->lock($courseid);
        if ($lock === null) {
            $record = $this->get_record($courseid);
            if ($record && $record->status === self::STATE_PENDING) {
                return $this->present($record, false);
            }
            throw new \moodle_exception('error:tutor_synthesis_busy', 'local_dixeo');
        }

        try {
            $record = $this->get_record($courseid);
            if ($record && $record->status === self::STATE_PENDING) {
                $record = $this->reconcile($record);
                if ($record->status === self::STATE_PENDING) {
                    return $this->present($record, false);
                }
            }

            $window = $this->current_window();
            $payload = $this->payload($courseid, $window['timestart'], $window['timeend'], $record);
            $response = $this->submit($payload);
            if (!empty($response['unavailable'])) {
                $view = $this->present($record, false);
                $view['unavailable'] = true;
                return $view;
            }
            if (!empty($response['unchanged'])) {
                $record = $this->remember_window($record, $window['timestart'], $window['timeend']);
                return $this->present($record, true);
            }

            $jobid = trim((string) ($response['id'] ?? ''));
            if (!\local_dixeo_is_valid_job_uuid($jobid)) {
                throw new \moodle_exception('error:unexpected', 'local_dixeo');
            }

            $this->jobs->bind_submitted_job(
                $jobid,
                self::ENDPOINT,
                $payload,
                'block_dixeo_tutor',
                job_access_mode::COURSE_SHARED
            );
            $record = $this->save_pending(
                $courseid,
                $window['timestart'],
                $window['timeend'],
                $jobid,
                $record
            );
            return $this->present($record, false);
        } finally {
            $lock->release();
        }
    }

    /**
     * Send the synthesis request.
     *
     * A 404 means this API has no synthesis route. The block shows that in the card.
     *
     * @param array $payload Request body.
     * @return array
     */
    private function submit(array $payload): array {
        try {
            return $this->client->post(self::ENDPOINT, $payload);
        } catch (api_exception $e) {
            if ($e->get_http_status() === 404) {
                return ['unavailable' => true];
            }
            throw $e;
        }
    }

    /**
     * Stored synthesis for this course and namespace.
     *
     * @param int $courseid Course id.
     * @return \stdClass|null
     */
    private function get_record(int $courseid): ?\stdClass {
        global $DB;

        $record = $DB->get_record(self::TABLE, [
            'courseid' => $courseid,
            'namespace' => $this->namespace(),
        ]);
        return $record ?: null;
    }

    /**
     * Request body for one course window.
     *
     * @param int $courseid Course id.
     * @param int $timestart Window start.
     * @param int $timeend Window end.
     * @param \stdClass|null $record Stored row, when a synthesis already exists.
     * @return array
     */
    private function payload(int $courseid, int $timestart, int $timeend, ?\stdClass $record): array {
        $roleids = tutor_usage_report_service::get_roleids_for_scope(
            tutor_usage_report_service::ROLE_SCOPE_STUDENTS
        );
        $userids = $this->report->get_in_scope_userids(
            tutor_usage_report_service::LEVEL_COURSE,
            $courseid,
            0,
            $roleids
        );
        $userids = array_slice(array_map('strval', $userids), 0, self::MAX_STUDENT_IDS);

        $payload = [
            'courseId' => (string) $courseid,
            'namespace' => $this->namespace(),
            'from' => $this->iso($timestart),
            'to' => $this->iso($timeend),
            'userIds' => $userids,
            'language' => current_language(),
        ];
        if ($record && $this->has_stored_synthesis($record)) {
            $payload['previousMessageCount'] = (int) $record->messagecount;
            if ($record->latestmessageat !== null && $record->latestmessageat !== '') {
                $payload['previousLatestMessageAt'] = (string) $record->latestmessageat;
            }
        }
        return $payload;
    }

    /**
     * A completed synthesis, including one that covered no messages.
     *
     * A failed first attempt leaves the summary null, so the retry does not send
     * a zero watermark and get an unchanged response for a window that has messages.
     *
     * @param \stdClass $record Stored row.
     * @return bool
     */
    private function has_stored_synthesis(\stdClass $record): bool {
        if ($record->status === self::STATE_READY) {
            return true;
        }
        return $record->summary !== null;
    }

    /**
     * Apply the remote job status onto the stored row.
     *
     * @param \stdClass $record Pending row.
     * @return \stdClass Row after the job status has been applied.
     */
    private function reconcile(\stdClass $record): \stdClass {
        $jobid = trim((string) ($record->jobid ?? ''));
        if (!\local_dixeo_is_valid_job_uuid($jobid)) {
            return $this->mark_failed($record);
        }

        try {
            $status = $this->jobs->get_job_status($jobid, (int) $record->courseid, $this->requester_id());
        } catch (\Throwable $e) {
            return $this->mark_failed($record);
        }

        if ($status->is_completed()) {
            return $this->store_result($record, $status->result ?? []);
        }
        if ($status->status === job_status::STATUS_PENDING || $status->status === job_status::STATUS_PROCESSING) {
            return $record;
        }
        return $this->mark_failed($record);
    }

    /**
     * Write a completed job result onto the stored row.
     *
     * @param \stdClass $record Pending row.
     * @param array $result Job result.
     * @return \stdClass
     */
    private function store_result(\stdClass $record, array $result): \stdClass {
        global $DB;

        if (!empty($result['empty'])) {
            $record->status = self::STATE_READY;
            $record->summary = null;
            $record->messagecount = 0;
            $record->latestmessageat = null;
            $record->truncated = 0;
            $record->timemodified = time();
            $DB->update_record(self::TABLE, $record);
            return $record;
        }

        $summary = $result['summary'] ?? null;
        if (!is_string($summary)) {
            return $this->mark_failed($record);
        }

        $summary = trim($summary);
        $record->status = self::STATE_READY;
        $record->summary = $summary === '' ? null : $summary;
        $record->messagecount = max(0, (int) ($result['messageCount'] ?? 0));
        $latest = $result['latestMessageAt'] ?? null;
        $record->latestmessageat = is_string($latest) && $latest !== '' ? $latest : null;
        $record->truncated = !empty($result['truncated']) ? 1 : 0;
        $record->timemodified = time();
        $DB->update_record(self::TABLE, $record);
        return $record;
    }

    /**
     * Mark the stored row failed and clear its job id.
     *
     * @param \stdClass $record Row to mark failed.
     * @return \stdClass
     */
    private function mark_failed(\stdClass $record): \stdClass {
        global $DB;

        $record->status = self::STATE_FAILED;
        $record->jobid = null;
        $record->timemodified = time();
        $DB->update_record(self::TABLE, $record);
        return $record;
    }

    /**
     * The checked window had the same messages, so the stored text now covers it.
     *
     * @param \stdClass|null $record Stored row.
     * @param int $timestart Window start.
     * @param int $timeend Window end.
     * @return \stdClass|null
     */
    private function remember_window(?\stdClass $record, int $timestart, int $timeend): ?\stdClass {
        global $DB;

        if ($record === null) {
            return null;
        }
        $record->timestart = $timestart;
        $record->timeend = $timeend;
        $record->timemodified = time();
        $DB->update_record(self::TABLE, $record);
        return $record;
    }

    /**
     * Store the window and job id while generation is in flight.
     *
     * @param int $courseid Course id.
     * @param int $timestart Window start.
     * @param int $timeend Window end.
     * @param string $jobid Remote job id.
     * @param \stdClass|null $existing Existing row for this course.
     * @return \stdClass
     */
    private function save_pending(
        int $courseid,
        int $timestart,
        int $timeend,
        string $jobid,
        ?\stdClass $existing
    ): \stdClass {
        global $DB, $USER;

        $now = time();
        if ($existing) {
            $existing->status = self::STATE_PENDING;
            $existing->jobid = $jobid;
            $existing->timestart = $timestart;
            $existing->timeend = $timeend;
            $existing->userid = (int) ($USER->id ?? 0);
            $existing->timemodified = $now;
            $DB->update_record(self::TABLE, $existing);
            return $existing;
        }

        $record = (object) [
            'courseid' => $courseid,
            'namespace' => $this->namespace(),
            'timestart' => $timestart,
            'timeend' => $timeend,
            'status' => self::STATE_PENDING,
            'jobid' => $jobid,
            'summary' => null,
            'messagecount' => 0,
            'latestmessageat' => null,
            'truncated' => 0,
            'userid' => (int) ($USER->id ?? 0),
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record(self::TABLE, $record);
        return $record;
    }

    /**
     * View model for the synthesis block.
     *
     * @param \stdClass|null $record Stored row.
     * @param bool $unchanged True when the API reported no new messages.
     * @return array
     */
    private function present(?\stdClass $record, bool $unchanged): array {
        $window = $record
            ? ['timestart' => (int) $record->timestart, 'timeend' => (int) $record->timeend]
            : $this->current_window();
        $label = $this->window_label($window['timestart'], $window['timeend']);

        if ($record === null) {
            return [
                'state' => self::STATE_EMPTY,
                'unchanged' => $unchanged,
                'summary' => '',
                'truncated' => false,
                'empty' => false,
                'jobid' => '',
                'window' => $label,
                'unavailable' => false,
            ];
        }

        $summary = (string) ($record->summary ?? '');
        return [
            'state' => (string) $record->status,
            'unchanged' => $unchanged,
            'summary' => $this->clean_summary($summary),
            'truncated' => (int) $record->truncated === 1,
            'empty' => $record->status === self::STATE_READY && $summary === '' && (int) $record->messagecount === 0,
            'jobid' => (string) ($record->jobid ?? ''),
            'window' => $label,
            'unavailable' => false,
        ];
    }

    /**
     * Keep the synthesis tags and drop anything else the API might return.
     *
     * @param string $summary HTML stored from the API.
     * @return string
     */
    private function clean_summary(string $summary): string {
        $summary = trim($summary);
        if ($summary === '') {
            return '';
        }
        return trim(clean_text($summary, FORMAT_HTML));
    }

    /**
     * Lock one course so two requests cannot start two jobs.
     *
     * @param int $courseid Course id.
     * @return \core\lock\lock|null
     */
    private function lock(int $courseid): ?\core\lock\lock {
        $factory = \core\lock\lock_config::get_lock_factory('local_dixeo_tutor_synthesis');
        $key = $courseid . ':' . sha1($this->namespace());
        $lock = $factory->get_lock($key, 10);
        return $lock ?: null;
    }

    /**
     * Configured Dixeo namespace for this site.
     *
     * @return string
     */
    private function namespace(): string {
        global $CFG;
        require_once($CFG->dirroot . '/local/dixeo/lib.php');
        return \local_dixeo_get_configured_namespace();
    }

    /**
     * UTC instant for the synthesis request.
     *
     * @param int $timestamp Unix timestamp.
     * @return string ISO 8601 in UTC.
     */
    private function iso(int $timestamp): string {
        return gmdate('Y-m-d\TH:i:s\+00:00', $timestamp);
    }

    /**
     * User id sent as the synthesis requester.
     *
     * @return int
     */
    private function requester_id(): int {
        global $USER;
        return (int) ($USER->id ?? 0);
    }
}
