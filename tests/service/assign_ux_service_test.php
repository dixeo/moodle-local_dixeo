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
 * Tests for assign_ux_service submit payloads and result mapping.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\dto\operation_result;
use local_dixeo\external\service_factory;
use local_dixeo\service\assign_submission_reader;
use local_dixeo\service\assign_ux_service;
use local_dixeo\service\job_service;

/**
 * Unit tests for {@see assign_ux_service}.
 *
 * @covers \local_dixeo\service\assign_ux_service
 */
final class assign_ux_service_test extends \advanced_testcase {
    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    public function test_submit_review_posts_camelcase_payload(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'name' => 'Essay 1',
            'intro' => 'Write an essay',
            'assignsubmission_onlinetext_enabled' => 1,
            'assignsubmission_file_enabled' => 0,
        ]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->create_onlinetext_submission((int) $assign->id, (int) $student->id, '<p>My draft</p>');

        $mockjob = $this->createMock(job_service::class);
        $mockjob->expects($this->once())
            ->method('submit_job')
            ->with(
                '/v1/assign/review',
                $this->callback(function (array $payload) use ($course, $assign): bool {
                    return ($payload['name'] ?? '') === 'Essay 1'
                        && ($payload['gradingMethod'] ?? '') === 'simple'
                        && str_contains((string) ($payload['submissionText'] ?? ''), 'My draft')
                        && ($payload['courseId'] ?? '') === (string) $course->id
                        && isset($payload['namespace'])
                        && !isset($payload['files'])
                        && is_array($payload['criteria'] ?? null)
                        && !isset($payload['authorship']);
                }),
                'plagiarism_dixeo',
                $this->callback(function ($meta) use ($assign): bool {
                    return $meta instanceof \local_dixeo\dto\job_binding_metadata
                        && (int) $meta->cmid === (int) $assign->cmid;
                })
            )
            ->willReturn(operation_result::pending('job-review-1', 'pending', 0));

        $service = new assign_ux_service($mockjob, null, null, 'test-ns');
        $result = $service->submit_review((int) $course->id, (int) $assign->cmid, (int) $student->id);

        $this->assertSame('job-review-1', $result->jobid);
    }

    public function test_submit_grade_sends_pdf_submission_as_file(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'name' => 'Graded essay',
            'assignsubmission_onlinetext_enabled' => 0,
            'assignsubmission_file_enabled' => 1,
            'assignsubmission_file_maxfiles' => 1,
            'assignsubmission_file_maxsizebytes' => 1048576,
        ]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->create_file_submission($assign, (int) $student->id, 'essay.pdf');

        $mockjob = $this->createMock(job_service::class);
        $mockjob->expects($this->once())
            ->method('submit_job')
            ->with(
                '/v1/assign/grade',
                $this->callback(function (array $payload): bool {
                    return ($payload['name'] ?? '') === 'Graded essay'
                        && ($payload['submissionText'] ?? null) === ''
                        && !isset($payload['authorship'])
                        && !isset($payload['files']);
                }),
                'plagiarism_dixeo',
                $this->anything(),
                $this->anything(),
                $this->callback(function (array $files): bool {
                    return count($files) === 1 && $files[0]->get_filename() === 'essay.pdf';
                })
            )
            ->willReturn(operation_result::pending('job-grade-1', 'pending', 0));

        $service = new assign_ux_service($mockjob, null, null, 'test-ns');
        $service->submit_grade((int) $course->id, (int) $assign->cmid, (int) $student->id);
    }

    public function test_submit_authorship_confidence_payload(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'name' => 'Authorship essay',
            'assignsubmission_onlinetext_enabled' => 1,
            'assignsubmission_file_enabled' => 0,
        ]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->create_onlinetext_submission((int) $assign->id, (int) $student->id, 'Original work');

        $mockjob = $this->createMock(job_service::class);
        $mockjob->expects($this->once())
            ->method('submit_job')
            ->with(
                '/v1/assign/authorship/confidence',
                $this->callback(function (array $payload): bool {
                    return !isset($payload['mode'], $payload['name'])
                        && str_contains((string) ($payload['submissionText'] ?? ''), 'Original work')
                        && isset($payload['courseId']);
                }),
                'plagiarism_dixeo',
                $this->anything()
            )
            ->willReturn(operation_result::pending('job-auth-1', 'pending', 0));

        $service = new assign_ux_service($mockjob, null, null, 'test-ns');
        $service->submit_authorship(
            (int) $course->id,
            (int) $assign->cmid,
            (int) $student->id,
            assign_ux_service::MODE_CONFIDENCE
        );
    }

    public function test_submit_authorship_final_skips_submission_files(): void {
        $this->resetAfterTest();

        $mockjob = $this->createMock(job_service::class);
        $mockjob->expects($this->once())
            ->method('submit_job')
            ->with(
                '/v1/assign/authorship/evaluation',
                $this->callback(function (array $payload): bool {
                    return !isset($payload['mode'])
                        && ($payload['initialConfidence'] ?? null) === 70.0
                        && isset($payload['questions'], $payload['answers'])
                        && !isset($payload['files'])
                        && !isset($payload['submissionText']);
                }),
                'plagiarism_dixeo',
                $this->anything()
            )
            ->willReturn(operation_result::pending('job-auth-final', 'pending', 0));

        $reader = $this->createMock(assign_submission_reader::class);
        $reader->expects($this->never())->method('get_assignment_context');

        $service = new assign_ux_service($mockjob, $reader, null, 'test-ns');
        $service->submit_authorship(10, 20, 30, assign_ux_service::MODE_FINAL, [
            'initialConfidence' => 70.0,
            'questions' => [['id' => 'q1', 'type' => 'open', 'text' => 'Why?']],
            'answers' => ['q1' => 'Because'],
        ]);
    }

    public function test_map_review_and_authorship_results(): void {
        $service = new assign_ux_service(
            $this->createStub(job_service::class),
            $this->createStub(assign_submission_reader::class),
            null,
            'test-ns'
        );

        $this->assertSame(
            ['feedback_html' => '<p>ok</p>'],
            $service->map_review_result(['feedback_html' => '<p>ok</p>'])
        );
        $this->assertArrayHasKey('error', $service->map_review_result([]));

        $this->assertSame(
            ['confidence' => 88.0, 'explanation' => ''],
            $service->map_authorship_result('confidence', ['confidence' => 88])
        );
        $this->assertSame(
            ['confidence' => 88.0, 'explanation' => 'Voice matches earlier work.'],
            $service->map_authorship_result('confidence', [
                'confidence' => 88,
                'explanation' => 'Voice matches earlier work.',
            ])
        );
        $this->assertSame(
            ['questions' => [['id' => 'q1']]],
            $service->map_authorship_result('quiz', ['questions' => [['id' => 'q1']]])
        );
        $this->assertSame(
            [
                'final_confidence' => 60.0,
                'grade' => 80.0,
                'explanation' => 'Answers were specific.',
                'quiz_explanation' => 'Two of three correct.',
                'question_feedbacks' => ['Clear on q1'],
            ],
            $service->map_authorship_result('final', [
                'final_confidence' => 60,
                'grade' => 80,
                'explanation' => 'Answers were specific.',
                'quiz_explanation' => 'Two of three correct.',
                'question_feedbacks' => ['Clear on q1', ''],
            ])
        );
    }

    public function test_map_grade_result_simple_and_guide(): void {
        $service = new assign_ux_service(
            $this->createStub(job_service::class),
            $this->createStub(assign_submission_reader::class),
            null,
            'test-ns'
        );

        $simple = $service->map_grade_result(
            ['grading_method' => 'simple', 'criteria' => []],
            ['analysis' => 'A', 'feedback' => 'B', 'grade' => 95]
        );
        $this->assertSame('simple', $simple['gradingmethod']);
        $this->assertSame(95.0, $simple['grade']);

        $guidectx = ['grading_method' => 'guide', 'criteria' => [['id' => 11, 'maxscore' => 10]]];
        $criteria = [['id' => 11, 'score' => 8, 'remark' => 'Good']];
        $guide = $service->map_grade_result($guidectx, ['analysis' => 'A', 'feedback' => 'B', 'criteria' => $criteria]);
        $this->assertSame($criteria, $guide['criteria']);

        $this->assertArrayHasKey('error', $service->map_grade_result($guidectx, ['analysis' => 'A', 'feedback' => 'B']));
        $this->assertArrayHasKey('error', $service->map_grade_result($guidectx, null));
    }

    public function test_factory_returns_test_instance(): void {
        $stub = $this->createStub(assign_ux_service::class);
        service_factory::set_test_assign_ux_service($stub);
        $this->assertSame($stub, service_factory::get_assign_ux_service());
        service_factory::reset();
        $this->assertInstanceOf(assign_ux_service::class, service_factory::get_assign_ux_service());
    }

    /**
     * Create a latest file submission for a user.
     *
     * @param \stdClass $assign Assign module record.
     * @param int $userid User id.
     * @param string $filename File name.
     */
    private function create_file_submission(\stdClass $assign, int $userid, string $filename): void {
        global $DB;

        $now = time();
        $submissionid = $DB->insert_record('assign_submission', (object) [
            'assignment' => $assign->id,
            'userid' => $userid,
            'timecreated' => $now,
            'timemodified' => $now,
            'status' => 'submitted',
            'groupid' => 0,
            'attemptnumber' => 0,
            'latest' => 1,
        ]);
        get_file_storage()->create_file_from_string([
            'contextid' => \context_module::instance($assign->cmid)->id,
            'component' => 'assignsubmission_file',
            'filearea' => 'submission_files',
            'itemid' => $submissionid,
            'filepath' => '/',
            'filename' => $filename,
        ], '%PDF-1.4 essay');
        $DB->insert_record('assignsubmission_file', (object) [
            'assignment' => $assign->id,
            'submission' => $submissionid,
            'numfiles' => 1,
        ]);
    }

    /**
     * Create a latest onlinetext submission for a user.
     *
     * @param int $assignmentid Assign instance id.
     * @param int $userid User id.
     * @param string $text Online text HTML.
     */
    private function create_onlinetext_submission(int $assignmentid, int $userid, string $text): void {
        global $DB;

        $now = time();
        $submissionid = $DB->insert_record('assign_submission', (object) [
            'assignment' => $assignmentid,
            'userid' => $userid,
            'timecreated' => $now,
            'timemodified' => $now,
            'status' => 'draft',
            'groupid' => 0,
            'attemptnumber' => 0,
            'latest' => 1,
        ]);
        $DB->insert_record('assignsubmission_onlinetext', (object) [
            'assignment' => $assignmentid,
            'submission' => $submissionid,
            'onlinetext' => $text,
            'onlineformat' => FORMAT_HTML,
        ]);
    }
}
