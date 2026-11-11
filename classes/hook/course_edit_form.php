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
use local_dixeo\settings\catalogue;
use local_dixeo\settings\definition;

/**
 * Dixeo course settings on the course edit form.
 *
 * The section is omitted when the Dixeo layer is off. The settings button is the first control.
 * The course switch and file sync follow it, then one yes/no control for each catalogue setting
 * that declares courseform, in catalogue order.
 * A save that omits a field leaves that stored value unchanged.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_edit_form {
    /** @var string Header element that groups Dixeo course fields. */
    public const HEADER = 'local_dixeo_settings';

    /** @var string Button that opens the Dixeo settings modal. */
    public const OPEN_BUTTON = 'local_dixeo_settings_open';

    /** @var string Course edit form element for the course on/off switch. */
    public const ENABLED = 'local_dixeo_enabled';

    /** @var string Course edit form element for file sync on or paused. */
    public const FILESYNC = 'local_dixeo_filesync_enabled';

    /** @var string Prefix of the course edit form elements for catalogue settings shown on the form. */
    private const CATALOGUE_PREFIX = 'local_dixeo_cf_';

    /**
     * Add the Dixeo header, the settings button, the course switch, file sync, then the catalogue course-form settings.
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
        $mform->addElement('selectyesno', self::FILESYNC, get_string('filesync_enabled', 'local_dixeo'));
        $mform->setType(self::FILESYNC, PARAM_INT);
        $mform->addHelpButton(self::FILESYNC, 'filesync_enabled', 'local_dixeo');
        foreach (self::course_form_definitions() as $definition) {
            $name = self::element_name($definition);
            $component = $definition->get_component();
            $mform->addElement('selectyesno', $name, get_string($definition->get_shortname(), $component));
            $mform->setType($name, PARAM_INT);
            $help = $definition->get_help();
            if (str_ends_with($help, '_help')) {
                $mform->addHelpButton($name, substr($help, 0, -strlen('_help')), $component);
            }
        }
        $PAGE->requires->js_call_amd('local_dixeo/settings_modal', 'init');
    }

    /**
     * Fill the course switch, file sync, and the catalogue course-form settings. A saved course also gives the settings
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
                $attributes['data-initial'] = 'status';
                if (has_capability('local/dixeo:syncfiles', $coursecontext)) {
                    $attributes['data-canmanagesync'] = '1';
                }
            }
            $mform->getElement(self::OPEN_BUTTON)->updateAttributes($attributes);
        }
        $catalogue = array_filter(
            self::course_form_definitions(),
            static fn(definition $definition): bool => $mform->elementExists(self::element_name($definition))
        );
        if (!$mform->elementExists(self::ENABLED) && !$mform->elementExists(self::FILESYNC) && !$catalogue) {
            return;
        }

        $config = service_factory::get_settings_config();
        if ($mform->elementExists(self::ENABLED)) {
            $enabledcontext = $coursecontext ?? \context_system::instance();
            $mform->getElement(self::ENABLED)->setValue(
                $config->get('local_dixeo', 'enabled', $enabledcontext)->get_value()
            );
        }
        if ($mform->elementExists(self::FILESYNC)) {
            if ($coursecontext !== null) {
                $filesync = $config->get('local_dixeo', 'filesync_enabled', $coursecontext)->get_value();
            } else {
                $filesync = $config->get(
                    'local_dixeo',
                    'filesync_enabled_default',
                    \context_system::instance()
                )->get_value();
            }
            $mform->getElement(self::FILESYNC)->setValue($filesync);
        }
        foreach ($catalogue as $definition) {
            $component = $definition->get_component();
            $key = $definition->get_key();
            if ($coursecontext !== null) {
                $value = $config->get($component, $key, $coursecontext)->get_value();
            } else if ($definition->retains_value()) {
                $value = $config->new_course_default_value($component, $key);
            } else {
                $value = $config->get($component, $key, \context_system::instance())->get_value();
            }
            $mform->getElement(self::element_name($definition))->setValue($value);
        }
    }

    /**
     * Store the submitted fields through the config helper.
     *
     * @param \core_course\hook\after_form_submission $hook Course form submission hook.
     */
    public static function submission(\core_course\hook\after_form_submission $hook): void {
        $data = $hook->get_data();
        $enabled = null;
        $filesync = null;
        if (property_exists($data, self::ENABLED)) {
            $enabled = (string) $data->{self::ENABLED};
            unset($data->{self::ENABLED});
        }
        if (property_exists($data, self::FILESYNC)) {
            $filesync = (string) $data->{self::FILESYNC};
            unset($data->{self::FILESYNC});
        }
        $catalogue = [];
        foreach (self::course_form_definitions() as $definition) {
            $name = self::element_name($definition);
            if (property_exists($data, $name)) {
                $catalogue[] = [$definition, (string) $data->{$name}];
                unset($data->{$name});
            }
        }
        if ($enabled === null && $filesync === null && !$catalogue) {
            return;
        }

        $courseid = (int) ($data->id ?? 0);
        if ($courseid <= 0) {
            return;
        }

        $config = service_factory::get_settings_config();
        $context = \context_course::instance($courseid);
        if ($enabled !== null) {
            $config->set('local_dixeo', 'enabled', $context, $enabled);
        }
        if ($filesync !== null) {
            $config->set('local_dixeo', 'filesync_enabled', $context, $filesync);
        }
        foreach ($catalogue as [$definition, $value]) {
            $config->set($definition->get_component(), $definition->get_key(), $context, $value);
        }
    }

    /**
     * Course edit form element name for a catalogue setting shown on the form.
     *
     * @param definition $definition Catalogue setting that declares courseform.
     * @return string
     */
    public static function element_name(definition $definition): string {
        return self::CATALOGUE_PREFIX . $definition->get_component() . '_' . $definition->get_key();
    }

    /**
     * Catalogue settings shown on the course form, in catalogue order.
     *
     * @return definition[]
     */
    private static function course_form_definitions(): array {
        return array_values(array_filter(
            catalogue::load(),
            static fn(definition $definition): bool => $definition->shows_on_course_form()
        ));
    }

    /**
     * Course id from the form element, or zero when it is empty.
     *
     * @param mixed $raw Value from the course id element.
     * @return int
     */
    private static function course_id(mixed $raw): int {
        return (int) self::element_text($raw);
    }

    /**
     * Text stored in a form element.
     *
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
