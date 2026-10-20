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
     * File sync on a course is a retained bool. Off stays stored.
     */
    public function test_load_returns_filesync_enabled_retained_on_the_course(): void {
        $settings = catalogue::load();

        $this->assertArrayHasKey('filesync_enabled', $settings);
        $setting = $settings['filesync_enabled'];
        $this->assertSame('bool', $setting->get_type());
        $this->assertSame('0', $setting->get_default());
        $this->assertSame(['course'], $setting->get_levels());
        $this->assertSame('filesync', $setting->get_feature());
        $this->assertSame('dixeo_core', $setting->get_plugingroup());
        $this->assertSame('filesync_enabled_help', $setting->get_help());
        $this->assertTrue($setting->retains_value());
        $this->assertSame('filesync_enabled_default', $setting->get_retain_parent());
        $this->assertFalse($setting->is_sensitive());
        $this->assertFalse($setting->has_core());
        $this->assertSame([], $setting->get_options());
        $this->assertTrue(get_string_manager()->string_exists('filesync_enabled', 'local_dixeo'));
        $this->assertTrue(get_string_manager()->string_exists('filesync_enabled_help', 'local_dixeo'));
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
     * Image generation modes and the enabled switch can be overridden per course.
     */
    public function test_load_returns_image_generation_course_overrides(): void {
        $settings = catalogue::load();
        $keys = [
            'image_generation_enabled',
            'image_generation_course_mode',
            'image_generation_section_mode',
            'image_generation_content_mode',
        ];
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $settings);
            $setting = $settings[$key];
            $this->assertSame(['dixeo', 'course'], $setting->get_levels());
            $this->assertSame('imagegeneration', $setting->get_feature());
            $this->assertSame('dixeo_core', $setting->get_plugingroup());
            $this->assertFalse($setting->has_core());
            $this->assertTrue(get_string_manager()->string_exists($key, 'local_dixeo'));
            $this->assertTrue(get_string_manager()->string_exists($setting->get_help(), 'local_dixeo'));
        }

        $this->assertSame('bool', $settings['image_generation_enabled']->get_type());
        $this->assertSame('0', $settings['image_generation_enabled']->get_default());
        foreach (['image_generation_course_mode', 'image_generation_section_mode', 'image_generation_content_mode'] as $key) {
            $setting = $settings[$key];
            $this->assertSame('select', $setting->get_type());
            $this->assertSame('disabled', $setting->get_default());
            $this->assertSame(['disabled', 'generate', 'generate_edit'], $setting->get_options());
            foreach ($setting->get_options() as $option) {
                $this->assertTrue(get_string_manager()->string_exists($key . '_' . $option, 'local_dixeo'));
            }
        }
        $this->assertTrue(get_string_manager()->string_exists('settings_category_imagegeneration', 'local_dixeo'));
    }

    /**
     * Designer defaults stay at the Dixeo layer. The pedagogical template is also a read-only course snapshot.
     */
    public function test_load_returns_designer_settings(): void {
        $settings = catalogue::load();

        $category = $settings['categoryname'];
        $this->assertSame('block_dixeo_designer', $category->get_component());
        $this->assertSame('text', $category->get_type());
        $this->assertSame(['dixeo'], $category->get_levels());
        $this->assertSame('designer', $category->get_feature());
        $this->assertSame(get_string('default_categoryname', 'block_dixeo_designer'), $category->get_default());
        $this->assertFalse($category->records_course_value());

        $configure = $settings['self_enrol_configure'];
        $this->assertSame(['dixeo'], $configure->get_levels());
        $this->assertSame('bool', $configure->get_type());
        $this->assertSame('0', $configure->get_default());
        $this->assertSame('selfenrol', $configure->get_sub());
        $generatekey = $settings['self_enrol_generate_key'];
        $this->assertSame(['dixeo'], $generatekey->get_levels());
        $this->assertSame('1', $generatekey->get_default());
        $this->assertSame('selfenrol', $generatekey->get_sub());

        $template = $settings['coursetemplate'];
        $this->assertSame(['dixeo', 'course'], $template->get_levels());
        $this->assertSame('select', $template->get_type());
        $this->assertSame('', $template->get_default());
        $this->assertContains('', $template->get_options());
        $this->assertTrue($template->records_course_value());
        $this->assertTrue($template->is_readonly_at('course'));
        $this->assertFalse($template->is_readonly_at('dixeo'));
        $this->assertSame(
            get_string('coursetemplate_none', 'block_dixeo_designer'),
            $template->get_labels()['']
        );

        // Certificate rows are registered only when the certificate plugins are installed.
        $certificatekeys = [
            'certificate_generation',
            'certificate_template',
            'certificate_location',
        ];
        $certificates = block_dixeo_designer_certificate_settings() !== [];
        if ($certificates) {
            $this->assertArrayHasKey('certificate_generation', $settings);
            $this->assertSame(['dixeo'], $settings['certificate_generation']->get_levels());
            $this->assertSame('0', $settings['certificate_generation']->get_default());
            $this->assertSame('certificate', $settings['certificate_generation']->get_sub());
            $this->assertSame('bool', $settings['certificate_generation']->get_type());
            $this->assertSame(['dixeo'], $settings['certificate_template']->get_levels());
            $this->assertSame('select', $settings['certificate_template']->get_type());
            $location = $settings['certificate_location'];
            $this->assertSame(['dixeo'], $location->get_levels());
            $this->assertSame(['summary', 'last'], $location->get_options());
            $this->assertSame('last', $location->get_default());
        } else {
            foreach ($certificatekeys as $key) {
                $this->assertArrayNotHasKey($key, $settings);
            }
        }

        $designerkeys = [
            'categoryname',
            'coursetemplate',
        ];
        if ($certificates) {
            $designerkeys = array_merge($designerkeys, $certificatekeys);
        }
        $ltikeys = [
            'lti_publication_enabled',
            'lti_maxenrolled',
            'lti_membersync',
            'lti_membersyncmode',
        ];
        if (class_exists(\enrol_lti\helper::class)) {
            $designerkeys = array_merge($designerkeys, $ltikeys);
            $this->assertSame('bool', $settings['lti_publication_enabled']->get_type());
            $this->assertSame('0', $settings['lti_publication_enabled']->get_default());
            $this->assertSame('lti', $settings['lti_publication_enabled']->get_sub());
            $this->assertSame('number', $settings['lti_maxenrolled']->get_type());
            $this->assertSame('0', $settings['lti_maxenrolled']->get_default());
            $this->assertSame(['0', '1'], $settings['lti_membersync']->get_options());
            $this->assertSame(
                [
                    (string) \enrol_lti\helper::MEMBER_SYNC_ENROL_AND_UNENROL,
                    (string) \enrol_lti\helper::MEMBER_SYNC_ENROL_NEW,
                    (string) \enrol_lti\helper::MEMBER_SYNC_UNENROL_MISSING,
                ],
                $settings['lti_membersyncmode']->get_options()
            );
        } else {
            foreach ($ltikeys as $key) {
                $this->assertArrayNotHasKey($key, $settings);
            }
        }
        $designerkeys = array_merge($designerkeys, [
            'self_enrol_configure',
            'self_enrol_generate_key',
        ]);
        foreach ($designerkeys as $key) {
            $setting = $settings[$key];
            $this->assertFalse($setting->has_core());
            $this->assertTrue(get_string_manager()->string_exists($key, 'block_dixeo_designer'));
            $this->assertTrue(get_string_manager()->string_exists($setting->get_help(), 'block_dixeo_designer'));
        }
        $this->assertTrue(get_string_manager()->string_exists('settings_category_designer', 'local_dixeo'));
        $this->assertTrue(get_string_manager()->string_exists('settings_category_dixeo_designer', 'local_dixeo'));

        $order = [];
        foreach ($settings as $key => $setting) {
            if ($setting->get_component() === 'block_dixeo_designer') {
                $order[] = $key;
            }
        }
        $this->assertSame($designerkeys, $order);
    }

    /**
     * Settings from a plugin that is not installed are left out of the catalogue and the config UI.
     */
    public function test_uninstalled_plugins_are_omitted_from_the_catalogue_and_the_config_ui(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        unset_config('version', 'block_dixeo_tutor');
        unset_config('version', 'block_dixeo_designer');
        unset_config('version', 'plagiarism_dixeo');

        $settings = catalogue::load();
        $this->assertArrayHasKey('enabled', $settings);
        $this->assertSame('local_dixeo', $settings['enabled']->get_component());
        foreach ($settings as $setting) {
            $this->assertNotContains($setting->get_component(), [
                'block_dixeo_tutor',
                'block_dixeo_designer',
                'plagiarism_dixeo',
            ]);
            $this->assertTrue(
                \local_dixeo\service\plugin_installation_service::is_plugin_installed($setting->get_component()),
                $setting->get_component()
            );
        }

        $context = \context_system::instance();
        $rows = (new config())->get_layers($context);
        $components = array_column($rows, 'component');
        $this->assertContains('local_dixeo', $components);
        $this->assertNotContains('block_dixeo_tutor', $components);
        $this->assertNotContains('block_dixeo_designer', $components);
        $this->assertNotContains('plagiarism_dixeo', $components);

        $result = \local_dixeo\external\get_settings::execute($context->id);
        $served = array_column($result['settings'], 'component');
        $this->assertContains('local_dixeo', $served);
        $this->assertNotContains('block_dixeo_tutor', $served);
        $this->assertNotContains('block_dixeo_designer', $served);
        $this->assertNotContains('plagiarism_dixeo', $served);
    }

    /**
     * Tutor for new courses is the first Tutor setting.
     */
    public function test_tutor_for_new_courses_is_first(): void {
        $order = [];
        foreach (catalogue::load() as $key => $setting) {
            if ($setting->get_feature() !== 'tutor') {
                continue;
            }
            $order[] = $key;
        }

        $this->assertSame('tutor_enabled_default', $order[0]);
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
     * Catalogue row that overrides the given core target.
     *
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
