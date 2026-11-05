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

namespace local_dixeo\api;

/**
 * Tests for the client-safe messages of API error codes.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\api\error_message
 */
final class error_message_test extends \basic_testcase {
    /**
     * A known code maps to its local_dixeo string.
     */
    public function test_known_code_returns_mapped_string(): void {
        $this->assertSame(get_string('error:busy', 'local_dixeo'), error_message::message('rate_limit_exceeded'));
        $this->assertSame(get_string('error:unavailable', 'local_dixeo'), error_message::message('upstream_ai'));
    }

    /**
     * An unknown or missing code uses the default fallback.
     */
    public function test_unknown_and_null_codes_return_fallback(): void {
        $fallback = get_string('error:unexpected', 'local_dixeo');
        $this->assertSame($fallback, error_message::message('internal_diagnostics'));
        $this->assertSame($fallback, error_message::message(null));
    }

    /**
     * The fallback may come from another component, but a known code still wins.
     */
    public function test_custom_fallback_component(): void {
        $this->assertSame(get_string('error', 'moodle'), error_message::message('weird', 'error', 'moodle'));
        $this->assertSame(
            get_string('error:busy', 'local_dixeo'),
            error_message::message('rate_limited', 'error', 'moodle')
        );
    }
}
