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

namespace local_dixeo\hook;

use local_dixeo\external\service_factory;

/**
 * Learning intent and the Dixeo settings button on the course edit form.
 *
 * The textarea is course-only. A save that does not include the field leaves the stored value unchanged.
 * The settings button opens an empty modal and does not submit the form.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_edit_form {
    /** @var string Course edit form element for learning intent. */
    public const FIELD = 'local_dixeo_learning_intent';

    /** @var string Header element that groups Dixeo course fields. */
    public const HEADER = 'local_dixeo_settings';

    /** @var string Button that opens the Dixeo settings modal. */
    public const OPEN_BUTTON = 'local_dixeo_settings_open';

    /**
     * Add the Dixeo header, the settings button, then the learning intent textarea.
     *
     * The button is the first control in the section.
     *
     * @param \core_course\hook\after_form_definition $hook Course form definition hook.
     */
    public static function definition(\core_course\hook\after_form_definition $hook): void {
        global $OUTPUT, $PAGE;

        $mform = $hook->mform;
        $mform->addElement('header', self::HEADER, get_string('settings_category', 'local_dixeo'));
        $logo = \html_writer::img($OUTPUT->image_url('dixeo-white', 'local_dixeo'), '', [
            'class' => 'local-dixeo-settings-logo',
            'alt' => '',
            'aria-hidden' => 'true',
            'width' => 16,
            'height' => 16,
        ]);
        $mform->addElement('button', self::OPEN_BUTTON, $logo . get_string('settings_button', 'local_dixeo'), [
            'data-action' => 'local-dixeo-settings-open',
            'type' => 'button',
            'parentclass' => 'local-dixeo-settings-open',
        ], ['customclassoverride' => 'btn-primary']);
        $mform->addElement(
            'textarea',
            self::FIELD,
            get_string('learning_intent', 'local_dixeo'),
            'wrap="virtual" rows="5" cols="50"'
        );
        $mform->setType(self::FIELD, PARAM_TEXT);
        $mform->addHelpButton(self::FIELD, 'learning_intent', 'local_dixeo');
        $PAGE->requires->js_call_amd('local_dixeo/settings_modal', 'init');
    }

    /**
     * Fill the textarea from the course value. A new course shows the shipped default.
     *
     * @param \core_course\hook\after_form_definition_after_data $hook Course form after-data hook.
     */
    public static function definition_after_data(\core_course\hook\after_form_definition_after_data $hook): void {
        $mform = $hook->mform;
        if (!$mform->elementExists(self::FIELD)) {
            return;
        }

        $courseid = self::course_id($mform->getElementValue('id'));
        $value = '';
        if ($courseid > 0) {
            $value = service_factory::get_settings_config()->get(
                'local_dixeo',
                'learning_intent',
                \context_course::instance($courseid)
            )->get_value();
        }
        $mform->getElement(self::FIELD)->setValue($value);
        if ($mform->elementExists(self::OPEN_BUTTON)) {
            $mform->getElement(self::OPEN_BUTTON)->updateAttributes([
                'data-course-name' => self::element_text($mform->getElementValue('fullname')),
            ]);
        }
    }

    /**
     * Store the submitted textarea through the config helper.
     *
     * @param \core_course\hook\after_form_submission $hook Course form submission hook.
     */
    public static function submission(\core_course\hook\after_form_submission $hook): void {
        $data = $hook->get_data();
        if (!property_exists($data, self::FIELD)) {
            return;
        }

        $value = (string) $data->{self::FIELD};
        unset($data->{self::FIELD});

        $courseid = (int) ($data->id ?? 0);
        if ($courseid <= 0) {
            return;
        }

        service_factory::get_settings_config()->set(
            'local_dixeo',
            'learning_intent',
            \context_course::instance($courseid),
            $value
        );
    }

    /**
     * @param mixed $raw Value from the course id element.
     * @return int
     */
    private static function course_id(mixed $raw): int {
        return (int) self::element_text($raw);
    }

    /**
     * @param mixed $raw Value from a form element.
     * @return string
     */
    private static function element_text(mixed $raw): string {
        if (is_array($raw)) {
            $raw = reset($raw);
        }
        return (string) $raw;
    }
}
