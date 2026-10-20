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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Tests for file sync opt-in defaults and pre-RAG synchronization.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\api\client;
use local_dixeo\external\service_factory;
use local_dixeo\external\trigger_file_sync;
use local_dixeo\repository\course_ai_repository;
use local_dixeo\service\file_sync_service;

/**
 * Unit tests for file sync service opt in.
 *
 * @covers \local_dixeo\service\file_sync_service
 * @covers \local_dixeo\external\trigger_file_sync
 */
final class file_sync_service_opt_in_test extends \advanced_testcase {
    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    public function test_is_enabled_false_when_row_missing(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $service = new file_sync_service();

        $this->assertFalse($service->is_enabled($course->id));
    }

    public function test_create_starts_disabled_until_opt_in(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $repository = new course_ai_repository();

        $record = $repository->create($course->id, $user->id);

        $this->assertSame(0, (int) $record->enabled);

        $stored = $repository->get_by_courseid($course->id);
        $this->assertNull($stored->enabledby);
        $this->assertNull($stored->enabledat);
    }

    public function test_enable_sync_always_sets_enabled_true(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $context = \context_course::instance($course->id);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('local/dixeo:syncfiles', CAP_ALLOW, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($user);

        $repository = new course_ai_repository();
        $service = new file_sync_service($repository);

        $service->enable_sync($course->id, $user->id);

        $record = $repository->get_by_courseid($course->id);
        $this->assertNotNull($record);
        $this->assertSame(1, (int) $record->enabled);
        $this->assertSame((int) $user->id, (int) $record->enabledby);
    }

    public function test_enable_sync_ignored_without_syncfiles(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $repository = new course_ai_repository();
        $service = new file_sync_service($repository);

        $service->enable_sync($course->id, (int) $student->id);

        $this->assertNull($repository->get_by_courseid($course->id));
        $this->assertFalse($service->is_enabled($course->id));
    }

    public function test_ensure_enabled_does_not_turn_sync_on(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $context = \context_course::instance($course->id);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('local/dixeo:syncfiles', CAP_ALLOW, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($user);

        $repository = new course_ai_repository();
        $service = new file_sync_service($repository, $this->createMock(client::class));
        $service->ensure_enabled_and_synchronized($course->id, (int) $user->id);

        $this->assertFalse($service->is_enabled($course->id));
        $this->assertNull($repository->get_by_courseid($course->id));
    }

    public function test_ensure_enabled_and_synchronized_waits_for_synchronized_status(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $context = \context_course::instance($course->id);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('local/dixeo:syncfiles', CAP_ALLOW, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($user);

        $mockclient = $this->createMock(client::class);
        $mockclient->method('delete_files')->willReturn([]);
        $mockclient->method('get_files_status')->willReturn([
            'status' => 'synchronized',
            'fileCount' => 0,
            'syncedCount' => 0,
            'progress' => ['filesTotal' => 0, 'filesCompleted' => 0, 'percent' => 100],
        ]);

        $service = new file_sync_service(null, $mockclient);
        $service->enable_sync($course->id, (int) $user->id);
        $service->ensure_enabled_and_synchronized($course->id, (int) $user->id);

        $status = $service->get_status($course->id);
        $this->assertTrue($status->enabled);
        $this->assertSame('synchronized', $status->status);
    }

    public function test_trigger_file_sync_leaves_a_paused_course_off(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $context = \context_course::instance($course->id);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('local/dixeo:syncfiles', CAP_ALLOW, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($user);

        $repository = new course_ai_repository();
        $repository->create($course->id, $user->id);
        $repository->set_enabled($course->id, false, $user->id);

        $mockclient = $this->createMock(client::class);
        $mockclient->method('delete_files')->willReturn([]);
        $mockclient->method('get_files_status')->willReturn([
            'status' => 'synchronized',
            'fileCount' => 0,
            'syncedCount' => 0,
            'progress' => ['filesTotal' => 0, 'filesCompleted' => 0, 'percent' => 100],
        ]);

        $service = new file_sync_service($repository, $mockclient);
        service_factory::set_test_file_sync_service($service);

        $result = trigger_file_sync::execute($course->id);

        $this->assertTrue($result['success']);
        $record = $repository->get_by_courseid($course->id);
        $this->assertSame(0, (int) $record->enabled);
    }
}
