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
 * Tests for the Dixeo master switch on file sync entry points.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\external\service_factory;
use local_dixeo\repository\course_ai_repository;
use local_dixeo\service\file_sync_service;
use local_dixeo\settings\config;

/**
 * Dixeo off stops sync, and the new-course default turns it on.
 *
 * @covers \local_dixeo\service\file_sync_service
 * @covers \local_dixeo\observer\file_sync_observer
 */
final class file_sync_service_gate_test extends \advanced_testcase {
    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    public function test_enable_sync_does_nothing_when_dixeo_is_off(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->teacher_with_syncfiles($course);
        $this->turn_dixeo_off();
        $this->setUser($user);

        $repository = new course_ai_repository();
        (new file_sync_service($repository))->enable_sync($course->id, $user->id);

        $this->assertNull($repository->get_by_courseid($course->id));
    }

    public function test_enable_sync_does_nothing_when_the_course_is_off(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->teacher_with_syncfiles($course);
        $this->setAdminUser();
        (new config())->set('local_dixeo', 'enabled', \context_course::instance($course->id), '0');
        $this->setUser($user);

        $repository = new course_ai_repository();
        (new file_sync_service($repository))->enable_sync($course->id, $user->id);

        $this->assertNull($repository->get_by_courseid($course->id));
    }

    public function test_opt_in_does_nothing_when_dixeo_is_off(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->teacher_with_syncfiles($course);
        $this->turn_dixeo_off();

        $repository = new course_ai_repository();
        (new file_sync_service($repository))->opt_in_on_block_added($course->id, $user->id);

        $this->assertNull($repository->get_by_courseid($course->id));
        $this->assertFalse($this->sync_task_queued($course->id));
    }

    public function test_queue_and_module_change_do_nothing_when_dixeo_is_off(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->teacher_with_syncfiles($course);
        $this->setUser($user);
        $repository = new course_ai_repository();
        $service = new file_sync_service($repository);
        $service->enable_sync($course->id, $user->id);
        $this->turn_dixeo_off();

        $service->queue_sync($course->id);
        $service->mark_outdated($course->id);
        $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);

        $record = $repository->get_by_courseid($course->id);
        $this->assertSame(1, (int) $record->enabled);
        $this->assertSame('none', $record->syncstatus);
        $this->assertFalse($this->sync_task_queued($course->id));
    }

    public function test_module_change_still_queues_when_dixeo_is_on(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->teacher_with_syncfiles($course);
        $this->setUser($user);
        $repository = new course_ai_repository();
        (new file_sync_service($repository))->enable_sync($course->id, $user->id);

        $this->getDataGenerator()->create_module('resource', ['course' => $course->id]);

        $record = $repository->get_by_courseid($course->id);
        $this->assertSame('outdated', $record->syncstatus);
        $this->assertTrue($this->sync_task_queued($course->id));
    }

    public function test_ensure_returns_without_enabling_when_dixeo_is_off(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->teacher_with_syncfiles($course);
        $this->turn_dixeo_off();

        (new file_sync_service())->ensure_enabled_and_synchronized($course->id, $user->id);

        $this->assertFalse((new file_sync_service())->is_enabled($course->id));
    }

    public function test_new_course_stays_off_when_the_default_is_off(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        $this->assertFalse((new file_sync_service())->is_enabled($course->id));
    }

    public function test_new_course_turns_sync_on_when_the_default_is_on(): void {
        $this->resetAfterTest();
        $this->set_new_course_default_on();

        $creator = $this->getDataGenerator()->create_user();
        $this->setUser($creator);
        $course = $this->getDataGenerator()->create_course();

        $record = (new course_ai_repository())->get_by_courseid($course->id);
        $this->assertNotNull($record);
        $this->assertSame(1, (int) $record->enabled);
        $this->assertSame((int) $creator->id, (int) $record->enabledby);
    }

    public function test_new_course_stays_off_when_dixeo_is_off(): void {
        $this->resetAfterTest();
        $this->set_new_course_default_on();
        $this->turn_dixeo_off();

        $course = $this->getDataGenerator()->create_course();

        $this->assertFalse((new file_sync_service())->is_enabled($course->id));
    }

    /**
     * Enrol an editing teacher who may enable file sync.
     *
     * @param \stdClass $course The course.
     * @return \stdClass
     */
    private function teacher_with_syncfiles(\stdClass $course): \stdClass {
        global $DB;

        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $context = \context_course::instance($course->id);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('local/dixeo:syncfiles', CAP_ALLOW, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        return $user;
    }

    /**
     * Store Dixeo off at the site layer.
     */
    private function turn_dixeo_off(): void {
        $this->setAdminUser();
        (new config())->set('local_dixeo', 'enabled', \context_system::instance(), '0');
    }

    /**
     * Store the new-course file sync default as on.
     */
    private function set_new_course_default_on(): void {
        $this->setAdminUser();
        (new config())->set('local_dixeo', 'filesync_enabled_default', \context_system::instance(), '1');
    }

    /**
     * Whether a debounced sync task is queued for the course.
     *
     * @param int $courseid The course ID.
     * @return bool
     */
    private function sync_task_queued(int $courseid): bool {
        $tasks = \core\task\manager::get_adhoc_tasks('\\local_dixeo\\task\\process_file_sync');
        foreach ($tasks as $task) {
            $data = $task->get_custom_data();
            if (isset($data->courseid) && (int) $data->courseid === $courseid) {
                return true;
            }
        }
        return false;
    }
}
