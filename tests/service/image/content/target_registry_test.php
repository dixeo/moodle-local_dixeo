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
 * Tests for target_registry.
 *
 * @covers \local_dixeo\service\image\content\target_registry
 * @package local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class target_registry_test extends \advanced_testcase {
    /**
     * Test resolve page content.
     */
    public function test_resolve_page_content(): void {
        $target = target_registry::resolve('page', 'content', 7, 3, 2, 11);
        $this->assertNotNull($target);
        $this->assertSame('mod_page', $target->component);
        $this->assertSame('content', $target->filearea);
        $this->assertSame(0, $target->itemid);
    }

    /**
     * Test resolve unknown returns null.
     */
    public function test_resolve_unknown_returns_null(): void {
        $this->resetDebugging();
        $this->assertNull(target_registry::resolve('forum', 'intro', 1, 1, 1));
        $this->assertDebuggingCalled();
    }

    /**
     * Test is html field.
     */
    public function test_is_html_field(): void {
        $record = (object) ['intro' => 'x', 'introformat' => FORMAT_HTML];
        $this->assertTrue(target_registry::is_html_field($record, 'intro'));

        $plain = (object) ['intro' => 'x', 'introformat' => FORMAT_PLAIN];
        $this->assertFalse(target_registry::is_html_field($plain, 'intro'));
    }
}
