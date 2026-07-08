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
 * Tests for editor_image_codec encode/decode round-trip.
 *
 * @covers \local_dixeo\service\image\content\editor_image_codec
 * @package local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class editor_image_codec_test extends \advanced_testcase {
    /**
     * Build a test editor image context.
     *
     * @param int $sessionid Editor session id.
     * @return editor_image_context
     */
    private function make_context(int $sessionid = 99): editor_image_context {
        return new editor_image_context(
            1,
            2,
            10,
            'page',
            'mod_page',
            'content',
            0,
            'content',
            'page',
            5,
            $sessionid
        );
    }

    /**
     * Create a real page module and a matching editor image context.
     *
     * @param int $sessionid
     * @return array{0: editor_image_context, 1: \stdClass, 2: \stdClass} Context, course, page.
     */
    private function make_page_context(int $sessionid = 42): array {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>x</p>',
            'contentformat' => FORMAT_HTML,
        ]);
        $context = \context_module::instance($page->cmid);

        $ctx = new editor_image_context(
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
            $sessionid
        );

        return [$ctx, $course, $page];
    }

    /**
     * Create module file.
     * @param editor_image_context $ctx
     * @param string $filename
     * @return \stored_file
     */
    private function create_module_file(editor_image_context $ctx, string $filename): \stored_file {
        $fs = get_file_storage();
        return $fs->create_file_from_string([
            'contextid' => $ctx->contextid,
            'component' => $ctx->component,
            'filearea' => $ctx->filearea,
            'itemid' => $ctx->fileitemid,
            'filepath' => '/',
            'filename' => $filename,
            'userid' => get_admin()->id,
        ], 'fakepng');
    }

    /**
     * Test encode generated image to ref shortcode.
     */
    public function test_encode_generated_image_to_ref_shortcode(): void {
        $this->resetAfterTest();

        $placeholderid = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
        $html = '<p>Text</p><img src="@@PLUGINFILE@@/dixeo-gen-' . $placeholderid .
            '.png" class="img-fluid" data-dixeo-img-gen="' . $placeholderid . '" alt="" />';

        $codec = new editor_image_codec();
        $encoded = $codec->encode_html_for_api($html, $this->make_context());

        $this->assertStringContainsString('[img-gen ref="' . $placeholderid . '"', $encoded);
        $this->assertStringNotContainsString('<img', $encoded);
    }

    /**
     * Test encode pluginfile image to file shortcode.
     */
    public function test_encode_pluginfile_image_to_file_shortcode(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$ctx] = $this->make_page_context();
        $this->create_module_file($ctx, 'diagram.png');

        $html = '<img src="@@PLUGINFILE@@/diagram.png" class="img-fluid" alt="" />';
        $codec = new editor_image_codec();
        $encoded = $codec->encode_html_for_api($html, $ctx);

        $this->assertStringContainsString('[img-gen file="diagram.png"', $encoded);
    }

    /**
     * Test encode leaves unresolvable image untouched.
     */
    public function test_encode_leaves_unresolvable_image_untouched(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$ctx] = $this->make_page_context();

        // File does not exist in the content or session draft filearea (e.g. an
        // intro image rendered into the same context block): must pass through.
        $html = '<img src="@@PLUGINFILE@@/elsewhere.png" class="img-fluid" alt="" />';
        $codec = new editor_image_codec();
        $encoded = $codec->encode_html_for_api($html, $ctx);

        $this->assertSame($html, $encoded);
    }

    /**
     * Test decode ref shortcode restores img tag.
     */
    public function test_decode_ref_shortcode_restores_img_tag(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$ctx] = $this->make_page_context();

        $placeholderid = '11111111-2222-3333-4444-555555555555';
        $filename = file_service::stub_filename_for_placeholder($placeholderid);
        $location = $ctx->module_location($filename);
        file_service::create_stub($location, (int) get_admin()->id);

        $html = '<p>Hi</p>' . shortcode_parser::build_ref_shortcode($placeholderid);
        $codec = new editor_image_codec();
        $result = $codec->decode_html_from_api($html, $ctx, (int) get_admin()->id);

        $this->assertStringContainsString('data-dixeo-img-gen="' . $placeholderid . '"', $result->html);
        $this->assertStringNotContainsString('[img-gen', $result->html);
    }

    /**
     * Test decode file shortcode restores pluginfile img.
     */
    public function test_decode_file_shortcode_restores_pluginfile_img(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$ctx] = $this->make_page_context(7);
        $this->create_module_file($ctx, 'photo.png');

        $html = shortcode_parser::build_file_shortcode('photo.png');
        $codec = new editor_image_codec();
        $result = $codec->decode_html_from_api($html, $ctx, (int) get_admin()->id);

        // A renderable URL for TinyMCE plus the marker the promoter rewrites on save.
        $this->assertStringContainsString('pluginfile.php', $result->html);
        $this->assertStringContainsString('photo.png', $result->html);
        $this->assertStringContainsString('data-dixeo-draft-file="photo.png"', $result->html);
        $this->assertStringNotContainsString('data-dixeo-img-gen', $result->html);
    }
}
