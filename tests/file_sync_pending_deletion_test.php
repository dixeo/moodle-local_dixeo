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
 * Tests for pending remote file deletion.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\api\client;
use local_dixeo\api\exception\api_exception;
use local_dixeo\external\service_factory;
use local_dixeo\external\set_file_sync_enabled;
use local_dixeo\privacy\provider;
use local_dixeo\repository\course_ai_repository;
use local_dixeo\service\file_sync_service;
use local_dixeo\task\process_remote_file_deletion;

/**
 * Pending remote deletion behaviour.
 *
 * @covers \local_dixeo\service\file_sync_service
 * @covers \local_dixeo\task\process_remote_file_deletion
 * @covers \local_dixeo\privacy\provider
 */
final class file_sync_pending_deletion_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        service_factory::reset();
    }

    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    public function test_disable_sync_delete_success_clears_to_none(): void {
        global $USER;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $userid = (int) $USER->id;

        $client = $this->createMock(client::class);
        $client->expects($this->once())
            ->method('delete_files')
            ->with((string) $course->id)
            ->willReturn([]);

        $repo = new course_ai_repository();
        $service = new file_sync_service($repo, $client);
        $service->enable_sync((int) $course->id, $userid);
        $repo->update_sync_status((int) $course->id, 'synchronized', [
            'filestotal' => 2,
            'filescompleted' => 2,
            'progresspercent' => 100,
        ]);

        $service->disable_sync((int) $course->id, $userid, true);

        $status = $service->get_status((int) $course->id);
        $this->assertFalse($status->enabled);
        $this->assertSame('none', $status->status);
        $this->assertNull($status->errormessage);
    }

    public function test_disable_sync_delete_failure_keeps_pending_deletion(): void {
        global $DB, $USER;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $userid = (int) $USER->id;

        $client = $this->createMock(client::class);
        $client->expects($this->once())
            ->method('delete_files')
            ->willThrowException(new api_exception('server_error', 'upstream 503', 503));

        $repo = new course_ai_repository();
        $service = new file_sync_service($repo, $client);
        $service->enable_sync((int) $course->id, $userid);

        $service->disable_sync((int) $course->id, $userid, true);

        $status = $service->get_status((int) $course->id);
        $this->assertFalse($status->enabled);
        $this->assertSame('pending_deletion', $status->status);
        $this->assertNotEmpty($status->errormessage);

        $record = $repo->get_by_courseid((int) $course->id);
        $this->assertSame(1, (int) $record->errorcount);

        $tasks = \core\task\manager::get_adhoc_tasks('\\local_dixeo\\task\\process_remote_file_deletion');
        $this->assertNotEmpty($tasks);
    }

    public function test_retry_pending_deletion_success_clears_state(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $client = $this->createMock(client::class);
        $client->expects($this->once())
            ->method('delete_files')
            ->with((string) $course->id)
            ->willReturn([]);

        $repo = new course_ai_repository();
        $repo->mark_pending_deletion((int) $course->id);
        $repo->record_pending_deletion_error((int) $course->id, 'previous failure');

        $service = new file_sync_service($repo, $client);
        $this->assertTrue($service->retry_pending_deletion((int) $course->id));

        $status = $service->get_status((int) $course->id);
        $this->assertSame('none', $status->status);
        $this->assertNull($status->errormessage);
    }

    public function test_retry_pending_deletion_failure_increments_and_requeues(): void {
        global $DB;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $client = $this->createMock(client::class);
        $client->expects($this->once())
            ->method('delete_files')
            ->willThrowException(new api_exception('server_error', 'still failing', 500));

        $repo = new course_ai_repository();
        $repo->mark_pending_deletion((int) $course->id);

        $service = new file_sync_service($repo, $client);
        $this->assertFalse($service->retry_pending_deletion((int) $course->id));

        $record = $repo->get_by_courseid((int) $course->id);
        $this->assertSame('pending_deletion', $record->syncstatus);
        $this->assertSame(1, (int) $record->errorcount);

        $tasks = \core\task\manager::get_adhoc_tasks('\\local_dixeo\\task\\process_remote_file_deletion');
        $this->assertNotEmpty($tasks);
    }

    public function test_set_file_sync_enabled_returns_pending_deletion_status(): void {
        global $USER;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $userid = (int) $USER->id;

        $client = $this->createMock(client::class);
        $client->expects($this->once())
            ->method('delete_files')
            ->willThrowException(new api_exception('timeout', 'timed out', 504));

        $service = new file_sync_service(new course_ai_repository(), $client);
        $service->enable_sync((int) $course->id, $userid);
        service_factory::set_test_file_sync_service($service);

        $result = set_file_sync_enabled::execute((int) $course->id, false, true);
        $this->assertTrue($result['success']);
        $this->assertSame('pending_deletion', $result['status']);
    }

    public function test_privacy_course_purge_deletes_row_when_remote_delete_succeeds(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $repo = new course_ai_repository();
        $repo->get_or_create((int) $course->id, (int) $user->id);
        $repo->set_enabled((int) $course->id, true, (int) $user->id);

        $client = $this->createMock(client::class);
        $client->expects($this->once())
            ->method('delete_files')
            ->with((string) $course->id)
            ->willReturn([]);
        service_factory::set_test_client($client);

        provider::delete_data_for_all_users_in_context(\context_course::instance((int) $course->id));

        $this->assertFalse($DB->record_exists('local_dixeo_course_ai', ['courseid' => $course->id]));
    }

    public function test_privacy_course_purge_leaves_pending_when_remote_delete_fails(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $repo = new course_ai_repository();
        $repo->get_or_create((int) $course->id, (int) $user->id);

        $client = $this->createMock(client::class);
        $client->expects($this->once())
            ->method('delete_files')
            ->willThrowException(new api_exception('server_error', 'privacy delete failed', 500));
        service_factory::set_test_client($client);

        provider::delete_data_for_all_users_in_context(\context_course::instance((int) $course->id));

        $record = $DB->get_record('local_dixeo_course_ai', ['courseid' => $course->id], '*', MUST_EXIST);
        $this->assertSame('pending_deletion', $record->syncstatus);
        $this->assertSame(0, (int) $record->enabled);
    }
}
