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
 * A synthesis covers the period the report page shows. The row keeps the last
 * result with the period it covers, and the in-flight job with its own period, so
 * the previous synthesis stays readable until the new one is ready or has failed.
 * The pending columns keep the period of the latest attempt, so a running or
 * failed job is only reported against that period. The watermark that lets the
 * API answer "unchanged" is only sent for the same period. The API decides whether enough students wrote for an anonymous summary;
 * this service only reads the counts it returns.
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

    /** @var string No synthesis has been requested for the course. */
    public const STATE_NONE = 'none';

    /** @var string A job is still running. */
    public const STATE_PENDING = 'pending';

    /** @var string A synthesis is stored. */
    public const STATE_READY = 'ready';

    /** @var string The latest attempt failed. A previous synthesis may still be stored. */
    public const STATE_FAILED = 'failed';

    /** @var string The API reported no new message for the same period. */
    public const FEEDBACK_UNCHANGED = 'unchanged';

    /** @var string The synthesis service cannot be reached. */
    public const FEEDBACK_UNAVAILABLE = 'unavailable';

    /** @var int Matches the API limit on student ids in one request. */
    private const MAX_STUDENT_IDS = 2000;

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
     * Synthesis stored for a course, reconciling an in-flight job first.
     *
     * @param int $courseid Course id.
     * @param int $timestart Start of the report period.
     * @param int $timeend End of the report period.
     * @return array View model for the report block.
     */
    public function get_for_course(int $courseid, int $timestart, int $timeend): array {
        $record = $this->get_record($courseid);
        if ($record && $record->status === self::STATE_PENDING) {
            $record = $this->reconcile($record);
        }
        return $this->present($record, $timestart, $timeend);
    }

    /**
     * Start a synthesis for the period, or report that the stored one is already up to date.
     *
     * A course that already has a running job returns that job. A second click
     * does not create another one.
     *
     * @param int $courseid Course id.
     * @param int $timestart Start of the report period.
     * @param int $timeend End of the report period.
     * @return array View model.
     */
    public function request(int $courseid, int $timestart, int $timeend): array {
        global $DB;

        $lock = $this->lock($courseid);
        if ($lock === null) {
            $record = $this->get_record($courseid);
            if ($record && $record->status === self::STATE_PENDING) {
                return $this->present($record, $timestart, $timeend);
            }
            throw new \moodle_exception('error:tutor_synthesis_busy', 'local_dixeo');
        }

        try {
            $record = $this->get_record($courseid);
            if ($record && $record->status === self::STATE_PENDING) {
                $record = $this->reconcile($record);
                if ($record->status === self::STATE_PENDING) {
                    return $this->present($record, $timestart, $timeend);
                }
            }

            $userids = $this->student_ids($courseid);
            $payload = $this->payload($courseid, $timestart, $timeend, $userids, $record);
            $response = $this->submit($payload);
            if (!empty($response['unavailable'])) {
                return $this->present($record, $timestart, $timeend, self::FEEDBACK_UNAVAILABLE);
            }
            if (!empty($response['unchanged'])) {
                // The stored result is confirmed for this period, which clears an earlier failure.
                if ($record && $record->status !== self::STATE_READY) {
                    $record->status = self::STATE_READY;
                    $record = $this->clear_pending($record);
                    $DB->update_record(self::TABLE, $record);
                }
                return $this->present($record, $timestart, $timeend, self::FEEDBACK_UNCHANGED);
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
            $record = $this->save_pending($courseid, $timestart, $timeend, count($userids), $jobid, $record);
            return $this->present($record, $timestart, $timeend);
        } finally {
            $lock->release();
        }
    }

    /**
     * View model for the synthesis block, shared by the page and the web services.
     *
     * @param \stdClass|null $record Stored row.
     * @param int $timestart Start of the report period.
     * @param int $timeend End of the report period.
     * @param string $feedback Empty, or one of the FEEDBACK_ constants.
     * @return array
     */
    public function present(?\stdClass $record, int $timestart, int $timeend, string $feedback = ''): array {
        $state = $record ? (string) $record->status : self::STATE_NONE;
        $unavailable = $feedback === self::FEEDBACK_UNAVAILABLE;
        $hasresult = $record !== null && $this->has_result($record);
        $stale = $hasresult && !$this->covers($record, $timestart, $timeend);
        $pending = $state === self::STATE_PENDING;
        $attempthere = $record !== null && (int) $record->pendingtimestart === $timestart
            && (int) $record->pendingtimeend === $timeend;
        $failed = $state === self::STATE_FAILED && $attempthere;
        $unchanged = $feedback === self::FEEDBACK_UNCHANGED;
        $pendinglabel = '';
        if ($pending) {
            $pendinglabel = $attempthere
                ? get_string('tutor_usage_report_synthesis_generating', 'local_dixeo')
                : $this->period_label(
                    'tutor_usage_report_synthesis_pending_other',
                    (int) $record->pendingtimestart,
                    (int) $record->pendingtimeend
                );
        }
        $buttonkey = 'tutor_usage_report_synthesis_update';
        if (!$hasresult) {
            $buttonkey = 'tutor_usage_report_synthesis_button';
        } else if ($stale) {
            $buttonkey = 'tutor_usage_report_synthesis_generate_period';
        }

        $view = [
            'state' => $state,
            'jobid' => (string) ($record->jobid ?? ''),
            'hasstatus' => $pending || $failed || $unchanged || $unavailable,
            'pending' => $pending,
            'pendinglabel' => $pendinglabel,
            'failed' => $failed,
            'unchanged' => $unchanged,
            'unavailable' => $unavailable,
            'showintro' => !$hasresult && !$pending && !$failed && !$unavailable,
            'hasresult' => $hasresult,
            'stale' => $stale,
            'stalelabel' => $stale
                ? $this->period_label('tutor_usage_report_synthesis_stale', (int) $record->timestart, (int) $record->timeend)
                : '',
            'nostudent' => false,
            'toofew' => false,
            'hascoverage' => false,
            'hasshare' => false,
            'coveragelabel' => '',
            'coveragepercent' => 0,
            'coveragelow' => false,
            'messagelabel' => '',
            'hassummary' => false,
            'summary' => '',
            'truncated' => false,
            'buttonlabel' => get_string($buttonkey, 'local_dixeo'),
        ];
        if (!$hasresult) {
            return $view;
        }

        $students = (int) $record->studentcount;
        $enrolled = (int) $record->enrolledcount;
        $summary = $this->clean_summary((string) ($record->summary ?? ''));
        $view['nostudent'] = $students === 0;
        $view['toofew'] = $students > 0 && $summary === '';
        $view['hassummary'] = $summary !== '';
        $view['summary'] = $summary;
        $view['truncated'] = $summary !== '' && (int) $record->truncated === 1;
        if ($students === 0) {
            return $view;
        }

        $counts = (object) ['students' => $students, 'enrolled' => $enrolled];
        $view['hascoverage'] = true;
        $view['hasshare'] = $enrolled > 0;
        $view['coveragelabel'] = $enrolled > 0
            ? get_string('tutor_usage_report_synthesis_coverage', 'local_dixeo', $counts)
            : get_string('tutor_usage_report_synthesis_coverage_students', 'local_dixeo', $students);
        $view['coveragepercent'] = $enrolled > 0 ? min(100, (int) round($students * 100 / $enrolled)) : 0;
        $view['coveragelow'] = $enrolled > 0 && $students * 2 < $enrolled;
        $view['messagelabel'] = get_string(
            'tutor_usage_report_synthesis_messages',
            'local_dixeo',
            (int) $record->messagecount
        );
        return $view;
    }

    /**
     * Sentence naming another period than the one the report shows.
     *
     * @param string $identifier String with from and to placeholders.
     * @param int $timestart Period start.
     * @param int $timeend Period end.
     * @return string
     */
    private function period_label(string $identifier, int $timestart, int $timeend): string {
        $format = get_string('strftimedate', 'langconfig');
        return get_string($identifier, 'local_dixeo', (object) [
            'from' => userdate($timestart, $format),
            'to' => userdate($timeend, $format),
        ]);
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
     * Ids of the students in the course, as many as one request accepts.
     *
     * @param int $courseid Course id.
     * @return string[]
     */
    private function student_ids(int $courseid): array {
        $roleids = tutor_usage_report_service::get_roleids_for_scope(
            tutor_usage_report_service::ROLE_SCOPE_STUDENTS
        );
        $userids = $this->report->get_in_scope_userids(
            tutor_usage_report_service::LEVEL_COURSE,
            $courseid,
            0,
            $roleids
        );
        return array_slice(array_map('strval', $userids), 0, self::MAX_STUDENT_IDS);
    }

    /**
     * Request body for one course period.
     *
     * @param int $courseid Course id.
     * @param int $timestart Period start.
     * @param int $timeend Period end.
     * @param string[] $userids Student ids.
     * @param \stdClass|null $record Stored row, when a synthesis already exists.
     * @return array
     */
    private function payload(int $courseid, int $timestart, int $timeend, array $userids, ?\stdClass $record): array {
        $payload = [
            'courseId' => (string) $courseid,
            'namespace' => $this->namespace(),
            'from' => $this->iso($timestart),
            'to' => $this->iso($timeend),
            'userIds' => $userids,
            'language' => current_language(),
        ];
        // The watermark only says something about the period it was measured on.
        if ($record && $this->has_result($record) && $this->covers($record, $timestart, $timeend)) {
            $payload['previousMessageCount'] = (int) $record->messagecount;
            if ($record->latestmessageat !== null && $record->latestmessageat !== '') {
                $payload['previousLatestMessageAt'] = (string) $record->latestmessageat;
            }
        }
        return $payload;
    }

    /**
     * Whether a completed result is stored, whatever the latest attempt did.
     *
     * @param \stdClass $record Stored row.
     * @return bool
     */
    private function has_result(\stdClass $record): bool {
        return (int) $record->timeend > 0;
    }

    /**
     * Whether the stored result covers exactly this period.
     *
     * @param \stdClass $record Stored row.
     * @param int $timestart Period start.
     * @param int $timeend Period end.
     * @return bool
     */
    private function covers(\stdClass $record, int $timestart, int $timeend): bool {
        return (int) $record->timestart === $timestart && (int) $record->timeend === $timeend;
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
     * Write a completed job result onto the stored row, with the period it covers.
     *
     * An empty summary with students is a valid result: the API declined to
     * summarise too few students.
     *
     * @param \stdClass $record Pending row.
     * @param array $result Job result.
     * @return \stdClass
     */
    private function store_result(\stdClass $record, array $result): \stdClass {
        global $DB;

        $summary = $result['summary'] ?? null;
        if (!is_string($summary)) {
            return $this->mark_failed($record);
        }

        $summary = trim($summary);
        $latest = $result['latestMessageAt'] ?? null;
        $record->status = self::STATE_READY;
        $record->jobid = null;
        $record->timestart = (int) $record->pendingtimestart;
        $record->timeend = (int) $record->pendingtimeend;
        $record->enrolledcount = (int) $record->pendingenrolledcount;
        $record->summary = $summary === '' ? null : $summary;
        $record->messagecount = max(0, (int) ($result['messageCount'] ?? 0));
        $record->studentcount = max(0, (int) ($result['studentCount'] ?? 0));
        $record->latestmessageat = is_string($latest) && $latest !== '' ? $latest : null;
        $record->truncated = !empty($result['truncated']) ? 1 : 0;
        $record = $this->clear_pending($record);
        $DB->update_record(self::TABLE, $record);
        return $record;
    }

    /**
     * Mark the stored row failed and forget the job id.
     *
     * The stored result stays, and the pending period records which period failed.
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
     * Reset the in-flight job fields.
     *
     * @param \stdClass $record Row.
     * @return \stdClass
     */
    private function clear_pending(\stdClass $record): \stdClass {
        $record->pendingtimestart = 0;
        $record->pendingtimeend = 0;
        $record->pendingenrolledcount = 0;
        $record->timemodified = time();
        return $record;
    }

    /**
     * Store the job and the period it covers while generation is in flight.
     *
     * @param int $courseid Course id.
     * @param int $timestart Period start.
     * @param int $timeend Period end.
     * @param int $enrolledcount Student ids sent with the request.
     * @param string $jobid Remote job id.
     * @param \stdClass|null $existing Existing row for this course.
     * @return \stdClass
     */
    private function save_pending(
        int $courseid,
        int $timestart,
        int $timeend,
        int $enrolledcount,
        string $jobid,
        ?\stdClass $existing
    ): \stdClass {
        global $DB;

        $now = time();
        $record = $existing ?? (object) [
            'courseid' => $courseid,
            'namespace' => $this->namespace(),
            'timestart' => 0,
            'timeend' => 0,
            'summary' => null,
            'messagecount' => 0,
            'latestmessageat' => null,
            'truncated' => 0,
            'studentcount' => 0,
            'enrolledcount' => 0,
            'timecreated' => $now,
        ];
        $record->status = self::STATE_PENDING;
        $record->jobid = $jobid;
        $record->pendingtimestart = $timestart;
        $record->pendingtimeend = $timeend;
        $record->pendingenrolledcount = $enrolledcount;
        $record->userid = $this->requester_id();
        $record->timemodified = $now;
        if ($existing) {
            $DB->update_record(self::TABLE, $record);
        } else {
            $record->id = $DB->insert_record(self::TABLE, $record);
        }
        return $record;
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
