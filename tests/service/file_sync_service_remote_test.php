<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_dixeo\service;

use local_dixeo\api\client;
use local_dixeo\api\exception\api_exception;
use local_dixeo\event\file_sync_disabled;
use local_dixeo\repository\course_ai_repository;
use local_dixeo\settings\config;

/**
 * Tests for remote file replacement, deletion and live sync status.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\service\file_sync_service
 */
final class file_sync_service_remote_test extends \advanced_testcase {
    /**
     * Replacing deletes first, then uploads, and a failed delete does not stop the upload.
     */
    public function test_replace_remote_files_deletes_then_uploads(): void {
        $this->resetAfterTest();
        $calls = [];
        $client = $this->createMock(client::class);
        $client->method('delete_files')->willReturnCallback(function (string $key) use (&$calls): array {
            $calls[] = 'delete:' . $key;
            throw new api_exception('server_error', 'down', 503);
        });
        $client->method('upload_files')->willReturnCallback(function (string $key, array $files) use (&$calls): array {
            $calls[] = 'upload:' . $key . ':' . count($files);
            return [];
        });

        $service = new file_sync_service(new course_ai_repository(), $client);
        $service->replace_remote_files('job-1', ['file']);
        $this->assertDebuggingCalled();

        $this->assertSame(['delete:job-1', 'upload:job-1:1'], $calls);
    }

    /**
     * Replacing with no files only deletes.
     */
    public function test_replace_remote_files_without_files_only_deletes(): void {
        $this->resetAfterTest();
        $client = $this->createMock(client::class);
        $client->expects($this->once())->method('delete_files')->with('job-2')->willReturn([]);
        $client->expects($this->never())->method('upload_files');

        (new file_sync_service(new course_ai_repository(), $client))->replace_remote_files('job-2', []);
    }

    /**
     * Deleting rethrows the API failure so a remote erasure task retries.
     */
    public function test_delete_remote_files_rethrows(): void {
        $this->resetAfterTest();
        $client = $this->createMock(client::class);
        $client->method('delete_files')->willThrowException(new api_exception('server_error', 'down', 503));

        $this->expectException(api_exception::class);
        (new file_sync_service(new course_ai_repository(), $client))->delete_remote_files('42');
    }

    /**
     * Status matrix: only a sync in progress whose upload has been sent polls the API.
     */
    public function test_get_live_status_matrix(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $courseid = (int) $course->id;
        $repository = new course_ai_repository();
        $repository->create($courseid, 2);
        $polls = 0;
        $client = $this->createMock(client::class);
        $client->method('is_configured')->willReturn(true);
        $client->method('get_files_status')->willReturnCallback(function () use (&$polls): array {
            $polls++;
            return ['status' => 'synchronized', 'fileCount' => 3, 'syncedCount' => 3];
        });
        $service = new file_sync_service($repository, $client);

        $set = function (string $status, ?int $bytes, ?int $total) use ($DB, $courseid): void {
            $DB->set_field('local_dixeo_course_ai', 'syncstatus', $status, ['courseid' => $courseid]);
            $DB->set_field('local_dixeo_course_ai', 'uploadbytes', $bytes, ['courseid' => $courseid]);
            $DB->set_field('local_dixeo_course_ai', 'uploadbytestotal', $total, ['courseid' => $courseid]);
        };

        foreach (['synchronized', 'none', 'outdated', 'pending_deletion'] as $status) {
            $set($status, null, null);
            $this->assertSame($status, $service->get_live_status($courseid)->status);
        }
        $this->assertSame(0, $polls);

        $set('syncing', 100, 1000);
        $this->assertSame('syncing', $service->get_live_status($courseid)->status);
        $this->assertSame(0, $polls);

        $set('syncing', 1000, 1000);
        $this->assertSame('synchronized', $service->get_live_status($courseid)->status);
        $this->assertSame(1, $polls);
    }

    /**
     * Purging an enabled course turns sync off, empties the remote store and resets the row.
     */
    public function test_purge_course_files_resets_enabled_course(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $courseid = (int) $course->id;
        $actor = $this->getDataGenerator()->create_user();
        $repository = new course_ai_repository();
        $repository->set_enabled($courseid, true, 2);
        $repository->update_filehash($courseid, 'abc');

        $client = $this->createMock(client::class);
        $client->expects($this->once())->method('delete_files')->with((string) $courseid)->willReturn([]);

        $sink = $this->redirectEvents();
        (new file_sync_service($repository, $client))->purge_course_files($courseid, (int) $actor->id);
        $events = array_values(array_filter(
            $sink->get_events(),
            static fn($event): bool => $event instanceof file_sync_disabled
        ));
        $sink->close();

        $this->assertSame(
            '0',
            (new config())->get('local_dixeo', 'filesync_enabled', \context_course::instance($courseid))->get_value()
        );
        $record = $repository->get_by_courseid($courseid);
        $this->assertSame('none', $record->syncstatus);
        $this->assertSame(0, (int) $record->enabled);
        $this->assertEmpty($record->filehash);
        $this->assertCount(1, $events);
        $this->assertSame((int) $actor->id, (int) $events[0]->userid);
    }

    /**
     * A failed purge rethrows, keeps the row pending deletion with the code only, and queues no retry.
     */
    public function test_purge_course_files_rethrows_without_queueing_a_retry(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $courseid = (int) $course->id;
        $repository = new course_ai_repository();
        $repository->set_enabled($courseid, true, 2);

        $client = $this->createMock(client::class);
        $client->method('delete_files')->willThrowException(new api_exception('upstream_ai', 'secret', 502));

        try {
            (new file_sync_service($repository, $client))->purge_course_files($courseid, 2);
            $this->fail('The remote delete failure must be rethrown.');
        } catch (api_exception $e) {
            $this->assertSame('upstream_ai', $e->get_error_code());
        }

        $record = $repository->get_by_courseid($courseid);
        $this->assertSame('pending_deletion', $record->syncstatus);
        $this->assertSame('upstream_ai', $record->errormessage);
        $this->assertEmpty(\core\task\manager::get_adhoc_tasks('\\local_dixeo\\task\\process_remote_file_deletion'));
    }

    /**
     * A deleted course is still purged under its id.
     */
    public function test_purge_course_files_for_deleted_course(): void {
        $this->resetAfterTest();
        $client = $this->createMock(client::class);
        $client->expects($this->once())->method('delete_files')->with('999999')->willReturn([]);

        (new file_sync_service(new course_ai_repository(), $client))->purge_course_files(999999, 2);
    }

    /**
     * Turning Dixeo off does not stop a purge.
     */
    public function test_purge_course_files_ignores_dixeo_switch(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        (new config())->set('local_dixeo', 'enabled', \context_system::instance(), '0');

        $client = $this->createMock(client::class);
        $client->expects($this->once())->method('delete_files')->with((string) $course->id)->willReturn([]);

        (new file_sync_service(new course_ai_repository(), $client))->purge_course_files((int) $course->id, 2);
    }

    /**
     * Purging a course that never synced creates no course_ai row.
     */
    public function test_purge_course_files_creates_no_row(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $repository = new course_ai_repository();

        $client = $this->createMock(client::class);
        $client->expects($this->once())->method('delete_files')->willReturn([]);

        (new file_sync_service($repository, $client))->purge_course_files((int) $course->id, 2);

        $this->assertNull($repository->get_by_courseid((int) $course->id));
    }
}
