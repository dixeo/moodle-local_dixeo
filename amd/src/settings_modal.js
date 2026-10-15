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
    'core_form/changechecker',
    'local_dixeo/settings_state',
], function(Ajax, Config, Modal, Notification, Str, Templates, UserDate, ChangeChecker, State) {
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
     * Cover the settings panel with a centred label. Loading also shows a spinner.
     *
     * @param {HTMLElement} shell
     * @param {string} text
     * @param {string} mode loading or saving.
     */
    const showPanelOverlay = function(shell, text, mode) {
        const overlay = shell.querySelector('[data-region="local-dixeo-settings-overlay"]');
        if (!overlay) {
            return;
        }
        overlay.dataset.mode = mode === 'saving' ? 'saving' : 'loading';
        const label = overlay.querySelector('[data-region="overlay-label"]');
        if (label) {
            label.textContent = text;
        }
        overlay.hidden = false;
    };

    /**
     * Remove the panel cover.
     *
     * @param {HTMLElement} shell
     */
    const hidePanelOverlay = function(shell) {
        const overlay = shell.querySelector('[data-region="local-dixeo-settings-overlay"]');
        if (overlay) {
            overlay.hidden = true;
        }
    };

    /**
     * Stop edits, filters, and close while a save is in flight.
     *
     * @param {HTMLElement} shell
     * @param {boolean} locked
     */
    const setUiLocked = function(shell, locked) {
        const modal = shell.closest('.modal');
        shell.classList.toggle('local-dixeo-settings-locked', locked);
        if (modal) {
            modal.classList.toggle('local-dixeo-settings-locked', locked);
        }
        if (locked) {
            shell.setAttribute('aria-busy', 'true');
        } else {
            shell.removeAttribute('aria-busy');
        }
        const controls = [...shell.querySelectorAll('button, input, select, textarea')];
        if (modal) {
            modal.querySelectorAll('.modal-header button').forEach(function(button) {
                controls.push(button);
            });
        }
        controls.forEach(function(control) {
            if (locked) {
                if (!control.disabled) {
                    control.dataset.locked = '1';
                    control.disabled = true;
                }
                return;
            }
            if (control.dataset.locked === '1') {
                control.disabled = false;
                delete control.dataset.locked;
            }
        });
    };

    /**
     * Whether Dixeo features run for this shell.
     *
     * Null means the catalogue has not said. Off at the Dixeo layer is off for the course
     * even when the course value is On.
     *
     * @param {HTMLElement} shell
     * @returns {boolean|null}
     */
    const featuresEnabled = function(shell) {
        const session = sessions.get(shell);
        if (!session) {
            return null;
        }
        const entry = session.entries['local_dixeo/enabled'];
        if (!entry) {
            return null;
        }
        if (entry.dixeo !== null && entry.dixeo !== '1') {
            return false;
        }
        if (shell.dataset.courseid && entry.saved !== '1') {
            return false;
        }
        return true;
    };

    /**
     * Status cannot read file sync while Dixeo is off. Say so instead of raising the gate error.
     *
     * @param {HTMLElement} shell
     * @returns {Promise}
     */
    const paintDixeoOff = function(shell) {
        cancelStatusPoll(shell);
        const panel = shell.querySelector('[data-region="local-dixeo-settings-status"]');
        if (!panel) {
            return Promise.resolve();
        }
        panel.dataset.enabled = '0';
        panel.dataset.canmanagesync = '0';
        delete shell.dataset.syncstatus;
        ['last-sync', 'progress', 'progress-text', 'error-message'].forEach(function(region) {
            const node = panel.querySelector('[data-region="' + region + '"]');
            if (node) {
                node.hidden = true;
            }
        });
        return Str.get_string('error:dixeo_disabled', 'local_dixeo').then(function(text) {
            const message = panel.querySelector('[data-region="status-message"]');
            if (message && shell.isConnected) {
                message.textContent = text;
            }
            return null;
        });
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
     * Progress bar and file count while a sync is running.
     *
     * @param {HTMLElement} panel
     * @param {Object} status
     * @param {Object} strings
     */
    const paintStatusProgress = function(panel, status, strings) {
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
        if (!progressText) {
            return;
        }
        const showCount = syncing && hasNumber(status.filescompleted) && hasNumber(status.filestotal);
        progressText.hidden = !showCount;
        if (showCount) {
            progressText.textContent = Number(status.filescompleted) + ' / ' + Number(status.filestotal) + ' ' + strings.files;
        }
    };

    /**
     * Last-sync time, hidden when sync has never completed or the course is off with no status.
     *
     * @param {HTMLElement} shell
     * @param {HTMLElement} panel
     * @param {Object} status
     * @param {Object} strings
     * @param {boolean} enabled
     */
    const paintStatusLastSync = function(shell, panel, status, strings, enabled) {
        const lastSync = panel.querySelector('[data-region="last-sync"]');
        const completed = hasNumber(status.lastsynccompleted) ? Number(status.lastsynccompleted) : 0;
        if (!lastSync) {
            return;
        }
        if (!completed || (status.status === 'none' && !enabled)) {
            lastSync.hidden = true;
            return;
        }
        lastSync.hidden = false;
        UserDate.get([{timestamp: completed, format: strings.dateformat}]).then(function(dates) {
            if (shell.isConnected && dates && dates[0]) {
                lastSync.textContent = strings.last_sync + ': ' + dates[0];
            }
            return null;
        }).catch(function() {
            return null;
        });
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
        paintStatusProgress(panel, status, strings);
        const error = panel.querySelector('[data-region="error-message"]');
        if (error) {
            const showError = status.status === 'error' && !!status.errormessage;
            error.hidden = !showError;
            error.textContent = showError ? status.errormessage : '';
        }
        paintStatusLastSync(shell, panel, status, strings, enabled);
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
        if (featuresEnabled(shell) === false) {
            return paintDixeoOff(shell);
        }
        let statusStrings = null;
        return loadStatusStrings().then(function(strings) {
            statusStrings = strings;
            return Ajax.call([{
                methodname: 'local_dixeo_get_file_sync_status',
                args: {courseid: courseid},
            }])[0];
        }).then(function(status) {
            if (!shell.isConnected) {
                return null;
            }
            paintStatus(shell, status, statusStrings);
            return null;
        }).catch(function(error) {
            if (error && error.errorcode === 'error:dixeo_disabled') {
                return paintDixeoOff(shell);
            }
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
            }).catch(function() {
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
        const preferences = shell.querySelector('[data-region="local-dixeo-settings-preferences"]');
        if (preferences) {
            preferences.hidden = true;
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
        }).catch(function() {
            return null;
        });
    };

    /**
     * Hide the course preferences panel and show the settings list again.
     *
     * @param {HTMLElement} shell
     */
    const leavePreferences = function(shell) {
        const panel = shell.querySelector('[data-region="local-dixeo-settings-preferences"]');
        if (panel) {
            panel.hidden = true;
        }
        if (shell.dataset.panel !== 'preferences') {
            return;
        }
        delete shell.dataset.panel;
        const list = shell.querySelector('[data-region="local-dixeo-settings-list"]');
        if (list) {
            list.hidden = false;
        }
    };

    /**
     * Load the delete-conversation label. The button stays hidden when Tutor is not installed.
     *
     * @param {HTMLElement} shell
     * @returns {Promise}
     */
    const prepareDeleteConversation = function(shell) {
        const button = shell.querySelector('[data-action="delete-conversation"]');
        if (!button || !shell.dataset.courseid) {
            return Promise.resolve();
        }
        if (button.dataset.ready === '1') {
            button.hidden = false;
            return Promise.resolve();
        }
        return Str.get_strings([
            {key: 'deleteconversation', component: 'block_dixeo_tutor'},
            {key: 'deleteconversationconfirm', component: 'block_dixeo_tutor'},
            {key: 'delete', component: 'moodle'},
        ]).then(function(strings) {
            const label = button.querySelector('[data-region="delete-conversation-label"]');
            if (label) {
                label.textContent = strings[0];
            }
            button.setAttribute('aria-label', strings[0]);
            button.dataset.confirm = strings[1];
            button.dataset.yes = strings[2];
            button.dataset.ready = '1';
            button.hidden = false;
            return null;
        }).catch(function() {
            button.hidden = true;
            return null;
        });
    };

    /**
     * Show course preferences. Outside a course the settings list stays in place.
     *
     * @param {HTMLElement} shell
     */
    const showPreferences = function(shell) {
        const panel = shell.querySelector('[data-region="local-dixeo-settings-preferences"]');
        if (!shell.dataset.courseid || !panel) {
            return;
        }
        shell.dataset.panel = 'preferences';
        const list = shell.querySelector('[data-region="local-dixeo-settings-list"]');
        if (list) {
            list.hidden = true;
        }
        const status = shell.querySelector('[data-region="local-dixeo-settings-status"]');
        if (status) {
            status.hidden = true;
        }
        panel.hidden = false;
        const session = sessions.get(shell);
        if (session && session.toolbarCount) {
            setCount(shell, session.toolbarCount.replace('{$a}', '0'));
        }
        prepareDeleteConversation(shell).catch(function() {
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
     * Reload the current page after a stored setting changes.
     *
     * The course form is not submitted. Its unsaved-change warning is cleared so the reload is not blocked.
     */
    const reloadAppliedPage = function() {
        ChangeChecker.resetAllFormDirtyStates();
        window.location.reload();
    };

    /**
     * Enable, pause, resync, or turn off. A change to the stored setting reloads the page.
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
            let enabledResult = null;
            request = Ajax.call([{
                methodname: 'local_dixeo_set_file_sync_enabled',
                args: {courseid: courseid, enabled: true, removefiles: false},
            }])[0].then(function(result) {
                enabledResult = result;
                if (!result || result.success === false) {
                    return null;
                }
                return Ajax.call([{
                    methodname: 'local_dixeo_trigger_file_sync',
                    args: {courseid: courseid},
                }])[0];
            }).then(function() {
                return enabledResult;
            }).catch(function(error) {
                if (enabledResult && enabledResult.success !== false) {
                    Notification.exception(error);
                    return enabledResult;
                }
                throw error;
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
        return request.then(function(result) {
            if (result && result.success === false) {
                if (result.error) {
                    Notification.addNotification({message: result.error, type: 'error'});
                }
                return refreshStatus(shell, false);
            }
            if (action === 'enable' || action === 'pause' || action === 'disable') {
                reloadAppliedPage();
                return null;
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
     * Confirm, then erase the current user's tutor conversation in this course.
     *
     * @param {HTMLElement} shell
     * @param {HTMLElement} button
     * @returns {Promise}
     */
    const deleteConversation = function(shell, button) {
        const courseid = parseInt(shell.dataset.courseid, 10);
        if (!courseid || button.dataset.busy === '1' || button.dataset.ready !== '1') {
            return Promise.resolve();
        }
        let confirmed = false;
        return Notification.deleteCancelPromise(
            button.getAttribute('aria-label') || '',
            button.dataset.confirm || '',
            button.dataset.yes || '',
            {triggerElement: button}
        ).then(function() {
            confirmed = true;
            button.dataset.busy = '1';
            button.disabled = true;
            return Ajax.call([{
                methodname: 'block_dixeo_tutor_delete_conversation',
                args: {courseid: courseid},
            }])[0];
        }).then(function() {
            if (!confirmed) {
                return null;
            }
            window.dispatchEvent(new CustomEvent('dixeo-tutor-conversation-deleted', {
                detail: {courseid: courseid},
            }));
            return null;
        }).catch(function(error) {
            if (confirmed) {
                Notification.exception(error);
            }
            return null;
        }).then(function() {
            delete button.dataset.busy;
            button.disabled = false;
            return null;
        });
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
     * Delete conversation lives on the preferences panel and is not a setting draft.
     *
     * @param {HTMLElement} shell
     */
    const bindPreferences = function(shell) {
        shell.addEventListener('click', function(event) {
            const button = event.target.closest('[data-action="delete-conversation"]');
            if (!button || button.hidden) {
                return;
            }
            event.preventDefault();
            deleteConversation(shell, button);
        });
    };

    /**
     * Place a group or feature title immediately before a row.
     *
     * @param {HTMLElement} table
     * @param {HTMLElement} row
     * @param {string} className
     * @param {string} titleClass
     * @param {string} tag
     * @param {string} text
     */
    const insertGroupTitle = function(table, row, className, titleClass, tag, text) {
        const heading = document.createElement('div');
        heading.className = className;
        heading.setAttribute('role', 'row');
        const title = document.createElement(tag);
        title.className = titleClass;
        title.textContent = text;
        heading.appendChild(title);
        table.insertBefore(heading, row);
    };

    /**
     * Feature headings on All features. Plugin headings on All plugins, with a feature subheading.
     *
     * A single selected feature or plugin already names the list, so that view has no group heading.
     * A selected plugin still shows its feature subheadings. Rows stay in catalogue order inside a
     * group. Groups follow the category order.
     *
     * @param {HTMLElement} shell
     */
    const syncGroupHeadings = function(shell) {
        const session = sessions.get(shell);
        const table = shell.querySelector('.local-dixeo-settings-table');
        if (!session || !table) {
            return;
        }
        table.querySelectorAll('.local-dixeo-settings-group, .local-dixeo-settings-subgroup').forEach(function(heading) {
            heading.remove();
        });
        const rows = Array.from(table.querySelectorAll('.local-dixeo-settings-row'));
        const grouping = shell.dataset.grouping === 'plugin' ? 'plugin' : 'feature';
        const allselected = selectedCategory(shell) === 'all';
        const show = grouping === 'plugin' || allselected;
        const byOrder = function(left, right) {
            return Number(left.dataset.order) - Number(right.dataset.order);
        };
        const compareId = function(left, right, pinned) {
            if (left === pinned) {
                return -1;
            }
            if (right === pinned) {
                return 1;
            }
            return left.localeCompare(right);
        };
        if (!show) {
            rows.sort(byOrder);
            rows.forEach(function(row) {
                table.appendChild(row);
            });
            return;
        }
        const names = session.categoryNames || {};
        const labelFor = function(id) {
            return names[id] || id;
        };
        rows.sort(function(left, right) {
            if (grouping === 'plugin') {
                const leftplugin = left.dataset.plugingroup || '';
                const rightplugin = right.dataset.plugingroup || '';
                if (leftplugin !== rightplugin) {
                    return compareId(leftplugin, rightplugin, 'dixeo_core');
                }
            }
            const leftfeature = left.dataset.feature || '';
            const rightfeature = right.dataset.feature || '';
            if (leftfeature !== rightfeature) {
                return compareId(leftfeature, rightfeature, 'dixeo');
            }
            return byOrder(left, right);
        });
        let currentplugin = null;
        let currentfeature = null;
        rows.forEach(function(row) {
            table.appendChild(row);
            if (row.hidden) {
                return;
            }
            const feature = row.dataset.feature || '';
            if (grouping === 'plugin') {
                const plugin = row.dataset.plugingroup || '';
                if (plugin && plugin !== currentplugin) {
                    currentplugin = plugin;
                    currentfeature = null;
                    if (allselected) {
                        insertGroupTitle(
                            table,
                            row,
                            'local-dixeo-settings-group',
                            'local-dixeo-settings-group-title',
                            'h3',
                            labelFor(plugin)
                        );
                    }
                }
                if (feature && feature !== currentfeature) {
                    currentfeature = feature;
                    insertGroupTitle(
                        table,
                        row,
                        'local-dixeo-settings-subgroup',
                        'local-dixeo-settings-subgroup-title',
                        'h4',
                        labelFor(feature)
                    );
                }
                return;
            }
            if (!feature || feature === currentfeature) {
                return;
            }
            currentfeature = feature;
            insertGroupTitle(
                table,
                row,
                'local-dixeo-settings-group',
                'local-dixeo-settings-group-title',
                'h3',
                labelFor(feature)
            );
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
        if (shell.dataset.panel === 'status' || shell.dataset.panel === 'preferences') {
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
        syncGroupHeadings(shell);
        if (session.toolbarCount) {
            setCount(shell, session.toolbarCount.replace('{$a}', String(shown)));
        }
        renderCategories(shell);
        syncParentColumns(shell);
    };

    /**
     * Site default is shown only when a visible row has one.
     * At the Dixeo level the Dixeo parent column is the value column, so it stays hidden.
     * Advanced is hidden there when Site default is hidden too.
     *
     * @param {HTMLElement} shell
     */
    const syncParentColumns = function(shell) {
        const list = shell.querySelector('[data-region="local-dixeo-settings-list"]');
        if (!list) {
            return;
        }
        const atDixeo = !shell.dataset.courseid;
        const visible = Array.from(shell.querySelectorAll('.local-dixeo-settings-row')).filter(function(row) {
            return !row.hidden;
        });
        const showSite = visible.some(function(row) {
            return !!row.querySelector('[data-layer="site"] [data-action="local-dixeo-settings-parent"]');
        });
        list.dataset.showSite = showSite ? '1' : '0';
        list.dataset.showDixeo = atDixeo ? '0' : '1';
        const advanced = list.querySelector('.local-dixeo-settings-advanced');
        if (!advanced) {
            return;
        }
        const hideAdvanced = atDixeo && !showSite;
        advanced.hidden = hideAdvanced;
        if (!hideAdvanced) {
            return;
        }
        const input = advanced.querySelector('[data-action="local-dixeo-settings-advanced"]');
        if (input) {
            input.checked = false;
        }
        list.dataset.advanced = '0';
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
            names: session.categoryNames || {},
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
     * Preferences is a category with no settings. Inside a course it shows course actions.
     *
     * @param {HTMLElement} shell
     * @param {string} category
     */
    const selectCategory = function(shell, category) {
        const value = category || 'all';
        if (value === 'preferences') {
            leaveStatus(shell);
            setCategory(shell, 'preferences');
            if (shell.dataset.courseid) {
                showPreferences(shell);
                renderCategories(shell);
                return;
            }
            leavePreferences(shell);
            applyFilter(shell);
            return;
        }
        leavePreferences(shell);
        leaveStatus(shell);
        setCategory(shell, value);
        applyFilter(shell);
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
                if (shell.dataset.panel !== 'status' && shell.dataset.panel !== 'preferences') {
                    setCategory(shell, 'all');
                }
                applyFilter(shell);
                return;
            }
            const category = event.target.closest('.local-dixeo-settings-category-list [data-category]');
            if (category) {
                selectCategory(shell, category.dataset.category || 'all');
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
                leavePreferences(shell);
                showStatus(shell);
                return;
            }
            selectCategory(shell, select.value || 'all');
        });
    };

    /**
     * Option labels sent with the catalogue. Missing options still use language strings.
     *
     * @param {Object} setting Catalogue row.
     * @returns {Object<string, string>}
     */
    const suppliedOptionLabels = function(setting) {
        const labels = {};
        (Array.isArray(setting.optionlabels) ? setting.optionlabels : []).forEach(function(item) {
            if (!item || item.value === undefined || item.value === null) {
                return;
            }
            labels[String(item.value)] = item.label || '';
        });
        return labels;
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
            {key: 'settings_readonly', component: 'local_dixeo'},
        ];
        const extraAt = requests.length;
        requests.push({key: 'settings_modal_count', component: 'local_dixeo'});
        requests.push({key: 'settings_modal_empty', component: 'local_dixeo'});
        requests.push({key: 'settings_modal_all_features', component: 'local_dixeo'});
        requests.push({key: 'settings_modal_all_plugins', component: 'local_dixeo'});
        requests.push({key: 'settings_modal_preferences', component: 'local_dixeo'});
        requests.push({key: 'settings_modal_saving', component: 'local_dixeo'});
        requests.push({key: 'settings_category_filesync', component: 'local_dixeo'});
        requests.push({key: 'settings_category_dixeo_core', component: 'local_dixeo'});
        requests.push({key: 'settings_category_tutor', component: 'local_dixeo'});
        requests.push({key: 'settings_category_dixeo_tutor', component: 'local_dixeo'});
        requests.push({key: 'settings_category_imagegeneration', component: 'local_dixeo'});
        requests.push({key: 'settings_category_designer', component: 'local_dixeo'});
        requests.push({key: 'settings_category_dixeo_designer', component: 'local_dixeo'});
        const fixedCount = requests.length;
        settings.forEach(function(setting) {
            requests.push({key: setting.name, component: setting.component});
            requests.push({key: setting.help, component: setting.component});
        });
        const optionAt = requests.length;
        settings.forEach(function(setting) {
            const supplied = suppliedOptionLabels(setting);
            (Array.isArray(setting.options) ? setting.options : []).forEach(function(option) {
                if (Object.prototype.hasOwnProperty.call(supplied, option)) {
                    return;
                }
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
                readonly: strings[13],
            };
            const entries = {};
            let optionCursor = optionAt;
            const rows = settings.map(function(setting, index) {
                const labelAt = fixedCount + (index * 2);
                const supplied = suppliedOptionLabels(setting);
                const optionlabels = {};
                (Array.isArray(setting.options) ? setting.options : []).forEach(function(option) {
                    if (Object.prototype.hasOwnProperty.call(supplied, option)) {
                        optionlabels[option] = supplied[option];
                        return;
                    }
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
                savingLabel: strings[extraAt + 5],
                categoryNames: {
                    filesync: strings[extraAt + 6],
                    'dixeo_core': strings[extraAt + 7],
                    tutor: strings[extraAt + 8],
                    'dixeo_tutor': strings[extraAt + 9],
                    imagegeneration: strings[extraAt + 10],
                    designer: strings[extraAt + 11],
                    'dixeo_designer': strings[extraAt + 12],
                },
                contextid: contextid,
                saving: false,
            });
            const context = {
                rows: rows,
                emptytext: strings[extraAt + 1],
                coursecolumn: shell.dataset.courseid ? strings[3] : '',
                sitecolumn: strings[4],
                dixeocolumn: strings[5],
                advanced: strings[6],
            };
            return Templates.renderForPromise('local_dixeo/settings_rows', context);
        }).then(function(rendered) {
            if (!shell.isConnected) {
                return null;
            }
            const list = shell.querySelector('[data-region="local-dixeo-settings-list"]');
            if (list) {
                list.dataset.advanced = '0';
                list.innerHTML = rendered.html;
                list.querySelectorAll('.local-dixeo-settings-row').forEach(function(row, index) {
                    row.dataset.order = String(index);
                });
            }
            Templates.runTemplateJS(rendered.js);
            return null;
        });
    };

    /**
     * Load the course settings, or explain that a new course must be saved first.
     *
     * @param {HTMLElement} shell
     * @param {string} contextid Course context id, empty when the course is new.
     * @param {boolean} openStatus Open the Status panel once the rows are ready.
     * @param {string} [openCategory] Category to select instead of All, such as feature:tutor.
     * @returns {Promise}
     */
    const loadSettings = function(shell, contextid, openStatus, openCategory) {
        resetControls(shell);
        const id = parseInt(contextid, 10);
        if (!id) {
            return Str.get_string('settings_modal_save_course', 'local_dixeo').then(function(message) {
                setList(shell, message);
                return null;
            });
        }

        let loadingStrings = null;
        let phase = 'strings';
        return Str.get_strings([
            {key: 'settings_modal_loading', component: 'local_dixeo'},
            {key: 'settings_modal_error', component: 'local_dixeo'},
        ]).then(function(strings) {
            loadingStrings = strings;
            phase = 'ajax';
            showPanelOverlay(shell, strings[0], 'loading');
            return Ajax.call([{
                methodname: 'local_dixeo_get_settings',
                args: {contextid: id},
            }])[0];
        }).then(function(result) {
            phase = 'render';
            if (!shell.isConnected) {
                phase = 'done';
                return null;
            }
            const settings = result && Array.isArray(result.settings) ? result.settings : [];
            hidePanelOverlay(shell);
            setList(shell, '');
            return renderRows(shell, settings, id);
        }).then(function() {
            if (phase !== 'render') {
                return null;
            }
            if (shell.dataset.panel === 'preferences') {
                phase = 'done';
                return null;
            }
            if (openCategory) {
                setCategory(shell, openCategory);
            }
            applyFilter(shell);
            if (openStatus) {
                showStatus(shell);
            }
            phase = 'done';
            return null;
        }).catch(function(error) {
            if (phase === 'strings' || phase === 'done') {
                throw error;
            }
            if (!shell.isConnected) {
                return null;
            }
            hidePanelOverlay(shell);
            setList(shell, loadingStrings[1]);
            if (openStatus) {
                showStatus(shell);
                return null;
            }
            return Str.get_string('settings_modal_count', 'local_dixeo', 0);
        }).then(function(label) {
            if (typeof label === 'string') {
                setCount(shell, label);
            }
            return null;
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
        const session = sessions.get(shell);
        if ((session && session.saving) || shell.dataset.closing === '1') {
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
            if (control.tagName === 'SELECT' && control.multiple) {
                const selected = String(entry.draft || '').split(',').filter(Boolean);
                Array.prototype.forEach.call(control.options, function(option) {
                    option.selected = selected.indexOf(option.value) !== -1;
                });
            } else if (control.tagName === 'TEXTAREA' || control.tagName === 'SELECT') {
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
            if (!entry || entry.setting.readonly) {
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
            const istext = control && control.tagName === 'INPUT' && control.type === 'text';
            if (!session || session.saving || !control || (control.tagName !== 'TEXTAREA' && !istext)) {
                return;
            }
            const row = control.closest('.local-dixeo-settings-row');
            if (!row) {
                return;
            }
            const entry = session.entries[row.dataset.component + '/' + row.dataset.name];
            if (!entry || entry.setting.readonly) {
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
            if (!entry || entry.setting.readonly) {
                return;
            }
            commitDraft(shell, row, entry, selectValue(control, entry));
        });
    };

    /**
     * Value of a select. A multiselect is the selected options in catalogue order.
     *
     * @param {HTMLSelectElement} control
     * @param {Object} entry
     * @returns {string}
     */
    const selectValue = function(control, entry) {
        if (!control.multiple) {
            return control.value;
        }
        const order = Array.isArray(entry.setting.options) ? entry.setting.options : [];
        const chosen = {};
        Array.prototype.forEach.call(control.selectedOptions, function(option) {
            chosen[option.value] = true;
        });
        return order.filter(function(option) {
            return chosen[option];
        }).join(',');
    };

    /**
     * Save the rows whose draft differs. Success clears the course cache and reloads the page.
     * Failure leaves the modal and the draft in place.
     *
     * @param {HTMLElement} shell
     */
    const bindSave = function(shell) {
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
            clearSaveError(shell);
            setUiLocked(shell, true);
            showPanelOverlay(shell, session.savingLabel, 'saving');
            const fallbackReady = Str.get_string('settings_modal_save_error', 'local_dixeo');
            let saveError = null;
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
                reloadAppliedPage();
                return null;
            }).catch(function(error) {
                if (!shell.isConnected) {
                    return null;
                }
                session.saving = false;
                hidePanelOverlay(shell);
                setUiLocked(shell, false);
                publishDirty(shell);
                saveError = error;
                return fallbackReady;
            }).then(function(fallback) {
                if (!saveError) {
                    return null;
                }
                showSaveError(shell, saveErrorText(saveError, fallback));
                return null;
            }).catch(function() {
                return null;
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
     * Preferences only. Other categories, grouping, and the settings list stay hidden.
     * This path does not call get_settings.
     *
     * @param {HTMLElement} shell
     * @returns {Promise}
     */
    const showPreferencesOnly = function(shell) {
        resetControls(shell);
        shell.dataset.preferencesonly = '1';
        setCategory(shell, 'preferences');
        leaveStatus(shell);
        return Str.get_strings([
            {key: 'settings_modal_preferences', component: 'local_dixeo'},
            {key: 'settings_modal_count', component: 'local_dixeo', param: 0},
        ]).then(function(strings) {
            const list = shell.querySelector('[data-region="local-dixeo-settings-category-list"]');
            const select = shell.querySelector('#local-dixeo-settings-category');
            if (list) {
                list.replaceChildren();
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn btn-outline-secondary';
                button.dataset.category = 'preferences';
                const name = document.createElement('span');
                name.textContent = strings[0];
                button.appendChild(name);
                setPressed(button, true);
                list.appendChild(button);
            }
            if (select) {
                select.replaceChildren();
                const option = document.createElement('option');
                option.value = 'preferences';
                option.textContent = strings[0];
                select.appendChild(option);
                select.value = 'preferences';
            }
            setList(shell, '');
            setCount(shell, strings[1]);
            showPreferences(shell);
            return null;
        });
    };

    /**
     * Open the settings shell. An explicit data-initial wins. A course with no initial opens on Status.
     * Moodle's modal traps focus.
     *
     * @param {HTMLElement} button
     * @returns {Promise}
     */
    const open = function(button) {
        const name = courseName(button);
        const requests = [{key: 'settings_modal_title', component: 'local_dixeo'}];
        if (name !== '') {
            requests.push({key: 'settings_modal_breadcrumb', component: 'local_dixeo', param: name});
        }
        const stringsReady = Str.get_strings(requests);
        const bodyReady = Templates.renderForPromise('local_dixeo/settings_modal', {});
        let modalStrings = [];
        let modalRendered = {html: '', js: ''};
        let createdModal = null;
        const paintTitle = function() {
            const title = createdModal.getRoot()[0].querySelector('[data-region="title"]');
            if (!title) {
                return;
            }
            const heading = document.createElement('span');
            heading.textContent = modalStrings[0];
            title.replaceChildren(heading);
            if (modalStrings[1]) {
                const crumbs = document.createElement('span');
                crumbs.className = 'local-dixeo-settings-crumbs';
                crumbs.textContent = modalStrings[1];
                title.appendChild(crumbs);
            }
        };
        return Promise.all([stringsReady, bodyReady]).then(function(results) {
            modalStrings = results[0];
            modalRendered = results[1];
            return Modal.create({
                title: modalStrings[0],
                body: modalRendered.html,
                large: true,
                removeOnClose: true,
                show: true,
                returnElement: button,
            });
        }).then(function(modal) {
            createdModal = modal;
            if (modal.titlePromise) {
                return modal.titlePromise;
            }
            paintTitle();
            return null;
        }).then(function() {
            if (createdModal.titlePromise) {
                paintTitle();
            }
            Templates.runTemplateJS(modalRendered.js);
            const shell = createdModal.getRoot()[0].querySelector('.local-dixeo-settings-modal');
            if (button.dataset.courseid) {
                shell.dataset.courseid = button.dataset.courseid;
                shell.dataset.canmanagesync = button.dataset.canmanagesync === '1' ? '1' : '0';
            }
            bindClose(createdModal, shell);
            bindAdvanced(shell);
            bindDraft(shell);
            bindSave(shell);
            bindFilter(shell);
            bindStatus(shell);
            bindPreferences(shell);
            if (button.dataset.preferencesonly === '1') {
                return showPreferencesOnly(shell);
            }
            const initial = button.dataset.initial || '';
            const openOnStatus = initial === 'status' || (!!button.dataset.courseid && initial === '');
            const openCategory = !openOnStatus && initial && initial !== 'status' ? initial : '';
            return loadSettings(shell, button.dataset.contextid, openOnStatus, openCategory);
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
