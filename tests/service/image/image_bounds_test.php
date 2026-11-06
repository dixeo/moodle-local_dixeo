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

/**
 * Tests for {@see \local_dixeo\service\image\image_bounds}.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service\image;

/**
 * Display bounds and Dixeo edit source bounds.
 *
 * @covers \local_dixeo\service\image\image_bounds
 * @covers \local_dixeo\service\image\pluginfile_helper::image_url_to_base64
 */
final class image_bounds_test extends \advanced_testcase {
    /**
     * PNG bytes of the given size.
     *
     * @param int $width Width in pixels.
     * @param int $height Height in pixels.
     * @return string
     */
    private static function png(int $width, int $height): string {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);
        return $bytes;
    }

    /**
     * Store a file in a page's content area.
     *
     * @param string $filename File name, which sets the MIME type.
     * @param string $content File content.
     * @return \stored_file
     */
    private function store(string $filename, string $content): \stored_file {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        return get_file_storage()->create_file_from_string([
            'contextid' => \context_module::instance((int) $page->cmid)->id,
            'component' => 'mod_page',
            'filearea' => 'content',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
        ], $content);
    }

    /**
     * Assert that a call is refused with the given local_dixeo error.
     *
     * @param string $errorcode Expected error code.
     * @param callable $call Call under test.
     * @return void
     */
    private function assert_refused(string $errorcode, callable $call): void {
        try {
            $call();
            $this->fail('Expected ' . $errorcode);
        } catch (\moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode);
            $this->assertSame('local_dixeo', $e->module);
        }
    }

    /**
     * Display edges: per-side and total pixel limits.
     */
    public function test_display_bounds_edges(): void {
        $this->assertTrue(image_bounds::within_display_bounds(8000, 5000));
        $this->assertFalse(image_bounds::within_display_bounds(8001, 1));
        $this->assertFalse(image_bounds::within_display_bounds(1, 8001));
        $this->assertFalse(image_bounds::within_display_bounds(7000, 7000));
        $this->assertFalse(image_bounds::within_display_bounds(0, 10));

        image_bounds::require_display_size(1536, 1024);
        $this->assert_refused('error:image_dimensions', fn() => image_bounds::require_display_size(20000, 20000));
    }

    /**
     * A GIF and an SVG are not edit sources.
     */
    public function test_edit_source_refuses_gif_and_svg(): void {
        $this->resetAfterTest();
        $image = imagecreatetruecolor(10, 10);
        ob_start();
        imagegif($image);
        $gif = (string) ob_get_clean();
        imagedestroy($image);

        $this->assert_refused('error:image_edit_source', fn() => image_bounds::encode_edit_source(
            $this->store('picture.gif', $gif)
        ));
        $this->assert_refused('error:image_edit_source', fn() => image_bounds::encode_edit_source(
            $this->store('drawing.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>')
        ));
    }

    /**
     * A file over 4 MiB is refused from its record, before its content is read.
     */
    public function test_edit_source_refuses_large_file_before_reading_it(): void {
        $file = $this->createMock(\stored_file::class);
        $file->method('get_mimetype')->willReturn('image/png');
        $file->method('get_filesize')->willReturn(image_bounds::SOURCE_MAX_BYTES + 1);
        $file->expects($this->never())->method('get_content');
        $file->expects($this->never())->method('get_imageinfo');

        $this->assert_refused('error:image_edit_source', fn() => image_bounds::encode_edit_source($file));
    }

    /**
     * A PNG wider than 4096 pixels is refused; a 1536 x 1024 PNG is encoded.
     */
    public function test_edit_source_dimensions(): void {
        $this->resetAfterTest();

        $this->assert_refused('error:image_edit_source', fn() => image_bounds::encode_edit_source(
            $this->store('wide.png', self::png(5000, 100))
        ));

        $bytes = self::png(1536, 1024);
        $this->assertSame(base64_encode($bytes), image_bounds::encode_edit_source($this->store('photo.png', $bytes)));
    }

    /**
     * Resolving a pluginfile URL keeps the edit source check.
     */
    public function test_image_url_to_base64_propagates_the_refusal(): void {
        $this->resetAfterTest();
        $file = $this->store('wide.png', self::png(5000, 100));
        $url = \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        )->out(false);

        $this->assert_refused('error:image_edit_source', fn() => pluginfile_helper::image_url_to_base64($url));
    }
}
