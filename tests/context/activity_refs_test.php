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
 * Activity citation references in the course context.
 *
 * The tutor cites the activities its answers come from and locates the student's page,
 * so its context tags each module with its cmid and each section with its id;
 * generation contexts stay free of them.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\context\context_builder_factory;
use local_dixeo\context\course_context_builder;

/**
 * Tests for course_context_builder::with_activity_refs().
 *
 * @covers \local_dixeo\context\course_context_builder
 */
final class activity_refs_test extends \advanced_testcase {
    public function test_headings_carry_refs_only_when_asked(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['numsections' => 4]);
        $page = $generator->create_module('page', ['course' => $course->id, 'section' => 1, 'name' => 'Introduction']);
        $quiz = $generator->create_module('quiz', ['course' => $course->id, 'section' => 4, 'name' => 'Quiz 1']);

        // Teaching mode on section 1 gives the page in full and the far quiz as a title line.
        $context = context_builder_factory::course($course->id, 1, course_context_builder::MODE_TEACHING)
            ->with_activity_refs()
            ->build();

        $this->assertStringContainsString("**[page] Introduction** (ref: A{$page->cmid})", $context);
        $this->assertStringContainsString("- [quiz] Quiz 1 (ref: A{$quiz->cmid})", $context);
        $sectionid = get_fast_modinfo($course)->get_section_info(4)->id;
        $this->assertStringContainsString("(section id: {$sectionid})", $context);

        $plain = context_builder_factory::course($course->id, 1, course_context_builder::MODE_TEACHING)->build();
        $this->assertStringNotContainsString('(ref: A', $plain);
        $this->assertStringNotContainsString('(section id: ', $plain);
    }
}
