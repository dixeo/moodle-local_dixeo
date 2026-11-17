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

namespace local_dixeo\util;

/**
 * Tests for the date request parameter check of the site calendar.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_dixeo\util\site_calendar::normalise_date_param
 */
final class site_calendar_test extends \basic_testcase {
    /**
     * Only real dates written exactly as Y-m-d are kept.
     *
     * @dataProvider date_param_provider
     * @param string $value Raw request value.
     * @param string $expected Normalised value.
     */
    public function test_normalise_date_param(string $value, string $expected): void {
        $this->assertSame($expected, site_calendar::normalise_date_param($value));
    }

    /**
     * Raw values and their normalised form.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function date_param_provider(): array {
        return [
            'real date' => ['2026-11-17', '2026-11-17'],
            'leap day' => ['2028-02-29', '2028-02-29'],
            'word' => ['x', ''],
            'relative format' => ['+500 years', ''],
            'overflow date' => ['2026-02-31', ''],
            'unpadded' => ['2026-1-5', ''],
            'empty' => ['', ''],
            'trailing text' => ['2026-11-17x', ''],
        ];
    }
}
