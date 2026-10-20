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

/**
 * Tests for the per-file status list.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\api\client;
use local_dixeo\api\exception\api_exception;
use local_dixeo\service\file_sync_service;

/**
 * Local classification and the remote overlay for the status file list.
 *
 * @covers \local_dixeo\service\file_sync_service::classify_sync_files
 * @covers \local_dixeo\service\file_sync_service::list_status_files
 */
final class file_sync_status_files_test extends \advanced_testcase {
    /**
     * Size and type decide the row. A remote failure replaces the API message with a generic reason.
     */
    public function test_classify_sync_files_uses_local_reasons_and_drops_api_text(): void {
        $this->resetAfterTest();
        $service = new file_sync_service();
        $secret = 'provider said the vector store rejected this file';

        $rows = $service->classify_sync_files([
            ['filename' => 'notes.pdf', 'size' => 1024],
            ['filename' => 'slides.pptx', 'size' => 12 * 1024 * 1024],
            ['filename' => 'limit.docx', 'size' => 20 * 1024 * 1024],
            ['filename' => 'huge.pdf', 'size' => (20 * 1024 * 1024) + 1],
            ['filename' => 'blank.txt', 'size' => 0],
            ['filename' => 'photo.jpg', 'size' => 400],
            ['filename' => 'archive.zip', 'size' => (20 * 1024 * 1024) + 1],
        ], [
            'hydra:member' => [
                [
                    'fileName' => 'notes.pdf',
                    'status' => 'error',
                    'errorMessage' => $secret,
                ],
                [
                    'fileName' => 'slides.pptx',
                    'status' => 'uploaded',
                    'errorMessage' => $secret,
                ],
            ],
        ], 'synchronized');

        $this->assertSame([
            ['filename' => 'archive.zip', 'state' => 'problem', 'reason' => 'unmanaged'],
            ['filename' => 'blank.txt', 'state' => 'problem', 'reason' => 'unmanaged'],
            ['filename' => 'huge.pdf', 'state' => 'problem', 'reason' => 'toolarge'],
            ['filename' => 'limit.docx', 'state' => 'synced', 'reason' => ''],
            ['filename' => 'notes.pdf', 'state' => 'problem', 'reason' => 'error'],
            ['filename' => 'photo.jpg', 'state' => 'problem', 'reason' => 'unmanaged'],
            ['filename' => 'slides.pptx', 'state' => 'problem', 'reason' => 'error'],
        ], $rows);
        $this->assertStringNotContainsString($secret, json_encode($rows));
    }

    /**
     * A bare list and a member collection are both accepted. An unknown shape is ignored.
     */
    public function test_classify_sync_files_accepts_production_list_shapes(): void {
        $this->resetAfterTest();
        $service = new file_sync_service();
        $local = [
            ['filename' => 'notes.pdf', 'size' => 100],
        ];

        $bare = $service->classify_sync_files($local, [
            ['filename' => 'notes.pdf', 'status' => 'failed'],
        ], 'synchronized');
        $member = $service->classify_sync_files($local, [
            'member' => [
                ['filename' => 'notes.pdf', 'status' => 'completed'],
            ],
        ], 'synchronized');
        $unknown = $service->classify_sync_files($local, [
            'files' => [
                ['filename' => 'notes.pdf', 'status' => 'error'],
            ],
        ], 'synchronized');

        $this->assertSame('problem', $bare[0]['state']);
        $this->assertSame('error', $bare[0]['reason']);
        $this->assertSame('synced', $member[0]['state']);
        $this->assertSame('synced', $unknown[0]['state']);
    }

    /**
     * During a sync, only confirmed managed files and local problems are listed.
     */
    public function test_classify_sync_files_omits_unconfirmed_files_while_syncing(): void {
        $this->resetAfterTest();
        $service = new file_sync_service();

        $rows = $service->classify_sync_files([
            ['filename' => 'done.pdf', 'size' => 100],
            ['filename' => 'waiting.pdf', 'size' => 100],
            ['filename' => 'huge.pdf', 'size' => (20 * 1024 * 1024) + 1],
            ['filename' => 'photo.jpg', 'size' => 10],
        ], [
            ['filename' => 'done.pdf', 'status' => 'completed'],
            ['filename' => 'waiting.pdf', 'status' => 'pending'],
        ], 'syncing');

        $this->assertSame([
            ['filename' => 'done.pdf', 'state' => 'synced', 'reason' => ''],
            ['filename' => 'huge.pdf', 'state' => 'problem', 'reason' => 'toolarge'],
            ['filename' => 'photo.jpg', 'state' => 'problem', 'reason' => 'unmanaged'],
        ], $rows);
    }

    /**
     * Course files are listed from visible resources and folders. A failed list call keeps the local rows.
     */
    public function test_list_status_files_reads_the_course_and_ignores_a_failed_remote_list(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('resource', [
            'course' => $course->id,
            'defaultfilename' => 'notes.pdf',
        ]);
        $this->add_folder_file($course, 'photo.jpg', 'jpeg-body');
        $this->getDataGenerator()->create_module('resource', [
            'course' => $course->id,
            'visible' => 0,
            'defaultfilename' => 'hidden.pdf',
        ]);

        $client = $this->createMock(client::class);
        $client->method('is_configured')->willReturn(true);
        $client->method('list_files')->willThrowException(new api_exception('server_error', 'upstream 503', 503));

        $service = new file_sync_service(null, $client);
        $rows = $service->list_status_files((int) $course->id, 'synchronized');

        $this->assertSame([
            ['filename' => 'notes.pdf', 'state' => 'synced', 'reason' => ''],
            ['filename' => 'photo.jpg', 'state' => 'problem', 'reason' => 'unmanaged'],
        ], $rows);
    }

    /**
     * Attach a file to a new folder.
     *
     * @param \stdClass $course Course.
     * @param string $filename File name.
     * @param string $content File body.
     */
    private function add_folder_file(\stdClass $course, string $filename, string $content): void {
        $folder = $this->getDataGenerator()->create_module('folder', ['course' => $course->id]);
        $this->store_module_file($folder->cmid, 'mod_folder', $filename, $content);
    }

    /**
     * Store one file in a module content area.
     *
     * @param int $cmid Course module id.
     * @param string $component File component.
     * @param string $filename File name.
     * @param string $content File body.
     */
    private function store_module_file(int $cmid, string $component, string $filename, string $content): void {
        $context = \context_module::instance($cmid);
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => $component,
            'filearea' => 'content',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
        ], $content);
    }
}
