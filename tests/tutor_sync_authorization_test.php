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
 * Tests that students cannot reactivate disabled course file sync.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\api\client;
use local_dixeo\dto\operation_result;
use local_dixeo\external\service_factory;
use local_dixeo\repository\course_ai_repository;
use local_dixeo\service\file_sync_service;
use local_dixeo\service\job_service;
use local_dixeo\service\tutor_service;

/**
 * Authorization for pre-tutor RAG sync.
 *
 * @covers \local_dixeo\service\file_sync_service::ensure_enabled_and_synchronized
 * @covers \local_dixeo\service\tutor_service::submit_message
 */
final class tutor_sync_authorization_test extends \advanced_testcase {
    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * Build a mock API client that never uploads or deletes course files.
     *
     * @return client&\PHPUnit\Framework\MockObject\MockObject
     */
    private function mock_client_never_uploads(): client {
        $mockclient = $this->createMock(client::class);
        $mockclient->expects($this->never())->method('upload_files');
        $mockclient->expects($this->never())->method('delete_files');
        $mockclient->method('get_files_status')->willReturn([
            'status' => 'synchronized',
            'fileCount' => 0,
            'syncedCount' => 0,
            'progress' => ['filesTotal' => 0, 'filesCompleted' => 0, 'percent' => 100],
        ]);
        return $mockclient;
    }

    /**
     * Build a mock API client that allows authorized sync/wait.
     *
     * @return client&\PHPUnit\Framework\MockObject\MockObject
     */
    private function mock_client_allows_sync(): client {
        $mockclient = $this->createMock(client::class);
        $mockclient->method('upload_files')->willReturn([]);
        $mockclient->method('delete_files')->willReturn([]);
        $mockclient->method('get_files_status')->willReturn([
            'status' => 'synchronized',
            'fileCount' => 0,
            'syncedCount' => 0,
            'progress' => ['filesTotal' => 0, 'filesCompleted' => 0, 'percent' => 100],
        ]);
        return $mockclient;
    }

    public function test_student_ensure_does_not_enable_or_upload_when_sync_disabled(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $repository = new course_ai_repository();
        $this->assertNull($repository->get_by_courseid((int) $course->id));

        $service = new file_sync_service($repository, $this->mock_client_never_uploads());
        $service->ensure_enabled_and_synchronized((int) $course->id, (int) $student->id);

        $this->assertFalse($service->is_enabled((int) $course->id));
        $this->assertNull($repository->get_by_courseid((int) $course->id));
    }

    public function test_teacher_ensure_enables_when_syncfiles_allowed(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $context = \context_course::instance($course->id);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('local/dixeo:syncfiles', CAP_ALLOW, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($teacher);

        $repository = new course_ai_repository();
        $service = new file_sync_service($repository, $this->mock_client_allows_sync());
        $service->ensure_enabled_and_synchronized((int) $course->id, (int) $teacher->id);

        $this->assertTrue($service->is_enabled((int) $course->id));
        $status = $service->get_status((int) $course->id);
        $this->assertSame('synchronized', $status->status);
    }

    public function test_student_submit_message_skips_sync_reactivation(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $repository = new course_ai_repository();
        $syncservice = new file_sync_service($repository, $this->mock_client_never_uploads());
        service_factory::set_test_file_sync_service($syncservice);

        $jobservice = $this->createMock(job_service::class);
        $jobservice->expects($this->once())
            ->method('submit_job')
            ->willReturn(operation_result::pending('job-tutor-1', 'pending', 0));

        $tutorservice = new tutor_service($jobservice);
        $result = $tutorservice->submit_message((int) $course->id, (int) $student->id, 'Hello tutor');

        $this->assertSame('job-tutor-1', $result->jobid);
        $this->assertFalse($syncservice->is_enabled((int) $course->id));
        $this->assertNull($repository->get_by_courseid((int) $course->id));
    }
}
