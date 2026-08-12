/**
 * JavaScript module for the Dixeo file sync indicator.
 *
 * Handles dropdown interactions, status polling, and enable/disable actions.
 *
 * @module     local_dixeo/sync_indicator
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/notification', 'core/str', 'core_course/events'], function(Ajax, Notification, Str, CourseEvents) {
    const getStrings = Str.get_strings;

/** @type {number|null} Scheduled poll timer ID. */
let pollTimer = null;

/** @type {Object} Loaded language strings. */
let strings = {};

/** @type {number} Polling delay during active sync (ms). */
const ACTIVE_POLL_DELAY = 5000;

/** @type {number} Background polling delay when idle (ms). */
const BACKGROUND_POLL_DELAY = 30000;

/** @type {number} Course ID. */
let courseId = 0;

/** @type {string} Current status. */
let currentStatus = 'none';

/** @type {boolean} Whether sync is enabled. */
let isEnabled = true;

/** @type {boolean} Whether the user may enable/disable/trigger sync. */
let canManageSync = false;

/**
 * Check whether a value can be rendered as a number.
 *
 * @param {*} value The value to test.
 * @returns {boolean}
 */
const hasNumber = (value) => value !== null && value !== undefined && !Number.isNaN(Number(value));

/**
 * Normalise optional numeric API/webservice fields.
 *
 * @param {*} value The raw value.
 * @param {number} fallback Value to use when raw value is absent or invalid.
 * @returns {number}
 */
const toNumber = (value, fallback = 0) => hasNumber(value) ? Number(value) : fallback;

/**
 * Initialize the sync indicator.
 *
 * @param {number} courseid The course ID.
 * @param {string} status Initial status.
 * @param {boolean} enabled Whether sync is enabled.
 * @param {number} filesTotal Total number of files.
 * @param {number} filesCompleted Files synced so far.
 * @param {boolean} [canmanagesync=false] Whether the user may manage sync actions.
 */
const init = async(courseid, status, enabled, filesTotal, filesCompleted, canmanagesync) => {
    courseId = courseid;
    currentStatus = status;
    isEnabled = enabled;
    canManageSync = canmanagesync === true || canmanagesync === 1 || canmanagesync === '1';

    // Relocate before async ops to avoid race conditions with other modules.
    relocateToTitle();

    // Load language strings.
    await loadStrings();

    updateBadgeCount(filesCompleted, filesTotal);

    setupEventListeners();

    // Begin adaptive poll loop (fast during sync, slow background otherwise).
    schedulePoll();
};

/**
 * Relocate the sync indicator next to the course title.
 *
 * This searches for common course title patterns across different themes
 * and places the indicator inline with the title.
 */
const relocateToTitle = () => {
    const indicator = document.getElementById('dixeo-sync-indicator');
    if (!indicator) {
        return;
    }

    // Try to find the course title heading using common selectors across themes.
    const titleSelectors = [
        // Boost theme.
        '.page-header-headings h1',
        // Classic/older themes.
        '#page-header h1',
        // Some custom themes.
        '.page-context-header h1',
        // Fallback.
        '#region-main h1:first-of-type',
        // Another pattern.
        '.course-content-header h1',
    ];

    let titleElement = null;
    for (const selector of titleSelectors) {
        titleElement = document.querySelector(selector);
        if (titleElement) {
            break;
        }
    }

    if (!titleElement) {
        // No title found, keep indicator in its default position but make it visible.
        indicator.classList.add('dixeo-sync-indicator--visible');
        return;
    }

    // Create a wrapper to hold title + indicator inline.
    const wrapper = document.createElement('span');
    wrapper.className = 'dixeo-sync-title-wrapper';

    // Insert indicator after the title text.
    titleElement.appendChild(wrapper);
    wrapper.appendChild(indicator);

    // Mark as relocated.
    indicator.classList.add('dixeo-sync-indicator--inline');
};

/**
 * Update the file count display.
 *
 * Hidden when synchronized — the count is shown inside the dropdown.
 *
 * @param {number|null} filesCompleted Files synced so far.
 * @param {number|null} filesTotal Total files expected.
 */
const updateBadgeCount = (filesCompleted, filesTotal) => {
    const countEl = document.querySelector('[data-region="sync-badge-count"]');
    if (!countEl) {
        return;
    }

    const total = toNumber(filesTotal);
    const done = toNumber(filesCompleted);

    if (currentStatus === 'synchronized' || total <= 0) {
        countEl.textContent = '0';
        countEl.classList.add('d-none');
        return;
    }

    countEl.textContent = done >= total ? String(total) : `${done} / ${total}`;
    countEl.classList.remove('d-none');
};

/**
 * Position the dropdown below the button using fixed positioning.
 *
 * This escapes any parent overflow clipping issues.
 *
 * @param {HTMLElement} dropdown The dropdown element.
 * @param {HTMLElement} button The trigger button.
 */
const positionDropdown = (dropdown, button) => {
    const buttonRect = button.getBoundingClientRect();
    const dropdownWidth = dropdown.offsetWidth || 260;

    // Position below the button, aligned to the right edge.
    dropdown.style.position = 'fixed';
    dropdown.style.top = `${buttonRect.bottom + 8}px`;
    dropdown.style.left = `${Math.max(8, buttonRect.right - dropdownWidth)}px`;
    dropdown.style.transform = 'none';
};

/**
 * Load required language strings.
 *
 * @returns {Promise<void>}
 */
const loadStrings = async() => {
    const stringKeys = [
        {key: 'filesync_status_none', component: 'local_dixeo'},
        {key: 'filesync_status_syncing', component: 'local_dixeo'},
        {key: 'filesync_status_synchronized', component: 'local_dixeo'},
        {key: 'filesync_status_error', component: 'local_dixeo'},
        {key: 'filesync_status_paused', component: 'local_dixeo'},
        {key: 'filesync_status_pending_deletion', component: 'local_dixeo'},
        {key: 'filesync_status_disabled', component: 'local_dixeo'},
        {key: 'filesync_status_outdated', component: 'local_dixeo'},
        {key: 'filesync_files_count', component: 'local_dixeo'},
        {key: 'filesync_progress', component: 'local_dixeo'},
    ];

    try {
        const loadedStrings = await getStrings(stringKeys);
        stringKeys.forEach((item, index) => {
            strings[item.key] = loadedStrings[index];
        });
    } catch (error) {
        window.console.error('Failed to load strings:', error);
    }
};

/**
 * Set up event listeners for dropdown actions.
 */
const setupEventListeners = () => {
    const container = document.getElementById('dixeo-sync-indicator');
    if (!container) {
        return;
    }

    const dropdown = container.querySelector('.dixeo-sync-dropdown');
    const button = container.querySelector('.dixeo-sync-btn');

    // Handle dropdown action clicks.
    container.addEventListener('click', async(e) => {
        const action = e.target.closest('[data-action]');
        if (!action) {
            return;
        }

        if (!canManageSync) {
            e.preventDefault();
            return;
        }

        e.preventDefault();
        const actionName = action.dataset.action;

        switch (actionName) {
            case 'enable':
                await handleEnable();
                break;
            case 'pause':
                await handlePause();
                break;
            case 'disable':
                await handleDisable();
                break;
            case 'resync':
                await handleResync();
                break;
        }
    });

    // Position dropdown correctly when shown.
    container.addEventListener('shown.bs.dropdown', () => {
        if (dropdown && button) {
            positionDropdown(dropdown, button);
        }
    });

    // Reposition on window resize.
    window.addEventListener('resize', () => {
        if (dropdown && dropdown.classList.contains('show')) {
            positionDropdown(dropdown, button);
        }
    });

    // Cleanup on page unload to prevent memory leaks.
    window.addEventListener('beforeunload', () => {
        cancelPoll();
    });

    // Pause/resume polling on tab visibility changes.
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            cancelPoll();
        } else {
            schedulePoll();
        }
    });

    // Listen for course content changes (module add/update/delete in editor).
    // Debounce to avoid excessive polling on rapid state changes.
    let stateChangeTimer = null;
    document.addEventListener(CourseEvents.stateChanged, () => {
        if (!isEnabled) {
            return;
        }
        if (stateChangeTimer) {
            clearTimeout(stateChangeTimer);
        }
        stateChangeTimer = setTimeout(() => {
            stateChangeTimer = null;
            pollNow();
        }, 2000);
    });
};

/**
 * Handle enable action - enables sync and triggers immediate sync.
 */
const handleEnable = async() => {
    try {
        cancelPoll();

        // First enable sync.
        const enableResult = await callSetEnabled(true, false);
        if (!enableResult.success) {
            return;
        }

        isEnabled = true;
        currentStatus = 'syncing';
        updateIndicatorUI();

        // Then trigger immediate sync.
        const syncResult = await callTriggerSync();
        if (syncResult.success) {
            currentStatus = syncResult.status;
            updateIndicatorUI({
                status: syncResult.status,
                filestotal: syncResult.filestotal,
                filescompleted: syncResult.filescompleted,
                enabled: true,
            });
        }

        schedulePoll();
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Handle pause action (disable but keep files).
 */
const handlePause = async() => {
    // Optimistic update for immediate feedback.
    const previousStatus = currentStatus;
    const previousEnabled = isEnabled;
    isEnabled = false;
    currentStatus = 'paused';
    updateIndicatorUI();
    cancelPoll();

    try {
        const result = await callSetEnabled(false, false);
        if (!result.success) {
            // Revert on failure.
            isEnabled = previousEnabled;
            currentStatus = previousStatus;
            updateIndicatorUI();
            schedulePoll();
        }
        // Calling schedulePoll will no-op here because isEnabled is false.
        schedulePoll();
    } catch (error) {
        // Revert on error.
        isEnabled = previousEnabled;
        currentStatus = previousStatus;
        updateIndicatorUI();
        schedulePoll();
        Notification.exception(error);
    }
};

/**
 * Handle disable action (disable and remove files).
 */
const handleDisable = async() => {
    // Optimistic update: show deleting state while clearing data.
    const previousStatus = currentStatus;
    const previousEnabled = isEnabled;
    currentStatus = 'deleting';
    updateIndicatorUI();
    cancelPoll();

    try {
        const result = await callSetEnabled(false, true);
        if (result.success) {
            isEnabled = false;
            currentStatus = 'none';
            updateIndicatorUI();
            updateBadgeCount(0, 0);
        } else {
            // Revert on failure.
            isEnabled = previousEnabled;
            currentStatus = previousStatus;
            updateIndicatorUI();
            schedulePoll();
        }
        // Calling schedulePoll will no-op here because isEnabled is false.
        schedulePoll();
    } catch (error) {
        // Revert on error.
        isEnabled = previousEnabled;
        currentStatus = previousStatus;
        updateIndicatorUI();
        schedulePoll();
        Notification.exception(error);
    }
};

/**
 * Handle resync action - triggers immediate sync.
 */
const handleResync = async() => {
    try {
        // Show syncing state immediately for responsive UI.
        currentStatus = 'syncing';
        updateIndicatorUI();
        cancelPoll();

        const result = await callTriggerSync();
        if (result.success) {
            isEnabled = true;
            currentStatus = result.status;
            updateIndicatorUI({
                status: result.status,
                filestotal: result.filestotal,
                filescompleted: result.filescompleted,
                enabled: true,
            });
        } else {
            // Handle error.
            currentStatus = 'error';
            updateIndicatorUI();
        }

        schedulePoll();
    } catch (error) {
        Notification.exception(error);
        currentStatus = 'error';
        updateIndicatorUI();
        schedulePoll();
    }
};

/**
 * Call the set_file_sync_enabled web service.
 *
 * @param {boolean} enabled Whether to enable.
 * @param {boolean} removefiles Whether to remove files.
 * @returns {Promise<Object>} The result.
 */
const callSetEnabled = (enabled, removefiles) => {
    return Ajax.call([{
        methodname: 'local_dixeo_set_file_sync_enabled',
        args: {
            courseid: courseId,
            enabled: enabled,
            removefiles: removefiles,
        },
    }])[0];
};

/**
 * Call the trigger_file_sync web service for immediate sync.
 *
 * @returns {Promise<Object>} The result.
 */
const callTriggerSync = () => {
    return Ajax.call([{
        methodname: 'local_dixeo_trigger_file_sync',
        args: {
            courseid: courseId,
        },
    }])[0];
};

/**
 * Schedule the next poll with adaptive delay.
 * Uses fast polling during sync, slow background polling otherwise.
 * Does not schedule if sync is disabled or page is hidden.
 */
const schedulePoll = () => {
    cancelPoll();

    if (!isEnabled || document.hidden) {
        return;
    }

    const delay = currentStatus === 'syncing' ? ACTIVE_POLL_DELAY : BACKGROUND_POLL_DELAY;

    pollTimer = setTimeout(async() => {
        pollTimer = null;
        try {
            const status = await pollStatus();
            updateFromStatus(status);
        } catch (error) {
            window.console.error('Poll failed:', error);
        }
        schedulePoll();
    }, delay);
};

/**
 * Cancel any scheduled poll.
 */
const cancelPoll = () => {
    if (pollTimer) {
        clearTimeout(pollTimer);
        pollTimer = null;
    }
};

/**
 * Poll immediately and reschedule.
 * Used for immediate feedback (e.g., after course editor state change).
 */
const pollNow = async() => {
    cancelPoll();
    try {
        const status = await pollStatus();
        updateFromStatus(status);
    } catch (error) {
        window.console.error('Poll failed:', error);
    }
    schedulePoll();
};

/**
 * Poll the API for current status.
 *
 * @returns {Promise<Object>} The status object.
 */
const pollStatus = () => {
    return Ajax.call([{
        methodname: 'local_dixeo_get_file_sync_status',
        args: {
            courseid: courseId,
        },
    }])[0];
};

/**
 * Update internal state from status response.
 *
 * @param {Object} status The status object.
 */
const updateFromStatus = (status) => {
    currentStatus = status.status;
    isEnabled = status.enabled;

    updateIndicatorUI(status);
};

/**
 * Update progress bar and text from a status payload.
 *
 * @param {HTMLElement} container The indicator container.
 * @param {Object} status The status object.
 */
const updateProgressUI = (container, status) => {
    if (!hasNumber(status.progresspercent)) {
        return;
    }

    const progressPercent = toNumber(status.progresspercent);
    const progressBar = container.querySelector('[data-region="progress-bar"]');
    if (progressBar) {
        progressBar.style.width = `${progressPercent}%`;
        progressBar.setAttribute('aria-valuenow', progressPercent);
    }

    const progressText = container.querySelector('[data-region="progress-text"]');
    if (progressText && hasNumber(status.filescompleted) && hasNumber(status.filestotal)) {
        progressText.textContent = `${toNumber(status.filescompleted)} / ${toNumber(status.filestotal)}`;
    }
};

/**
 * Update the indicator UI based on current state.
 *
 * @param {Object|null} status Optional status object with details.
 */
const updateIndicatorUI = (status = null) => {
    const container = document.getElementById('dixeo-sync-indicator');
    if (!container) {
        return;
    }

    const button = container.querySelector('.dixeo-sync-btn');
    if (!button) {
        return;
    }

    // Update data attributes.
    container.dataset.status = currentStatus;
    container.dataset.enabled = isEnabled;

    // Update button class.
    // 'deleting' uses the same visual style as 'syncing' (blue animated icon).
    let statusClass;
    if (currentStatus === 'deleting') {
        statusClass = 'syncing';
    } else {
        statusClass = isEnabled ? currentStatus : 'disabled';
    }
    // Remove existing status classes and add new one.
    button.className = button.className.replace(/dixeo-sync-btn--\w+/g, '');
    button.classList.add(`dixeo-sync-btn--${statusClass}`);

    if (status) {
        updateProgressUI(container, status);
    }

    // Update status message — always, using a minimal fallback when no detailed status is provided.
    const statusMessage = container.querySelector('[data-region="status-message"]');
    if (statusMessage) {
        statusMessage.textContent = getStatusMessage(status || {status: currentStatus, enabled: isEnabled});
    }

    // Show or hide error message based on current status.
    const errorMessage = container.querySelector('[data-region="error-message"]');
    if (errorMessage) {
        if (status && status.errormessage && currentStatus === 'error') {
            errorMessage.textContent = status.errormessage;
            errorMessage.classList.remove('d-none');
        } else {
            errorMessage.classList.add('d-none');
        }
    }

    // Show or hide last sync based on whether data exists.
    const lastSync = container.querySelector('[data-region="last-sync"]');
    if (lastSync) {
        if (currentStatus === 'none' && !isEnabled) {
            lastSync.classList.add('d-none');
        } else {
            lastSync.classList.remove('d-none');
        }
    }

    // Update badge count.
    if (status) {
        updateBadgeCount(status.filescompleted, status.filestotal);
    }
};

/**
 * Get the status message for display.
 *
 * @param {Object} status The status object.
 * @returns {string} The status message.
 */
const getStatusMessage = (status) => {
    if (status.status === 'pending_deletion') {
        return strings.filesync_status_pending_deletion || 'Remote file deletion pending';
    }

    if (!status.enabled) {
        return strings.filesync_status_disabled || 'Sync disabled';
    }

    if (status.status === 'synchronized' && hasNumber(status.filestotal)) {
        const template = strings.filesync_files_count || '{$a} files';
        return template.replace('{$a}', toNumber(status.filestotal));
    }

    if (status.status === 'syncing') {
        const template = strings.filesync_progress || '{$a}% complete';
        return template.replace('{$a}', toNumber(status.progresspercent));
    }

    const stringKey = `filesync_status_${status.status}`;
    return strings[stringKey] || status.status;
};

return {init: init};
});
