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

use local_dixeo\repository\image\job_repository;
use local_dixeo\service\image\apply\content_handler;
use local_dixeo\service\image\content_target;
use local_dixeo\service\plugin_installation_service;

/**
 * Tests for slideshow featured-image completion and failure.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\service\image\content\slide_featured_image
 * @covers     \local_dixeo\service\image\apply\content_handler
 */
final class slide_featured_image_test extends \advanced_testcase {
    /**
     * Skip when the slideshow activity is not installed.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        if (!plugin_installation_service::is_plugin_installed('mod_slideshow')) {
            $this->markTestSkipped('mod_slideshow is not installed');
        }
    }

    /**
     * A completed featured-image job replaces the file and leaves the layout alone.
     */
    public function test_completed_job_replaces_the_slide_image(): void {
        [$location, $slide] = $this->create_slide_image('contentleft');
        $png = $this->fixture_png_bytes();
        $jobrow = (object) ['origin' => job_repository::ORIGIN_SLIDE_FEATURED];

        content_handler::apply(
            content_target::from_location($location),
            ['image_base64' => base64_encode($png)],
            2,
            'generated',
            $jobrow,
            null
        );

        $stored = $location->get_stored_file();
        $this->assertNotNull($stored);
        $this->assertSame($png, $stored->get_content());
        $this->assertSame('contentleft', $this->layout((int) $slide->id));
        $this->assertSame('<p>Body</p>', $this->content((int) $slide->id));
    }

    /**
     * A failed featured-image job deletes the file and switches the slide to background.
     */
    public function test_failed_job_drops_the_image_and_uses_background(): void {
        [$location, $slide] = $this->create_slide_image('contentleft');
        $jobrow = (object) ['origin' => job_repository::ORIGIN_SLIDE_FEATURED];

        content_handler::apply_failure(content_target::from_location($location), 2, $jobrow);

        $this->assertNull($location->get_stored_file());
        $this->assertSame('background', $this->layout((int) $slide->id));
        $this->assertSame('<p>Body</p>', $this->content((int) $slide->id));
    }

    /**
     * Create a content slide with one image file.
     *
     * @param string $layout Stored layout.
     * @return array{0: location, 1: \stdClass}
     */
    private function create_slide_image(string $layout): array {
        global $DB;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $slideshow = $this->getDataGenerator()->create_module('slideshow', ['course' => $course->id]);
        $slideid = $this->getDataGenerator()->get_plugin_generator('mod_slideshow')->create_slide([
            'slideshow' => $slideshow->id,
            'name' => 'Membrane',
            'content' => '<p>Body</p>',
            'layout' => $layout,
        ]);
        $cm = get_coursemodule_from_instance('slideshow', $slideshow->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $location = new location(
            $context->id,
            'mod_slideshow',
            'image',
            $slideid,
            '/',
            'membrane.png',
            (int) $course->id
        );
        get_file_storage()->create_file_from_string([
            'contextid' => $location->contextid,
            'component' => $location->component,
            'filearea' => $location->filearea,
            'itemid' => $location->itemid,
            'filepath' => $location->filepath,
            'filename' => $location->filename,
            'userid' => 2,
        ], $this->fixture_png_bytes());

        return [$location, $DB->get_record('slideshow_slide', ['id' => $slideid], '*', MUST_EXIST)];
    }

    /**
     * Return PNG bytes from core filestorage fixtures.
     *
     * @return string
     */
    private function fixture_png_bytes(): string {
        global $CFG;

        return (string) file_get_contents($CFG->dirroot . '/lib/filestorage/tests/fixtures/testimage.png');
    }

    /**
     * Stored layout for a slide.
     *
     * @param int $slideid Slide id.
     * @return string
     */
    private function layout(int $slideid): string {
        global $DB;

        return (string) $DB->get_field('slideshow_slide', 'layout', ['id' => $slideid], MUST_EXIST);
    }

    /**
     * Stored HTML for a slide.
     *
     * @param int $slideid Slide id.
     * @return string
     */
    private function content(int $slideid): string {
        global $DB;

        return (string) $DB->get_field('slideshow_slide', 'content', ['id' => $slideid], MUST_EXIST);
    }
}
