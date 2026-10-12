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

/**
 * Event when a Dixeo catalogue setting changes.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\event;

/**
 * Fired when a catalogue setting is stored or its override is removed.
 *
 * The log names the setting and the context. It does not include the value.
 */
class setting_updated extends \core\event\base {
    /**
     * Init method.
     */
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventsettingupdated', 'local_dixeo');
    }

    /**
     * Description for logs.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('eventsettingupdateddesc', 'local_dixeo', (object) [
            'userid' => $this->userid,
            'component' => $this->other['component'],
            'name' => $this->other['name'],
            'contextid' => $this->contextid,
        ]);
    }

    /**
     * Relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        if ($this->contextlevel == CONTEXT_COURSE) {
            return new \moodle_url('/course/view.php', ['id' => $this->courseid]);
        }
        return new \moodle_url('/admin/settings.php', ['section' => 'local_dixeo']);
    }

    /**
     * Validate the setting identity.
     */
    protected function validate_data(): void {
        parent::validate_data();
        if (empty($this->other['component']) || empty($this->other['name'])) {
            throw new \coding_exception('The component and name must be set.');
        }
    }

    /**
     * Other fields are not backup ids.
     *
     * @return false
     */
    public static function get_other_mapping() {
        return false;
    }
}
