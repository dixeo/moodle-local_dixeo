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
 * Tests for files a sync skips without failing the course.
 *
 * A file whose upload fails is retried on a later sync; a file the API rejects
 * for good is not sent again until it changes.
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
use local_dixeo\dto\file_upload_part;
use local_dixeo\repository\course_ai_repository;
use local_dixeo\service\file_sync_service;

/**
 * Unit tests for skipped files during a course sync.
 *
 * @covers \local_dixeo\service\file_sync_service
 */
final class file_sync_service_skip_test extends \advanced_testcase {
    /** @var string[] Absolute paths of temp files to delete in tearDown. */
    private array $tempfiles = [];

    /** @var array[] Upload calls: filenames, final flag, manifest filenames. */
    private array $calls = [];

    protected function tearDown(): void {
        foreach ($this->tempfiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $this->tempfiles = [];
        $this->calls = [];
        parent::tearDown();
    }

    public function test_order_largest_first_keeps_keys_and_ties(): void {
        $this->resetAfterTest();
        $service = new file_sync_service();

        $items = [
            3 => $this->make_part('a.txt', 10),
            7 => $this->make_part('b.txt', 30),
            1 => $this->make_part('c.txt', 20),
            5 => $this->make_part('d.txt', 30),
        ];

        $ordered = $service->order_largest_first($items);

        $this->assertSame([7, 5, 1, 3], array_keys($ordered));
    }

    public function test_largest_file_goes_first_and_smallest_ride_the_final_chunk(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course] = $this->create_course_with_files(['small.txt' => 10, 'big.txt' => 3000, 'mid.txt' => 200]);

        $service = new file_sync_service(new course_ai_repository(), $this->mock_client([]));
        $service->trigger_sync($course->id);

        $this->assertCount(1, $this->calls);
        $this->assertSame(['big.txt', 'mid.txt', 'small.txt'], $this->calls[0]['files']);
    }

    public function test_failed_one_file_chunk_is_skipped_and_retried_later(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course] = $this->create_course_with_files(['big.pdf' => 9 * 1024 * 1024, 'notes.txt' => 100]);

        $repository = new course_ai_repository();
        $service = new file_sync_service($repository, $this->mock_client(['big.pdf' => 413]));
        $service->trigger_sync($course->id);
        $this->assertDebuggingCalled();

        // The second chunk still carries the manifest, with the skipped file in it.
        $this->assertCount(2, $this->calls);
        $this->assertTrue($this->calls[1]['final']);
        $this->assertSame(['notes.txt'], $this->calls[1]['files']);
        $this->assertSame(['big.pdf', 'notes.txt'], $this->calls[1]['manifest']);

        $skipped = $repository->get_skipped_files($course->id);
        $this->assertCount(1, $skipped);
        $this->assertSame('big.pdf', $skipped[0]['filename']);
        $this->assertSame('retry', $skipped[0]['reason']);
        $this->assertTrue($skipped[0]['retry']);
        $this->assertSame(1, (int) $repository->get_by_courseid($course->id)->errorcount);
        $this->assertSame('synchronized', $service->get_status($course->id)->status);
        $this->assertSame(1, $service->get_status($course->id)->filesskipped);

        $tasks = \core\task\manager::get_adhoc_tasks('\\local_dixeo\\task\\process_file_sync');
        $this->assertCount(1, $tasks);
        $this->assertGreaterThan(time() + 200, (int) reset($tasks)->get_next_run_time());

        // The course is not up to date: the next sync sends the files again.
        $this->calls = [];
        $service->trigger_sync($course->id);
        $this->assertDebuggingCalled();
        $this->assertCount(2, $this->calls);
        $this->assertSame(2, (int) $repository->get_by_courseid($course->id)->errorcount);
        $this->assertSame('synchronized', $service->get_status($course->id)->status);

        // A clean sync clears the list and the backoff count.
        $service = new file_sync_service($repository, $this->mock_client([]));
        $service->trigger_sync($course->id);
        $this->assertSame([], $repository->get_skipped_files($course->id));
        $this->assertSame(0, (int) $repository->get_by_courseid($course->id)->errorcount);
    }

    public function test_skipped_final_chunk_lets_an_accepted_file_carry_the_manifest(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course] = $this->create_course_with_files(['first.pdf' => 5 * 1024 * 1024, 'second.pdf' => 4 * 1024 * 1024]);

        $service = new file_sync_service(new course_ai_repository(), $this->mock_client(['second.pdf' => 504]));
        $service->trigger_sync($course->id);
        $this->assertDebuggingCalled();

        $this->assertCount(3, $this->calls);
        $this->assertFalse($this->calls[0]['final']);
        $this->assertTrue($this->calls[1]['final']);
        $this->assertSame(['first.pdf'], $this->calls[2]['files']);
        $this->assertTrue($this->calls[2]['final']);
        $this->assertSame(['first.pdf', 'second.pdf'], $this->calls[2]['manifest']);
        $this->assertSame('synchronized', $service->get_status($course->id)->status);
    }

    public function test_sync_fails_when_no_chunk_reaches_the_api(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course] = $this->create_course_with_files(['only.pdf' => 100]);

        $repository = new course_ai_repository();
        $service = new file_sync_service($repository, $this->mock_client(['only.pdf' => 413]));
        try {
            $service->trigger_sync($course->id);
            $this->fail('Expected api_exception');
        } catch (api_exception $e) {
            $this->assertSame(413, $e->get_http_status());
        }
        $this->assertDebuggingCalled();

        $this->assertSame('error', $service->get_status($course->id)->status);
        $this->assertSame('retry', $repository->get_skipped_files($course->id)[0]['reason']);
    }

    public function test_multi_file_chunk_failure_still_fails_the_sync(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course] = $this->create_course_with_files(['a.txt' => 10, 'b.txt' => 20]);

        $service = new file_sync_service(new course_ai_repository(), $this->mock_client(['a.txt' => 500]));

        $this->expectException(api_exception::class);
        $service->trigger_sync($course->id);
    }

    public function test_upload_failed_is_retried_but_a_permanent_rejection_is_not_sent_again(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $cms] = $this->create_course_with_files(['bad.txt' => 30, 'flaky.txt' => 20, 'good.txt' => 10]);

        $repository = new course_ai_repository();
        $rejections = [
            ['filename' => 'bad.txt', 'code' => 'MIME_TYPE_MISMATCH', 'reason' => 'mismatch'],
            ['filename' => 'flaky.txt', 'code' => 'UPLOAD_FAILED', 'reason' => 'provider error'],
        ];
        $service = new file_sync_service($repository, $this->mock_client([], $rejections));
        $service->trigger_sync($course->id);
        $this->resetDebugging();

        $reasons = array_column($repository->get_skipped_files($course->id), 'reason', 'filename');
        $this->assertSame(['bad.txt' => 'unsupported', 'flaky.txt' => 'retry'], $reasons);
        $this->assertSame('synchronized', $service->get_status($course->id)->status);

        $rows = array_column($service->classify_sync_files(
            [
                ['filename' => 'bad.txt', 'size' => 30],
                ['filename' => 'flaky.txt', 'size' => 20],
                ['filename' => 'good.txt', 'size' => 10],
            ],
            null,
            'synchronized',
            0,
            0,
            $repository->get_skipped_files($course->id)
        ), null, 'filename');
        $this->assertSame('problem', $rows['flaky.txt']['state']);
        $this->assertSame('retry', $rows['flaky.txt']['reason']);
        $this->assertSame('unsupported', $rows['bad.txt']['reason']);
        $this->assertSame('synced', $rows['good.txt']['state']);

        // A retry sends the flaky file again but not the rejected one, which stays listed.
        $this->calls = [];
        $service = new file_sync_service($repository, $this->mock_client([]));
        $service->trigger_sync($course->id);
        $this->assertSame(['flaky.txt', 'good.txt'], $this->calls[0]['files']);
        $this->assertSame(['bad.txt', 'flaky.txt', 'good.txt'], $this->calls[0]['manifest']);
        $reasons = array_column($repository->get_skipped_files($course->id), 'reason', 'filename');
        $this->assertSame(['bad.txt' => 'unsupported'], $reasons);
        $this->assertCount(3, $cms);

        // Without files to retry, an unchanged course is not sent again.
        $this->calls = [];
        $service->trigger_sync($course->id);
        $this->assertSame([], $this->calls);

        // A forced sync sends it all again, the rejected file included.
        $service->trigger_sync($course->id, true);
        $this->assertSame(['bad.txt', 'flaky.txt', 'good.txt'], $this->calls[0]['files']);
        $this->assertSame([], $repository->get_skipped_files($course->id));
    }

    public function test_ensure_does_not_resync_or_verify_while_files_wait_for_a_retry(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course] = $this->create_course_with_files(['big.pdf' => 9 * 1024 * 1024, 'notes.txt' => 100]);

        $repository = new course_ai_repository();
        $service = new file_sync_service($repository, $this->mock_client(['big.pdf' => 413]));
        $service->trigger_sync($course->id);
        $this->assertDebuggingCalled();

        $this->calls = [];
        $service->ensure_enabled_and_synchronized($course->id, (int) get_admin()->id, 5);

        $this->assertSame([], $this->calls);
        $this->assertFalse(\cache::make('local_dixeo', 'filesyncverified')->get((string) $course->id));
    }

    /**
     * Course with sync on and one resource per file, each holding only that file.
     *
     * @param int[] $files Size by filename.
     * @return array [course, cm list]
     */
    private function create_course_with_files(array $files): array {
        $course = $this->getDataGenerator()->create_course();
        $fs = get_file_storage();
        $cms = [];
        foreach ($files as $filename => $size) {
            $cm = $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);
            $context = \context_module::instance($cm->cmid);
            $fs->delete_area_files($context->id, 'mod_resource', 'content');
            $fs->create_file_from_string([
                'contextid' => $context->id,
                'component' => 'mod_resource',
                'filearea' => 'content',
                'itemid' => 0,
                'filepath' => '/',
                'filename' => $filename,
            ], str_repeat('x', $size));
            $cms[] = $cm;
        }

        $repository = new course_ai_repository();
        $repository->create($course->id, get_admin()->id);
        $repository->set_enabled($course->id, true, get_admin()->id);
        return [$course, $cms];
    }

    /**
     * API client stub recording each upload call.
     *
     * @param int[] $failures HTTP status thrown for any chunk holding that filename.
     * @param array[] $rejections failedFiles entries returned by the chunk holding each file.
     * @return client
     */
    private function mock_client(array $failures, array $rejections = []): client {
        $mock = $this->createMock(client::class);
        $mock->method('is_configured')->willReturn(true);
        $mock->method('get_files_status')->willReturn(['status' => 'synchronized']);
        $mock->method('upload_files')->willReturnCallback(function (
            string $courseid,
            array $files,
            ?string $namespace,
            ?callable $progress,
            bool $finalchunk,
            ?array $expectedfiles
        ) use (
            $failures,
            $rejections
        ): array {
            $names = array_map(static fn($file): string => $file->get_filename(), $files);
            $this->calls[] = [
                'files' => $names,
                'final' => $finalchunk,
                'manifest' => $expectedfiles === null ? null : array_column($expectedfiles, 'filename'),
            ];
            foreach ($names as $name) {
                if (isset($failures[$name])) {
                    throw new api_exception('unknown_error', 'HTTP ' . $failures[$name], $failures[$name]);
                }
            }
            $failed = array_values(array_filter(
                $rejections,
                static fn(array $entry): bool => in_array($entry['filename'], $names, true)
            ));
            return ['uploaded' => count($files), 'failedFiles' => $failed];
        });
        return $mock;
    }

    /**
     * Build a file_upload_part backed by a temp file of the given size.
     *
     * @param string $filename
     * @param int $size
     * @return file_upload_part
     */
    private function make_part(string $filename, int $size): file_upload_part {
        $base = tempnam(sys_get_temp_dir(), 'dixeo_skiptest_');
        if ($base === false) {
            throw new \RuntimeException('tempnam failed');
        }
        $path = $base . '_' . $filename;
        rename($base, $path);
        file_put_contents($path, str_repeat('x', $size));
        $this->tempfiles[] = $path;
        return new file_upload_part($path, $filename);
    }
}
