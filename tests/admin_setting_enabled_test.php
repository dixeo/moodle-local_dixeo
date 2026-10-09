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
 * Tests for {@see \local_dixeo\admin_setting_enabled}.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

/**
 * Admin checkbox tests for the Dixeo master switch.
 *
 * @covers \local_dixeo\admin_setting_enabled
 */
final class admin_setting_enabled_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    /**
     * Unset reads as On. Saving Off stores the override. Saving On deletes it.
     */
    public function test_checkbox_reads_and_writes_through_the_config_helper(): void {
        $setting = new admin_setting_enabled();

        $this->assertSame('1', $setting->get_setting());
        $this->assertFalse(get_config('local_dixeo', 'enabled'));

        $this->assertSame('', $setting->write_setting('0'));
        $this->assertSame('0', get_config('local_dixeo', 'enabled'));
        $this->assertSame('0', $setting->get_setting());

        $this->assertSame('', $setting->write_setting('1'));
        $this->assertFalse(get_config('local_dixeo', 'enabled'));
        $this->assertSame('1', $setting->get_setting());
    }

    /**
     * A null write, used when no value was posted, does not turn Dixeo off.
     */
    public function test_null_write_leaves_the_stored_value_unchanged(): void {
        $setting = new admin_setting_enabled();
        $setting->write_setting('0');

        $this->assertSame('', $setting->write_setting(null));
        $this->assertSame('0', get_config('local_dixeo', 'enabled'));
    }
}
