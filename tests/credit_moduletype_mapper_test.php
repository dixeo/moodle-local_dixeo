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

namespace local_dixeo;

use local_dixeo\util\credit_moduletype_mapper;

/**
 * Tests for credit report activity type labels.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_dixeo\util\credit_moduletype_mapper
 */
final class credit_moduletype_mapper_test extends \advanced_testcase {
    public function test_get_label_empty(): void {
        $this->assertSame('', credit_moduletype_mapper::get_label(null));
        $this->assertSame('', credit_moduletype_mapper::get_label(''));
        $this->assertSame('', credit_moduletype_mapper::get_label('   '));
    }

    public function test_get_label_uses_plugin_string(): void {
        $this->assertSame(
            get_string('credit_moduletype_page', 'local_dixeo'),
            credit_moduletype_mapper::get_label('page')
        );
        $this->assertSame(
            get_string('credit_moduletype_slideshow', 'local_dixeo'),
            credit_moduletype_mapper::get_label('slideshow')
        );
    }

    public function test_get_label_falls_back_to_humanized_code(): void {
        $this->assertSame('Foo Bar', credit_moduletype_mapper::get_label('foo_bar'));
    }

    public function test_get_label_falls_back_to_mod_string(): void {
        $this->assertSame(
            get_string('modulename', 'mod_forum'),
            credit_moduletype_mapper::get_label('forum')
        );
    }
}
