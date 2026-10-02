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

namespace local_dixeo\dsl;

/**
 * Tests for stripping active content from generated HTML.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\dsl\generated_html
 */
final class generated_html_test extends \advanced_testcase {
    /**
     * Script and event handlers are removed, and inline SVG is kept.
     */
    public function test_strips_script_and_event_handlers_and_keeps_svg(): void {
        $html = '<p onclick="alert(1)">Hi</p>'
            . '<script>alert(1)</script>'
            . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" onload="alert(1)">'
            . '<circle cx="5" cy="5" r="4" fill="red"></circle>'
            . '</svg>';

        $result = generated_html::strip_active_content($html);

        $this->assertStringNotContainsString('<script', strtolower($result));
        $this->assertStringNotContainsString('onclick', strtolower($result));
        $this->assertStringNotContainsString('onload', strtolower($result));
        $this->assertStringNotContainsString('alert(1)', $result);
        $this->assertStringContainsString('viewBox="0 0 10 10"', $result);
        $this->assertStringContainsString('<circle cx="5" cy="5" r="4" fill="red"></circle>', $result);
        $this->assertStringContainsString('>Hi</p>', $result);
    }

    /**
     * javascript: URLs are removed and only allowlisted iframes remain.
     */
    public function test_strips_javascript_urls_and_disallowed_iframes(): void {
        $html = '<a href="javascript:alert(1)">Go</a>'
            . '<iframe src="https://www.youtube.com/embed/abc"></iframe>'
            . '<iframe src="https://evil.example/x"></iframe>';

        $result = generated_html::strip_active_content($html);

        $this->assertStringNotContainsString('javascript:', strtolower($result));
        $this->assertStringContainsString('https://www.youtube.com/embed/abc', $result);
        $this->assertStringNotContainsString('evil.example', $result);
        $this->assertStringContainsString('>Go</a>', $result);
    }
}
