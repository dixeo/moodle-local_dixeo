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
 * Tests for learning intent on the course edit form.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\hook;

use local_dixeo\external\service_factory;
use local_dixeo\service\file_sync_service;
use local_dixeo\settings\catalogue;
use local_dixeo\settings\catalogue_fixture_trait;
use local_dixeo\settings\definition;
use local_dixeo\settings\resolver;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/dixeo/tests/settings/catalogue_fixture_trait.php');

/**
 * Course form tests for the Dixeo section.
 *
 * @covers \local_dixeo\hook\course_edit_form
 */
final class course_edit_form_test extends \advanced_testcase {
    use catalogue_fixture_trait;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    /**
     * The Dixeo header, the settings button and the course switches are shown, with no learning intent field.
     */
    public function test_form_shows_the_dixeo_section(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/tests/fixtures/testable_course_edit_form.php');

        $course = $this->getDataGenerator()->create_course();

        $form = $this->edit_form($course, $DB);
        $form->definition_after_data();
        $mform = $form->get_quick_form();

        $this->assertTrue($mform->elementExists(course_edit_form::HEADER));
        $this->assertSame(
            get_string('settings_category', 'local_dixeo'),
            $mform->getElement(course_edit_form::HEADER)->toHtml()
        );
        $this->assertFalse($mform->elementExists('local_dixeo_learning_intent'));

        $button = $mform->getElement(course_edit_form::OPEN_BUTTON);
        $this->assertSame('button', $button->getAttribute('type'));
        $this->assertStringContainsString(get_string('settings_button', 'local_dixeo'), $button->getValue());
        $this->assertStringContainsString('local-dixeo-settings-logo', $button->getValue());
        $this->assertStringContainsString('width="16"', $button->getValue());
        $this->assertSame('local-dixeo-settings-open', $button->getAttribute('data-action'));
        $this->assertSame('local-dixeo-settings-open', $button->getAttribute('parentclass'));
        $this->assertSame($course->fullname, $button->getAttribute('data-course-name'));
        $this->assertSame(
            (string) \context_course::instance($course->id)->id,
            $button->getAttribute('data-contextid')
        );
        $this->assertSame((string) $course->id, $button->getAttribute('data-courseid'));
        $this->assertSame('status', $button->getAttribute('data-initial'));
        $this->assertSame('1', $button->getAttribute('data-canmanagesync'));
        $override = new \ReflectionProperty($button, 'customclassoverride');
        $this->assertSame('btn-primary', $override->getValue($button));

        $enabled = $mform->getElement(course_edit_form::ENABLED);
        $this->assertSame('selectyesno', $mform->getElementType(course_edit_form::ENABLED));
        $this->assertSame('1', $this->element_value($enabled->getValue()));
        $this->assertStringContainsString(
            get_string('enabled_course_help', 'local_dixeo'),
            $enabled->_helpbutton
        );

        $index = new \ReflectionProperty($mform, '_elementIndex');
        $positions = $index->getValue($mform);
        $this->assertLessThan($positions[course_edit_form::OPEN_BUTTON], $positions[course_edit_form::HEADER]);
        $this->assertLessThan($positions[course_edit_form::ENABLED], $positions[course_edit_form::OPEN_BUTTON]);
        $this->assertLessThan($positions[course_edit_form::FILESYNC], $positions[course_edit_form::ENABLED]);
        $tutor = $this->tutor_definition();
        if ($tutor !== null) {
            $name = course_edit_form::element_name($tutor);
            $this->assertLessThan($positions[$name], $positions[course_edit_form::FILESYNC]);
            $element = $mform->getElement($name);
            $this->assertSame('selectyesno', $mform->getElementType($name));
            $this->assertSame('1', $this->element_value($element->getValue()));
            $this->assertStringContainsString(
                get_string('tutor_enabled_help', 'block_dixeo_tutor'),
                $element->_helpbutton
            );
        }
        $this->assertSame(
            service_factory::get_settings_config()->get(
                'local_dixeo',
                'filesync_enabled',
                \context_course::instance($course->id)
            )->get_value(),
            $this->element_value($mform->getElement(course_edit_form::FILESYNC)->getValue())
        );
    }

    /**
     * A course that has not been saved yet has no context for the settings modal.
     */
    public function test_new_course_button_has_no_context(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/tests/fixtures/testable_course_edit_form.php');

        $category = $this->getDataGenerator()->create_category();
        $course = (object) [
            'id' => 0,
            'category' => $category->id,
            'fullname' => 'New course',
            'shortname' => '',
        ];
        $form = new \testable_course_edit_form(null, [
            'course' => $course,
            'category' => $DB->get_record('course_categories', ['id' => $category->id]),
            'editoroptions' => [
                'context' => \context_coursecat::instance($category->id),
                'subdirs' => 0,
            ],
            'returnto' => new \moodle_url('/'),
            'returnurl' => new \moodle_url('/'),
        ]);
        $form->definition_after_data();
        $button = $form->get_quick_form()->getElement(course_edit_form::OPEN_BUTTON);

        $this->assertEmpty($button->getAttribute('data-contextid'));
        $this->assertEmpty($button->getAttribute('data-courseid'));
        $this->assertEmpty($button->getAttribute('data-initial'));
        $this->assertSame(
            '0',
            $this->element_value($form->get_quick_form()->getElement(course_edit_form::FILESYNC)->getValue())
        );
        $tutor = $this->tutor_definition();
        if ($tutor !== null) {
            $this->assertSame(
                '1',
                $this->element_value(
                    $form->get_quick_form()->getElement(course_edit_form::element_name($tutor))->getValue()
                )
            );
        }
    }

    /**
     * Course off stays on the form, including the switch.
     */
    public function test_course_off_still_shows_the_dixeo_section(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/tests/fixtures/testable_course_edit_form.php');

        $course = $this->getDataGenerator()->create_course();
        service_factory::get_settings_config()->set(
            'local_dixeo',
            'enabled',
            \context_course::instance($course->id),
            '0'
        );

        $form = $this->edit_form($course, $DB);
        $form->definition_after_data();
        $mform = $form->get_quick_form();

        $this->assertTrue($mform->elementExists(course_edit_form::HEADER));
        $this->assertTrue($mform->elementExists(course_edit_form::OPEN_BUTTON));
        $this->assertTrue($mform->elementExists(course_edit_form::FILESYNC));
        $this->assertSame('0', $this->element_value($mform->getElement(course_edit_form::ENABLED)->getValue()));
    }

    /**
     * Dixeo off removes the whole Dixeo section from the course form.
     */
    public function test_dixeo_off_omits_the_course_section(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/tests/fixtures/testable_course_edit_form.php');

        $course = $this->getDataGenerator()->create_course();
        service_factory::get_settings_config()->set(
            'local_dixeo',
            'enabled',
            \context_system::instance(),
            '0'
        );

        $form = $this->edit_form($course, $DB);
        $mform = $form->get_quick_form();

        $this->assertFalse($mform->elementExists(course_edit_form::HEADER));
        $this->assertFalse($mform->elementExists(course_edit_form::OPEN_BUTTON));
        $this->assertFalse($mform->elementExists(course_edit_form::ENABLED));
        $this->assertFalse($mform->elementExists(course_edit_form::FILESYNC));
        $this->assertSame([], $this->catalogue_elements($mform));
    }

    /**
     * Saving the course switch stores Off on the course and leaves the Dixeo layer on.
     */
    public function test_course_save_stores_the_switch_and_ignores_a_missing_field(): void {
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $config = service_factory::get_settings_config();

        $record = get_course($course->id);
        $record->{course_edit_form::ENABLED} = '0';
        update_course($record);

        $this->assertFalse($config->enabled_for($context));
        $this->assertTrue($config->enabled_for(\context_system::instance()));
        $this->assertFalse(get_config('local_dixeo', 'enabled'));

        $unchanged = get_course($course->id);
        update_course($unchanged);
        $this->assertFalse($config->enabled_for($context));

        $restored = get_course($course->id);
        $restored->{course_edit_form::ENABLED} = '1';
        update_course($restored);
        $this->assertTrue($config->enabled_for($context));
        $this->assertSame(resolver::LAYER_DIXEO, $config->get('local_dixeo', 'enabled', $context)->get_source());
    }

    /**
     * Saving the course form stores file sync On and Off, and Off keeps the row.
     */
    public function test_course_save_stores_file_sync_on_and_off(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $config = service_factory::get_settings_config();
        $conditions = [
            'contextid' => $context->id,
            'component' => 'local_dixeo',
            'name' => 'filesync_enabled',
        ];

        $on = get_course($course->id);
        $on->{course_edit_form::FILESYNC} = '1';
        update_course($on);
        $this->assertSame('1', $config->get('local_dixeo', 'filesync_enabled', $context)->get_value());
        $this->assertTrue((new file_sync_service())->is_enabled($course->id));
        $this->assertTrue($DB->record_exists('local_dixeo_setting', $conditions));

        $off = get_course($course->id);
        $off->{course_edit_form::FILESYNC} = '0';
        update_course($off);
        $this->assertSame('0', $config->get('local_dixeo', 'filesync_enabled', $context)->get_value());
        $this->assertFalse((new file_sync_service())->is_enabled($course->id));
        $this->assertTrue($DB->record_exists('local_dixeo_setting', $conditions));
    }

    /**
     * Saving the course form stores the tutor switch On and Off, and Off keeps the row.
     */
    public function test_course_save_stores_tutor_on_and_off(): void {
        global $DB;

        $tutor = $this->tutor_definition();
        if ($tutor === null) {
            $this->markTestSkipped('Student Tutor is not installed or does not show its switch on the course form.');
        }
        $name = course_edit_form::element_name($tutor);

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $config = service_factory::get_settings_config();
        $conditions = [
            'contextid' => $context->id,
            'component' => 'block_dixeo_tutor',
            'name' => 'tutor_enabled',
        ];

        $off = get_course($course->id);
        $off->{$name} = '0';
        update_course($off);
        $this->assertSame('0', $config->get('block_dixeo_tutor', 'tutor_enabled', $context)->get_value());
        $this->assertSame(resolver::LAYER_COURSE, $config->get('block_dixeo_tutor', 'tutor_enabled', $context)->get_source());
        $this->assertTrue($DB->record_exists('local_dixeo_setting', $conditions));

        $on = get_course($course->id);
        $on->{$name} = '1';
        update_course($on);
        $this->assertSame('1', $config->get('block_dixeo_tutor', 'tutor_enabled', $context)->get_value());
        $this->assertTrue($DB->record_exists('local_dixeo_setting', $conditions));
    }

    /**
     * A course-form setting appears after file sync, with its own label and help.
     */
    public function test_a_course_form_setting_follows_file_sync(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/tests/fixtures/testable_course_edit_form.php');

        $course = $this->getDataGenerator()->create_course();
        $this->prime_catalogue_fixtures();
        $name = course_edit_form::element_name($this->fixture('test_course_switch'));

        $mform = $this->edit_form($course, $DB)->get_quick_form();

        $this->assertSame('local_dixeo_cf_local_dixeo_test_course_switch', $name);
        $this->assertSame('selectyesno', $mform->getElementType($name));
        $positions = (new \ReflectionProperty($mform, '_elementIndex'))->getValue($mform);
        $this->assertLessThan($positions[$name], $positions[course_edit_form::FILESYNC]);
        $this->assertStringContainsString(get_string('enabled_help', 'local_dixeo'), $mform->getElement($name)->_helpbutton);
    }

    /**
     * A saved course shows its own value; a new course shows the Dixeo value.
     */
    public function test_a_course_form_setting_is_prefilled(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/tests/fixtures/testable_course_edit_form.php');

        $course = $this->getDataGenerator()->create_course();
        $category = $this->getDataGenerator()->create_category();
        $config = service_factory::get_settings_config();
        $this->prime_catalogue_fixtures();
        $config->set('local_dixeo', 'test_course_switch', \context_system::instance(), '0');
        $this->prime_catalogue_fixtures();
        $config->set('local_dixeo', 'test_course_switch', \context_course::instance($course->id), '1');
        $this->prime_catalogue_fixtures();
        $name = course_edit_form::element_name($this->fixture('test_course_switch'));

        $saved = $this->edit_form($course, $DB);
        $saved->definition_after_data();
        $this->assertSame('1', $this->element_value($saved->get_quick_form()->getElement($name)->getValue()));

        $new = new \testable_course_edit_form(null, [
            'course' => (object) ['id' => 0, 'category' => $category->id, 'fullname' => 'New', 'shortname' => ''],
            'category' => $DB->get_record('course_categories', ['id' => $category->id]),
            'editoroptions' => ['context' => \context_coursecat::instance($category->id), 'subdirs' => 0],
            'returnto' => new \moodle_url('/'),
            'returnurl' => new \moodle_url('/'),
        ]);
        $new->definition_after_data();
        $this->assertSame('0', $this->element_value($new->get_quick_form()->getElement($name)->getValue()));
    }

    /**
     * Saving the course form stores a course-form setting through the settings helper.
     */
    public function test_a_course_form_setting_is_saved(): void {
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $this->prime_catalogue_fixtures();
        $name = course_edit_form::element_name($this->fixture('test_course_switch'));

        $record = get_course($course->id);
        $record->{$name} = '0';
        update_course($record);

        $this->prime_catalogue_fixtures();
        $resolved = service_factory::get_settings_config()->get('local_dixeo', 'test_course_switch', $context);
        $this->assertSame('0', $resolved->get_value());
        $this->assertSame(resolver::LAYER_COURSE, $resolved->get_source());
    }

    /**
     * Without a course-form definition, the form has no catalogue element.
     */
    public function test_no_catalogue_element_without_a_course_form_definition(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/tests/fixtures/testable_course_edit_form.php');

        $course = $this->getDataGenerator()->create_course();
        $definitions = array_filter(
            catalogue::load(),
            static fn(definition $definition): bool => !$definition->shows_on_course_form()
        );
        \cache::make_from_params(\cache_store::MODE_REQUEST, 'local_dixeo', 'settingscatalogue')
            ->set(current_language(), ['t' => \core\di::get(\core\clock::class)->time(), 'd' => $definitions]);

        $mform = $this->edit_form($course, $DB)->get_quick_form();

        $this->assertSame([], $this->catalogue_elements($mform));
    }

    /**
     * Installed tutor definition when it is shown on the course form, or null.
     *
     * @return definition|null
     */
    private function tutor_definition(): ?definition {
        $definition = catalogue::load()['tutor_enabled'] ?? null;
        if ($definition === null || $definition->get_component() !== 'block_dixeo_tutor') {
            return null;
        }
        return $definition->shows_on_course_form() ? $definition : null;
    }

    /**
     * Fixture definition from the primed catalogue.
     *
     * @param string $key Fixture key.
     * @return definition
     */
    private function fixture(string $key): definition {
        return catalogue::load()[$key];
    }

    /**
     * Names of the catalogue course-form elements on a form.
     *
     * @param \MoodleQuickForm $mform Course edit form.
     * @return string[]
     */
    private function catalogue_elements(\MoodleQuickForm $mform): array {
        $index = (new \ReflectionProperty($mform, '_elementIndex'))->getValue($mform);
        return array_values(array_filter(
            array_keys($index),
            static fn(string $name): bool => str_starts_with($name, 'local_dixeo_cf_')
        ));
    }

    /**
     * Text stored in a form element.
     *
     * @param mixed $raw Value from a form element.
     * @return string
     */
    private function element_value(mixed $raw): string {
        if (is_array($raw)) {
            $raw = reset($raw);
        }
        return (string) $raw;
    }

    /**
     * Course edit form for the given course.
     *
     * @param \stdClass $course Course record.
     * @param \moodle_database $db Database.
     * @return \testable_course_edit_form
     */
    private function edit_form(\stdClass $course, \moodle_database $db): \testable_course_edit_form {
        $category = $db->get_record('course_categories', ['id' => $course->category]);
        return new \testable_course_edit_form(null, [
            'course' => $course,
            'category' => $category,
            'editoroptions' => [
                'context' => \context_course::instance($course->id),
                'subdirs' => 0,
            ],
            'returnto' => new \moodle_url('/'),
            'returnurl' => new \moodle_url('/'),
        ]);
    }
}
