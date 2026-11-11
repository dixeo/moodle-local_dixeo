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
 * Backup of the Dixeo setting values stored on a course or an activity.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Writes every local_dixeo_setting row of the backed-up course or module context.
 *
 * Only catalogue values are written. Records about people, such as who switched a feature on,
 * are not part of the backup.
 */
class backup_local_dixeo_plugin extends backup_local_plugin {
    /**
     * Setting rows of the course context.
     *
     * @return backup_plugin_element
     */
    protected function define_course_plugin_structure() {
        return $this->define_settings_structure();
    }

    /**
     * Setting rows of the module context.
     *
     * @return backup_plugin_element
     */
    protected function define_module_plugin_structure() {
        return $this->define_settings_structure();
    }

    /**
     * Setting elements sourced from the rows of the task's context.
     *
     * @return backup_plugin_element
     */
    private function define_settings_structure(): backup_plugin_element {
        $plugin = $this->get_plugin_element();
        $wrapper = new backup_nested_element($this->get_recommended_name());
        $settings = new backup_nested_element('settings');
        $setting = new backup_nested_element('setting', ['id'], ['component', 'name', 'value']);

        $plugin->add_child($wrapper);
        $wrapper->add_child($settings);
        $settings->add_child($setting);

        // The course master switch is restored first, so the values it governs see its restored state.
        $setting->set_source_sql(
            'SELECT id, component, name, value
               FROM {' . \local_dixeo\settings\config::TABLE . '}
              WHERE contextid = ?
           ORDER BY CASE WHEN component = ? AND name = ? THEN 0 ELSE 1 END, id',
            [backup::VAR_CONTEXTID, backup_helper::is_sqlparam('local_dixeo'), backup_helper::is_sqlparam('enabled')]
        );

        return $plugin;
    }
}
