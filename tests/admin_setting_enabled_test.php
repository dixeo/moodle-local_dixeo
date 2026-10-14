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
     * The Image generation section is omitted while Dixeo is off and returns when it is on.
     */
    public function test_image_generation_section_follows_the_dixeo_switch(): void {
        $config = \local_dixeo\external\service_factory::get_settings_config();
        $system = \context_system::instance();

        $config->set('local_dixeo', 'enabled', $system, '0');
        $off = $this->settings_page();
        $this->assertTrue(isset($off->settings->local_dixeoenabled));
        $this->assertTrue(isset($off->settings->local_dixeoapi_url));
        $this->assertFalse(isset($off->settings->local_dixeoimage_generation));
        $this->assertFalse(isset($off->settings->local_dixeoimage_generation_enabled));
        $this->assertFalse(isset($off->settings->local_dixeoimage_generation_course_mode));
        $this->assertFalse(isset($off->settings->local_dixeoimage_generation_section_mode));
        $this->assertFalse(isset($off->settings->local_dixeoimage_generation_content_mode));

        $config->set('local_dixeo', 'enabled', $system, '1');
        $on = $this->settings_page();
        $this->assertTrue(isset($on->settings->local_dixeoimage_generation));
        $this->assertTrue(isset($on->settings->local_dixeoimage_generation_content_mode));
    }

    /**
     * Reload the Dixeo admin page from the current switch value.
     *
     * @return \admin_settingpage
     */
    private function settings_page(): \admin_settingpage {
        $page = admin_get_root(true, true)->locate('local_dixeo');
        $this->assertInstanceOf(\admin_settingpage::class, $page);
        return $page;
    }

    /**
     * The admin page button opens the Dixeo-layer modal, not a course.
     */
    public function test_admin_page_button_opens_the_dixeo_layer_modal(): void {
        $page = $this->settings_page();
        $this->assertTrue(isset($page->settings->local_dixeosettings_modal_open));

        $html = $page->settings->local_dixeosettings_modal_open->output_html('');
        $this->assertStringContainsString('data-action="local-dixeo-settings-open"', $html);
        $this->assertStringContainsString(
            'data-contextid="' . \context_system::instance()->id . '"',
            $html
        );
        $this->assertStringContainsString(get_string('settings_button', 'local_dixeo'), $html);
        $this->assertStringContainsString('type="button"', $html);
        $this->assertStringNotContainsString('data-courseid', $html);
    }

    /**
     * Every other Dixeo plugin admin page has the same button.
     */
    public function test_other_plugin_pages_open_the_dixeo_layer_modal(): void {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        $root = \admin_get_root(true, true);
        $sections = [
            'blocksettingdixeo_tutor' => 'block_dixeo_tutorsettings_modal_open',
            'blocksettingdixeo_designer' => 'block_dixeo_designersettings_modal_open',
            'local_dixeo_assistant' => 'local_dixeo_assistantsettings_modal_open',
        ];
        $checked = 0;
        foreach ($sections as $section => $settingname) {
            $page = $root->locate($section);
            if (!$page instanceof \admin_settingpage) {
                continue;
            }
            $checked++;
            $this->assertTrue(isset($page->settings->$settingname), $section);
            $html = $page->settings->$settingname->output_html('');
            $this->assertStringContainsString('data-action="local-dixeo-settings-open"', $html, $section);
            $this->assertStringContainsString(
                'data-contextid="' . \context_system::instance()->id . '"',
                $html,
                $section
            );
        }
        $this->assertGreaterThan(0, $checked);
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
