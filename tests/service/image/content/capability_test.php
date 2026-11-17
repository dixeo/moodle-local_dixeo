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

namespace local_dixeo\service\image\content;

/**
 * Tests for the content image capability checks.
 *
 * @covers \local_dixeo\service\image\content\capability
 * @package local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class capability_test extends \advanced_testcase {
    /**
     * Course and user ids below 1 never allow generation.
     */
    public function test_can_generate_refuses_missing_ids(): void {
        $this->resetAfterTest();

        $this->assertFalse(capability::can_generate(0, (int) get_admin()->id));
        $this->assertFalse(capability::can_generate(SITEID + 1000, 0));
    }

    /**
     * The check reads the given user's capability, not the current user's.
     */
    public function test_can_generate_uses_given_user(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setAdminUser();

        $this->assertTrue(capability::can_generate((int) $course->id, (int) $teacher->id));
        $this->assertFalse(capability::can_generate((int) $course->id, (int) $student->id));
    }
}
