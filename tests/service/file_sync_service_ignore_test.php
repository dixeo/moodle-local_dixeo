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
 * Tests for files the sync ignores instead of failing the course.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\dto\file_upload_part;
use local_dixeo\service\file_sync_service;

/**
 * Unit tests for ignored upload items and non-fatal API rejections.
 *
 * @covers \local_dixeo\service\file_sync_service::select_managed_files
 * @covers \local_dixeo\service\file_sync_service::fatal_api_rejections
 */
final class file_sync_service_ignore_test extends \advanced_testcase {
    /** @var string[] Absolute paths of temp files to delete in tearDown. */
    private array $tempfiles = [];

    protected function tearDown(): void {
        foreach ($this->tempfiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $this->tempfiles = [];
        parent::tearDown();
    }

    /**
     * A file over 20 MB, and an empty file, never reach the chunks or the manifest.
     *
     * A 12 MB file is still uploaded: it is over the 8 MB chunk budget and under the API cap.
     */
    public function test_oversized_and_empty_files_are_excluded_from_chunks_and_manifest(): void {
        $this->resetAfterTest();
        $service = new file_sync_service();

        $small = $this->make_sized_part('notes.txt', 64);
        $withinlimit = $this->make_sized_part('slides.pptx', 12 * 1024 * 1024);
        $huge = $this->make_sized_part('huge.pdf', (20 * 1024 * 1024) + 1);
        $blank = $this->make_sized_part('blank.txt', 0);

        $managed = $service->select_managed_files([$small, $withinlimit, $huge, $blank]);

        $filenames = [];
        foreach ($managed as $part) {
            $filenames[] = $part->filename;
        }
        self::assertSame(['notes.txt', 'slides.pptx'], $filenames);

        $chunks = $service->build_chunks($managed);
        self::assertCount(2, $chunks);
        self::assertSame('notes.txt', $chunks[0][0]->filename);
        self::assertSame('slides.pptx', $chunks[1][0]->filename);

        $manifest = $service->compute_expected_files($managed, [11, 22, 33, 44]);
        self::assertSame(
            [
                ['hash' => hash_file('sha256', $small->path), 'filename' => 'notes.txt', 'sourceId' => '11'],
                ['hash' => hash_file('sha256', $withinlimit->path), 'filename' => 'slides.pptx', 'sourceId' => '22'],
            ],
            $manifest
        );

        $messages = [];
        foreach ($this->getDebuggingMessages() as $debug) {
            $messages[] = $debug->message;
        }
        self::assertCount(2, $messages);
        self::assertStringContainsString('huge.pdf', $messages[0]);
        self::assertStringContainsString('too large', $messages[0]);
        self::assertStringContainsString('blank.txt', $messages[1]);
        self::assertStringContainsString('empty', $messages[1]);
        $this->resetDebugging();
    }

    /**
     * A size rejection, and a rejection with no code, do not fail the course sync.
     */
    public function test_file_too_large_rejection_is_not_a_course_failure(): void {
        $this->resetAfterTest();
        $service = new file_sync_service();

        $toolarge = [
            'filename' => 'huge.pdf',
            'code' => 'FILE_TOO_LARGE',
            'reason' => 'File exceeds maximum size of 20 MB',
        ];
        $nocode = [
            'filename' => 'notes.pdf',
            'reason' => 'rejected',
        ];

        self::assertSame([], $service->fatal_api_rejections([$toolarge, $nocode, 'skip']));
    }

    /**
     * UPLOAD_FAILED is retried on a later sync and does not fail the course. An unknown code still does.
     */
    public function test_only_an_unknown_rejection_code_fails_the_course_sync(): void {
        $this->resetAfterTest();
        $service = new file_sync_service();

        $toolarge = [
            'filename' => 'huge.pdf',
            'code' => 'FILE_TOO_LARGE',
            'reason' => 'File exceeds maximum size of 20 MB',
        ];
        $uploadfailed = [
            'filename' => 'notes.pdf',
            'code' => 'UPLOAD_FAILED',
            'reason' => 'provider error',
        ];
        $unknown = [
            'filename' => 'other.pdf',
            'code' => 'SOMETHING_NEW',
            'reason' => 'rejected',
        ];

        self::assertSame(
            [$unknown],
            $service->fatal_api_rejections([$toolarge, $uploadfailed, $unknown])
        );
    }

    /**
     * Build a file_upload_part whose on-disk size is $size without writing that many bytes.
     *
     * @param string $filename
     * @param int $size
     * @return file_upload_part
     */
    private function make_sized_part(string $filename, int $size): file_upload_part {
        $base = tempnam(sys_get_temp_dir(), 'dixeo_ignore_');
        if ($base === false) {
            throw new \RuntimeException('tempnam failed');
        }
        $path = $base . '_' . $filename;
        rename($base, $path);
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('fopen failed');
        }
        if ($size > 0 && ftruncate($handle, $size) === false) {
            fclose($handle);
            throw new \RuntimeException('ftruncate failed');
        }
        fclose($handle);
        $this->tempfiles[] = $path;
        return new file_upload_part($path, $filename);
    }
}
