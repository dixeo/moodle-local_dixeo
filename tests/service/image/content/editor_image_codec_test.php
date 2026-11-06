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
        set_config('image_generation_enabled', '1', 'local_dixeo');
        set_config('image_generation_content_mode', 'generate', 'local_dixeo');

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
        set_config('image_generation_enabled', '1', 'local_dixeo');
        set_config('image_generation_content_mode', 'generate', 'local_dixeo');

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

    /**
     * A new shortcode becomes a pending placeholder whose generation is queued, not submitted.
     */
    public function test_decode_new_shortcode_queues_generation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('image_generation_enabled', '1', 'local_dixeo');
        set_config('image_generation_content_mode', 'generate', 'local_dixeo');

        [$ctx] = $this->make_page_context(8);
        $codec = new editor_image_codec();
        $result = $codec->decode_html_from_api('[img-gen prompt="A volcano"]', $ctx, (int) get_admin()->id);

        $this->assertCount(1, $result->newplaceholderids);
        $this->assertStringContainsString('dixeo-img-gen-pending', $result->html);
        $job = \local_dixeo\repository\image\job_repository::get_by_placeholderid($result->newplaceholderids[0]);
        $this->assertNotNull($job);
        $this->assertSame('', $job->jobid);
        $this->assertSame(\local_dixeo\repository\image\job_repository::ORIGIN_EDITOR_DRAFT, $job->origin);

        $tasks = \core\task\manager::get_adhoc_tasks(\local_dixeo\task\poll_image_job::class);
        $this->assertCount(1, $tasks);
        $this->assertSame('local_dixeo_editor', reset($tasks)->get_custom_data()->request->component);
    }

    /**
     * Oversized draft HTML stops before any lookup, and before the edit payload is built.
     */
    public function test_oversized_draft_html_stops_before_encoding(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        [$ctx, , $page] = $this->make_page_context(11);
        $draft = '<img data-dixeo-img-gen="aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee" src="x" />'
            . str_repeat('x', \local_dixeo\util\input_limits::MAX_EDIT_CONTENT_BYTES);

        $reads = $DB->perf_get_reads();
        try {
            editor_regenerate_service::build_api_context((int) $page->cmid, null, $draft, $ctx, (int) get_admin()->id);
            $this->fail('Oversized draft HTML must stop.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:content_too_long', $e->errorcode);
        }
        $this->assertSame($reads, $DB->perf_get_reads());

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string(
            'error:content_too_long',
            'local_dixeo',
            display_size(\local_dixeo\util\input_limits::MAX_EDIT_CONTENT_BYTES)
        ));
        editor_regenerate_service::build_edit_payload((int) $page->cmid, null, 'Shorter', $ctx, $draft, (int) get_admin()->id);
    }

    /**
     * Encoding stops after the image cap and leaves later tags unchanged.
     */
    public function test_encode_leaves_tags_past_the_cap_unchanged(): void {
        $this->resetAfterTest();

        $cap = \local_dixeo\util\input_limits::MAX_IMAGES_PER_CONTENT;
        $html = '';
        for ($i = 0; $i <= $cap; $i++) {
            $html .= '<img src="x.png" data-dixeo-img-gen="' . \core\uuid::generate() . '" alt="" />';
        }
        $last = '<img src="last.png" data-dixeo-img-gen="' . \core\uuid::generate() . '" alt="" />';
        $html = preg_replace('/<img[^>]*>$/', $last, $html);

        $encoded = (new editor_image_codec())->encode_html_for_api($html, $this->make_context());

        $this->assertSame($cap, substr_count($encoded, '[img-gen ref='));
        $this->assertSame(1, substr_count($encoded, '<img'));
        $this->assertStringEndsWith($last, $encoded);
    }

    /**
     * Decoding queues at most the image cap of new images and still restores every ref.
     */
    public function test_decode_caps_new_images_and_keeps_refs(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('image_generation_enabled', '1', 'local_dixeo');
        set_config('image_generation_content_mode', 'generate', 'local_dixeo');
        [$ctx] = $this->make_page_context(12);

        $refs = ['11111111-2222-3333-4444-555555555555', '66666666-7777-8888-9999-000000000000'];
        foreach ($refs as $ref) {
            $location = $ctx->module_location(file_service::stub_filename_for_placeholder($ref));
            file_service::create_stub($location, (int) get_admin()->id);
        }
        $cap = \local_dixeo\util\input_limits::MAX_IMAGES_PER_CONTENT;
        $html = shortcode_parser::build_ref_shortcode($refs[0]);
        for ($i = 0; $i <= $cap; $i++) {
            $html .= '[img-gen prompt="Picture ' . $i . '"]';
        }
        $html .= shortcode_parser::build_ref_shortcode($refs[1]);

        $result = (new editor_image_codec())->decode_html_from_api($html, $ctx, (int) get_admin()->id);

        $this->assertCount($cap, $result->newplaceholderids);
        $this->assertSame($cap, $DB->count_records('local_dixeo_image_job'));
        $this->assertSame($refs, $result->restoredplaceholderids);
        foreach ($refs as $ref) {
            $this->assertStringContainsString('data-dixeo-img-gen="' . $ref . '"', $result->html);
        }
        $this->assertStringNotContainsString('[img-gen', $result->html);
        $this->assertSame($cap + 2, substr_count($result->html, '<img'));
    }

    /**
     * A placeholder of another course module neither sends that job's prompt nor restores its file.
     */
    public function test_foreign_module_placeholder_is_treated_as_unknown(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('image_generation_enabled', '1', 'local_dixeo');
        set_config('image_generation_content_mode', 'generate', 'local_dixeo');
        [$foreignctx] = $this->make_page_context(21);
        [$ownctx] = $this->make_page_context(22);
        $codec = new editor_image_codec();
        $foreign = $codec->decode_html_from_api('[img-gen prompt="Secret foreign prompt"]', $foreignctx, (int) get_admin()->id);
        $placeholderid = $foreign->newplaceholderids[0];

        $encoded = $codec->encode_html_for_api(
            '<img src="x.png" data-dixeo-img-gen="' . $placeholderid . '" alt="" />',
            $ownctx
        );
        $this->assertStringNotContainsString('Secret foreign prompt', $encoded);
        $this->assertStringContainsString(shortcode_parser::PRESERVE_PROMPT, $encoded);

        $decoded = $codec->decode_html_from_api(
            '<p>Own</p>' . shortcode_parser::build_ref_shortcode($placeholderid),
            $ownctx,
            (int) get_admin()->id
        );
        $this->assertStringNotContainsString('<img', $decoded->html);
        $this->assertStringNotContainsString('/' . $foreignctx->contextid . '/', $decoded->html);

        $ownencoded = $codec->encode_html_for_api(
            '<img src="x.png" data-dixeo-img-gen="' . $placeholderid . '" alt="" />',
            $foreignctx
        );
        $this->assertStringContainsString('Secret foreign prompt', $ownencoded);
    }

    /**
     * A copied module whose file area holds the original stub still restores the image from its own area.
     */
    public function test_copied_module_restores_ref_from_its_own_files(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('image_generation_enabled', '1', 'local_dixeo');
        set_config('image_generation_content_mode', 'generate', 'local_dixeo');
        [$originalctx] = $this->make_page_context(23);
        [$copyctx] = $this->make_page_context(24);
        $codec = new editor_image_codec();
        $original = $codec->decode_html_from_api('[img-gen prompt="A volcano"]', $originalctx, (int) get_admin()->id);
        $placeholderid = $original->newplaceholderids[0];
        $filename = file_service::stub_filename_for_placeholder($placeholderid);
        file_service::create_stub($copyctx->module_location($filename), (int) get_admin()->id);

        $decoded = $codec->decode_html_from_api(
            shortcode_parser::build_ref_shortcode($placeholderid),
            $copyctx,
            (int) get_admin()->id
        );

        $this->assertStringContainsString('data-dixeo-img-gen="' . $placeholderid . '"', $decoded->html);
        $this->assertStringContainsString('/' . $copyctx->contextid . '/mod_page/content/', $decoded->html);
        $this->assertStringNotContainsString('/' . $originalctx->contextid . '/', $decoded->html);
    }
}
