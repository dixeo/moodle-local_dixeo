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
 * Tests for the Dixeo settings read service.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\external\get_settings;
use local_dixeo\external\service_factory;

/**
 * The read service returns the helper's rows and rejects a user who cannot open the context.
 *
 * @covers \local_dixeo\external\get_settings
 */
final class get_settings_test extends \advanced_testcase {
    /**
     * A teacher receives the same course rows the helper returns.
     */
    public function test_teacher_receives_the_course_layers(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $result = get_settings::execute($context->id);

        $this->assertEquals(
            service_factory::get_settings_config()->get_layers($context),
            $result['settings']
        );
        get_settings::clean_returnvalue(get_settings::execute_returns(), $result);
    }

    /**
     * A student cannot read the course settings.
     */
    public function test_student_cannot_read_the_course_layers(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        get_settings::execute($context->id);
    }
}
