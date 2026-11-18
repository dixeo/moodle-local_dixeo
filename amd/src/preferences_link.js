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
 * Turns the Dixeo preferences link on the user Preferences page into an opener of the
 * preferences-only settings modal.
 *
 * @module     local_dixeo/preferences_link
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['local_dixeo/settings_modal'], function(SettingsModal) {
    'use strict';

    /**
     * Mark every link ending with the fragment as a modal opener and bind the modal.
     *
     * The same navigation node can be rendered more than once on the page (for example by the
     * settings block on the classic theme), so every matching link is marked.
     *
     * @param {string} fragment URL fragment of the Dixeo preferences node, without the hash.
     */
    const init = function(fragment) {
        const selector = 'a[href$="#' + CSS.escape(fragment) + '"]';
        document.querySelectorAll(selector).forEach(function(anchor) {
            anchor.dataset.action = 'local-dixeo-settings-open';
            anchor.dataset.initial = 'preferences';
            anchor.dataset.preferencesonly = '1';
            anchor.setAttribute('aria-haspopup', 'dialog');
        });
        SettingsModal.init();
    };

    return {
        init: init,
    };
});
