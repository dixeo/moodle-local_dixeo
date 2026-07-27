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
 * Tests for job_binding_metadata.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\dto\job_binding_metadata;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/page/lib.php');

/**
 * @covers \local_dixeo\dto\job_binding_metadata
 */
final class job_binding_metadata_test extends \advanced_testcase {

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    public function test_for_stored_file_module_context(): void {
        global $USER;

        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $page = $gen->create_module('page', ['course' => $course->id]);
        $context = \context_module::instance($page->cmid);

        $fs = get_file_storage();
        $file = $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_page',
            'filearea' => 'content',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'embedded.png',
            'userid' => $USER->id,
        ], 'fakepng');

        $metadata = job_binding_metadata::for_stored_file($file);
        $this->assertNotNull($metadata);
        $this->assertSame('page', $metadata->moduletype);
        $this->assertSame((int) $page->cmid, $metadata->cmid);
        $this->assertSame((int) $context->id, $metadata->contextid);
    }

    public function test_for_stored_file_course_context(): void {
        global $USER;

        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $context = \context_course::instance($course->id);

        $fs = get_file_storage();
        $file = $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'course',
            'filearea' => 'summary',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'banner.png',
            'userid' => $USER->id,
        ], 'fakepng');

        $metadata = job_binding_metadata::for_stored_file($file);
        $this->assertNotNull($metadata);
        $this->assertNull($metadata->moduletype);
        $this->assertSame(0, $metadata->cmid);
        $this->assertSame((int) $context->id, $metadata->contextid);
    }
}
