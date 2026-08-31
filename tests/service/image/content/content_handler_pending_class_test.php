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
use local_dixeo\service\image\content\location;
use local_dixeo\service\image\content_target;

/**
 * Reproduces pending CSS class remaining after successful apply.
 * @package local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Tests for content handler pending class.
 *
 * @coversNothing
 */
final class content_handler_pending_class_test extends \advanced_testcase {
    /**
     * Test success apply clears pending class from html.
     */
    public function test_success_apply_clears_pending_class_from_html(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $placeholderid = 'test-placeholder-uuid';
        $img = '<img src="@@PLUGINFILE@@/x.png" class="img-fluid dixeo-img-gen-pending" data-dixeo-img-gen="' .
            $placeholderid . '" alt="" />';
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>' . $img . '</p>',
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
            'targettable' => 'page',
            'targetfield' => 'content',
            'targetid' => (int) $page->id,
        ];

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8j0NgAAAABJRU5ErkJggg==');
        content_handler::apply($target, ['image_base64' => base64_encode($png)], (int) $USER->id, 'generated', $jobrow, null);

        $fresh = $DB->get_record('page', ['id' => $page->id], 'content', MUST_EXIST);
        $this->assertStringNotContainsString('dixeo-img-gen-pending', $fresh->content);
    }
}
