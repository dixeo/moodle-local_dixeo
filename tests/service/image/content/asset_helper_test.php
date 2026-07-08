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
 * Tests for bundled content image assets.
 *
 * @covers \local_dixeo\service\image\content\asset_helper
 * @package local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class asset_helper_test extends \advanced_testcase {
    /**
     * Test placeholder and error assets are png images.
     */
    public function test_placeholder_and_error_assets_are_png_images(): void {
        $this->resetAfterTest();

        $placeholder = asset_helper::get_placeholder_binary();
        $error = asset_helper::get_error_binary();

        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $placeholder);
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $error);
        $this->assertNotSame($placeholder, $error);
        $this->assertGreaterThan(1000, strlen($placeholder));
        $this->assertGreaterThan(1000, strlen($error));
    }

    /**
     * Test create stub uses placeholder asset.
     */
    public function test_create_stub_uses_placeholder_asset(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $context = \context_module::instance($page->cmid);
        $filename = file_service::stub_filename_for_placeholder('asset-test');
        $location = new location(
            $context->id,
            'mod_page',
            'content',
            0,
            '/',
            $filename,
            (int) $course->id
        );

        file_service::create_stub($location, (int) $USER->id);

        $stored = $location->get_stored_file();
        $this->assertNotNull($stored);
        $this->assertSame(asset_helper::get_placeholder_binary(), $stored->get_content());
    }
}
