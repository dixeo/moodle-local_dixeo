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
     * Text shown in the current-value control. An empty textarea stays blank here.
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

    /**
     * One layer object from the payload, or null when this setting has no such layer.
     *
     * @param {Object} setting Catalogue row from get_settings.
     * @param {string} level site, dixeo, or course.
     * @returns {Object|null}
     */
    const layerAt = function(setting, level) {
        const layers = Array.isArray(setting.layers) ? setting.layers : [];
        const found = layers.find(function(layer) {
            return layer && layer.level === level;
        });
        return found || null;
    };

    /**
     * Text for a parent cell. Null means the layer is absent and the cell stays blank.
     * An empty string displays the empty label.
     *
     * @param {Object} setting Catalogue row from get_settings.
     * @param {string} level site or dixeo.
     * @param {{on: string, off: string, empty: string}} labels
     * @returns {string|null}
     */
    const parentText = function(setting, level, labels) {
        const layer = layerAt(setting, level);
        if (!layer) {
            return null;
        }
        if (setting.type === 'bool') {
            return layer.value === '1' ? labels.on : labels.off;
        }
        if (!layer.value) {
            return labels.empty;
        }
        return layer.value;
    };

    /**
     * Template context for one read-only row, including parent cells.
     *
     * @param {Object} setting Catalogue row from get_settings.
     * @param {string} label
     * @param {string} help
     * @param {string} wwwroot
     * @param {{on: string, off: string, empty: string}} labels
     * @returns {Object}
     */
    const rowView = function(setting, label, help, wwwroot, labels) {
        const site = parentText(setting, 'site', labels);
        const dixeo = parentText(setting, 'dixeo', labels);
        return {
            component: setting.component,
            name: setting.name,
            label: label,
            help: help,
            shortname: setting.shortname,
            shortnameurl: shortnameUrl(setting, wwwroot),
            isbool: setting.type === 'bool',
            istextarea: setting.type === 'textarea',
            displayvalue: displayValue(setting, labels.on, labels.off),
            hassite: site !== null,
            sitevalue: site || '',
            hasdixeo: dixeo !== null,
            dixeovalue: dixeo || '',
        };
    };

    return {
        shortnameUrl: shortnameUrl,
        displayValue: displayValue,
        rowView: rowView,
    };
});
