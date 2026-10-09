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
use local_dixeo\settings\resolver;

/**
 * Course form tests for learning intent.
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
        $this->assertSame(resolver::LAYER_SITE, $empty->get_source());
    }

    /**
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
