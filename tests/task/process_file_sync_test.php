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
 * Tests for the course file sync task retry.
 *
 * A failed sync queues its next attempt right away, delayed by the course backoff.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\task;

use local_dixeo\api\client;
use local_dixeo\api\exception\api_exception;
use local_dixeo\external\service_factory;
use local_dixeo\repository\course_ai_repository;
use local_dixeo\service\file_sync_service;

/**
 * Retry scheduling of process_file_sync.
 *
 * @covers \local_dixeo\task\process_file_sync
 */
final class process_file_sync_test extends \advanced_testcase {
    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    public function test_failed_sync_queues_a_delayed_retry_until_the_backoff_runs_out(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);

        $repository = new course_ai_repository();
        $repository->create($course->id, get_admin()->id);
        $repository->set_enabled($course->id, true, get_admin()->id);

        $client = $this->createMock(client::class);
        $client->method('is_configured')->willReturn(true);
        $client->method('upload_files')->willThrowException(new api_exception('server_error', 'down', 500));
        service_factory::set_test_file_sync_service(new file_sync_service($repository, $client));

        $classname = '\\local_dixeo\\task\\process_file_sync';
        $expected = [1 => 300, 2 => 900, 3 => 3600, 4 => null];
        foreach ($expected as $attempt => $delay) {
            $DB->delete_records('task_adhoc', ['classname' => $classname]);
            $task = new process_file_sync();
            $task->set_custom_data((object) ['courseid' => $course->id]);

            $before = time();
            ob_start();
            $task->execute();
            ob_end_clean();
            $this->resetDebugging();

            $this->assertSame($attempt, (int) $repository->get_by_courseid($course->id)->errorcount);
            $queued = \core\task\manager::get_adhoc_tasks($classname);
            if ($delay === null) {
                $this->assertSame([], $queued, "Attempt {$attempt} must not queue a retry");
                continue;
            }
            $this->assertCount(1, $queued, "Attempt {$attempt} must queue a retry");
            $this->assertGreaterThanOrEqual($before + $delay, (int) reset($queued)->get_next_run_time());
        }
    }
}
