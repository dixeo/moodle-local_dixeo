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
 * URL revision bump after a content image file swap.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\service\image\content\target_registry;

/**
 * Tables whose pluginfile URL embeds a revision get it bumped; others are untouched.
 *
 * @covers \local_dixeo\service\image\content\target_registry
 */
final class image_url_revision_test extends \advanced_testcase {
    /**
     * Page revision increments, label row stays identical.
     */
    public function test_bump_url_revision_only_where_the_url_embeds_it(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $label = $this->getDataGenerator()->create_module('label', ['course' => $course->id]);

        $before = (int) $DB->get_field('page', 'revision', ['id' => $page->id]);
        target_registry::bump_url_revision('page', (int) $page->id);
        $this->assertSame($before + 1, (int) $DB->get_field('page', 'revision', ['id' => $page->id]));

        $labelbefore = $DB->get_record('label', ['id' => $label->id]);
        target_registry::bump_url_revision('label', (int) $label->id);
        $this->assertEquals($labelbefore, $DB->get_record('label', ['id' => $label->id]));
    }
}
