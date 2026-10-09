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
 * Learning intent on the course edit form.
 *
 * The textarea is course-only. A save that does not include the field leaves the stored value unchanged.
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

    /**
     * Add the Dixeo header and the learning intent textarea.
     *
     * @param \core_course\hook\after_form_definition $hook Course form definition hook.
     */
    public static function definition(\core_course\hook\after_form_definition $hook): void {
        $mform = $hook->mform;
        $mform->addElement('header', self::HEADER, get_string('settings_category', 'local_dixeo'));
        $mform->addElement(
            'textarea',
            self::FIELD,
            get_string('learning_intent', 'local_dixeo'),
            'wrap="virtual" rows="5" cols="50"'
        );
        $mform->setType(self::FIELD, PARAM_TEXT);
        $mform->addHelpButton(self::FIELD, 'learning_intent', 'local_dixeo');
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
        if (is_array($raw)) {
            $raw = reset($raw);
        }
        return (int) $raw;
    }
}
