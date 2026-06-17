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
 * Unit tests for teach_lesson_service.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\dto\job_status;
use local_dixeo\external\service_factory;
use local_dixeo\service\job_service;
use local_dixeo\service\teach_lesson_service;


/**
 * Tests for teach_lesson_service_test.
 * @covers \local_dixeo\service\teach_lesson_service
 */
final class teach_lesson_service_test extends \advanced_testcase {
    /**
     * build_instructions includes learner request and scope.
     */
    public function test_build_instructions_includes_learner_request(): void {
        $this->resetAfterTest();

        $service = new teach_lesson_service();
        $instructions = $service->build_instructions(
            teach_lesson_service::SCOPE_SECTION,
            'Unit 2',
            'Explain photosynthesis in simpler terms'
        );

        $this->assertStringContainsString('Unit 2', $instructions);
        $this->assertStringContainsString('Explain photosynthesis in simpler terms', $instructions);
        $this->assertStringContainsString('MANDATORY REQUIREMENTS', $instructions);
        $this->assertStringContainsString('Page module', $instructions);
    }

    /**
     * submit_from_setup rejects empty learner request.
     */
    public function test_submit_from_setup_rejects_empty_learner_request(): void {
        $this->resetAfterTest();

        $service = new teach_lesson_service();

        $this->expectException(\invalid_parameter_exception::class);
        $service->submit_from_setup(1, teach_lesson_service::SCOPE_COURSE, 0, 0, 'Course', '   ');
    }

    /**
     * submit_from_setup rejects invalid scope values.
     */
    public function test_submit_from_setup_rejects_invalid_scope(): void {
        $this->resetAfterTest();

        $service = new teach_lesson_service();

        $this->expectException(\invalid_parameter_exception::class);
        $service->submit_from_setup(1, 'invalid', 0, 0, 'Topic', 'Tell me more');
    }

    /**
     * finalize_from_job transforms completed page job data.
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
                'moduleType' => 'page',
                'data' => [
                    'name' => 'Introduction to cells',
                    'intro' => '<p>A brief overview.</p>',
                    'content' => '<p>Cells are the basic unit of life.</p>',
                ],
            ]
        ));

        service_factory::set_test_job_service($mockjob);

        $service = new teach_lesson_service(null, $mockjob);
        $result = $service->finalize_from_job('test-job-id');

        $this->assertTrue($result['success']);
        $this->assertEquals('Introduction to cells', $result['title']);
        $this->assertStringContainsString('brief overview', $result['introhtml']);
        $this->assertStringContainsString('basic unit of life', $result['contenthtml']);

        service_factory::reset();
    }

    /**
     * finalize_from_job rejects wrong module type.
     */
    public function test_finalize_from_job_wrong_module_type(): void {
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
                'data' => ['content' => '<p>Test</p>'],
            ]
        ));

        service_factory::set_test_job_service($mockjob);

        $service = new teach_lesson_service(null, $mockjob);
        $result = $service->finalize_from_job('test-job-id');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('page', $result['error']);

        service_factory::reset();
    }

    /**
     * finalize_from_job rejects empty content.
     */
    public function test_finalize_from_job_no_content(): void {
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
                'moduleType' => 'page',
                'data' => [
                    'name' => 'Empty lesson',
                    'content' => '',
                ],
            ]
        ));

        service_factory::set_test_job_service($mockjob);

        $service = new teach_lesson_service(null, $mockjob);
        $result = $service->finalize_from_job('test-job-id');

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['error']);

        service_factory::reset();
    }
}
