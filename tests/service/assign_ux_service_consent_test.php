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
 * Tests for the Terms of Use gate on assign_ux_service sends.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\api\client;
use local_dixeo\dto\operation_result;
use local_dixeo\exception\consent_required_exception;
use local_dixeo\external\service_factory;
use local_dixeo\repository\job_repository;
use local_dixeo\service\assign_submission_reader;
use local_dixeo\service\assign_ux_service;
use local_dixeo\service\job_service;

/**
 * A student's work is sent only when the acting user and the student have accepted the current terms.
 *
 * @covers \local_dixeo\service\assign_ux_service
 */
final class assign_ux_service_consent_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass Assign module record. */
    private \stdClass $assign;

    /** @var \stdClass Student. */
    private \stdClass $student;

    /** @var \stdClass Teacher. */
    private \stdClass $teacher;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->assign = $generator->create_module('assign', [
            'course' => $this->course->id,
            'assignsubmission_onlinetext_enabled' => 1,
            'assignsubmission_file_enabled' => 0,
        ]);
        $this->student = $generator->create_and_enrol($this->course, 'student');
        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');
    }

    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * A student without current terms is refused on every submit method, and nothing is read or posted.
     */
    public function test_acting_user_without_terms_is_refused_before_anything_is_sent(): void {
        $this->setUser($this->student);
        $service = $this->service_expecting_no_send();

        foreach ($this->submit_calls($service, (int) $this->student->id) as $name => $call) {
            try {
                $call();
                $this->fail("$name must refuse a student without current terms.");
            } catch (consent_required_exception $e) {
                $this->assertSame('error:consentrequired', $e->errorcode, $name);
                $this->assertSame('local_dixeo', $e->module, $name);
            }
        }
    }

    /**
     * A teacher with current terms cannot send the work of a student who has not accepted them.
     */
    public function test_teacher_with_terms_cannot_send_work_of_student_without_terms(): void {
        $this->accept_terms((int) $this->teacher->id);
        $this->setUser($this->teacher);
        $service = $this->service_expecting_no_send();

        foreach ($this->submit_calls($service, (int) $this->student->id) as $name => $call) {
            try {
                $call();
                $this->fail("$name must refuse a student without current terms.");
            } catch (consent_required_exception $e) {
                $this->assertSame('error:consent_subject_not_current', $e->errorcode, $name);
                $this->assertSame('local_dixeo', $e->module, $name);
            }
        }
    }

    /**
     * A teacher without current terms is refused even when the student has accepted them.
     */
    public function test_teacher_without_terms_is_refused_when_student_accepted(): void {
        $this->accept_terms((int) $this->student->id);
        $this->setUser($this->teacher);
        $service = $this->service_expecting_no_send();

        $this->expectException(consent_required_exception::class);
        $this->expectExceptionMessage(get_string('error:consentrequired', 'local_dixeo'));
        $service->submit_grade((int) $this->course->id, (int) $this->assign->cmid, (int) $this->student->id);
    }

    /**
     * When both have accepted, the grading request is posted.
     */
    public function test_teacher_and_student_with_terms_can_send(): void {
        $this->accept_terms((int) $this->teacher->id, (int) $this->student->id);
        $this->setUser($this->teacher);
        $this->create_submission();

        $job = $this->createMock(job_service::class);
        $job->expects($this->once())
            ->method('submit_job')
            ->willReturn(operation_result::pending('job-consented', 'pending', 0));
        $service = new assign_ux_service($job, null, null, 'test-ns');

        $result = $service->submit_grade((int) $this->course->id, (int) $this->assign->cmid, (int) $this->student->id);

        $this->assertSame('job-consented', $result->jobid);
    }

    /**
     * With Dixeo off for the course, the switch error is reported rather than a consent error.
     */
    public function test_dixeo_off_reports_the_switch_not_consent(): void {
        global $DB;

        $this->setUser($this->student);
        $this->create_submission();
        service_factory::get_settings_config()->set(
            'local_dixeo',
            'enabled',
            \context_course::instance($this->course->id),
            '0',
            false
        );
        $client = $this->createMock(client::class);
        $client->expects($this->never())->method('post');
        $client->expects($this->never())->method('post_multipart');
        $service = new assign_ux_service(new job_service($client, null, new job_repository()), null, null, 'test-ns');

        try {
            $service->submit_review((int) $this->course->id, (int) $this->assign->cmid, (int) $this->student->id);
            $this->fail('A send while Dixeo is off must stop.');
        } catch (consent_required_exception $e) {
            $this->fail('Dixeo off must not report a consent error.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:dixeo_disabled', $e->errorcode);
        }
        $this->assertSame(0, $DB->count_records('local_dixeo_jobs'));
    }

    /**
     * A service whose job service and submission reader must never be used.
     *
     * @return assign_ux_service
     */
    private function service_expecting_no_send(): assign_ux_service {
        $job = $this->createMock(job_service::class);
        $job->expects($this->never())->method('submit_job');
        $job->expects($this->never())->method('submit_and_wait');
        $reader = $this->createMock(assign_submission_reader::class);
        $reader->expects($this->never())->method('get_assignment_context');
        $reader->expects($this->never())->method('get_submission_data');

        return new assign_ux_service($job, $reader, null, 'test-ns');
    }

    /**
     * One call per public submit method.
     *
     * @param assign_ux_service $service Service under test.
     * @param int $studentid Student whose work is sent.
     * @return array<string, callable>
     */
    private function submit_calls(assign_ux_service $service, int $studentid): array {
        $courseid = (int) $this->course->id;
        $cmid = (int) $this->assign->cmid;
        $final = [
            'initialConfidence' => 40.0,
            'questions' => [['id' => 'q0', 'type' => 'open', 'text' => 'Why?']],
            'answers' => ['q0' => 'Because'],
        ];

        return [
            'submit_review' => fn() => $service->submit_review($courseid, $cmid, $studentid),
            'submit_review_and_wait' => fn() => $service->submit_review_and_wait($courseid, $cmid, $studentid),
            'submit_grade' => fn() => $service->submit_grade($courseid, $cmid, $studentid),
            'submit_grade_and_wait' => fn() => $service->submit_grade_and_wait($courseid, $cmid, $studentid),
            'submit_authorship' => fn() => $service->submit_authorship(
                $courseid,
                $cmid,
                $studentid,
                assign_ux_service::MODE_FINAL,
                $final
            ),
            'submit_authorship_and_wait' => fn() => $service->submit_authorship_and_wait(
                $courseid,
                $cmid,
                $studentid,
                assign_ux_service::MODE_CONFIDENCE
            ),
        ];
    }

    /**
     * Record acceptance of the current terms for each user.
     *
     * @param int ...$userids User ids.
     */
    private function accept_terms(int ...$userids): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_dixeo');
        foreach ($userids as $userid) {
            $generator->create_terms_acceptance(['userid' => $userid]);
        }
    }

    /**
     * Give the student a latest online text submission.
     */
    private function create_submission(): void {
        global $DB;

        $now = time();
        $submissionid = $DB->insert_record('assign_submission', (object) [
            'assignment' => $this->assign->id,
            'userid' => $this->student->id,
            'timecreated' => $now,
            'timemodified' => $now,
            'status' => 'draft',
            'groupid' => 0,
            'attemptnumber' => 0,
            'latest' => 1,
        ]);
        $DB->insert_record('assignsubmission_onlinetext', (object) [
            'assignment' => $this->assign->id,
            'submission' => $submissionid,
            'onlinetext' => '<p>Essay</p>',
            'onlineformat' => FORMAT_HTML,
        ]);
    }
}
