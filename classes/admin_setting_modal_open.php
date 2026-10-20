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

namespace local_dixeo;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

/**
 * Button that opens the Dixeo settings modal from a plugin admin page.
 *
 * The button edits the Dixeo layer. It does not submit the plugin's own settings form.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_modal_open {
    /**
     * Admin sections whose settings page shows the button.
     *
     * @return string[]
     */
    public static function admin_sections(): array {
        return [
            'local_dixeo',
            'local_dixeo_assistant',
        ];
    }

    /**
     * Add the button as the first control is the caller's responsibility.
     *
     * @param \admin_settingpage $page Plugin settings page.
     * @param string $component Component of the plugin that owns the page.
     */
    public static function add(\admin_settingpage $page, string $component): void {
        $page->add(new \admin_setting_description(
            $component . '/settings_modal_open',
            '',
            self::html()
        ));
        self::prepare_stylesheet();
    }

    /**
     * Load the button styles before the page head is printed.
     *
     * @return void
     */
    public static function prepare_stylesheet(): void {
        global $PAGE;

        if (!self::applies_to_current_page()) {
            return;
        }
        $PAGE->requires->css(new \moodle_url('/local/dixeo/styles.css'));
    }

    /**
     * Markup for the button. Safe to echo on an admin page that is not an admin_settingpage.
     *
     * @return string
     */
    public static function html(): string {
        global $OUTPUT;

        $logo = \html_writer::img($OUTPUT->image_url('dixeo-white', 'local_dixeo'), '', [
            'class' => 'local-dixeo-settings-logo',
            'alt' => '',
            'aria-hidden' => 'true',
            'width' => 16,
            'height' => 16,
        ]);
        return \html_writer::div(
            \html_writer::tag('button', $logo . get_string('settings_button', 'local_dixeo'), [
                'type' => 'button',
                'class' => 'btn btn-primary',
                'data-action' => 'local-dixeo-settings-open',
                'data-contextid' => (string) \context_system::instance()->id,
            ]),
            'local-dixeo-settings-open mb-3'
        );
    }

    /**
     * Whether the current page is a Dixeo plugin admin page that shows the button.
     *
     * @return bool
     */
    public static function applies_to_current_page(): bool {
        global $PAGE;

        if (!$PAGE->has_set_url()) {
            return false;
        }
        $section = (string) $PAGE->url->get_param('section');
        return in_array($section, self::admin_sections(), true);
    }
}
