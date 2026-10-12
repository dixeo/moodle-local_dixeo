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

define(['core/ajax', 'core/modal', 'core/str', 'core/templates'], function(Ajax, Modal, Str, Templates) {
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
     * Clear the search box and the Customised pill. Neither filters the list.
     *
     * @param {HTMLElement} shell
     */
    const resetControls = function(shell) {
        const search = shell.querySelector('#local-dixeo-settings-search');
        if (search) {
            search.value = '';
        }
        const pill = shell.querySelector('[data-action="local-dixeo-settings-customised"]');
        if (pill) {
            pill.setAttribute('aria-pressed', 'false');
        }
    };

    /**
     * @param {HTMLElement} shell
     * @param {string} text
     */
    const setList = function(shell, text) {
        const list = shell.querySelector('[data-region="local-dixeo-settings-list"]');
        if (list) {
            list.textContent = text;
        }
    };

    /**
     * @param {HTMLElement} shell
     * @param {string} text
     */
    const setCount = function(shell, text) {
        const count = shell.querySelector('[data-region="local-dixeo-settings-count"]');
        if (count) {
            count.textContent = text;
        }
    };

    /**
     * Load the course settings, or explain that a new course must be saved first.
     *
     * @param {HTMLElement} shell
     * @param {string} contextid Course context id, empty when the course is new.
     * @returns {Promise}
     */
    const loadSettings = function(shell, contextid) {
        resetControls(shell);
        const id = parseInt(contextid, 10);
        if (!id) {
            return Str.get_string('settings_modal_save_course', 'local_dixeo').then(function(message) {
                setList(shell, message);
            });
        }

        return Str.get_strings([
            {key: 'settings_modal_loading', component: 'local_dixeo'},
            {key: 'settings_modal_error', component: 'local_dixeo'},
        ]).then(function(strings) {
            setList(shell, strings[0]);
            return Ajax.call([{
                methodname: 'local_dixeo_get_settings',
                args: {contextid: id},
            }])[0].then(function(result) {
                if (!shell.isConnected) {
                    return null;
                }
                const settings = result && Array.isArray(result.settings) ? result.settings : [];
                setList(shell, '');
                return Str.get_string('settings_modal_count', 'local_dixeo', settings.length).then(function(label) {
                    setCount(shell, label);
                    return null;
                });
            }).catch(function() {
                if (!shell.isConnected) {
                    return null;
                }
                setList(shell, strings[1]);
                return Str.get_string('settings_modal_count', 'local_dixeo', 0).then(function(label) {
                    setCount(shell, label);
                    return null;
                });
            });
        });
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
                const shell = modal.getRoot()[0].querySelector('.local-dixeo-settings-modal');
                return loadSettings(shell, button.dataset.contextid);
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
