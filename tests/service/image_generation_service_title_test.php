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

namespace local_dixeo\service;

/**
 * Tests for the image title derived from a stored file's context.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_dixeo\service\image_generation_service::resolve_title_for_stored_file
 */
final class image_generation_service_title_test extends \advanced_testcase {
    /**
     * A block file uses the title stored in the block configuration.
     */
    public function test_block_file_uses_configured_title(): void {
        $this->resetAfterTest();

        $file = $this->block_file(base64_encode(serialize((object) ['title' => 'T'])));

        $this->assertSame('T', image_generation_service::resolve_title_for_stored_file($file));
    }

    /**
     * Configuration data that is not valid base64 falls back to the course name.
     */
    public function test_invalid_configdata_falls_back_to_course_name(): void {
        $this->resetAfterTest();

        $file = $this->block_file('not base64 %%%');

        $this->assertSame('Course for titles', image_generation_service::resolve_title_for_stored_file($file));
    }

    /**
     * Stored file in a new block instance of a new course.
     *
     * @param string $configdata Raw block configdata column value.
     * @return \stored_file
     */
    private function block_file(string $configdata): \stored_file {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['fullname' => 'Course for titles']);
        $block = $this->getDataGenerator()->create_block('html', [
            'parentcontextid' => \context_course::instance($course->id)->id,
        ]);
        $DB->set_field('block_instances', 'configdata', $configdata, ['id' => $block->id]);
        $context = \context_block::instance($block->id);

        return get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'block_html',
            'filearea' => 'content',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'image.png',
        ], 'image');
    }
}
