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

define(['core/ajax', 'core_course/events'], function(Ajax, CourseEvents) {

/** @type {number|null} Scheduled poll timer ID. */
let pollTimer = null;

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
 */
const init = (courseid, status, enabled, filesTotal, filesCompleted, placement) => {
    courseId = courseid;
    currentStatus = status;
    isEnabled = enabled;

    // Title sits beside the heading. Footer is moved into the dark footer bar.
    if (placement === 'footer') {
        relocateToFooter();
    } else {
        relocateToTitle();
    }

    updateBadgeCount(filesCompleted, filesTotal);

    setupEventListeners();

    // Begin adaptive poll loop (fast during sync, slow background otherwise).
    schedulePoll();
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
    container.dataset.enabled = isEnabled;

    // 'deleting' uses the same visual style as 'syncing' (blue animated icon).
    let statusClass;
    if (currentStatus === 'deleting') {
        statusClass = 'syncing';
    } else {
        statusClass = isEnabled ? currentStatus : 'disabled';
    }
    button.className = button.className.replace(/dixeo-sync-btn--\w+/g, '');
    button.classList.add(`dixeo-sync-btn--${statusClass}`);

    if (status) {
        updateBadgeCount(status.filescompleted, status.filestotal);
    }
};

return {init: init};
});
