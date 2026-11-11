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
 * Tests for storing a component's Dixeo defaults at install.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\settings;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/dixeo/tests/settings/catalogue_fixture_trait.php');

/**
 * Only the given component's missing Dixeo-level defaults are written.
 *
 * @covers \local_dixeo\settings\config::install_dixeo_defaults
 */
final class config_install_defaults_test extends \advanced_testcase {
    use catalogue_fixture_trait;

    public function test_only_missing_dixeo_defaults_of_the_component_are_written(): void {
        $this->resetAfterTest();
        $this->prime_catalogue_with([
            [
                'key' => 'test_other_switch',
                'shortname' => 'enabled',
                'component' => 'mod_assign',
                'type' => 'bool',
                'default' => '1',
                'levels' => ['dixeo'],
                'feature' => 'dixeo',
                'plugingroup' => 'dixeo_core',
                'help' => 'enabled_help',
                'sensitive' => false,
            ],
        ]);
        $this->prime_catalogue_fixtures();
        unset_config('test_course_switch', 'local_dixeo');
        unset_config('test_course_text', 'local_dixeo');
        unset_config('test_other_switch', 'mod_assign');
        set_config('filesync_pill', 'hidden', 'local_dixeo');
        unset_config('image_generation_enabled', 'local_dixeo');

        config::install_dixeo_defaults('local_dixeo');

        $this->assertSame('1', get_config('local_dixeo', 'test_course_switch'));
        $this->assertSame('0', get_config('local_dixeo', 'image_generation_enabled'));
        $this->assertSame('hidden', get_config('local_dixeo', 'filesync_pill'));
        $this->assertFalse(get_config('local_dixeo', 'test_course_text'));
        $this->assertFalse(get_config('mod_assign', 'test_other_switch'));
    }
}
