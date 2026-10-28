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
 * Tests for the chunking + expected-file manifest logic used by the upload pipeline.
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
 * Unit tests for file sync service chunk.
 *
 * @covers \local_dixeo\service\file_sync_service::build_chunks
 * @covers \local_dixeo\service\file_sync_service::compute_expected_files
 */
final class file_sync_service_chunk_test extends \advanced_testcase {
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

    public function test_build_chunks_keeps_single_small_batch_intact(): void {
        $this->resetAfterTest();
        $service = new file_sync_service();

        $items = [
            $this->make_part('a.txt', 1024),
            $this->make_part('b.txt', 1024),
            $this->make_part('c.txt', 1024),
        ];

        $chunks = $service->build_chunks($items);

        self::assertCount(1, $chunks);
        self::assertCount(3, $chunks[0]);
    }

    public function test_build_chunks_splits_when_file_count_exceeds_limit(): void {
        $this->resetAfterTest();
        $service = new file_sync_service();

        $items = [];
        for ($i = 0; $i < 23; $i++) {
            $items[] = $this->make_part("f{$i}.txt", 1024);
        }

        $chunks = $service->build_chunks($items);

        self::assertCount(3, $chunks);
        self::assertCount(10, $chunks[0]);
        self::assertCount(10, $chunks[1]);
        self::assertCount(3, $chunks[2]);
    }

    public function test_build_chunks_splits_when_byte_budget_exceeded(): void {
        $this->resetAfterTest();
        $service = new file_sync_service();

        // 3 MB × 2 fits in a single 8 MB chunk, but adding a 3rd
        // forces a split even though we're well under the 10-file limit.
        $items = [
            $this->make_part('big-1.txt', 3 * 1024 * 1024),
            $this->make_part('big-2.txt', 3 * 1024 * 1024),
            $this->make_part('big-3.txt', 3 * 1024 * 1024),
        ];

        $chunks = $service->build_chunks($items);

        self::assertCount(2, $chunks);
        self::assertCount(2, $chunks[0]);
        self::assertCount(1, $chunks[1]);
    }

    public function test_build_chunks_keeps_single_oversized_file_alone(): void {
        $this->resetAfterTest();
        $service = new file_sync_service();

        // A 12 MB file exceeds the 8 MB budget on its own. We still
        // send it as a one-item chunk rather than rejecting the sync.
        $items = [$this->make_part('huge.txt', 12 * 1024 * 1024)];

        $chunks = $service->build_chunks($items);

        self::assertCount(1, $chunks);
        self::assertCount(1, $chunks[0]);
    }

    public function test_build_chunks_handles_empty_payload(): void {
        $this->resetAfterTest();
        $service = new file_sync_service();

        self::assertSame([], $service->build_chunks([]));
    }

    public function test_compute_expected_files_matches_hash_filename_and_source(): void {
        $this->resetAfterTest();
        $service = new file_sync_service();

        $apath = $this->write_temp('alpha.txt', 'alpha');
        $bpath = $this->write_temp('beta.txt', 'beta');
        $items = [
            new file_upload_part($apath, 'alpha.txt'),
            new file_upload_part($bpath, 'beta.txt'),
        ];

        $manifest = $service->compute_expected_files($items, [12, 34]);

        self::assertSame(
            [
                ['hash' => hash('sha256', 'alpha'), 'filename' => 'alpha.txt', 'sourceId' => '12'],
                ['hash' => hash('sha256', 'beta'), 'filename' => 'beta.txt', 'sourceId' => '34'],
            ],
            $manifest
        );
    }

    /**
     * Build a file_upload_part backed by a temp file of the given size.
     *
     * @param string $filename
     * @param int $size
     * @return file_upload_part
     */
    private function make_part(string $filename, int $size): file_upload_part {
        $path = $this->write_temp($filename, str_repeat('x', $size));
        return new file_upload_part($path, $filename);
    }

    /**
     * Write content to a uniquely named temp file tracked for cleanup.
     *
     * @param string $filename
     * @param string $content
     * @return string Absolute path to the temp file.
     */
    private function write_temp(string $filename, string $content): string {
        $base = tempnam(sys_get_temp_dir(), 'dixeo_chunktest_');
        if ($base === false) {
            throw new \RuntimeException('tempnam failed');
        }
        $path = $base . '_' . $filename;
        rename($base, $path);
        file_put_contents($path, $content);
        $this->tempfiles[] = $path;
        return $path;
    }
}
