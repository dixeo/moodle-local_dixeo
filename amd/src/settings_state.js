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
 * Display rules for one Dixeo settings row.
 *
 * @module     local_dixeo/settings_state
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    /**
     * Admin page for the master switch. Other settings have no admin anchor.
     *
     * @param {Object} setting Catalogue row from get_settings.
     * @param {string} wwwroot Site root without a trailing slash.
     * @returns {string}
     */
    const shortnameUrl = function(setting, wwwroot) {
        if (setting.component === 'local_dixeo' && setting.name === 'enabled') {
            return wwwroot + '/admin/settings.php?section=local_dixeo#id_s_local_dixeo_enabled';
        }
        return '';
    };

    /**
     * Text shown in the current-value control.
     *
     * @param {Object} setting Catalogue row from get_settings.
     * @param {string} onLabel
     * @param {string} offLabel
     * @returns {string}
     */
    const displayValue = function(setting, onLabel, offLabel) {
        if (setting.type === 'bool') {
            return setting.value === '1' ? onLabel : offLabel;
        }
        return setting.value || '';
    };

    return {
        shortnameUrl: shortnameUrl,
        displayValue: displayValue,
    };
});
