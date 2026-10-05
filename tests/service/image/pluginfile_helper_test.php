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

namespace local_dixeo\service\image;

/**
 * Tests for pluginfile URL resolution (incl. mod_page revision-as-itemid).
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_dixeo\service\image\pluginfile_helper
 */
final class pluginfile_helper_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    /**
     * mod_page content URLs use revision in the itemid segment; files are itemid 0.
     */
    public function test_resolves_mod_page_revision_url_to_stored_file(): void {
        global $CFG, $USER;

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>x</p>',
            'contentformat' => FORMAT_HTML,
        ]);
        $context = \context_module::instance($page->cmid);

        $fs = get_file_storage();
        $filename = 'dixeo-gen-test-uuid.png';
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_page',
            'filearea' => 'content',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
            'userid' => (int) $USER->id,
        ], 'fakepng');

        $revision = max(1, (int) $page->revision);
        $url = $CFG->wwwroot . '/pluginfile.php/' . $context->id .
            '/mod_page/content/' . $revision . '/' . $filename;

        $file = pluginfile_helper::get_stored_file_from_pluginfile_url($url);
        $this->assertNotNull($file);
        $this->assertSame($filename, $file->get_filename());
        $this->assertSame(0, (int) $file->get_itemid());
    }
}
