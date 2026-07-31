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
use local_dixeo\service\image\content\asset_helper;
use local_dixeo\service\image\content_target;

/**
 * apply_failure must not overwrite applied modal images (late cron poll scenario).
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_dixeo\service\image\apply\content_handler::apply_failure
 */
final class content_handler_apply_failure_test extends \advanced_testcase {
    /**
     * Return PNG bytes from core filestorage fixtures.
     */
    private static function fixture_png_bytes(): string {
        global $CFG;
        return (string) file_get_contents($CFG->dirroot . '/lib/filestorage/tests/fixtures/testimage.png');
    }

    /**
     * Create a page module with an embedded PNG for tests.
     *
     * @return array{0: location, 1: int}
     */
    private function create_page_image_location(): array {
        global $USER;

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $page = $gen->create_module('page', ['course' => $course->id]);
        $context = \context_module::instance($page->cmid);

        $draftitemid = file_get_unused_draft_itemid();
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => 'embedded.png',
        ], self::fixture_png_bytes());

        file_save_draft_area_files(
            $draftitemid,
            $context->id,
            'mod_page',
            'content',
            0,
            ['subdirs' => 0, 'maxfiles' => 1]
        );

        $file = $fs->get_file($context->id, 'mod_page', 'content', 0, '/', 'embedded.png');
        $this->assertNotFalse($file);

        return [location::from_stored_file($file), (int) $course->id];
    }

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    /**
     * Simulates poll_image_job::mark_failed after acknowledge deleted the job row.
     */
    public function test_apply_failure_with_null_jobrow_preserves_applied_image(): void {
        global $USER;

        [$location] = $this->create_page_image_location();
        $file = $location->get_stored_file();
        $this->assertNotFalse($file);
        $beforehash = $file->get_contenthash();

        $target = content_target::from_location($location);
        content_handler::apply_failure($target, (int) $USER->id, null);

        $after = $location->get_stored_file();
        $this->assertNotFalse($after);
        $this->assertSame($beforehash, $after->get_contenthash());
    }

    /**
     * Modal jobs must not be overwritten with the error placeholder on failure.
     */
    public function test_apply_failure_modal_origin_preserves_applied_image(): void {
        global $USER;

        [$location] = $this->create_page_image_location();
        $file = $location->get_stored_file();
        $this->assertNotFalse($file);
        $beforehash = $file->get_contenthash();

        $target = content_target::from_location($location);
        $jobrow = (object) ['origin' => job_repository::ORIGIN_MODAL];
        content_handler::apply_failure($target, (int) $USER->id, $jobrow);

        $after = $location->get_stored_file();
        $this->assertNotFalse($after);
        $this->assertSame($beforehash, $after->get_contenthash());
    }

    /**
     * Shortcode placeholders should still receive the error asset on failure.
     */
    public function test_apply_failure_shortcode_replaces_stub_with_error_asset(): void {
        global $USER;

        $course = $this->getDataGenerator()->create_course();
        $placeholderid = 'test-placeholder-uuid';
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>pending</p>',
            'contentformat' => FORMAT_HTML,
        ]);
        $context = \context_module::instance($page->cmid);
        $filename = file_service::stub_filename_for_placeholder($placeholderid);
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

        $target = content_target::from_location($location);
        $jobrow = (object) [
            'origin' => job_repository::ORIGIN_SHORTCODE,
            'placeholderid' => $placeholderid,
        ];
        content_handler::apply_failure($target, (int) $USER->id, $jobrow);

        $file = $location->get_stored_file();
        $this->assertNotFalse($file);
        $this->assertSame(
            sha1(asset_helper::get_error_binary()),
            sha1($file->get_content())
        );
    }
}
