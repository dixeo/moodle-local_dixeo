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
 * Tests for shortcode_parser.
 *
 * @covers \local_dixeo\service\image\content\shortcode_parser
 * @package local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class shortcode_parser_test extends \advanced_testcase {
    /**
     * Test find all valid shortcode.
     */
    public function test_find_all_valid_shortcode(): void {
        $html = '[img-gen prompt="Sunset over mountains" quality="high" mode="portrait"]';
        $parsed = shortcode_parser::find_all($html);
        $this->assertCount(1, $parsed);
        $this->assertSame('Sunset over mountains', $parsed[0]['prompt']);
        $this->assertSame('high', $parsed[0]['quality']);
        $this->assertSame('portrait', $parsed[0]['mode']);
        $this->assertSame('1024x1536', $parsed[0]['size']);
    }

    /**
     * Test strip all.
     */
    public function test_strip_all(): void {
        $html = 'Before [img-gen prompt="Test image"] after';
        $this->assertSame('Before  after', shortcode_parser::strip_all($html));
    }

    /**
     * Test invalid shortcode ignored.
     */
    public function test_invalid_shortcode_ignored(): void {
        $html = '[img-gen unknown="x" prompt="Ok"]';
        $this->assertSame([], shortcode_parser::find_all($html));
    }

    /**
     * Test ref shortcode parsed without prompt.
     */
    public function test_ref_shortcode_parsed_without_prompt(): void {
        $html = '[img-gen ref="abc-123"]';
        $parsed = shortcode_parser::find_all($html);
        $this->assertCount(1, $parsed);
        $this->assertSame('abc-123', $parsed[0]['ref']);
        $this->assertFalse(shortcode_parser::is_new_generation($parsed[0]));
    }

    /**
     * Test file shortcode parsed.
     */
    public function test_file_shortcode_parsed(): void {
        $html = '[img-gen file="diagram.png"]';
        $parsed = shortcode_parser::find_all($html);
        $this->assertCount(1, $parsed);
        $this->assertSame('diagram.png', $parsed[0]['file']);
    }
}
