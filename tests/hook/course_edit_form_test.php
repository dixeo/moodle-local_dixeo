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
use local_dixeo\settings\resolver;

/**
 * Course form tests for the Dixeo section.
 *
 * @covers \local_dixeo\hook\course_edit_form
 */
final class course_edit_form_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    /**
     * The Dixeo header and textarea show the stored course text.
     */
    public function test_form_shows_the_stored_course_text(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/tests/fixtures/testable_course_edit_form.php');

        $course = $this->getDataGenerator()->create_course();
        service_factory::get_settings_config()->set(
            'local_dixeo',
            'learning_intent',
            \context_course::instance($course->id),
            'Course intent'
        );

        $form = $this->edit_form($course, $DB);
        $form->definition_after_data();
        $mform = $form->get_quick_form();

        $this->assertTrue($mform->elementExists(course_edit_form::HEADER));
        $this->assertSame(
            get_string('settings_category', 'local_dixeo'),
            $mform->getElement(course_edit_form::HEADER)->toHtml()
        );
        $this->assertSame('Course intent', $mform->getElement(course_edit_form::FIELD)->getValue());

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
        if ($this->tutor_installed()) {
            $this->assertLessThan($positions[course_edit_form::TUTOR], $positions[course_edit_form::FILESYNC]);
            $this->assertLessThan($positions[course_edit_form::FIELD], $positions[course_edit_form::TUTOR]);
            $tutor = $mform->getElement(course_edit_form::TUTOR);
            $this->assertSame('selectyesno', $mform->getElementType(course_edit_form::TUTOR));
            $this->assertSame('1', $this->element_value($tutor->getValue()));
            $this->assertStringContainsString(
                get_string('tutor_enabled_help', 'block_dixeo_tutor'),
                $tutor->_helpbutton
            );
        } else {
            $this->assertLessThan($positions[course_edit_form::FIELD], $positions[course_edit_form::FILESYNC]);
            $this->assertFalse($mform->elementExists(course_edit_form::TUTOR));
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
        if ($this->tutor_installed()) {
            $this->assertSame(
                '1',
                $this->element_value($form->get_quick_form()->getElement(course_edit_form::TUTOR)->getValue())
            );
        } else {
            $this->assertFalse($form->get_quick_form()->elementExists(course_edit_form::TUTOR));
        }
    }

    /**
     * Course off stays on the form, including the switch and learning intent.
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
        $this->assertTrue($mform->elementExists(course_edit_form::FIELD));
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
        $this->assertFalse($mform->elementExists(course_edit_form::TUTOR));
        $this->assertFalse($mform->elementExists(course_edit_form::FIELD));
    }

    /**
     * Saving the course form stores text and drops the row when the text is empty.
     */
    public function test_course_save_stores_text_and_clears_the_default(): void {
        $course = $this->getDataGenerator()->create_course();
        $record = get_course($course->id);
        $record->{course_edit_form::FIELD} = 'Course intent';
        update_course($record);

        $context = \context_course::instance($course->id);
        $config = service_factory::get_settings_config();
        $resolved = $config->get('local_dixeo', 'learning_intent', $context);
        $this->assertSame('Course intent', $resolved->get_value());
        $this->assertSame(resolver::LAYER_COURSE, $resolved->get_source());

        $unchanged = get_course($course->id);
        update_course($unchanged);
        $this->assertSame('Course intent', $config->get('local_dixeo', 'learning_intent', $context)->get_value());

        $cleared = get_course($course->id);
        $cleared->{course_edit_form::FIELD} = '';
        update_course($cleared);
        $empty = $config->get('local_dixeo', 'learning_intent', $context);
        $this->assertSame('', $empty->get_value());
        $this->assertSame(resolver::LAYER_DIXEO, $empty->get_source());
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

        if (!$this->tutor_installed()) {
            $this->markTestSkipped('Student Tutor is not installed.');
        }

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $config = service_factory::get_settings_config();
        $conditions = [
            'contextid' => $context->id,
            'component' => 'block_dixeo_tutor',
            'name' => 'tutor_enabled',
        ];

        $off = get_course($course->id);
        $off->{course_edit_form::TUTOR} = '0';
        update_course($off);
        $this->assertSame('0', $config->get('block_dixeo_tutor', 'tutor_enabled', $context)->get_value());
        $this->assertSame(resolver::LAYER_COURSE, $config->get('block_dixeo_tutor', 'tutor_enabled', $context)->get_source());
        $this->assertTrue($DB->record_exists('local_dixeo_setting', $conditions));

        $on = get_course($course->id);
        $on->{course_edit_form::TUTOR} = '1';
        update_course($on);
        $this->assertSame('1', $config->get('block_dixeo_tutor', 'tutor_enabled', $context)->get_value());
        $this->assertTrue($DB->record_exists('local_dixeo_setting', $conditions));
    }

    /**
     * Whether the Student Tutor block is installed.
     *
     * The course form omits the tutor switch when the block is not installed.
     *
     * @return bool
     */
    private function tutor_installed(): bool {
        return array_key_exists('dixeo_tutor', \core_component::get_plugin_list('block'));
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
