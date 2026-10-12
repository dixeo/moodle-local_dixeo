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
 * Dixeo course settings on the course edit form.
 *
 * The section is omitted when the Dixeo layer is off. The settings button is the first control.
 * The course switch and learning intent follow it. A save that omits a field leaves that stored value unchanged.
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

    /** @var string Course edit form element for the course on/off switch. */
    public const ENABLED = 'local_dixeo_enabled';

    /**
     * Add the Dixeo header, the settings button, the course switch, then learning intent.
     *
     * The button is the first control in the section. Nothing is added when the Dixeo layer is off.
     *
     * @param \core_course\hook\after_form_definition $hook Course form definition hook.
     */
    public static function definition(\core_course\hook\after_form_definition $hook): void {
        global $OUTPUT, $PAGE;

        if (!service_factory::get_settings_config()->enabled_for(\context_system::instance())) {
            return;
        }

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
        $mform->addElement('selectyesno', self::ENABLED, get_string('enabled', 'local_dixeo'));
        $mform->setType(self::ENABLED, PARAM_INT);
        $mform->addHelpButton(self::ENABLED, 'enabled_course', 'local_dixeo');
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
     * Fill learning intent and the course switch. A saved course also gives the settings
     * button its course id, so the modal can show that course's file sync status.
     *
     * @param \core_course\hook\after_form_definition_after_data $hook Course form after-data hook.
     */
    public static function definition_after_data(\core_course\hook\after_form_definition_after_data $hook): void {
        $mform = $hook->mform;
        $courseid = $mform->elementExists('id') ? self::course_id($mform->getElementValue('id')) : 0;
        $coursecontext = $courseid > 0 ? \context_course::instance($courseid) : null;
        if ($mform->elementExists(self::OPEN_BUTTON)) {
            $attributes = [
                'data-course-name' => self::element_text($mform->getElementValue('fullname')),
            ];
            if ($coursecontext !== null) {
                $attributes['data-contextid'] = (string) $coursecontext->id;
                $attributes['data-courseid'] = (string) $courseid;
                if (has_capability('local/dixeo:syncfiles', $coursecontext)) {
                    $attributes['data-canmanagesync'] = '1';
                }
            }
            $mform->getElement(self::OPEN_BUTTON)->updateAttributes($attributes);
        }
        if (!$mform->elementExists(self::FIELD) && !$mform->elementExists(self::ENABLED)) {
            return;
        }

        $config = service_factory::get_settings_config();
        if ($mform->elementExists(self::FIELD)) {
            $intent = '';
            if ($coursecontext !== null) {
                $intent = $config->get('local_dixeo', 'learning_intent', $coursecontext)->get_value();
            }
            $mform->getElement(self::FIELD)->setValue($intent);
        }
        if ($mform->elementExists(self::ENABLED)) {
            $enabledcontext = $coursecontext ?? \context_system::instance();
            $mform->getElement(self::ENABLED)->setValue(
                $config->get('local_dixeo', 'enabled', $enabledcontext)->get_value()
            );
        }
    }

    /**
     * Store the submitted textarea through the config helper.
     *
     * @param \core_course\hook\after_form_submission $hook Course form submission hook.
     */
    public static function submission(\core_course\hook\after_form_submission $hook): void {
        $data = $hook->get_data();
        $intent = null;
        $enabled = null;
        if (property_exists($data, self::FIELD)) {
            $intent = (string) $data->{self::FIELD};
            unset($data->{self::FIELD});
        }
        if (property_exists($data, self::ENABLED)) {
            $enabled = (string) $data->{self::ENABLED};
            unset($data->{self::ENABLED});
        }
        if ($intent === null && $enabled === null) {
            return;
        }

        $courseid = (int) ($data->id ?? 0);
        if ($courseid <= 0) {
            return;
        }

        $config = service_factory::get_settings_config();
        $context = \context_course::instance($courseid);
        if ($intent !== null) {
            $config->set('local_dixeo', 'learning_intent', $context, $intent);
        }
        if ($enabled !== null) {
            $config->set('local_dixeo', 'enabled', $context, $enabled);
        }
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
