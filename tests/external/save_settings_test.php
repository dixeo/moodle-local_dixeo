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
 * Tests for the Dixeo settings save service.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\external\save_settings;
use local_dixeo\external\service_factory;
use local_dixeo\settings\config;

/**
 * Save writes only the submitted keys through the settings helper.
 *
 * @covers \local_dixeo\external\save_settings
 */
final class save_settings_test extends \advanced_testcase {
    /**
     * A course intent is stored, cleared when emptied, and does not touch an omitted switch.
     */
    public function test_save_writes_only_the_submitted_course_keys(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $config = service_factory::get_settings_config();
        $config->set('local_dixeo', 'enabled', $context, '0');

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        save_settings::execute($context->id, [[
            'component' => 'local_dixeo',
            'name' => 'learning_intent',
            'value' => 'Course intent',
        ]]);

        $this->assertSame('Course intent', $DB->get_field(config::TABLE, 'value', [
            'contextid' => $context->id,
            'component' => 'local_dixeo',
            'name' => 'learning_intent',
        ]));
        $this->assertSame('0', $DB->get_field(config::TABLE, 'value', [
            'contextid' => $context->id,
            'component' => 'local_dixeo',
            'name' => 'enabled',
        ]));

        save_settings::execute($context->id, [[
            'component' => 'local_dixeo',
            'name' => 'learning_intent',
            'value' => '',
        ]]);

        $this->assertFalse($DB->record_exists(config::TABLE, [
            'contextid' => $context->id,
            'component' => 'local_dixeo',
            'name' => 'learning_intent',
        ]));
        $this->assertSame('0', $DB->get_field(config::TABLE, 'value', [
            'contextid' => $context->id,
            'component' => 'local_dixeo',
            'name' => 'enabled',
        ]));
    }

    /**
     * Saving a course setting clears that course cache so the next page load shows it.
     */
    public function test_save_clears_the_course_cache(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $before = (int) $DB->get_field('course', 'cacherev', ['id' => $course->id]);

        save_settings::execute(\context_course::instance($course->id)->id, [[
            'component' => 'local_dixeo',
            'name' => 'learning_intent',
            'value' => 'Course intent',
        ]]);

        $this->assertGreaterThan($before, (int) $DB->get_field('course', 'cacherev', ['id' => $course->id]));
    }

    /**
     * A student cannot save course settings.
     */
    public function test_student_cannot_save(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        save_settings::execute($context->id, [[
            'component' => 'local_dixeo',
            'name' => 'learning_intent',
            'value' => 'Course intent',
        ]]);
    }

    /**
     * A teacher cannot write the Dixeo layer.
     */
    public function test_teacher_cannot_save_the_system_context(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $this->expectException(\required_capability_exception::class);
        save_settings::execute(\context_system::instance()->id, [[
            'component' => 'local_dixeo',
            'name' => 'enabled',
            'value' => '0',
        ]]);
    }

    /**
     * An unknown key is rejected before anything is stored.
     */
    public function test_unknown_key_is_rejected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $this->expectException(\invalid_parameter_exception::class);
        save_settings::execute(\context_course::instance($course->id)->id, [[
            'component' => 'local_dixeo',
            'name' => 'not_a_setting',
            'value' => 'x',
        ]]);
    }
}
