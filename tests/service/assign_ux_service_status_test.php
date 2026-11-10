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
 * Tests for module-bound job polling in assign_ux_service.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\dto\job_binding_metadata;
use local_dixeo\dto\job_status;
use local_dixeo\repository\job_repository;
use local_dixeo\service\assign_ux_service;
use local_dixeo\service\job_service;

/**
 * A job is polled only for the assignment, user and component that started it.
 *
 * @covers \local_dixeo\service\assign_ux_service::get_status_for_module
 */
final class assign_ux_service_status_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass Assignment the job was started from. */
    private \stdClass $assign;

    /** @var \stdClass Student who started the job. */
    private \stdClass $student;

    /** @var job_repository Job bindings. */
    private job_repository $repository;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $this->assign = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id]);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->repository = new job_repository();
    }

    /**
     * Register a job binding.
     *
     * @param string $jobid Job id.
     * @param int $cmid Course module id.
     * @param string $component Originating component.
     */
    private function register(string $jobid, int $cmid, string $component = assign_ux_service::COMPONENT): void {
        $this->repository->register(
            $jobid,
            (int) $this->course->id,
            (int) $this->student->id,
            'default',
            'assign_review',
            $component,
            job_binding_metadata::for_module('assign', $cmid)
        );
    }

    /**
     * Service over the real repository with the remote poll mocked.
     *
     * @param bool $expectpoll Whether the remote status may be read.
     * @return assign_ux_service
     */
    private function service(bool $expectpoll): assign_ux_service {
        $job = $this->createMock(job_service::class);
        $job->method('get_job_repository')->willReturn($this->repository);
        $job->expects($expectpoll ? $this->once() : $this->never())
            ->method('get_job_status')
            ->willReturn(new job_status('job-1', 'assign_review', job_status::STATUS_COMPLETED, 100, time()));
        return new assign_ux_service($job, null, null, 'test-ns');
    }

    public function test_job_of_this_module_is_polled(): void {
        $this->register('job-1', (int) $this->assign->cmid);

        $status = $this->service(true)->get_status_for_module(
            'job-1',
            (int) $this->course->id,
            (int) $this->assign->cmid,
            (int) $this->student->id
        );

        $this->assertSame(job_status::STATUS_COMPLETED, $status->status);
    }

    public function test_job_of_another_module_is_not_found(): void {
        $other = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id]);
        $this->register('job-1', (int) $this->assign->cmid);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error:job_not_found', 'local_dixeo'));
        $this->service(false)->get_status_for_module(
            'job-1',
            (int) $this->course->id,
            (int) $other->cmid,
            (int) $this->student->id
        );
    }

    public function test_job_of_another_component_is_not_found(): void {
        $this->register('job-1', (int) $this->assign->cmid, 'block_dixeo_tutor');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error:job_not_found', 'local_dixeo'));
        $this->service(false)->get_status_for_module(
            'job-1',
            (int) $this->course->id,
            (int) $this->assign->cmid,
            (int) $this->student->id
        );
    }

    public function test_job_of_another_user_is_not_found(): void {
        $this->register('job-1', (int) $this->assign->cmid);
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $this->expectException(\moodle_exception::class);
        $this->service(false)->get_status_for_module(
            'job-1',
            (int) $this->course->id,
            (int) $this->assign->cmid,
            (int) $other->id
        );
    }
}
