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
 * Tests for the Dixeo settings read service.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\external\get_settings;
use local_dixeo\external\service_factory;
use local_dixeo\settings\catalogue_fixture_trait;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/dixeo/tests/settings/catalogue_fixture_trait.php');

/**
 * The read service returns the helper's rows and rejects a user who cannot open the context.
 *
 * @covers \local_dixeo\external\get_settings
 */
final class get_settings_test extends \advanced_testcase {
    use catalogue_fixture_trait;

    /**
     * A teacher receives the same course rows the helper returns.
     */
    public function test_teacher_receives_the_course_layers(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $result = get_settings::execute($context->id);

        $rows = array_map(static function (array $row): array {
            unset($row['featurelabel'], $row['plugingrouplabel'], $row['sublabel']);
            return $row;
        }, $result['settings']);
        $this->assertEquals(service_factory::get_settings_config()->get_layers($context), $rows);
        get_settings::clean_returnvalue(get_settings::execute_returns(), $result);
    }

    /**
     * A student cannot read the course settings.
     */
    public function test_student_cannot_read_the_course_layers(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        get_settings::execute($context->id);
    }

    /**
     * Group labels come from the owning component, then from local_dixeo, then fall back to the id.
     */
    public function test_group_labels_follow_the_owner_then_local_dixeo_then_the_id(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->prime_catalogue_with([
            $this->label_row('test_label_own', 'local_dixeo', 'filesync', 'dixeo_core', ''),
            $this->label_row('test_label_fallback', 'mod_assign', 'imagegeneration', 'test_group', 'test_sub'),
        ]);

        $rows = array_column(get_settings::execute(\context_system::instance()->id)['settings'], null, 'name');

        $own = $rows['test_label_own'];
        $this->assertSame(get_string('settings_category_filesync', 'local_dixeo'), $own['featurelabel']);
        $this->assertSame(get_string('settings_category_dixeo_core', 'local_dixeo'), $own['plugingrouplabel']);
        $this->assertSame('', $own['sublabel']);
        $fallback = $rows['test_label_fallback'];
        $this->assertSame(get_string('settings_category_imagegeneration', 'local_dixeo'), $fallback['featurelabel']);
        $this->assertSame('test_group', $fallback['plugingrouplabel']);
        $this->assertSame('test_sub', $fallback['sublabel']);
    }

    /**
     * A label defined by the owning component wins over local_dixeo's.
     */
    public function test_the_owning_component_label_is_preferred(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        if (!\local_dixeo\service\plugin_installation_service::is_plugin_installed('plagiarism_dixeo')) {
            $this->markTestSkipped('No installed component defines its own group labels.');
        }

        $rows = array_column(get_settings::execute(\context_system::instance()->id)['settings'], null, 'name');

        $row = $rows['assignment_feedback_enabled'];
        $this->assertSame(get_string('settings_category_assignment', 'plagiarism_dixeo'), $row['featurelabel']);
        $this->assertSame(get_string('settings_subcategory_feedback', 'plagiarism_dixeo'), $row['sublabel']);
    }

    /**
     * Catalogue row at the Dixeo level for label tests.
     *
     * @param string $key Catalogue key.
     * @param string $component Owning component.
     * @param string $feature Feature id.
     * @param string $plugingroup Plugin group id.
     * @param string $sub Subcategory id, or empty for none.
     * @return array
     */
    private function label_row(string $key, string $component, string $feature, string $plugingroup, string $sub): array {
        $row = [
            'key' => $key,
            'shortname' => $key,
            'component' => $component,
            'type' => 'bool',
            'default' => '0',
            'levels' => ['dixeo'],
            'feature' => $feature,
            'plugingroup' => $plugingroup,
            'help' => 'enabled_help',
            'sensitive' => false,
        ];
        if ($sub !== '') {
            $row['sub'] = $sub;
        }
        return $row;
    }
}
