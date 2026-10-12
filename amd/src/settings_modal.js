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
 * Dixeo settings modal shell opened from the course edit form.
 *
 * @module     local_dixeo/settings_modal
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/modal', 'core/str', 'core/templates'], function(Modal, Str, Templates) {
    let bound = false;

    /**
     * Course name currently typed on the edit form.
     *
     * @param {HTMLElement} button
     * @returns {string}
     */
    const courseName = function(button) {
        const nameInput = document.getElementById('id_fullname');
        if (nameInput && nameInput.value) {
            return nameInput.value;
        }
        return button.dataset.courseName || '';
    };

    /**
     * Open the settings shell. Moodle's modal traps focus and closes on Escape.
     *
     * @param {HTMLElement} button
     * @returns {Promise}
     */
    const open = function(button) {
        const titleReady = Str.get_string('settings_modal_breadcrumb', 'local_dixeo', courseName(button));
        const bodyReady = Templates.renderForPromise('local_dixeo/settings_modal', {});
        return Promise.all([titleReady, bodyReady]).then(function(results) {
            const title = results[0];
            const rendered = results[1];
            return Modal.create({
                title: title,
                body: rendered.html,
                large: true,
                removeOnClose: true,
                show: true,
                returnElement: button,
            }).then(function(modal) {
                Templates.runTemplateJS(rendered.js);
                return modal;
            });
        });
    };

    return {
        /**
         * Listen for the course-form button. The listener does not submit the form.
         */
        init: function() {
            if (bound) {
                return;
            }
            bound = true;
            document.addEventListener('click', function(event) {
                const button = event.target.closest('[data-action="local-dixeo-settings-open"]');
                if (!button) {
                    return;
                }
                event.preventDefault();
                open(button);
            });
        },
    };
});
