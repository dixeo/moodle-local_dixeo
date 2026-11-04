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
use local_dixeo\repository\course_ai_repository;

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
}
