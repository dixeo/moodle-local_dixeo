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
 * Unit tests for practice_quiz_service.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\context\context_builder_factory;
use local_dixeo\dto\job_status;
use local_dixeo\external\service_factory;
use local_dixeo\service\job_service;
use local_dixeo\service\module_generation_service;
use local_dixeo\service\practice_quiz_service;



defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Tests for practice_quiz_service_test.
 * @covers \local_dixeo\service\practice_quiz_service
 */
final class practice_quiz_service_test extends \advanced_testcase {
    /**
     * build_instructions states question count and difficulty explicitly.
     */
    public function test_build_instructions_includes_count_and_difficulty(): void {
        $this->resetAfterTest();

        $service = new practice_quiz_service();
        $instructions = $service->build_instructions(7, 'hard', practice_quiz_service::SCOPE_SECTION, 'Unit 2');

        $this->assertStringContainsString('exactly 7', $instructions);
        $this->assertStringContainsString('QUESTION COUNT', $instructions);
        $this->assertStringContainsString('DIFFICULTY LEVEL', $instructions);
        $this->assertStringContainsString('hard', $instructions);
        $this->assertStringContainsString('section', $instructions);
        $this->assertStringContainsString('Unit 2', $instructions);
        $this->assertStringNotContainsString('ephemeral', $instructions);
    }

    /**
     * submit_from_setup rejects invalid scope values.
     */
    public function test_submit_from_setup_rejects_invalid_scope(): void {
        $this->resetAfterTest();

        $service = new practice_quiz_service();

        $this->expectException(\invalid_parameter_exception::class);
        $service->submit_from_setup(1, 'invalid');
    }

    /**
     * Practice quiz service whose generation must never be reached.
     *
     * @return practice_quiz_service
     */
    private function service_without_generation(): practice_quiz_service {
        $generation = $this->createMock(module_generation_service::class);
        $generation->expects($this->never())->method('submit_generate_job');
        return new practice_quiz_service($generation, $this->createMock(job_service::class));
    }

    /**
     * Run a call and assert it is refused with the scope error, without saying whether the item exists.
     *
     * @param callable $call The call.
     */
    private function assert_scope_refused(callable $call): void {
        try {
            $call();
            $this->fail('The scope must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:scope_unavailable', $e->errorcode);
            $this->assertSame('local_dixeo', $e->module);
        }
    }

    /**
     * Hidden sections, section-0 and excluded or unknown activities are refused before any generation call.
     */
    public function test_submit_from_setup_refuses_unavailable_scopes(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['numsections' => 2]);
        $student = $gen->create_and_enrol($course, 'student');
        $hidden = $gen->create_module('page', ['course' => $course->id, 'section' => 1, 'visible' => 0]);
        $intro = $gen->create_module('page', ['course' => $course->id, 'section' => 0]);
        $quiz = $gen->create_module('quiz', ['course' => $course->id, 'section' => 1]);
        $other = $gen->create_course();
        $foreign = $gen->create_module('page', ['course' => $other->id]);
        set_section_visible((int) $course->id, 2, 0);
        $courseid = (int) $course->id;
        $service = $this->service_without_generation();

        $this->setUser($student);
        $this->assert_scope_refused(fn() => $service->submit_from_setup($courseid, 'section', 2));
        $this->assert_scope_refused(fn() => $service->submit_from_setup($courseid, 'section', 0, (int) $hidden->cmid));
        $this->assert_scope_refused(fn() => $service->submit_from_setup($courseid, 'activity', 0, (int) $hidden->cmid));
        $this->assert_scope_refused(fn() => $service->submit_from_setup($courseid, 'activity', 0, (int) $intro->cmid));
        $this->assert_scope_refused(fn() => $service->submit_from_setup($courseid, 'activity', 0, (int) $foreign->cmid));
        $this->assert_scope_refused(fn() => $service->submit_from_setup($courseid, 'activity', 0, 999999));
        $this->assert_scope_refused(
            fn() => $service->submit_from_setup($courseid, 'activity', 0, (int) $quiz->cmid, 5, 'medium', '', '', ['quiz'])
        );

        $this->expectException(\invalid_parameter_exception::class);
        $service->submit_from_setup($courseid, 'unknown');
    }

    /**
     * A visible activity is accepted and only its cmid is kept; a visible section keeps only its number.
     */
    public function test_require_visible_scope_accepts_visible_items(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['numsections' => 2]);
        $student = $gen->create_and_enrol($course, 'student');
        $page = $gen->create_module('page', ['course' => $course->id, 'section' => 1]);
        $service = new practice_quiz_service();

        $this->setUser($student);
        $this->assertSame(
            ['activity', 0, (int) $page->cmid],
            $service->require_visible_scope((int) $course->id, ' Activity ', 2, (int) $page->cmid, ['quiz'])
        );
        $this->assertSame(['section', 1, 0], $service->require_visible_scope((int) $course->id, 'section', 1, (int) $page->cmid));
        $this->assertSame(['course', 0, 0], $service->require_visible_scope((int) $course->id, 'course', 1, (int) $page->cmid));
    }

    /**
     * finalize_from_job trims excess questions when expected count is set.
     */
    public function test_finalize_from_job_trims_to_expected_count(): void {
        $this->resetAfterTest(true);

        $mockjob = $this->getMockBuilder(job_service::class)
            ->onlyMethods(['get_job_status'])
            ->getMock();

        $mockjob->method('get_job_status')->willReturn(new job_status(
            jobid: 'test-job-id',
            type: 'generate_module',
            status: job_status::STATUS_COMPLETED,
            progress: 100,
            createdat: time(),
            result: [
                'moduleType' => 'simplequiz2',
                'data' => [
                    'name' => 'Quiz',
                    'questions' => [
                        ['text' => 'Q1', 'options' => ['A', 'B'], 'answer' => 0],
                        ['text' => 'Q2', 'options' => ['A', 'B'], 'answer' => 0],
                        ['text' => 'Q3', 'options' => ['A', 'B'], 'answer' => 0],
                        ['text' => 'Q4', 'options' => ['A', 'B'], 'answer' => 0],
                        ['text' => 'Q5', 'options' => ['A', 'B'], 'answer' => 0],
                    ],
                ],
            ]
        ));

        service_factory::set_test_job_service($mockjob);

        $service = new practice_quiz_service(null, $mockjob);
        $result = $service->finalize_from_job('test-job-id', '', 3);

        $questions = json_decode($result['questions'], true);
        $this->assertCount(3, $questions);
        $this->assertEquals('Q1', $questions[0]['text']);

        service_factory::reset();
    }

    /**
     * finalize_from_job transforms completed simplequiz2 job data.
     */
    public function test_finalize_from_job_success(): void {
        $this->resetAfterTest(true);

        $mockjob = $this->getMockBuilder(job_service::class)
            ->onlyMethods(['get_job_status'])
            ->getMock();

        $mockjob->method('get_job_status')->willReturn(new job_status(
            jobid: 'test-job-id',
            type: 'generate_module',
            status: job_status::STATUS_COMPLETED,
            progress: 100,
            createdat: time(),
            result: [
                'moduleType' => 'simplequiz2',
                'data' => [
                    'name' => 'Cell biology quiz',
                    'intro' => '<p>Check your knowledge of cell biology.</p>',
                    'questions' => [
                        [
                            'text' => 'What is a cell?',
                            'options' => ['Unit of life', 'Organ'],
                            'answer' => 0,
                        ],
                    ],
                ],
            ]
        ));

        service_factory::set_test_job_service($mockjob);

        $service = new practice_quiz_service(null, $mockjob);
        $result = $service->finalize_from_job('test-job-id');

        $this->assertTrue($result['success']);
        $this->assertEquals('Cell biology quiz', $result['title']);
        $this->assertStringContainsString('cell biology', strtolower($result['introhtml']));
        $questions = json_decode($result['questions'], true);
        $this->assertCount(1, $questions);
        $this->assertEquals('What is a cell?', $questions[0]['text']);

        service_factory::reset();
    }

    /**
     * Practice-quiz intro HTML is cleaned before it is returned for display.
     */
    public function test_finalize_from_job_strips_active_content_from_intro(): void {
        $this->resetAfterTest(true);

        $mockjob = $this->getMockBuilder(job_service::class)
            ->onlyMethods(['get_job_status'])
            ->getMock();

        $mockjob->method('get_job_status')->willReturn(new job_status(
            jobid: 'test-job-id',
            type: 'generate_module',
            status: job_status::STATUS_COMPLETED,
            progress: 100,
            createdat: time(),
            result: [
                'moduleType' => 'simplequiz2',
                'data' => [
                    'name' => 'Quiz',
                    'intro' => '<p>Safe intro</p><script>alert(1)</script><img src="x" onerror="alert(1)">',
                    'questions' => [
                        [
                            'text' => 'What is a cell?',
                            'options' => ['Unit of life', 'Organ'],
                            'answer' => 0,
                        ],
                    ],
                ],
            ]
        ));

        service_factory::set_test_job_service($mockjob);

        $service = new practice_quiz_service(null, $mockjob);
        $result = $service->finalize_from_job('test-job-id');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Safe intro', $result['introhtml']);
        $this->assertStringNotContainsString('<script', strtolower($result['introhtml']));
        $this->assertStringNotContainsString('onerror', strtolower($result['introhtml']));

        service_factory::reset();
    }

    /**
     * Activity scope context includes file annotation and omits adjacent modules.
     */
    public function test_build_context_activity_includes_files_and_omits_adjacent(): void {
        $this->resetAfterTest(true);
        $fixtures = $this->create_practice_quiz_fixtures();
        $service = new practice_quiz_service();

        $context = $service->build_context(
            (int) $fixtures['course']->id,
            practice_quiz_service::SCOPE_ACTIVITY,
            null,
            $fixtures['resourcecmid']
        );

        $this->assertStringContainsString('# Module Context', $context);
        $this->assertStringContainsString('The Simpsons', $context);
        $this->assertStringContainsString('(files:', $context);
        $this->assertStringContainsString('simpsons-wiki.pdf', $context);
        $this->assertStringNotContainsString('Adjacent Modules', $context);
    }

    /**
     * Section scope context uses section builder with full module content.
     */
    public function test_build_context_section_uses_full_section_content(): void {
        $this->resetAfterTest(true);
        $fixtures = $this->create_practice_quiz_fixtures();
        $service = new practice_quiz_service();

        $context = $service->build_context(
            (int) $fixtures['course']->id,
            practice_quiz_service::SCOPE_SECTION,
            $fixtures['sectionnum'],
            null
        );

        $this->assertStringContainsString('# Section Context', $context);
        $this->assertStringContainsString('Section Page Activity', $context);
        $this->assertStringContainsString($fixtures['longmarker'], $context);
        $this->assertStringNotContainsString('# Course Context', $context);
    }

    /**
     * Course scope context uses full course assessment context.
     */
    public function test_build_context_course_uses_full_course_context(): void {
        $this->resetAfterTest(true);
        $fixtures = $this->create_practice_quiz_fixtures();
        $service = new practice_quiz_service();

        $context = $service->build_context(
            (int) $fixtures['course']->id,
            practice_quiz_service::SCOPE_COURSE,
            null,
            null
        );

        $this->assertStringContainsString('# Course Context', $context);
        $this->assertStringContainsString('## Course Structure', $context);
        $this->assertStringContainsString('Section Page Activity', $context);
        $this->assertStringContainsString($fixtures['longmarker'], $context);
    }

    /**
     * buildsectioncontextfornumber throws when section number does not exist.
     */
    public function test_build_section_context_for_number_invalid_section(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();

        $this->expectException(\dml_missing_record_exception::class);
        context_builder_factory::buildsectioncontextfornumber((int) $course->id, 99);
    }

    /**
     * Create a course with a long page and a resource with an attached file.
     *
     * @return array{course: object, pagecmid: int, resourcecmid: int, sectionnum: int, longmarker: string}
     */
    private function create_practice_quiz_fixtures(): array {
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['numsections' => 2]);
        $longmarker = 'SectionPageContentMarker';
        $longcontent = '<p>' . str_repeat($longmarker, 40) . '</p>';

        $page = $gen->create_module('page', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'Section Page Activity',
            'content' => $longcontent,
            'contentformat' => FORMAT_HTML,
        ]);

        $resource = $gen->create_module('resource', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'The Simpsons',
            'intro' => '<p>Simpsons resource intro</p>',
            'introformat' => FORMAT_HTML,
        ]);
        $resourcecm = get_coursemodule_from_instance('resource', $resource->id, $course->id);

        $fs = get_file_storage();
        $context = \context_module::instance($resourcecm->id);
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_resource',
            'filearea' => 'content',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'simpsons-wiki.pdf',
        ], 'Fake PDF body');

        rebuild_course_cache($course->id, true);

        return [
            'course' => $course,
            'pagecmid' => (int) get_coursemodule_from_instance('page', $page->id, $course->id)->id,
            'resourcecmid' => (int) $resourcecm->id,
            'sectionnum' => 1,
            'longmarker' => $longmarker,
        ];
    }
}
