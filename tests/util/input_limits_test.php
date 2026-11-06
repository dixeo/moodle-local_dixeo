<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_dixeo\util;

/**
 * Tests for the shared input size primitives.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\util\input_limits
 */
final class input_limits_test extends \advanced_testcase {
    /**
     * A payload of exactly the maximum size decodes; one byte more does not.
     */
    public function test_decode_size_edge(): void {
        $json = json_encode(['a' => str_repeat('x', 10)]);
        $size = strlen($json);

        $this->assertSame(['a' => str_repeat('x', 10)], input_limits::decode_json_array($json, $size, 4));
        $this->assertNull(input_limits::decode_json_array($json, $size - 1, 4));
    }

    /**
     * Nesting beyond the depth limit, invalid JSON and scalars are refused.
     */
    public function test_decode_depth_and_type(): void {
        $nested = '[[["deep"]]]';
        $this->assertSame([[['deep']]], input_limits::decode_json_array($nested, 100, 4));
        $this->assertNull(input_limits::decode_json_array($nested, 100, 3));
        $this->assertNull(input_limits::decode_json_array('{"a":', 100, 4));
        $this->assertNull(input_limits::decode_json_array('"text"', 100, 4));
        $this->assertNull(input_limits::decode_json_array('42', 100, 4));
        $this->assertSame([], input_limits::decode_json_array('[]', 100, 4));
    }

    /**
     * require_json_array() throws the too-large or the invalid error of the caller.
     */
    public function test_require_json_array_errors(): void {
        $this->assertSame(
            ['a' => 1],
            input_limits::require_json_array('{"a":1}', 100, 4, 'local_dixeo', 'error:busy', 'error:unexpected')
        );

        try {
            input_limits::require_json_array('{"a":1}', 3, 4, 'local_dixeo', 'error:busy', 'error:unexpected');
            $this->fail('An oversized payload must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:busy', $e->errorcode);
        }

        try {
            input_limits::require_json_array('"scalar"', 100, 4, 'local_dixeo', 'error:busy', 'error:unexpected');
            $this->fail('A scalar must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:unexpected', $e->errorcode);
        }
    }

    /**
     * Byte limits count bytes; character limits count characters.
     */
    public function test_bytes_versus_characters(): void {
        $text = str_repeat('é', 10);

        input_limits::require_max_chars($text, 10, 'error:busy', 'local_dixeo');
        input_limits::require_max_bytes($text, 20, 'title');

        try {
            input_limits::require_max_bytes($text, 19, 'title');
            $this->fail('Twenty bytes exceed a nineteen-byte limit.');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString('title is too long', $e->getMessage());
        }

        $this->expectException(\moodle_exception::class);
        input_limits::require_max_chars($text, 9, 'error:busy', 'local_dixeo');
    }

    /**
     * Item counts, string sizes and depth are bounded at every level.
     */
    public function test_is_bounded(): void {
        $value = ['a', ['bb', ['ccc']]];

        $this->assertTrue(input_limits::is_bounded($value, 2, 3, 3));
        $this->assertFalse(input_limits::is_bounded($value, 2, 3, 2));
        $this->assertFalse(input_limits::is_bounded($value, 2, 2, 3));
        $this->assertFalse(input_limits::is_bounded(['a', 'b', 'c'], 2, 3, 2));
        $this->assertFalse(input_limits::is_bounded([[1, 2, 3]], 2, 3, 2));
        $this->assertTrue(input_limits::is_bounded(42, 0, 0, 0));
    }

    /**
     * A value of exactly the byte limit passes; one byte more stops with a localised size message.
     */
    public function test_require_max_size(): void {
        input_limits::require_max_size(str_repeat('x', 10), 10, 'error:content_too_long', 'local_dixeo');

        try {
            input_limits::require_max_size(str_repeat('x', 11), 10, 'error:content_too_long', 'local_dixeo');
            $this->fail('A value over the byte limit must stop.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:content_too_long', $e->errorcode);
            $this->assertSame('local_dixeo', $e->module);
            $this->assertSame(display_size(10), $e->a);
            $this->assertStringContainsString(display_size(10), $e->getMessage());
        }
    }
}
