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
 * Tests for editor_session_promoter save-time promotion.
 *
 * @covers \local_dixeo\service\image\content\editor_session_promoter
 * @package local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class editor_session_promoter_test extends \advanced_testcase {
    /** @var int */
    private const SESSIONID = 55;

    /**
     * Make page context.
     * @return editor_image_context
     */
    private function make_page_context(): editor_image_context {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>x</p>',
            'contentformat' => FORMAT_HTML,
        ]);
        $context = \context_module::instance($page->cmid);

        return new editor_image_context(
            $context->id,
            (int) $course->id,
            (int) $page->cmid,
            'page',
            'mod_page',
            'content',
            0,
            'content',
            'page',
            (int) $page->id,
            self::SESSIONID
        );
    }

    /**
     * Create file at.
     * @param location $location
     * @return \stored_file
     */
    private function create_file_at(location $location): \stored_file {
        $fs = get_file_storage();
        return $fs->create_file_from_string([
            'contextid' => $location->contextid,
            'component' => $location->component,
            'filearea' => $location->filearea,
            'itemid' => $location->itemid,
            'filepath' => $location->filepath,
            'filename' => $location->filename,
            'userid' => get_admin()->id,
        ], 'fakepng');
    }

    /**
     * Test promote copies draft file and rewrites src.
     */
    public function test_promote_copies_draft_file_and_rewrites_src(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $ctx = $this->make_page_context();
        $placeholderid = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
        $filename = file_service::stub_filename_for_placeholder($placeholderid);
        $this->create_file_at($ctx->draft_location($filename));

        $draftsrc = $ctx->draft_location($filename)->get_pluginfile_url();
        $html = '<p>Hi</p><img src="' . s($draftsrc) . '" class="img-fluid custom" ' .
            'width="320" style="float:right" data-dixeo-img-gen="' . $placeholderid . '" alt="A cat" />';

        $result = editor_session_promoter::promote_html($html, $ctx, (int) get_admin()->id);

        $this->assertNotNull($ctx->module_location($filename)->get_stored_file());
        $this->assertStringContainsString('src="@@PLUGINFILE@@/' . $filename . '"', $result);
        // User-set attributes survive promotion.
        $this->assertStringContainsString('class="img-fluid custom"', $result);
        $this->assertStringContainsString('width="320"', $result);
        $this->assertStringContainsString('style="float:right"', $result);
        $this->assertStringContainsString('alt="A cat"', $result);
        $this->assertStringContainsString('data-dixeo-img-gen="' . $placeholderid . '"', $result);
    }

    /**
     * Test promote rewrites module file without draft copy.
     */
    public function test_promote_rewrites_module_file_without_draft_copy(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $ctx = $this->make_page_context();
        $placeholderid = '11111111-2222-3333-4444-555555555555';
        $filename = file_service::stub_filename_for_placeholder($placeholderid);
        // Image completed in an earlier session: only the module copy exists.
        $this->create_file_at($ctx->module_location($filename));

        $modulesrc = $ctx->module_location($filename)->get_pluginfile_url();
        $html = '<img src="' . s($modulesrc) . '" class="img-fluid" data-dixeo-img-gen="' .
            $placeholderid . '" alt="" />';

        $result = editor_session_promoter::promote_html($html, $ctx, (int) get_admin()->id);

        $this->assertStringContainsString('src="@@PLUGINFILE@@/' . $filename . '"', $result);
    }

    /**
     * Test promote leaves unresolvable img gen untouched.
     */
    public function test_promote_leaves_unresolvable_img_gen_untouched(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $ctx = $this->make_page_context();
        $html = '<img src="http://example.com/x.png" data-dixeo-img-gen="does-not-exist" alt="" />';

        $result = editor_session_promoter::promote_html($html, $ctx, (int) get_admin()->id);

        $this->assertSame($html, $result);
    }

    /**
     * Test promote rewrites draft file marker to token.
     */
    public function test_promote_rewrites_draft_file_marker_to_token(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $ctx = $this->make_page_context();
        $this->create_file_at($ctx->module_location('photo.png'));

        $modulesrc = $ctx->module_location('photo.png')->get_pluginfile_url();
        $html = '<img src="' . s($modulesrc) . '" class="img-fluid" ' .
            'data-dixeo-draft-file="photo.png" alt="Photo" />';

        $result = editor_session_promoter::promote_html($html, $ctx, (int) get_admin()->id);

        $this->assertStringContainsString('src="@@PLUGINFILE@@/photo.png"', $result);
        $this->assertStringNotContainsString('data-dixeo-draft-file', $result);
        $this->assertStringContainsString('alt="Photo"', $result);
    }

    /**
     * Test promote copies draft only file to module area.
     */
    public function test_promote_copies_draft_only_file_to_module_area(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $ctx = $this->make_page_context();
        $this->create_file_at($ctx->draft_location('upload.png'));

        $draftsrc = $ctx->draft_location('upload.png')->get_pluginfile_url();
        $html = '<img src="' . s($draftsrc) . '" data-dixeo-draft-file="upload.png" alt="" />';

        $result = editor_session_promoter::promote_html($html, $ctx, (int) get_admin()->id);

        $this->assertNotNull($ctx->module_location('upload.png')->get_stored_file());
        $this->assertStringContainsString('src="@@PLUGINFILE@@/upload.png"', $result);
    }
}
