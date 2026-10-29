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

namespace local_dixeo;

use local_dixeo\api\exception\api_exception;
use local_dixeo\external\request_tutor_synthesis;
use local_dixeo\service\job_service;
use local_dixeo\service\tutor_synthesis_service;
use local_dixeo\service\tutor_usage_report_service;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/synthesis_fake_client.php');

/**
 * Course synthesis requests for the report period: one in-flight job, the watermark only
 * for the same period, and the states the block shows.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\service\tutor_synthesis_service
 * @covers     \local_dixeo\external\request_tutor_synthesis
 */
final class tutor_synthesis_service_test extends \advanced_testcase {
    /** @var string Remote job id used by the fake API. */
    private const JOBID = '11111111-1111-4111-8111-111111111111';

    /** @var int Report period start used by most tests. */
    private const FROM = 1_760_000_000;

    /** @var int Report period end used by most tests. */
    private const TO = 1_760_604_799;

    /**
     * The first request sends the report period, starts one job, and a second click reuses it.
     */
    public function test_request_starts_one_job_for_the_period_and_ignores_a_duplicate(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->course_with_student();
        $this->setUser($this->getDataGenerator()->create_user());
        set_config('namespace', 'school', 'local_dixeo');

        $api = new synthesis_fake_client([
            $this->pending_job(),
            $this->pending_job(),
        ]);
        $service = $this->service($api);

        $first = $service->request((int) $course->id, self::FROM, self::TO);
        $second = $service->request((int) $course->id, self::FROM, self::TO);

        $this->assertSame(tutor_synthesis_service::STATE_PENDING, $first['state']);
        $this->assertTrue($first['pending']);
        $this->assertSame(self::JOBID, $first['jobid']);
        $this->assertSame($first, $second);
        $posts = array_values(array_filter($api->calls, static fn(array $call): bool => $call['method'] === 'post'));
        $this->assertCount(1, $posts);

        $payload = $posts[0]['data'];
        $this->assertSame([(string) $student->id], $payload['userIds']);
        $this->assertSame('school', $payload['namespace']);
        $this->assertSame(self::FROM, strtotime($payload['from']));
        $this->assertSame(self::TO, strtotime($payload['to']));
        $this->assertArrayNotHasKey('previousMessageCount', $payload);

        $row = $this->db()->get_record(tutor_synthesis_service::TABLE, ['courseid' => $course->id], '*', MUST_EXIST);
        $this->assertEquals(self::FROM, $row->pendingtimestart);
        $this->assertEquals(self::TO, $row->pendingtimeend);
        $this->assertEquals(1, $row->pendingenrolledcount);
        $this->assertEquals(0, $row->timeend);

        $job = $this->db()->get_record('local_dixeo_jobs', ['jobid' => self::JOBID], '*', MUST_EXIST);
        $this->assertSame('tutor_synthesis', $job->operation);
        $this->assertSame('block_dixeo_tutor', $job->component);
        $this->assertSame('course_shared', $job->accessmode);
        $this->assertEquals($course->id, $job->courseid);
    }

    /**
     * An update for the same period sends the watermark and keeps the synthesis when nothing changed.
     */
    public function test_same_period_sends_the_watermark_and_keeps_an_unchanged_synthesis(): void {
        $this->resetAfterTest();
        [$course] = $this->course_with_student();
        $this->setUser($this->getDataGenerator()->create_user());
        set_config('namespace', 'school', 'local_dixeo');
        $this->store_ready((int) $course->id, 'Students asked about fractions.');

        $api = new synthesis_fake_client([
            ['unchanged' => true],
        ]);
        $result = $this->service($api)->request((int) $course->id, self::FROM, self::TO);

        $this->assertTrue($result['unchanged']);
        $this->assertFalse($result['stale']);
        $this->assertSame(tutor_synthesis_service::STATE_READY, $result['state']);
        $this->assertSame('Students asked about fractions.', $result['summary']);
        $this->assertSame(4, $api->calls[0]['data']['previousMessageCount']);
        $this->assertSame('2026-10-21T12:00:00+00:00', $api->calls[0]['data']['previousLatestMessageAt']);
        $this->assertSame(
            get_string('tutor_usage_report_synthesis_update', 'local_dixeo'),
            $result['buttonlabel']
        );
    }

    /**
     * Another period is stale, gets no watermark, and keeps the old synthesis until the new one is ready.
     */
    public function test_other_period_is_stale_and_replaced_when_the_new_job_completes(): void {
        $this->resetAfterTest();
        [$course] = $this->course_with_student();
        $this->setUser($this->getDataGenerator()->create_user());
        set_config('namespace', 'school', 'local_dixeo');
        $this->store_ready((int) $course->id, 'Students asked about fractions.');
        $from = self::FROM + WEEKSECS;
        $to = self::TO + WEEKSECS;

        $api = new synthesis_fake_client([
            $this->pending_job(),
            $this->pending_job(),
            $this->completed_job('Students asked about percentages.', 2, 3),
        ]);
        $service = $this->service($api);

        $before = $service->get_for_course((int) $course->id, $from, $to);
        $this->assertTrue($before['stale']);
        $this->assertNotSame('', $before['stalelabel']);
        $this->assertSame(
            get_string('tutor_usage_report_synthesis_generate_period', 'local_dixeo'),
            $before['buttonlabel']
        );

        $pending = $service->request((int) $course->id, $from, $to);
        $this->assertArrayNotHasKey('previousMessageCount', $api->calls[0]['data']);
        $this->assertTrue($pending['pending']);
        $this->assertTrue($pending['stale']);
        $this->assertSame('Students asked about fractions.', $pending['summary']);

        $stillpending = $service->get_for_course((int) $course->id, $from, $to);
        $this->assertSame('Students asked about fractions.', $stillpending['summary']);

        $ready = $service->get_for_course((int) $course->id, $from, $to);
        $this->assertSame(tutor_synthesis_service::STATE_READY, $ready['state']);
        $this->assertFalse($ready['stale']);
        $this->assertSame('Students asked about percentages.', $ready['summary']);

        $row = $this->db()->get_record(tutor_synthesis_service::TABLE, ['courseid' => $course->id], '*', MUST_EXIST);
        $this->assertEquals($from, $row->timestart);
        $this->assertEquals($to, $row->timeend);
        $this->assertEquals(1, $row->enrolledcount);
        $this->assertEquals(2, $row->studentcount);
        $this->assertEquals(0, $row->pendingtimeend);
    }

    /**
     * A failed job for another period keeps the previous synthesis and its period.
     */
    public function test_failed_job_keeps_the_previous_synthesis(): void {
        $this->resetAfterTest();
        [$course] = $this->course_with_student();
        $this->setUser($this->getDataGenerator()->create_user());
        set_config('namespace', 'school', 'local_dixeo');
        $this->store_ready((int) $course->id, 'Students asked about fractions.');
        $from = self::FROM + WEEKSECS;
        $to = self::TO + WEEKSECS;

        $failed = $this->pending_job();
        $failed['status'] = 'failed';
        $api = new synthesis_fake_client([$this->pending_job(), $failed]);
        $service = $this->service($api);
        $service->request((int) $course->id, $from, $to);

        $result = $service->get_for_course((int) $course->id, $from, $to);

        $this->assertTrue($result['failed']);
        $this->assertTrue($result['stale']);
        $this->assertSame('Students asked about fractions.', $result['summary']);
        $old = $service->get_for_course((int) $course->id, self::FROM, self::TO);
        $this->assertFalse($old['stale']);
        $this->assertFalse($old['failed'], 'A failure on another period is not shown on this one.');
        $this->assertFalse($old['hasstatus']);
    }

    /**
     * An unchanged answer for the stored period clears an earlier failure.
     */
    public function test_unchanged_answer_clears_an_earlier_failure(): void {
        $this->resetAfterTest();
        [$course] = $this->course_with_student();
        $this->setUser($this->getDataGenerator()->create_user());
        set_config('namespace', 'school', 'local_dixeo');
        $this->store_ready((int) $course->id, 'Students asked about fractions.');
        $this->db()->set_field(tutor_synthesis_service::TABLE, 'status', tutor_synthesis_service::STATE_FAILED);
        $this->db()->set_field(tutor_synthesis_service::TABLE, 'pendingtimestart', self::FROM);
        $this->db()->set_field(tutor_synthesis_service::TABLE, 'pendingtimeend', self::TO);
        $service = $this->service(new synthesis_fake_client([['unchanged' => true]]));
        $this->assertTrue($service->get_for_course((int) $course->id, self::FROM, self::TO)['failed']);

        $result = $service->request((int) $course->id, self::FROM, self::TO);

        $this->assertTrue($result['unchanged']);
        $this->assertFalse($result['failed']);
        $this->assertSame(tutor_synthesis_service::STATE_READY, $result['state']);
        $row = $this->db()->get_record(tutor_synthesis_service::TABLE, ['courseid' => $course->id], '*', MUST_EXIST);
        $this->assertSame(tutor_synthesis_service::STATE_READY, $row->status);
    }

    /**
     * A job running for another period is named as such and a click on this period reuses it.
     */
    public function test_job_for_another_period_is_named_and_not_passed_off_as_this_one(): void {
        $this->resetAfterTest();
        [$course] = $this->course_with_student();
        $this->setUser($this->getDataGenerator()->create_user());
        set_config('namespace', 'school', 'local_dixeo');
        $from = self::FROM + WEEKSECS;
        $to = self::TO + WEEKSECS;

        $api = new synthesis_fake_client([
            $this->pending_job(),
            $this->pending_job(),
            $this->pending_job(),
        ]);
        $service = $this->service($api);
        $here = $service->request((int) $course->id, self::FROM, self::TO);
        $other = $service->request((int) $course->id, $from, $to);

        $this->assertSame(get_string('tutor_usage_report_synthesis_generating', 'local_dixeo'), $here['pendinglabel']);
        $this->assertTrue($other['pending']);
        $this->assertNotSame($here['pendinglabel'], $other['pendinglabel']);
        $this->assertStringContainsString(userdate(self::FROM, get_string('strftimedate', 'langconfig')), $other['pendinglabel']);
        $posts = array_filter($api->calls, static fn(array $call): bool => $call['method'] === 'post');
        $this->assertCount(1, $posts);
    }

    /**
     * The web services accept the report's periods and nothing longer or reversed.
     */
    public function test_period_is_checked_against_the_report_limit(): void {
        request_tutor_synthesis::require_period(self::FROM, self::TO);
        request_tutor_synthesis::require_period(
            self::FROM,
            self::FROM + tutor_usage_report_service::MAX_CUSTOM_RANGE_SECONDS
        );

        $toolong = self::FROM + tutor_usage_report_service::MAX_CUSTOM_RANGE_SECONDS + 1;
        foreach ([[self::TO, self::FROM], [self::FROM, $toolong]] as $period) {
            try {
                request_tutor_synthesis::require_period($period[0], $period[1]);
                $this->fail('Period accepted: ' . implode('-', $period));
            } catch (\invalid_parameter_exception $e) {
                $this->assertStringContainsString('Invalid period', $e->getMessage());
            }
        }
    }

    /**
     * Completed results show coverage, no student, or too few students from the API counts alone.
     *
     * @dataProvider result_states_provider
     * @param string $summary Summary returned by the API.
     * @param int $students Students who wrote.
     * @param bool $nostudent Expected no-student flag.
     * @param bool $toofew Expected too-few flag.
     * @param bool $coverage Expected coverage flag.
     */
    public function test_result_states(string $summary, int $students, bool $nostudent, bool $toofew, bool $coverage): void {
        $this->resetAfterTest();
        [$course] = $this->course_with_student();
        $this->setUser($this->getDataGenerator()->create_user());
        set_config('namespace', 'school', 'local_dixeo');

        $api = new synthesis_fake_client([
            $this->pending_job(),
            $this->completed_job($summary, $students, $students * 3),
        ]);
        $service = $this->service($api);
        $service->request((int) $course->id, self::FROM, self::TO);

        $result = $service->get_for_course((int) $course->id, self::FROM, self::TO);

        $this->assertTrue($result['hasresult']);
        $this->assertSame($nostudent, $result['nostudent']);
        $this->assertSame($toofew, $result['toofew']);
        $this->assertSame($coverage, $result['hascoverage']);
        $this->assertSame($summary !== '', $result['hassummary']);
        $this->assertArrayNotHasKey('empty', $result);
    }

    /**
     * Summary and counts for each result state.
     *
     * @return array
     */
    public static function result_states_provider(): array {
        return [
            'no student' => ['', 0, true, false, false],
            'too few students' => ['', 2, false, true, true],
            'synthesis' => ['Students asked about fractions.', 4, false, false, true],
        ];
    }

    /**
     * Coverage compares the students who wrote with the enrolled students sent.
     */
    public function test_coverage_is_low_under_half_of_the_enrolled_students(): void {
        $this->resetAfterTest();
        $service = $this->service(new synthesis_fake_client([]));
        $record = (object) [
            'status' => tutor_synthesis_service::STATE_READY,
            'jobid' => null,
            'timestart' => self::FROM,
            'timeend' => self::TO,
            'summary' => 'Text',
            'messagecount' => 41,
            'truncated' => 1,
            'studentcount' => 3,
            'enrolledcount' => 25,
            'pendingtimestart' => 0,
            'pendingtimeend' => 0,
        ];

        $low = $service->present($record, self::FROM, self::TO);
        $this->assertTrue($low['coveragelow']);
        $this->assertSame(12, $low['coveragepercent']);
        $this->assertTrue($low['hasshare']);
        $this->assertTrue($low['truncated']);
        $this->assertStringContainsString('3 / 25', $low['coveragelabel']);
        $this->assertStringContainsString('41', $low['messagelabel']);

        $record->studentcount = 13;
        $this->assertFalse($service->present($record, self::FROM, self::TO)['coveragelow']);

        $record->enrolledcount = 0;
        $unknown = $service->present($record, self::FROM, self::TO);
        $this->assertFalse($unknown['hasshare']);
        $this->assertFalse($unknown['coveragelow']);
        $this->assertStringNotContainsString('/', $unknown['coveragelabel']);
    }

    /**
     * An API without the synthesis route tells the teacher the service is unavailable.
     */
    public function test_missing_endpoint_reports_that_the_service_is_unavailable(): void {
        $this->resetAfterTest();
        [$course] = $this->course_with_student();
        $this->setUser($this->getDataGenerator()->create_user());
        set_config('namespace', 'school', 'local_dixeo');

        $api = new synthesis_fake_client([
            new api_exception(
                'unknown_error',
                'No route found for "POST http://staging-api.dixeo.com/v1/tutor/synthesis"',
                404
            ),
        ]);
        $result = $this->service($api)->request((int) $course->id, self::FROM, self::TO);

        $this->assertTrue($result['unavailable']);
        $this->assertFalse($result['unchanged']);
        $this->assertFalse($result['showintro']);
        $this->assertSame(tutor_synthesis_service::STATE_NONE, $result['state']);
        $this->assertFalse($this->db()->record_exists(tutor_synthesis_service::TABLE, ['courseid' => $course->id]));
    }

    /**
     * Polling a finished job stores the sanitised synthesis text returned by the API.
     */
    public function test_get_stores_a_completed_synthesis(): void {
        $this->resetAfterTest();
        [$course] = $this->course_with_student();
        $this->setUser($this->getDataGenerator()->create_user());
        set_config('namespace', 'school', 'local_dixeo');

        $summary = '<p>Students asked about fractions.</p>'
            . '<script>alert(1)</script>'
            . '<ul><li><strong>Fractions</strong>: Several students asked how to simplify.</li></ul>';
        $api = new synthesis_fake_client([
            $this->pending_job(),
            $this->completed_job($summary),
        ]);
        $service = $this->service($api);
        $service->request((int) $course->id, self::FROM, self::TO);

        $result = $service->get_for_course((int) $course->id, self::FROM, self::TO);

        $this->assertSame(tutor_synthesis_service::STATE_READY, $result['state']);
        $this->assertStringContainsString('<strong>Fractions</strong>', $result['summary']);
        $this->assertStringContainsString('<ul>', $result['summary']);
        $this->assertStringNotContainsString('<script>', $result['summary']);
        $this->assertStringNotContainsString('alert(1)', $result['summary']);
        $this->assertFalse($result['truncated']);
    }

    /**
     * Synthesis service backed by the queued client.
     *
     * @param synthesis_fake_client $api Queued API client.
     * @return tutor_synthesis_service
     */
    private function service(synthesis_fake_client $api): tutor_synthesis_service {
        return new tutor_synthesis_service($api, new job_service($api));
    }

    /**
     * Course with one enrolled student.
     *
     * @return array{0: \stdClass, 1: \stdClass}
     */
    private function course_with_student(): array {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $roleid = $this->db()->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $roleid);
        return [$course, $student];
    }

    /**
     * Insert a ready synthesis row.
     *
     * @param int $courseid Course id.
     * @param string $summary Stored summary.
     */
    private function store_ready(int $courseid, string $summary): void {
        $now = time();
        $this->db()->insert_record(tutor_synthesis_service::TABLE, (object) [
            'courseid' => $courseid,
            'namespace' => 'school',
            'timestart' => self::FROM,
            'timeend' => self::TO,
            'status' => tutor_synthesis_service::STATE_READY,
            'jobid' => null,
            'summary' => $summary,
            'messagecount' => 4,
            'latestmessageat' => '2026-10-21T12:00:00+00:00',
            'truncated' => 0,
            'studentcount' => 4,
            'enrolledcount' => 10,
            'userid' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Queued pending job response.
     *
     * @return array
     */
    private function pending_job(): array {
        return [
            'id' => self::JOBID,
            'type' => 'tutor_synthesis',
            'status' => 'pending',
            'progress' => 0,
            'createdAt' => '2026-10-22T12:00:00+00:00',
            'updatedAt' => '2026-10-22T12:00:00+00:00',
        ];
    }

    /**
     * Queued completed job response.
     *
     * @param string $summary Text the API already composed. Empty when it declined.
     * @param int $students Students who wrote in the period.
     * @param int $messages Student messages in the period.
     * @return array
     */
    private function completed_job(
        string $summary = 'Students asked about fractions.',
        int $students = 3,
        int $messages = 3
    ): array {
        return [
            'id' => self::JOBID,
            'type' => 'tutor_synthesis',
            'status' => 'completed',
            'progress' => 100,
            'createdAt' => '2026-10-22T12:00:00+00:00',
            'updatedAt' => '2026-10-22T12:01:00+00:00',
            'completedAt' => '2026-10-22T12:01:00+00:00',
            'result' => [
                'summary' => $summary,
                'messageCount' => $messages,
                'studentCount' => $students,
                'latestMessageAt' => '2026-10-21T12:00:00+00:00',
                'truncated' => false,
            ],
        ];
    }

    /**
     * Moodle database used by this test.
     *
     * @return \moodle_database
     */
    private function db(): \moodle_database {
        global $DB;
        return $DB;
    }
}
