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
 * Tests for the activity source id sent with each synced file.
 *
 * The tutor cites the activities its answers come from, so the manifest names the
 * course module of every file.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\api\client;
use local_dixeo\repository\course_ai_repository;
use local_dixeo\service\file_sync_service;

/**
 * Tests for the sourceId of the upload manifest.
 *
 * @covers \local_dixeo\service\file_sync_service::trigger_sync
 */
final class file_sync_service_source_test extends \advanced_testcase {
    public function test_trigger_sync_sends_the_activity_of_each_file(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $first = $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);
        $second = $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);

        $repository = new course_ai_repository();
        $repository->create($course->id, get_admin()->id);
        $repository->set_enabled($course->id, true, get_admin()->id);

        $manifest = null;
        $mockclient = $this->createMock(client::class);
        $mockclient->expects($this->once())
            ->method('upload_files')
            ->willReturnCallback(function (
                string $courseid,
                array $files,
                ?string $namespace,
                ?callable $progress,
                bool $finalchunk,
                ?array $expectedfiles
            ) use (&$manifest): array {
                $manifest = $expectedfiles;
                return ['uploaded' => count($files)];
            });
        $mockclient->method('get_files_status')->willReturn(['status' => 'synchronized']);

        (new file_sync_service($repository, $mockclient))->trigger_sync($course->id);

        $this->assertSame(
            [(string) $first->cmid, (string) $second->cmid],
            array_column($manifest, 'sourceId')
        );
    }
}
