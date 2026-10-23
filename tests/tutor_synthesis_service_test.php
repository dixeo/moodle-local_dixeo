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
use local_dixeo\service\job_service;
use local_dixeo\service\tutor_synthesis_service;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/synthesis_fake_client.php');

/**
 * Course synthesis requests: one in-flight job, and no rewrite without new messages.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\service\tutor_synthesis_service
 */
final class tutor_synthesis_service_test extends \advanced_testcase {
    /** @var string Remote job id used by the fake API. */
    private const JOBID = '11111111-1111-4111-8111-111111111111';

    /**
     * The first request starts one job for the enrolled students and a second click reuses it.
     */
    public function test_request_starts_one_job_and_ignores_a_duplicate(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->course_with_student();
        $teacher = $this->getDataGenerator()->create_user();
        $this->setUser($teacher);
        set_config('namespace', 'school', 'local_dixeo');

        $api = new synthesis_fake_client([
            $this->pending_job(),
            $this->pending_job(),
        ]);
        $service = $this->service($api);

        $first = $service->request((int) $course->id);
        $second = $service->request((int) $course->id);

        $this->assertSame(tutor_synthesis_service::STATE_PENDING, $first['state']);
        $this->assertSame(self::JOBID, $first['jobid']);
        $this->assertSame($first, $second);
        $posts = array_values(array_filter($api->calls, static fn(array $call): bool => $call['method'] === 'post'));
        $this->assertCount(1, $posts);

        $payload = $posts[0]['data'];
        $this->assertSame([(string) $student->id], $payload['userIds']);
        $this->assertSame('school', $payload['namespace']);
        $this->assertArrayNotHasKey('previousMessageCount', $payload);
        $from = strtotime($payload['from']);
        $to = strtotime($payload['to']);
        $this->assertEqualsWithDelta(time(), $to, 5);
        $expectedfrom = (new \DateTimeImmutable('@' . $to))->modify('-3 months')->getTimestamp();
        $this->assertEqualsWithDelta($expectedfrom, $from, 2);

        $job = $this->db()->get_record('local_dixeo_jobs', ['jobid' => self::JOBID], '*', MUST_EXIST);
        $this->assertSame('tutor_synthesis', $job->operation);
        $this->assertSame('block_dixeo_tutor', $job->component);
        $this->assertSame('course_shared', $job->accessmode);
        $this->assertEquals($course->id, $job->courseid);
    }

    /**
     * An update with no new messages leaves the stored synthesis where it is.
     */
    public function test_update_with_no_new_messages_does_nothing_to_the_synthesis(): void {
        $this->resetAfterTest();
        [$course] = $this->course_with_student();
        $this->setUser($this->getDataGenerator()->create_user());
        set_config('namespace', 'school', 'local_dixeo');
        $this->store_ready((int) $course->id, 'Students asked about fractions.');

        $api = new synthesis_fake_client([
            ['unchanged' => true],
        ]);
        $service = $this->service($api);

        $result = $service->request((int) $course->id);

        $this->assertTrue($result['unchanged']);
        $this->assertSame(tutor_synthesis_service::STATE_READY, $result['state']);
        $this->assertSame('Students asked about fractions.', $result['summary']);
        $this->assertSame(4, $api->calls[0]['data']['previousMessageCount']);
        $this->assertSame('2026-10-21T12:00:00+00:00', $api->calls[0]['data']['previousLatestMessageAt']);

        $row = $this->db()->get_record(tutor_synthesis_service::TABLE, ['courseid' => $course->id], '*', MUST_EXIST);
        $this->assertSame('Students asked about fractions.', $row->summary);
        $this->assertSame(tutor_synthesis_service::STATE_READY, $row->status);
        $this->assertEqualsWithDelta(time(), (int) $row->timeend, 5);
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
        $service = $this->service($api);

        $result = $service->request((int) $course->id);

        $this->assertTrue($result['unavailable']);
        $this->assertFalse($result['unchanged']);
        $this->assertSame(tutor_synthesis_service::STATE_EMPTY, $result['state']);
        $this->assertSame('', $result['summary']);
        $this->assertFalse($this->db()->record_exists(tutor_synthesis_service::TABLE, ['courseid' => $course->id]));
    }

    /**
     * Polling a finished job stores the synthesis text returned by the API.
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
        $service->request((int) $course->id);

        $result = $service->get_for_course((int) $course->id);

        $this->assertSame(tutor_synthesis_service::STATE_READY, $result['state']);
        $this->assertStringContainsString('<strong>Fractions</strong>', $result['summary']);
        $this->assertStringContainsString('<ul>', $result['summary']);
        $this->assertStringNotContainsString('<script>', $result['summary']);
        $this->assertStringNotContainsString('alert(1)', $result['summary']);
        $this->assertFalse($result['truncated']);
        $this->assertFalse($result['empty']);
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
            'timestart' => 1_700_000_000,
            'timeend' => 1_700_086_399,
            'status' => tutor_synthesis_service::STATE_READY,
            'jobid' => null,
            'summary' => $summary,
            'messagecount' => 4,
            'latestmessageat' => '2026-10-21T12:00:00+00:00',
            'truncated' => 0,
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
     * @param string $summary Text the API already composed.
     * @return array
     */
    private function completed_job(string $summary = 'Students asked about fractions.'): array {
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
                'truncated' => false,
                'messageCount' => 3,
                'latestMessageAt' => '2026-10-21T12:00:00+00:00',
                'empty' => false,
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
