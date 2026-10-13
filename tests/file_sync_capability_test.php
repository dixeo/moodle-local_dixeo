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
 * Tests for local/dixeo:syncfiles on file sync externals.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\api\client;
use local_dixeo\external\service_factory;
use local_dixeo\external\set_file_sync_enabled;
use local_dixeo\external\trigger_file_sync;
use local_dixeo\repository\course_ai_repository;
use local_dixeo\service\file_sync_service;

/**
 * Capability checks for enable/trigger file sync.
 *
 * @covers \local_dixeo\external\trigger_file_sync
 * @covers \local_dixeo\external\set_file_sync_enabled
 * @covers \local_dixeo\external\traits\capability_check
 * @covers \local_dixeo\service\file_sync_service::enable_and_queue_sync_after_module_creation
 */
final class file_sync_capability_test extends \advanced_testcase {
    /**
     * Reset factory mocks after each test.
     */
    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * Deny syncfiles for editingteacher in a course (overrides archetype allow).
     *
     * @param \context_course $context Course context.
     * @return void
     */
    private function deny_syncfiles_for_editingteacher(\context_course $context): void {
        global $DB;

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('local/dixeo:syncfiles', CAP_PROHIBIT, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
    }

    /**
     * Editing teacher with generate but without syncfiles cannot trigger sync.
     */
    public function test_trigger_denied_without_syncfiles(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $context = \context_course::instance($course->id);
        $this->deny_syncfiles_for_editingteacher($context);
        $this->setUser($teacher);

        $this->assertTrue(has_capability('local/dixeo:generate', $context));
        $this->assertFalse(has_capability('local/dixeo:syncfiles', $context));

        $this->expectException(\required_capability_exception::class);
        trigger_file_sync::execute($course->id);
    }

    /**
     * Editing teacher with generate but without syncfiles cannot enable sync.
     */
    public function test_set_enabled_denied_without_syncfiles(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $context = \context_course::instance($course->id);
        $this->deny_syncfiles_for_editingteacher($context);
        $this->setUser($teacher);

        $this->expectException(\required_capability_exception::class);
        set_file_sync_enabled::execute($course->id, true, false);
    }

    /**
     * User with syncfiles can trigger sync.
     */
    public function test_trigger_allowed_with_syncfiles(): void {
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

    /**
     * Creating a module does not turn file sync on.
     */
    public function test_enable_and_queue_sync_after_module_creation_does_not_enable(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $context = \context_course::instance($course->id);
        $this->deny_syncfiles_for_editingteacher($context);
        $this->setUser($teacher);

        $repository = new course_ai_repository();
        $service = new file_sync_service($repository);
        $service->enable_and_queue_sync_after_module_creation($course->id);

        $this->assertNull($repository->get_by_courseid($course->id));
    }

    /**
     * An already-on course is queued for refresh without local/dixeo:syncfiles.
     */
    public function test_enable_and_queue_sync_after_module_creation_queues_when_already_on(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $context = \context_course::instance($course->id);
        $this->deny_syncfiles_for_editingteacher($context);
        $this->setUser($teacher);

        $repository = new course_ai_repository();
        $repository->create($course->id, $teacher->id);
        $repository->set_enabled($course->id, true, $teacher->id);

        $service = new file_sync_service($repository);
        $service->enable_and_queue_sync_after_module_creation($course->id);

        $tasks = \core\task\manager::get_adhoc_tasks('\\local_dixeo\\task\\process_file_sync');
        $found = false;
        foreach ($tasks as $task) {
            $data = $task->get_custom_data();
            if (isset($data->courseid) && (int) $data->courseid === (int) $course->id) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
        $this->assertSame(1, (int) $repository->get_by_courseid($course->id)->enabled);
    }
}
