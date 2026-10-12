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
 * Dixeo settings modal opened from the course edit form and the file sync pill.
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
    'core/user_date',
    'local_dixeo/settings_state',
], function(Ajax, Config, Modal, Notification, Str, Templates, UserDate, State) {
    let bound = false;

    /** @type {WeakMap} Draft for the open shell. Discarded when the modal closes. */
    const sessions = new WeakMap();

    /** @type {WeakMap} Poll timer for a shell whose Status panel is open. */
    const statusTimers = new WeakMap();

    /** @type {Object|null} Strings used to paint the file sync status panel. */
    let statusStrings = null;

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
        leaveStatus(shell);
        const statusButton = shell.querySelector('[data-action="local-dixeo-settings-status"]');
        if (statusButton) {
            statusButton.hidden = !shell.dataset.courseid;
            setPressed(statusButton, false);
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
     * Stop the status poll for this shell.
     *
     * @param {HTMLElement} shell
     */
    const cancelStatusPoll = function(shell) {
        const timer = statusTimers.get(shell);
        if (timer) {
            clearTimeout(timer);
            statusTimers.delete(shell);
        }
    };

    /**
     * @param {*} value
     * @returns {boolean}
     */
    const isOn = function(value) {
        return value === true || value === 1 || value === '1';
    };

    /**
     * @param {*} value
     * @returns {boolean}
     */
    const hasNumber = function(value) {
        return value !== null && value !== undefined && !Number.isNaN(Number(value));
    };

    /**
     * Strings for the status panel. Loaded once, then reused while the modal stays open.
     *
     * @returns {Promise<Object>}
     */
    const loadStatusStrings = function() {
        if (statusStrings) {
            return Promise.resolve(statusStrings);
        }
        const keys = [
            'filesync_status_none',
            'filesync_status_syncing',
            'filesync_status_synchronized',
            'filesync_status_error',
            'filesync_status_paused',
            'filesync_status_pending_deletion',
            'filesync_status_disabled',
            'filesync_status_outdated',
            'filesync_files_count',
            'filesync_progress',
            'files',
            'last_sync',
        ];
        const requests = keys.map(function(key) {
            return {key: key, component: 'local_dixeo'};
        });
        requests.push({key: 'strftimedatetimeshort', component: 'core_langconfig'});
        return Str.get_strings(requests).then(function(loaded) {
            statusStrings = {};
            keys.forEach(function(key, index) {
                statusStrings[key] = loaded[index];
            });
            statusStrings.dateformat = loaded[keys.length];
            return statusStrings;
        });
    };

    /**
     * The same message the file sync dropdown shows.
     *
     * @param {Object} status
     * @param {Object} strings
     * @returns {string}
     */
    const statusMessage = function(status, strings) {
        if (status.status === 'pending_deletion') {
            return strings.filesync_status_pending_deletion;
        }
        if (!isOn(status.enabled)) {
            return strings.filesync_status_disabled;
        }
        if (status.status === 'synchronized' && hasNumber(status.filestotal)) {
            return strings.filesync_files_count.replace('{$a}', String(Number(status.filestotal)));
        }
        if (status.status === 'syncing') {
            const percent = hasNumber(status.progresspercent) ? Number(status.progresspercent) : 0;
            return strings.filesync_progress.replace('{$a}', String(percent));
        }
        const key = 'filesync_status_' + status.status;
        return strings[key] || status.status;
    };

    /**
     * Paint the fixed status panel. This does not touch the settings draft.
     *
     * @param {HTMLElement} shell
     * @param {Object} status
     * @param {Object} strings
     */
    const paintStatus = function(shell, status, strings) {
        const panel = shell.querySelector('[data-region="local-dixeo-settings-status"]');
        if (!panel) {
            return;
        }
        const enabled = isOn(status.enabled);
        panel.dataset.enabled = enabled ? '1' : '0';
        panel.dataset.canmanagesync = shell.dataset.canmanagesync === '1' ? '1' : '0';
        shell.dataset.syncstatus = status.status || '';
        const message = panel.querySelector('[data-region="status-message"]');
        if (message) {
            message.textContent = statusMessage(status, strings);
        }
        const progress = panel.querySelector('[data-region="progress"]');
        const progressText = panel.querySelector('[data-region="progress-text"]');
        const syncing = status.status === 'syncing';
        if (progress) {
            progress.hidden = !syncing;
        }
        if (syncing && hasNumber(status.progresspercent)) {
            const bar = panel.querySelector('[data-region="progress-bar"]');
            if (bar) {
                bar.style.width = Number(status.progresspercent) + '%';
                bar.setAttribute('aria-valuenow', String(Number(status.progresspercent)));
            }
        }
        if (progressText) {
            const showCount = syncing && hasNumber(status.filescompleted) && hasNumber(status.filestotal);
            progressText.hidden = !showCount;
            if (showCount) {
                progressText.textContent = Number(status.filescompleted) + ' / ' + Number(status.filestotal) + ' ' + strings.files;
            }
        }
        const error = panel.querySelector('[data-region="error-message"]');
        if (error) {
            const showError = status.status === 'error' && !!status.errormessage;
            error.hidden = !showError;
            error.textContent = showError ? status.errormessage : '';
        }
        const lastSync = panel.querySelector('[data-region="last-sync"]');
        const completed = hasNumber(status.lastsynccompleted) ? Number(status.lastsynccompleted) : 0;
        if (lastSync) {
            if (!completed || (status.status === 'none' && !enabled)) {
                lastSync.hidden = true;
            } else {
                lastSync.hidden = false;
                UserDate.get([{timestamp: completed, format: strings.dateformat}]).then(function(dates) {
                    if (shell.isConnected && dates && dates[0]) {
                        lastSync.textContent = strings.last_sync + ': ' + dates[0];
                    }
                    return null;
                }).catch(function() {
                    return null;
                });
            }
        }
    };

    /**
     * Read the course sync status and paint it.
     *
     * @param {HTMLElement} shell
     * @param {boolean} notify Show a dialogue when the read fails.
     * @returns {Promise}
     */
    const refreshStatus = function(shell, notify) {
        const courseid = parseInt(shell.dataset.courseid, 10);
        if (!courseid) {
            return Promise.resolve();
        }
        return loadStatusStrings().then(function(strings) {
            return Ajax.call([{
                methodname: 'local_dixeo_get_file_sync_status',
                args: {courseid: courseid},
            }])[0].then(function(status) {
                if (!shell.isConnected) {
                    return null;
                }
                paintStatus(shell, status, strings);
                return null;
            });
        }).catch(function(error) {
            if (notify) {
                Notification.exception(error);
            }
            return null;
        });
    };

    /**
     * Poll while the Status panel is open and sync is on. A disabled course stays as last painted.
     *
     * @param {HTMLElement} shell
     */
    const scheduleStatusPoll = function(shell) {
        cancelStatusPoll(shell);
        const panel = shell.querySelector('[data-region="local-dixeo-settings-status"]');
        if (!shell.isConnected || shell.dataset.panel !== 'status' || !panel || panel.dataset.enabled !== '1') {
            return;
        }
        const delay = shell.dataset.syncstatus === 'syncing' ? 5000 : 30000;
        const timer = setTimeout(function() {
            statusTimers.delete(shell);
            refreshStatus(shell, false).then(function() {
                scheduleStatusPoll(shell);
                return null;
            });
        }, delay);
        statusTimers.set(shell, timer);
    };

    /**
     * Hide the status panel and show the settings list again.
     *
     * @param {HTMLElement} shell
     */
    const leaveStatus = function(shell) {
        if (shell.dataset.panel !== 'status') {
            cancelStatusPoll(shell);
            return;
        }
        delete shell.dataset.panel;
        cancelStatusPoll(shell);
        const button = shell.querySelector('[data-action="local-dixeo-settings-status"]');
        if (button) {
            setPressed(button, false);
        }
        const list = shell.querySelector('[data-region="local-dixeo-settings-list"]');
        if (list) {
            list.hidden = false;
        }
        const panel = shell.querySelector('[data-region="local-dixeo-settings-status"]');
        if (panel) {
            panel.hidden = true;
        }
    };

    /**
     * Show the fixed status panel. Search, Customised only, and Group by leave it in place.
     *
     * @param {HTMLElement} shell
     */
    const showStatus = function(shell) {
        shell.dataset.panel = 'status';
        const button = shell.querySelector('[data-action="local-dixeo-settings-status"]');
        if (button) {
            setPressed(button, true);
        }
        shell.querySelectorAll('.local-dixeo-settings-category-list [data-category]').forEach(function(item) {
            setPressed(item, false);
        });
        const list = shell.querySelector('[data-region="local-dixeo-settings-list"]');
        if (list) {
            list.hidden = true;
        }
        const panel = shell.querySelector('[data-region="local-dixeo-settings-status"]');
        if (panel) {
            panel.hidden = false;
            panel.dataset.canmanagesync = shell.dataset.canmanagesync === '1' ? '1' : '0';
        }
        const select = shell.querySelector('#local-dixeo-settings-category');
        if (select && [...select.options].some(function(option) {
            return option.value === 'status';
        })) {
            paintingCategories = true;
            select.value = 'status';
            paintingCategories = false;
        }
        refreshStatus(shell, true).then(function() {
            scheduleStatusPoll(shell);
            return null;
        });
    };

    /**
     * Label of the Status button, used as the first option in the small-screen select.
     *
     * @param {HTMLElement} shell
     * @returns {string}
     */
    const statusLabel = function(shell) {
        const button = shell.querySelector('[data-action="local-dixeo-settings-status"]');
        return button ? button.textContent.trim() : '';
    };

    /**
     * Enable, pause, resync, or turn off. The result is applied immediately and the draft stays clean.
     *
     * @param {HTMLElement} shell
     * @param {string} action
     * @returns {Promise}
     */
    const runStatusAction = function(shell, action) {
        const courseid = parseInt(shell.dataset.courseid, 10);
        if (!courseid || shell.dataset.canmanagesync !== '1' || shell.dataset.statusbusy === '1') {
            return Promise.resolve();
        }
        shell.dataset.statusbusy = '1';
        const panel = shell.querySelector('[data-region="local-dixeo-settings-status"]');
        if (panel) {
            panel.querySelectorAll('button').forEach(function(button) {
                button.disabled = true;
            });
        }
        let request;
        if (action === 'resync') {
            request = Ajax.call([{
                methodname: 'local_dixeo_trigger_file_sync',
                args: {courseid: courseid},
            }])[0];
        } else if (action === 'enable') {
            request = Ajax.call([{
                methodname: 'local_dixeo_set_file_sync_enabled',
                args: {courseid: courseid, enabled: true, removefiles: false},
            }])[0].then(function() {
                return Ajax.call([{
                    methodname: 'local_dixeo_trigger_file_sync',
                    args: {courseid: courseid},
                }])[0];
            });
        } else if (action === 'disable') {
            request = Ajax.call([{
                methodname: 'local_dixeo_set_file_sync_enabled',
                args: {courseid: courseid, enabled: false, removefiles: true},
            }])[0];
        } else {
            request = Ajax.call([{
                methodname: 'local_dixeo_set_file_sync_enabled',
                args: {courseid: courseid, enabled: false, removefiles: false},
            }])[0];
        }
        return request.then(function() {
            if (action === 'enable' || action === 'pause' || action === 'disable') {
                syncFilesyncDraft(shell, action === 'enable');
            }
            return refreshStatus(shell, true);
        }).catch(function(error) {
            Notification.exception(error);
            return refreshStatus(shell, false);
        }).then(function() {
            delete shell.dataset.statusbusy;
            if (panel) {
                panel.querySelectorAll('button').forEach(function(button) {
                    button.disabled = false;
                });
            }
            scheduleStatusPoll(shell);
            return null;
        });
    };

    /**
     * Status actions write file sync immediately. Keep the catalogue draft on that saved value.
     *
     * @param {HTMLElement} shell
     * @param {boolean} enabled True when the action turned sync on.
     */
    const syncFilesyncDraft = function(shell, enabled) {
        const session = sessions.get(shell);
        if (!session) {
            return;
        }
        const entry = session.entries['local_dixeo/filesync_enabled'];
        if (!entry) {
            return;
        }
        const value = enabled ? '1' : '0';
        entry.saved = value;
        entry.draft = value;
        const row = shell.querySelector('.local-dixeo-settings-row[data-name="filesync_enabled"]');
        if (row) {
            paintRow(row, entry, session.labels);
        }
        publishDirty(shell);
        applyFilter(shell);
    };

    /**
     * Status is not a category. Its button and its actions stay off the settings draft.
     *
     * @param {HTMLElement} shell
     */
    const bindStatus = function(shell) {
        shell.addEventListener('click', function(event) {
            const status = event.target.closest('[data-action="local-dixeo-settings-status"]');
            if (status) {
                event.preventDefault();
                showStatus(shell);
                return;
            }
            const action = event.target.closest('.local-dixeo-settings-status-panel [data-action]');
            if (!action) {
                return;
            }
            event.preventDefault();
            runStatusAction(shell, action.dataset.action);
        });
    };

    /**
     * Hide rows that miss the search, the Customised pill, or the category. The count is how many remain.
     *
     * While Status is open, the category counts may change and the status panel does not.
     *
     * @param {HTMLElement} shell
     */
    const applyFilter = function(shell) {
        const session = sessions.get(shell);
        if (!session) {
            return;
        }
        if (shell.dataset.panel === 'status') {
            renderCategories(shell);
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
        if (shell.dataset.courseid) {
            const statusOption = document.createElement('option');
            statusOption.value = 'status';
            statusOption.textContent = statusLabel(shell);
            select.appendChild(statusOption);
        }
        items.forEach(function(item) {
            if (item.preferences) {
                const divider = document.createElement('hr');
                divider.className = 'local-dixeo-settings-category-divider';
                list.appendChild(divider);
            }
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-outline-secondary';
            button.dataset.category = item.value;
            button.hidden = !!item.hidden;
            const name = document.createElement('span');
            name.textContent = item.label;
            const count = document.createElement('span');
            count.className = 'local-dixeo-settings-category-count';
            count.textContent = String(item.count);
            button.appendChild(name);
            button.appendChild(count);
            setPressed(button, shell.dataset.panel !== 'status' && item.value === selectedCategory(shell));
            list.appendChild(button);

            const option = document.createElement('option');
            option.value = item.value;
            option.textContent = item.label + ' (' + item.count + ')';
            option.hidden = !!item.hidden;
            option.disabled = !!item.hidden;
            select.appendChild(option);
        });
        const selected = shell.dataset.panel === 'status' ? 'status' : selectedCategory(shell);
        if ([...select.options].some(function(option) {
            return option.value === selected;
        })) {
            select.value = selected;
        }
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
                if (shell.dataset.panel !== 'status') {
                    setCategory(shell, 'all');
                }
                applyFilter(shell);
                return;
            }
            const category = event.target.closest('.local-dixeo-settings-category-list [data-category]');
            if (category) {
                leaveStatus(shell);
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
            if (select.value === 'status') {
                showStatus(shell);
                return;
            }
            leaveStatus(shell);
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
     * @param {boolean} openStatus Open the Status panel once the rows are ready.
     * @returns {Promise}
     */
    const loadSettings = function(shell, contextid, openStatus) {
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
                    if (openStatus) {
                        showStatus(shell);
                    }
                    return null;
                });
            }).catch(function() {
                if (!shell.isConnected) {
                    return null;
                }
                setList(shell, strings[1]);
                if (openStatus) {
                    showStatus(shell);
                    return null;
                }
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
            cancelStatusPoll(shell);
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
            cancelStatusPoll(shell);
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
                    if (button.dataset.courseid) {
                        shell.dataset.courseid = button.dataset.courseid;
                        shell.dataset.canmanagesync = button.dataset.canmanagesync === '1' ? '1' : '0';
                    }
                    bindClose(modal, shell);
                    bindAdvanced(shell);
                    bindDraft(shell);
                    bindSave(modal, shell);
                    bindFilter(shell);
                    bindStatus(shell);
                    return loadSettings(shell, button.dataset.contextid, button.dataset.initial === 'status');
                });
            });
        });
    };

    return {
        /**
         * Listen for the button that opens the modal. The listener does not submit the course form.
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
