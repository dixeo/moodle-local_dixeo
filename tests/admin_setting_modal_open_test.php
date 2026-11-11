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
 * Tests for the admin sections that show the Dixeo settings button.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

/**
 * The button appears on local_dixeo, on catalogue components and on pages that register themselves.
 *
 * @covers \local_dixeo\admin_setting_modal_open
 */
final class admin_setting_modal_open_test extends \advanced_testcase {
    public function test_a_component_that_adds_the_button_is_listed(): void {
        $this->resetAfterTest();
        $page = new \admin_settingpage('mod_assign_test_page', 'Test page');

        admin_setting_modal_open::add($page, 'mod_assign');

        $this->assertContains('mod_assign', admin_setting_modal_open::admin_sections());
    }

    public function test_local_dixeo_and_catalogue_components_are_listed(): void {
        $this->resetAfterTest();

        $sections = admin_setting_modal_open::admin_sections();

        $this->assertContains('local_dixeo', $sections);
        foreach (settings\catalogue::load() as $definition) {
            $this->assertContains($definition->get_component(), $sections);
        }
    }

    public function test_sections_are_listed_once(): void {
        $this->resetAfterTest();
        $page = new \admin_settingpage('local_dixeo_test_page', 'Test page');
        admin_setting_modal_open::add($page, 'local_dixeo');
        admin_setting_modal_open::add($page, 'local_dixeo');

        $sections = admin_setting_modal_open::admin_sections();

        $this->assertSame(array_values(array_unique($sections)), $sections);
    }

    public function test_an_unregistered_component_without_settings_is_not_listed(): void {
        $this->resetAfterTest();

        $this->assertNotContains('mod_forum', admin_setting_modal_open::admin_sections());
    }
}
