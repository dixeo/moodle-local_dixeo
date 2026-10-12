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
 * Tests for {@see \local_dixeo\settings\catalogue}.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\settings;

/**
 * Unit tests for the Dixeo settings catalogue loader.
 *
 * @covers \local_dixeo\settings\catalogue
 */
final class catalogue_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(false);
    }

    /**
     * Learning intent is a course-only textarea and does not override a core setting.
     */
    public function test_load_returns_learning_intent_without_core_target(): void {
        $settings = catalogue::load();

        $this->assertArrayHasKey('learning_intent', $settings);
        $setting = $settings['learning_intent'];
        $this->assertSame('learning_intent', $setting->get_shortname());
        $this->assertSame('local_dixeo', $setting->get_component());
        $this->assertSame('textarea', $setting->get_type());
        $this->assertSame('', $setting->get_default());
        $this->assertSame(['course'], $setting->get_levels());
        $this->assertSame('courses', $setting->get_feature());
        $this->assertSame('dixeo_core', $setting->get_plugingroup());
        $this->assertSame('learning_intent_help', $setting->get_help());
        $this->assertFalse($setting->is_sensitive());
        $this->assertFalse($setting->has_core());
        $this->assertTrue(get_string_manager()->string_exists('learning_intent', 'local_dixeo'));
        $this->assertTrue(get_string_manager()->string_exists('learning_intent_help', 'local_dixeo'));
    }

    /**
     * The master switch is a bool at the Dixeo and course levels and does not override a core setting.
     */
    public function test_load_returns_enabled_without_core_target(): void {
        $settings = catalogue::load();

        $this->assertArrayHasKey('enabled', $settings);
        $setting = $settings['enabled'];
        $this->assertSame('enabled', $setting->get_shortname());
        $this->assertSame('local_dixeo', $setting->get_component());
        $this->assertSame('bool', $setting->get_type());
        $this->assertSame('1', $setting->get_default());
        $this->assertSame(['dixeo', 'course'], $setting->get_levels());
        $this->assertSame('dixeo', $setting->get_feature());
        $this->assertSame('dixeo_core', $setting->get_plugingroup());
        $this->assertSame('enabled_help', $setting->get_help());
        $this->assertFalse($setting->is_sensitive());
        $this->assertFalse($setting->has_core());
        $this->assertTrue(get_string_manager()->string_exists('enabled', 'local_dixeo'));
        $this->assertTrue(get_string_manager()->string_exists('enabled_help', 'local_dixeo'));
        $this->assertTrue(get_string_manager()->string_exists('enabled_course', 'local_dixeo'));
        $this->assertTrue(get_string_manager()->string_exists('enabled_course_help', 'local_dixeo'));
        $this->assertSame([], $setting->get_options());
    }

    /**
     * File sync for new courses is a Dixeo-level bool and is off until an admin turns it on.
     */
    public function test_load_returns_filesync_enabled_default_without_core_target(): void {
        $settings = catalogue::load();

        $this->assertArrayHasKey('filesync_enabled_default', $settings);
        $setting = $settings['filesync_enabled_default'];
        $this->assertSame('bool', $setting->get_type());
        $this->assertSame('0', $setting->get_default());
        $this->assertSame(['dixeo'], $setting->get_levels());
        $this->assertSame('filesync', $setting->get_feature());
        $this->assertSame('dixeo_core', $setting->get_plugingroup());
        $this->assertSame('filesync_enabled_default_help', $setting->get_help());
        $this->assertFalse($setting->is_sensitive());
        $this->assertFalse($setting->has_core());
        $this->assertSame([], $setting->get_options());
        $this->assertTrue(get_string_manager()->string_exists('filesync_enabled_default', 'local_dixeo'));
        $this->assertTrue(get_string_manager()->string_exists('filesync_enabled_default_help', 'local_dixeo'));
    }

    /**
     * The status pill location is a select at the Dixeo and course levels.
     */
    public function test_load_returns_filesync_pill_with_options(): void {
        $settings = catalogue::load();

        $this->assertArrayHasKey('filesync_pill', $settings);
        $setting = $settings['filesync_pill'];
        $this->assertSame('select', $setting->get_type());
        $this->assertSame('coursetitle', $setting->get_default());
        $this->assertSame(['hidden', 'coursetitle', 'footer'], $setting->get_options());
        $this->assertSame(['dixeo', 'course'], $setting->get_levels());
        $this->assertSame('filesync', $setting->get_feature());
        $this->assertSame('dixeo_core', $setting->get_plugingroup());
        $this->assertSame('filesync_pill_help', $setting->get_help());
        $this->assertFalse($setting->is_sensitive());
        $this->assertFalse($setting->has_core());
        foreach ($setting->get_options() as $option) {
            $this->assertTrue(get_string_manager()->string_exists('filesync_pill_' . $option, 'local_dixeo'));
        }
        $this->assertTrue(get_string_manager()->string_exists('filesync_pill', 'local_dixeo'));
        $this->assertTrue(get_string_manager()->string_exists('filesync_pill_help', 'local_dixeo'));
    }

    /**
     * A core target that exists in the admin tree is kept.
     */
    public function test_existing_core_target_is_accepted(): void {
        $settings = catalogue::from_definitions([
            $this->sample_row([
                'plugin' => 'assign',
                'name' => 'duedate',
            ]),
        ]);

        $this->assertTrue($settings['sample']->has_core());
        $this->assertSame('assign/duedate', $settings['sample']->get_core()->admin_setting_name());
    }

    /**
     * A core target that is not an admin setting is rejected, not treated as Dixeo-only.
     */
    public function test_missing_core_target_is_rejected(): void {
        $this->expectException(unresolved_core_target_exception::class);

        catalogue::from_definitions([
            $this->sample_row([
                'plugin' => 'assign',
                'name' => 'this_setting_does_not_exist_dixeo',
            ]),
        ]);
    }

    /**
     * @param array $core Core target to attach.
     * @return array
     */
    private function sample_row(array $core): array {
        return [
            'key' => 'sample',
            'shortname' => 'sample',
            'component' => 'local_dixeo',
            'type' => 'text',
            'default' => '',
            'levels' => ['dixeo'],
            'feature' => 'courses',
            'plugingroup' => 'dixeo_core',
            'help' => 'learning_intent_help',
            'sensitive' => false,
            'core' => $core,
        ];
    }
}
