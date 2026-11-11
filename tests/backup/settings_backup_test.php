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
 * Tests for backup and restore of course and activity setting values.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\backup;

use local_dixeo\event\setting_updated;
use local_dixeo\external\service_factory;
use local_dixeo\service\file_sync_service;
use local_dixeo\settings\catalogue;
use local_dixeo\settings\config;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Course and activity values travel with a course backup; unknown values are dropped.
 *
 * @covers \backup_local_dixeo_plugin
 * @covers \restore_local_dixeo_plugin
 * @covers \local_dixeo\settings\config::restore_value
 * @covers \local_dixeo\observer\file_sync_observer::setting_updated
 */
final class settings_backup_test extends \advanced_testcase {
    public function test_course_and_activity_values_are_restored_into_a_new_course(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $config = service_factory::get_settings_config();
        $config->set('local_dixeo', 'filesync_enabled_default', \context_system::instance(), '1');
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $coursecontext = \context_course::instance($course->id);
        $config->set('local_dixeo', 'filesync_pill', $coursecontext, 'footer');
        $DB->delete_records(config::TABLE, [
            'contextid' => $coursecontext->id,
            'component' => 'local_dixeo',
            'name' => 'filesync_enabled',
        ]);
        $this->insert_row($coursecontext->id, 'local_dixeo', 'no_such_setting', 'x');
        $activity = $this->activity_setting();
        if ($activity !== null) {
            $DB->delete_records(config::TABLE, ['contextid' => \context_module::instance($assign->cmid)->id]);
            $this->insert_row(\context_module::instance($assign->cmid)->id, $activity[0], $activity[1], $activity[2]);
        }

        $sink = $this->redirectEvents();
        $newcourseid = $this->backup_and_restore((int) $course->id);
        $events = array_filter($sink->get_events(), static fn($event): bool => $event instanceof setting_updated);
        $sink->close();

        $newcontext = \context_course::instance($newcourseid);
        $this->assertSame('footer', $this->stored($newcontext->id, 'local_dixeo', 'filesync_pill'));
        $this->assertNull($this->stored($newcontext->id, 'local_dixeo', 'no_such_setting'));
        $this->assertSame('1', $this->stored($newcontext->id, 'local_dixeo', 'filesync_enabled'));
        $restored = array_filter($events, static fn($event): bool => !empty($event->other['restored']));
        $this->assertContains('filesync_pill', array_map(static fn($event): string => $event->other['name'], $restored));

        if ($activity !== null) {
            $instances = get_fast_modinfo($newcourseid)->get_instances_of('assign');
            $newcm = reset($instances);
            $this->assertSame(
                $activity[2],
                $this->stored(\context_module::instance($newcm->id)->id, $activity[0], $activity[1])
            );
        }
    }

    public function test_a_restored_file_sync_on_does_not_start_sync_while_the_course_is_off(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->getDataGenerator()->get_plugin_generator('local_dixeo')
            ->create_terms_acceptance(['userid' => (int) get_admin()->id]);
        $config = service_factory::get_settings_config();
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $config->set('local_dixeo', 'enabled', $coursecontext, '0');
        $config->set('local_dixeo', 'filesync_enabled', $coursecontext, '1');

        $newcourseid = $this->backup_and_restore((int) $course->id);

        $newcontext = \context_course::instance($newcourseid);
        $this->assertSame('0', $this->stored($newcontext->id, 'local_dixeo', 'enabled'));
        $this->assertSame('1', $this->stored($newcontext->id, 'local_dixeo', 'filesync_enabled'));
        $this->assertFalse((new file_sync_service())->is_enabled($newcourseid));
    }

    /**
     * Back up a course and restore it into a new course.
     *
     * @param int $courseid Source course id.
     * @return int New course id.
     */
    private function backup_and_restore(int $courseid): int {
        global $USER;

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $courseid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        $category = $this->getDataGenerator()->create_category();
        $newcourseid = \restore_dbops::create_new_course('Restored', 'restored' . $courseid, $category->id);
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        return (int) $newcourseid;
    }

    /**
     * An installed activity-level setting with an allowed value, or null when none exists.
     *
     * @return array{0: string, 1: string, 2: string}|null Component, key and value.
     */
    private function activity_setting(): ?array {
        foreach (catalogue::load() as $definition) {
            if (in_array('activity', $definition->get_levels(), true)) {
                return [$definition->get_component(), $definition->get_key(), $definition->get_default()];
            }
        }
        return null;
    }

    /**
     * Stored value at a context, or null.
     *
     * @param int $contextid Context id.
     * @param string $component Component.
     * @param string $name Setting key.
     * @return string|null
     */
    private function stored(int $contextid, string $component, string $name): ?string {
        global $DB;

        $value = $DB->get_field(config::TABLE, 'value', [
            'contextid' => $contextid,
            'component' => $component,
            'name' => $name,
        ]);
        return $value === false ? null : (string) $value;
    }

    /**
     * Store a raw setting row.
     *
     * @param int $contextid Context id.
     * @param string $component Component.
     * @param string $name Setting key.
     * @param string $value Value.
     */
    private function insert_row(int $contextid, string $component, string $name, string $value): void {
        global $DB;

        $DB->insert_record(config::TABLE, (object) [
            'contextid' => $contextid,
            'component' => $component,
            'name' => $name,
            'value' => $value,
        ]);
    }
}
