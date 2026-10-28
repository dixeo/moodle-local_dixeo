/**
 * JavaScript module for the Dixeo file sync pill.
 *
 * Polls status and updates the pill. The button opens the settings modal.
 *
 * @module     local_dixeo/sync_indicator
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/str', 'core_course/events'], function(Ajax, Str, CourseEvents) {

/** @type {number|null} Scheduled poll timer ID. */
let pollTimer = null;

/** @type {number} Polling delay during active sync (ms). */
const ACTIVE_POLL_DELAY = 5000;

/** @type {number} Background polling delay when idle (ms). */
const BACKGROUND_POLL_DELAY = 10000;

/** @type {number} Course ID. */
let courseId = 0;

/** @type {string} Current status. */
let currentStatus = 'none';

/** @type {boolean} Whether sync is enabled. */
let isEnabled = true;

/** @type {number} Files the last sync left out. */
let skippedCount = 0;

/** @type {Object|null} Tooltip strings, loaded once. */
let labels = null;

/** @type {string[]} Button modifiers the poll may replace. */
const STATUS_MODIFIERS = [
    'none',
    'syncing',
    'synchronized',
    'partial',
    'error',
    'outdated',
    'paused',
    'disabled',
];

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
 * @param {string} [placement] coursetitle moves the pill to the heading. footer moves it into the page footer.
 * @param {number} [filesSkipped] Files the last sync left out.
 */
const init = (courseid, status, enabled, filesTotal, filesCompleted, placement, filesSkipped) => {
    courseId = courseid;
    currentStatus = status;
    isEnabled = enabled;
    skippedCount = toNumber(filesSkipped);

    // Title sits beside the heading. Footer is moved into the dark footer bar.
    if (placement === 'footer') {
        relocateToFooter();
    } else {
        relocateToTitle();
    }

    updateBadgeCount(filesCompleted, filesTotal);

    setupEventListeners();

    // Strings let the poll rewrite the tooltip. The first read is soon, then
    // the loop stays fast while a sync is pending or running.
    loadLabels().then(() => {
        updateIndicatorUI();
        return null;
    }).catch(() => null);
    schedulePoll(2000);
};

/**
 * Load the pill tooltip strings.
 *
 * @returns {Promise<Object>}
 */
const loadLabels = () => {
    if (labels) {
        return Promise.resolve(labels);
    }
    const keys = [
        'filesync_title',
        'filesync_status_none',
        'filesync_status_syncing',
        'filesync_status_synchronized',
        'filesync_status_partial',
        'filesync_status_error',
        'filesync_status_paused',
        'filesync_status_disabled',
        'filesync_status_outdated',
    ];
    const requests = keys.map((key) => ({key, component: 'local_dixeo'}));
    return Str.get_strings(requests).then((loaded) => {
        labels = {};
        keys.forEach((key, index) => {
            labels[key] = loaded[index];
        });
        return labels;
    });
};

/**
 * Delay until the next read. Pending and running syncs are polled quickly.
 *
 * @returns {number}
 */
const pollDelay = () => {
    if (currentStatus === 'syncing' || currentStatus === 'outdated') {
        return ACTIVE_POLL_DELAY;
    }
    return BACKGROUND_POLL_DELAY;
};

/**
 * Move a footer pill into the dark footer bar.
 *
 * The footer hook prints the pill inside the main region. The visible place is
 * .footer-dark-inner in #page-footer.
 */
const relocateToFooter = () => {
    const indicator = document.getElementById('dixeo-sync-indicator');
    if (!indicator) {
        return;
    }
    const slot = document.querySelector('#page-footer .footer-dark-inner');
    if (!slot) {
        indicator.classList.add('dixeo-sync-indicator--visible');
        return;
    }
    slot.appendChild(indicator);
    indicator.classList.add('dixeo-sync-indicator--placed');
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
 * Hidden when synchronized.
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
 * Keep the pill polling while the page is open. The button itself opens the settings modal.
 */
const setupEventListeners = () => {
    const container = document.getElementById('dixeo-sync-indicator');
    if (!container) {
        return;
    }

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
 * Schedule the next poll with adaptive delay.
 * Uses fast polling while a sync is pending or running, slow background polling otherwise.
 * Does not schedule if sync is disabled or page is hidden.
 *
 * @param {number} [delay] Override the wait, in milliseconds.
 */
const schedulePoll = (delay) => {
    cancelPoll();

    if (!isEnabled || document.hidden) {
        return;
    }

    const wait = typeof delay === 'number' ? delay : pollDelay();

    pollTimer = setTimeout(async() => {
        pollTimer = null;
        try {
            const status = await pollStatus();
            updateFromStatus(status);
        } catch (error) {
            window.console.error('Poll failed:', error);
        }
        schedulePoll();
    }, wait);
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
    isEnabled = status.enabled === true || status.enabled === 1 || status.enabled === '1';
    skippedCount = toNumber(status.filesskipped);

    updateIndicatorUI(status);
    document.dispatchEvent(new CustomEvent('local_dixeo_syncstatus_updated', {
        detail: Object.assign({courseid: courseId}, status),
    }));
};

/**
 * Update the pill dot and count. The status text lives in the settings modal.
 *
 * @param {Object|null} status Optional status object with file counts.
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

    container.dataset.status = currentStatus;
    container.dataset.enabled = isEnabled ? '1' : '0';

    // Synchronized with files left out shows a warning, not a clean sync.
    const partial = isEnabled && currentStatus === 'synchronized' && skippedCount > 0;
    let statusClass = currentStatus;
    if (!isEnabled) {
        statusClass = 'disabled';
    } else if (partial) {
        statusClass = 'partial';
    }
    STATUS_MODIFIERS.forEach((name) => {
        button.classList.remove(`dixeo-sync-btn--${name}`);
    });
    button.classList.add(`dixeo-sync-btn--${statusClass}`);
    if (labels) {
        let tip = labels.filesync_status_none;
        const key = `filesync_status_${currentStatus}`;
        if (!isEnabled) {
            tip = labels.filesync_status_disabled;
        } else if (partial) {
            tip = labels.filesync_status_partial;
        } else if (labels[key]) {
            tip = labels[key];
        }
        const text = `${labels.filesync_title}: ${tip}`;
        button.title = text;
        button.setAttribute('aria-label', text);
    }

    if (status) {
        updateBadgeCount(status.filescompleted, status.filestotal);
    }
};

return {init: init};
});
