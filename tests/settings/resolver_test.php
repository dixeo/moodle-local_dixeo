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

/**
 * Tests for {@see \local_dixeo\settings\resolver}.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\settings;

/**
 * Unit tests for the course parent chain.
 *
 * @covers \local_dixeo\settings\resolver
 */
final class resolver_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(false);
    }

    /**
     * Course inherits site, then Dixeo. Activity is not in that chain.
     */
    public function test_course_chain_is_site_then_dixeo_then_course(): void {
        $this->assertSame(
            [resolver::LAYER_SITE, resolver::LAYER_DIXEO, resolver::LAYER_COURSE],
            resolver::chain(resolver::LAYER_COURSE)
        );
        $this->assertNotContains('activity', resolver::chain(resolver::LAYER_COURSE));
    }

    /**
     * There is no activity chain. Activity does not sit under the course.
     */
    public function test_activity_level_has_no_chain(): void {
        $this->expectException(\coding_exception::class);
        resolver::chain('activity');
    }

    /**
     * The last layer with its own value wins. An unset course uses Dixeo, then the site default.
     */
    public function test_effective_value_uses_the_last_stored_layer(): void {
        $stored = [
            resolver::LAYER_SITE => 'shipped',
            resolver::LAYER_DIXEO => 'site text',
            resolver::LAYER_COURSE => null,
        ];
        $course = resolver::effective(resolver::LAYER_COURSE, $stored);
        $this->assertSame('site text', $course['value']);
        $this->assertSame(resolver::LAYER_DIXEO, $course['source']);

        $stored[resolver::LAYER_DIXEO] = null;
        $fallback = resolver::effective(resolver::LAYER_COURSE, $stored);
        $this->assertSame('shipped', $fallback['value']);
        $this->assertSame(resolver::LAYER_SITE, $fallback['source']);

        $stored[resolver::LAYER_COURSE] = 'course text';
        $own = resolver::effective(resolver::LAYER_COURSE, $stored);
        $this->assertSame('course text', $own['value']);
        $this->assertSame(resolver::LAYER_COURSE, $own['source']);
    }

    /**
     * A value equal to its parent is not stored. A different value is.
     */
    public function test_value_equal_to_parent_is_not_an_override(): void {
        $stored = [
            resolver::LAYER_SITE => '',
            resolver::LAYER_DIXEO => 'site text',
        ];
        $this->assertFalse(resolver::stores_override(resolver::LAYER_COURSE, 'site text', $stored));
        $this->assertTrue(resolver::stores_override(resolver::LAYER_COURSE, 'course text', $stored));
        $this->assertFalse(resolver::stores_override(resolver::LAYER_DIXEO, '', [
            resolver::LAYER_SITE => '',
        ]));
        $this->assertTrue(resolver::stores_override(resolver::LAYER_DIXEO, 'site text', [
            resolver::LAYER_SITE => '',
        ]));
    }
}
