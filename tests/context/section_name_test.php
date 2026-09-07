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
 * Section naming in AI contexts.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\context\context_builder_factory;

/**
 * Unnamed sections must not carry Moodle's localized default name into the context.
 *
 * @covers \local_dixeo\context\abstract_context_builder
 */
final class section_name_test extends \advanced_testcase {
    /**
     * Unnamed sections get a language-neutral label, named sections keep their name.
     */
    public function test_unnamed_sections_use_neutral_label(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['numsections' => 2]);
        $DB->set_field('course_sections', 'name', 'Buoyancy', ['course' => $course->id, 'section' => 1]);
        rebuild_course_cache($course->id, true);

        $sectionids = $DB->get_records_menu('course_sections', ['course' => $course->id], '', 'section, id');

        $general = context_builder_factory::section($sectionids[0])->build();
        $this->assertStringContainsString('## Section: Section 0', $general);
        $this->assertStringNotContainsString('General', $general);

        $named = context_builder_factory::section($sectionids[1])->build();
        $this->assertStringContainsString('## Section: Buoyancy', $named);

        $coursecontext = context_builder_factory::course($course->id)->build();
        $this->assertStringContainsString('### Section 0', $coursecontext);
        $this->assertStringContainsString('### Buoyancy', $coursecontext);
        $this->assertStringNotContainsString('General', $coursecontext);
    }
}
