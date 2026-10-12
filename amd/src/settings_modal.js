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

define([
    'core/ajax',
    'core/config',
    'core/modal',
    'core/notification',
    'core/str',
    'core/templates',
    'local_dixeo/settings_state',
], function(Ajax, Config, Modal, Notification, Str, Templates, State) {
    let bound = false;

    /** @type {WeakMap} Draft for the open shell. Discarded when the modal closes. */
    const sessions = new WeakMap();

    /** True while the category controls are being rebuilt, so their change event does not loop. */
    let paintingCategories = false;

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
     * Clear the search box, the Customised pill, and the category. Opening starts from All.
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
            setPressed(pill, false);
        }
        setGrouping(shell, 'feature');
        setCategory(shell, 'all');
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
     * @param {HTMLElement} button
     * @param {boolean} pressed
     */
    const setPressed = function(button, pressed) {
        button.setAttribute('aria-pressed', pressed ? 'true' : 'false');
        button.classList.toggle('btn-primary', pressed);
        button.classList.toggle('btn-outline-secondary', !pressed);
    };

    /**
     * @param {HTMLElement} shell
     * @param {string} category
     */
    const setCategory = function(shell, category) {
        shell.dataset.category = category || 'all';
    };

    /**
     * @param {HTMLElement} shell
     * @returns {string}
     */
    const selectedCategory = function(shell) {
        return shell.dataset.category || 'all';
    };

    /**
     * Feature is the default. Changing it selects All features or All plugins.
     *
     * @param {HTMLElement} shell
     * @param {string} grouping feature or plugin.
     */
    const setGrouping = function(shell, grouping) {
        shell.dataset.grouping = grouping === 'plugin' ? 'plugin' : 'feature';
        shell.querySelectorAll('.local-dixeo-settings-grouping [data-grouping]').forEach(function(button) {
            setPressed(button, button.dataset.grouping === shell.dataset.grouping);
        });
    };

    /**
     * Hide rows that miss the search, the Customised pill, or the category. The count is how many remain.
     *
     * @param {HTMLElement} shell
     */
    const applyFilter = function(shell) {
        const session = sessions.get(shell);
        if (!session) {
            return;
        }
        const search = shell.querySelector('#local-dixeo-settings-search');
        const pill = shell.querySelector('[data-action="local-dixeo-settings-customised"]');
        const filter = {
            query: search ? search.value : '',
            customisedOnly: pill ? pill.getAttribute('aria-pressed') === 'true' : false,
            category: selectedCategory(shell),
        };
        let shown = 0;
        shell.querySelectorAll('.local-dixeo-settings-row').forEach(function(row) {
            const entry = session.entries[row.dataset.component + '/' + row.dataset.name];
            const visible = entry ? State.matches(entry, filter) : false;
            row.hidden = !visible;
            if (visible) {
                shown = shown + 1;
            }
        });
        const table = shell.querySelector('.local-dixeo-settings-table');
        const empty = shell.querySelector('[data-region="local-dixeo-settings-empty"]');
        if (table) {
            table.hidden = shown === 0;
        }
        if (empty) {
            empty.hidden = shown !== 0;
        }
        if (session.toolbarCount) {
            setCount(shell, session.toolbarCount.replace('{$a}', String(shown)));
        }
        renderCategories(shell);
    };

    /**
     * Fill the category list and the small-screen select from the loaded settings.
     *
     * @param {HTMLElement} shell
     */
    const renderCategories = function(shell) {
        const session = sessions.get(shell);
        const list = shell.querySelector('[data-region="local-dixeo-settings-category-list"]');
        const select = shell.querySelector('#local-dixeo-settings-category');
        if (!session || !list || !select || !session.allFeatures) {
            return;
        }
        const search = shell.querySelector('#local-dixeo-settings-search');
        const pill = shell.querySelector('[data-action="local-dixeo-settings-customised"]');
        const items = State.categoryItems(session.entries, shell.dataset.grouping, {
            query: search ? search.value : '',
            customisedOnly: !!(pill && pill.getAttribute('aria-pressed') === 'true'),
            category: selectedCategory(shell),
        }, {
            allFeatures: session.allFeatures,
            allPlugins: session.allPlugins,
            preferences: session.preferences,
        });
        paintingCategories = true;
        list.replaceChildren();
        select.replaceChildren();
        items.forEach(function(item) {
            if (item.preferences) {
                const divider = document.createElement('hr');
                divider.className = 'local-dixeo-settings-category-divider';
                list.appendChild(divider);
            }
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn';
            button.dataset.category = item.value;
            button.hidden = !!item.hidden;
            const name = document.createElement('span');
            name.textContent = item.label;
            const count = document.createElement('span');
            count.className = 'local-dixeo-settings-category-count';
            count.textContent = String(item.count);
            button.appendChild(name);
            button.appendChild(count);
            setPressed(button, item.value === selectedCategory(shell));
            list.appendChild(button);

            const option = document.createElement('option');
            option.value = item.value;
            option.textContent = item.label + ' (' + item.count + ')';
            option.hidden = !!item.hidden;
            option.disabled = !!item.hidden;
            select.appendChild(option);
        });
        select.value = selectedCategory(shell);
        paintingCategories = false;
    };

    /**
     * Search, Customised only, and the category filter together and leave the draft in place.
     *
     * @param {HTMLElement} shell
     */
    const bindFilter = function(shell) {
        shell.addEventListener('input', function(event) {
            if (event.target.closest('#local-dixeo-settings-search')) {
                applyFilter(shell);
            }
        });
        shell.addEventListener('click', function(event) {
            const pill = event.target.closest('[data-action="local-dixeo-settings-customised"]');
            if (pill) {
                setPressed(pill, pill.getAttribute('aria-pressed') !== 'true');
                applyFilter(shell);
                return;
            }
            const grouping = event.target.closest('.local-dixeo-settings-grouping [data-grouping]');
            if (grouping) {
                setGrouping(shell, grouping.dataset.grouping);
                setCategory(shell, 'all');
                applyFilter(shell);
                return;
            }
            const category = event.target.closest('.local-dixeo-settings-categories [data-category]');
            if (category) {
                setCategory(shell, category.dataset.category || 'all');
                applyFilter(shell);
            }
        });
        shell.addEventListener('change', function(event) {
            if (paintingCategories) {
                return;
            }
            const select = event.target.closest('#local-dixeo-settings-category');
            if (!select) {
                return;
            }
            setCategory(shell, select.value || 'all');
            applyFilter(shell);
        });
    };

    /**
     * Replace the list with one read-only row per setting.
     *
     * @param {HTMLElement} shell
     * @param {Array} settings
     * @param {number} contextid Course context id.
     * @returns {Promise}
     */
    const renderRows = function(shell, settings, contextid) {
        if (!settings.length) {
            return Promise.resolve();
        }
        const requests = [
            {key: 'settings_value_on', component: 'local_dixeo'},
            {key: 'settings_value_off', component: 'local_dixeo'},
            {key: 'settings_value_empty', component: 'local_dixeo'},
            {key: 'settings_column_course', component: 'local_dixeo'},
            {key: 'settings_column_site', component: 'local_dixeo'},
            {key: 'settings_column_dixeo', component: 'local_dixeo'},
            {key: 'settings_modal_advanced', component: 'local_dixeo'},
            {key: 'settings_badge_customised', component: 'local_dixeo'},
            {key: 'settings_badge_dixeo', component: 'local_dixeo'},
            {key: 'settings_badge_site', component: 'local_dixeo'},
            {key: 'settings_revert', component: 'local_dixeo'},
            {key: 'settings_modal_unsaved_count', component: 'local_dixeo'},
            {key: 'settings_modal_unsaved_none', component: 'local_dixeo'},
        ];
        const extraAt = requests.length;
        requests.push({key: 'settings_modal_count', component: 'local_dixeo'});
        requests.push({key: 'settings_modal_empty', component: 'local_dixeo'});
        requests.push({key: 'settings_modal_all_features', component: 'local_dixeo'});
        requests.push({key: 'settings_modal_all_plugins', component: 'local_dixeo'});
        requests.push({key: 'settings_modal_preferences', component: 'local_dixeo'});
        const fixedCount = requests.length;
        settings.forEach(function(setting) {
            requests.push({key: setting.name, component: setting.component});
            requests.push({key: setting.help, component: setting.component});
        });
        const optionAt = requests.length;
        settings.forEach(function(setting) {
            (Array.isArray(setting.options) ? setting.options : []).forEach(function(option) {
                requests.push({key: setting.name + '_' + option, component: setting.component});
            });
        });
        return Str.get_strings(requests).then(function(strings) {
            const labels = {
                on: strings[0],
                off: strings[1],
                empty: strings[2],
                badges: {
                    customised: strings[7],
                    dixeo: strings[8],
                    site: strings[9],
                },
                revert: strings[10],
            };
            const entries = {};
            let optionCursor = optionAt;
            const rows = settings.map(function(setting, index) {
                const labelAt = fixedCount + (index * 2);
                const optionlabels = {};
                (Array.isArray(setting.options) ? setting.options : []).forEach(function(option) {
                    optionlabels[option] = strings[optionCursor];
                    optionCursor = optionCursor + 1;
                });
                const labelled = Object.assign({}, setting, {optionlabels: optionlabels});
                const entry = State.entryFor(labelled);
                entry.haystack = State.searchText(labelled, strings[labelAt], strings[labelAt + 1]);
                entries[setting.component + '/' + setting.name] = entry;
                return State.rowView(labelled, strings[labelAt], strings[labelAt + 1], Config.wwwroot, labels);
            });
            sessions.set(shell, {
                labels: labels,
                entries: entries,
                countLabel: strings[11],
                noneLabel: strings[12],
                toolbarCount: strings[extraAt],
                allFeatures: strings[extraAt + 2],
                allPlugins: strings[extraAt + 3],
                preferences: strings[extraAt + 4],
                contextid: contextid,
                saving: false,
            });
            const context = {
                rows: rows,
                emptytext: strings[extraAt + 1],
                coursecolumn: strings[3],
                sitecolumn: strings[4],
                dixeocolumn: strings[5],
                advanced: strings[6],
            };
            return Templates.renderForPromise('local_dixeo/settings_rows', context).then(function(rendered) {
                if (!shell.isConnected) {
                    return null;
                }
                const list = shell.querySelector('[data-region="local-dixeo-settings-list"]');
                if (list) {
                    list.dataset.advanced = '0';
                    list.innerHTML = rendered.html;
                }
                Templates.runTemplateJS(rendered.js);
                return null;
            });
        });
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
                return renderRows(shell, settings, id).then(function() {
                    applyFilter(shell);
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
     * The shell is dirty when data-dirty is "1".
     *
     * @param {HTMLElement} shell
     * @returns {boolean}
     */
    const isDirty = function(shell) {
        return shell.dataset.dirty === '1';
    };

    /**
     * Close, or ask first when the shell is dirty. Declining leaves it open.
     *
     * @param {Object} modal
     * @param {HTMLElement} shell
     * @returns {Promise}
     */
    const requestClose = function(modal, shell) {
        if (shell.dataset.closing === '1') {
            return Promise.resolve();
        }
        if (!isDirty(shell)) {
            modal.destroy();
            return Promise.resolve();
        }
        shell.dataset.closing = '1';
        return Str.get_strings([
            {key: 'settings_modal_unsaved_title', component: 'local_dixeo'},
            {key: 'settings_modal_unsaved_confirm', component: 'local_dixeo'},
            {key: 'settings_modal_unsaved_close', component: 'local_dixeo'},
        ]).then(function(strings) {
            return Notification.saveCancelPromise(strings[0], strings[1], strings[2], {
                triggerElement: shell,
            });
        }).then(function() {
            modal.destroy();
            return null;
        }).catch(function() {
            delete shell.dataset.closing;
            return null;
        });
    };

    /**
     * @param {HTMLElement} control
     * @param {string} className local-dixeo-settings-supplied or local-dixeo-settings-inherited.
     */
    const setBorder = function(control, className) {
        control.classList.toggle('local-dixeo-settings-supplied', className === 'local-dixeo-settings-supplied');
        control.classList.toggle('local-dixeo-settings-inherited', className === 'local-dixeo-settings-inherited');
    };

    /**
     * Show the draft on one row: value, badge, borders, and the revert and reset icons.
     *
     * @param {HTMLElement} row
     * @param {Object} entry
     * @param {Object} labels
     */
    const paintRow = function(row, entry, labels) {
        const view = State.draftView(entry, labels);
        const badge = row.querySelector('.local-dixeo-settings-badge');
        if (badge) {
            badge.textContent = view.badge;
            badge.classList.toggle('local-dixeo-settings-badge-own', view.badgeown);
        }
        const control = row.querySelector('[data-action="local-dixeo-settings-value"]');
        if (control) {
            if (control.tagName === 'TEXTAREA' || control.tagName === 'SELECT') {
                if (control.value !== entry.draft) {
                    control.value = entry.draft;
                }
            } else {
                const valueLabel = control.querySelector('.local-dixeo-settings-control-label');
                if (valueLabel) {
                    valueLabel.textContent = view.display;
                }
                control.setAttribute('aria-pressed', view.pressed ? 'true' : 'false');
            }
            setBorder(control, view.valueclass);
        }
        row.querySelectorAll('[data-action="local-dixeo-settings-parent"]').forEach(function(cell) {
            setBorder(cell, cell.dataset.layer === 'dixeo' ? view.dixeoclass : view.siteclass);
        });
        const revert = row.querySelector('[data-action="local-dixeo-settings-revert"]');
        if (revert) {
            revert.hidden = !view.showrevert;
        }
    };

    /**
     * Footer count, Save, and data-dirty follow the number of drafts that differ.
     *
     * @param {HTMLElement} shell
     */
    const publishDirty = function(shell) {
        const session = sessions.get(shell);
        if (!session) {
            return;
        }
        const count = State.dirtyCount(session.entries);
        const save = shell.querySelector('[data-action="local-dixeo-settings-save"]');
        if (save) {
            save.disabled = session.saving || count === 0;
        }
        if (count > 0) {
            shell.dataset.dirty = '1';
        } else {
            delete shell.dataset.dirty;
        }
        const unsaved = shell.querySelector('[data-region="local-dixeo-settings-unsaved"]');
        if (unsaved) {
            unsaved.textContent = count === 0 ? session.noneLabel : session.countLabel.replace('{$a}', String(count));
        }
    };

    /**
     * @param {HTMLElement} shell
     * @param {HTMLElement} row
     * @param {Object} entry
     * @param {string} draft
     */
    const commitDraft = function(shell, row, entry, draft) {
        if (draft === null || draft === entry.draft) {
            return;
        }
        entry.draft = draft;
        const session = sessions.get(shell);
        clearSaveError(shell);
        paintRow(row, entry, session.labels);
        publishDirty(shell);
        applyFilter(shell);
    };

    /**
     * Remove a previous save failure from the list.
     *
     * @param {HTMLElement} shell
     */
    const clearSaveError = function(shell) {
        const error = shell.querySelector('[data-region="local-dixeo-settings-save-error"]');
        if (error) {
            error.remove();
        }
    };

    /**
     * Keep the rows and show the save failure at the top of the list.
     *
     * @param {HTMLElement} shell
     * @param {string} message
     */
    const showSaveError = function(shell, message) {
        const list = shell.querySelector('[data-region="local-dixeo-settings-list"]');
        if (!list) {
            return;
        }
        let error = list.querySelector('[data-region="local-dixeo-settings-save-error"]');
        if (!error) {
            error = document.createElement('div');
            error.dataset.region = 'local-dixeo-settings-save-error';
            error.className = 'alert alert-danger';
            error.setAttribute('role', 'alert');
            list.insertBefore(error, list.firstChild);
        }
        error.textContent = message;
    };

    /**
     * Text from a failed web service call.
     *
     * @param {*} error
     * @param {string} fallback
     * @returns {string}
     */
    const saveErrorText = function(error, fallback) {
        if (error && typeof error.message === 'string' && error.message) {
            return error.message;
        }
        if (error && typeof error.error === 'string' && error.error) {
            return error.error;
        }
        return fallback;
    };

    /**
     * Draft edits stay local until Save.
     *
     * @param {HTMLElement} shell
     */
    const bindDraft = function(shell) {
        shell.addEventListener('click', function(event) {
            const session = sessions.get(shell);
            if (!session || session.saving) {
                return;
            }
            const actionEl = event.target.closest(
                '[data-action="local-dixeo-settings-revert"],' +
                '[data-action="local-dixeo-settings-parent"],' +
                '[data-action="local-dixeo-settings-value"]'
            );
            if (!actionEl || actionEl.tagName === 'TEXTAREA') {
                return;
            }
            const row = actionEl.closest('.local-dixeo-settings-row');
            if (!row) {
                return;
            }
            const entry = session.entries[row.dataset.component + '/' + row.dataset.name];
            if (!entry) {
                return;
            }
            const action = actionEl.dataset.action;
            let next = null;
            if (action === 'local-dixeo-settings-revert') {
                next = State.nextDraft(entry, 'revert');
            } else if (action === 'local-dixeo-settings-parent') {
                next = State.nextDraft(entry, 'parent', actionEl.dataset.layer);
            } else if (entry.setting.type === 'bool') {
                next = State.nextDraft(entry, 'toggle');
            }
            if (next === null) {
                return;
            }
            event.preventDefault();
            commitDraft(shell, row, entry, next);
        });
        shell.addEventListener('input', function(event) {
            const session = sessions.get(shell);
            const control = event.target.closest('[data-action="local-dixeo-settings-value"]');
            if (!session || session.saving || !control || control.tagName !== 'TEXTAREA') {
                return;
            }
            const row = control.closest('.local-dixeo-settings-row');
            if (!row) {
                return;
            }
            const entry = session.entries[row.dataset.component + '/' + row.dataset.name];
            if (!entry) {
                return;
            }
            commitDraft(shell, row, entry, control.value);
        });
        shell.addEventListener('change', function(event) {
            const session = sessions.get(shell);
            const control = event.target.closest('select[data-action="local-dixeo-settings-value"]');
            if (!session || session.saving || !control) {
                return;
            }
            const row = control.closest('.local-dixeo-settings-row');
            if (!row) {
                return;
            }
            const entry = session.entries[row.dataset.component + '/' + row.dataset.name];
            if (!entry) {
                return;
            }
            commitDraft(shell, row, entry, control.value);
        });
    };

    /**
     * Save the rows whose draft differs. Success clears the draft and closes.
     * Failure leaves the modal and the draft in place.
     *
     * @param {Object} modal
     * @param {HTMLElement} shell
     */
    const bindSave = function(modal, shell) {
        shell.addEventListener('click', function(event) {
            const button = event.target.closest('[data-action="local-dixeo-settings-save"]');
            const session = sessions.get(shell);
            if (!button || !session || session.saving || button.disabled) {
                return;
            }
            const settings = State.changedSettings(session.entries);
            if (!settings.length) {
                return;
            }
            event.preventDefault();
            session.saving = true;
            button.disabled = true;
            clearSaveError(shell);
            const fallbackReady = Str.get_string('settings_modal_save_error', 'local_dixeo');
            Ajax.call([{
                methodname: 'local_dixeo_save_settings',
                args: {
                    contextid: session.contextid,
                    settings: settings,
                },
            }])[0].then(function() {
                if (!shell.isConnected) {
                    return null;
                }
                sessions.delete(shell);
                delete shell.dataset.dirty;
                modal.destroy();
                return null;
            }).catch(function(error) {
                if (!shell.isConnected) {
                    return null;
                }
                session.saving = false;
                publishDirty(shell);
                return fallbackReady.then(function(fallback) {
                    showSaveError(shell, saveErrorText(error, fallback));
                    return null;
                });
            });
        });
    };

    /**
     * Advanced reveals the Site default and Dixeo columns. It starts off.
     *
     * @param {HTMLElement} shell
     */
    const bindAdvanced = function(shell) {
        shell.addEventListener('change', function(event) {
            const input = event.target.closest('[data-action="local-dixeo-settings-advanced"]');
            if (!input) {
                return;
            }
            const list = shell.querySelector('[data-region="local-dixeo-settings-list"]');
            if (list) {
                list.dataset.advanced = input.checked ? '1' : '0';
            }
        });
    };

    /**
     * Cancel, the header close button, and Escape share one close path.
     *
     * @param {Object} modal
     * @param {HTMLElement} shell
     */
    const bindClose = function(modal, shell) {
        const root = modal.getRoot()[0];
        root.addEventListener('keydown', function(event) {
            if (event.key !== 'Escape') {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            requestClose(modal, shell);
        }, true);
        root.addEventListener('click', function(event) {
            const hide = event.target.closest('[data-action="hide"]');
            const cancel = event.target.closest('[data-action="local-dixeo-settings-cancel"]');
            if (!hide && !cancel) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            requestClose(modal, shell);
        }, true);
    };

    /**
     * Open the settings shell. Moodle's modal traps focus.
     *
     * @param {HTMLElement} button
     * @returns {Promise}
     */
    const open = function(button) {
        const stringsReady = Str.get_strings([
            {key: 'settings_modal_title', component: 'local_dixeo'},
            {key: 'settings_modal_breadcrumb', component: 'local_dixeo', param: courseName(button)},
        ]);
        const bodyReady = Templates.renderForPromise('local_dixeo/settings_modal', {});
        return Promise.all([stringsReady, bodyReady]).then(function(results) {
            const strings = results[0];
            const rendered = results[1];
            return Modal.create({
                title: strings[0],
                body: rendered.html,
                large: true,
                removeOnClose: true,
                show: true,
                returnElement: button,
            }).then(function(modal) {
                const paintTitle = function() {
                    const title = modal.getRoot()[0].querySelector('[data-region="title"]');
                    if (!title) {
                        return;
                    }
                    const heading = document.createElement('span');
                    heading.textContent = strings[0];
                    const crumbs = document.createElement('span');
                    crumbs.className = 'local-dixeo-settings-crumbs';
                    crumbs.textContent = strings[1];
                    title.replaceChildren(heading, crumbs);
                };
                const titleReady = modal.titlePromise ? modal.titlePromise.then(paintTitle) : paintTitle();
                return Promise.resolve(titleReady).then(function() {
                    Templates.runTemplateJS(rendered.js);
                    const shell = modal.getRoot()[0].querySelector('.local-dixeo-settings-modal');
                    bindClose(modal, shell);
                    bindAdvanced(shell);
                    bindDraft(shell);
                    bindSave(modal, shell);
                    bindFilter(shell);
                    return loadSettings(shell, button.dataset.contextid);
                });
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
