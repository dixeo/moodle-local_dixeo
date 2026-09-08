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
        $this->assertStringContainsString(
            'rev=' . $location->get_stored_file()->get_contenthash(),
            $fresh->content
        );
    }

    /**
     * Label intros are cached in modinfo: the course page must show the applied image.
     */
    public function test_success_apply_refreshes_label_intro_in_modinfo(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $placeholderid = 'label-placeholder-uuid';
        $filename = file_service::stub_filename_for_placeholder($placeholderid);
        $img = '<img src="@@PLUGINFILE@@/' . $filename . '" class="img-fluid dixeo-img-gen-pending" ' .
            'data-dixeo-img-gen="' . $placeholderid . '" alt="" />';
        $label = $this->getDataGenerator()->create_module('label', [
            'course' => $course->id,
            'intro' => '<p>' . $img . '</p>',
            'introformat' => FORMAT_HTML,
        ]);
        $context = \context_module::instance($label->cmid);
        $location = new location($context->id, 'mod_label', 'intro', 0, '/', $filename, (int) $course->id);
        file_service::create_stub($location, (int) $USER->id);

        // Warm the modinfo cache the way a course page view does.
        $cached = get_fast_modinfo($course->id)->get_cm((int) $label->cmid)->content;
        $this->assertStringContainsString('dixeo-img-gen-pending', $cached);

        $jobrow = (object) [
            'origin' => job_repository::ORIGIN_SHORTCODE,
            'placeholderid' => $placeholderid,
            'targettable' => 'label',
            'targetfield' => 'intro',
            'targetid' => (int) $label->id,
            'courseid' => (int) $course->id,
            'cmid' => (int) $label->cmid,
        ];

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8j0NgAAAABJRU5ErkJggg==');
        content_handler::apply(
            content_target::from_location($location),
            ['image_base64' => base64_encode($png)],
            (int) $USER->id,
            'generated',
            $jobrow,
            null
        );

        $contenthash = $location->get_stored_file()->get_contenthash();
        $fresh = $DB->get_record('label', ['id' => $label->id], 'intro', MUST_EXIST);
        $this->assertStringNotContainsString('dixeo-img-gen-pending', $fresh->intro);
        $this->assertStringContainsString('rev=' . $contenthash, $fresh->intro);

        // Drop the per-request static cache: a later page view reads MUC only.
        get_fast_modinfo(0, 0, true);
        $rendered = get_fast_modinfo($course->id)->get_cm((int) $label->cmid)->content;
        $this->assertStringNotContainsString('dixeo-img-gen-pending', $rendered);
        $this->assertStringContainsString('rev=' . $contenthash, $rendered);
    }

    /**
     * Failed jobs must also bust the browser cache of the pending stub.
     */
    public function test_failure_apply_marks_failed_and_revs_src(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $placeholderid = 'failed-placeholder-uuid';
        $filename = file_service::stub_filename_for_placeholder($placeholderid);
        $img = '<img src="@@PLUGINFILE@@/' . $filename . '" class="img-fluid dixeo-img-gen-pending" ' .
            'data-dixeo-img-gen="' . $placeholderid . '" alt="" />';
        $label = $this->getDataGenerator()->create_module('label', [
            'course' => $course->id,
            'intro' => '<p>' . $img . '</p>',
            'introformat' => FORMAT_HTML,
        ]);
        $context = \context_module::instance($label->cmid);
        $location = new location($context->id, 'mod_label', 'intro', 0, '/', $filename, (int) $course->id);
        file_service::create_stub($location, (int) $USER->id);

        $jobrow = (object) [
            'origin' => job_repository::ORIGIN_SHORTCODE,
            'placeholderid' => $placeholderid,
            'targettable' => 'label',
            'targetfield' => 'intro',
            'targetid' => (int) $label->id,
            'courseid' => (int) $course->id,
            'cmid' => (int) $label->cmid,
        ];

        content_handler::apply_failure(content_target::from_location($location), (int) $USER->id, $jobrow);

        $fresh = $DB->get_record('label', ['id' => $label->id], 'intro', MUST_EXIST);
        $this->assertStringNotContainsString('dixeo-img-gen-pending', $fresh->intro);
        $this->assertStringContainsString('dixeo-img-gen-failed', $fresh->intro);
        $this->assertStringContainsString(
            'rev=' . $location->get_stored_file()->get_contenthash(),
            $fresh->intro
        );
    }

    /**
     * Success after failure must clear the failed class and refresh contenthash.
     */
    public function test_success_apply_clears_failed_class_and_updates_hash(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $placeholderid = 'failed-then-success-uuid';
        $img = '<img src="@@PLUGINFILE@@/x.png" class="img-fluid dixeo-img-gen-failed" ' .
            'data-dixeo-img-gen="' . $placeholderid . '" data-dixeo-contenthash="errorhash" alt="" />';
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
        $this->assertStringNotContainsString('dixeo-img-gen-failed', $fresh->content);
        $this->assertStringNotContainsString('data-dixeo-contenthash="errorhash"', $fresh->content);
        $newhash = $location->get_stored_file()->get_contenthash();
        $this->assertStringContainsString('data-dixeo-contenthash="' . $newhash . '"', $fresh->content);
    }
}
