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
 * Keeps the Dixeo terms dialog open, enables Continue only after both boxes are ticked,
 * and confirms revoke from the review modal.
 *
 * Escape and the backdrop do not dismiss the dialog.
 *
 * @module     local_dixeo/consent_gate
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/notification'], function(Notification) {
    /**
     * Elements inside the dialog that can be tabbed to.
     *
     * @param {HTMLElement} dialog
     * @returns {HTMLElement[]}
     */
    const focusable = function(dialog) {
        return Array.prototype.filter.call(
            dialog.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])'),
            function(node) {
                return node.offsetParent !== null || node === document.activeElement;
            }
        );
    };

    /**
     * Keep Tab inside the dialog.
     *
     * @param {KeyboardEvent} event
     * @param {HTMLElement} dialog
     */
    const trapTab = function(event, dialog) {
        const nodes = focusable(dialog);
        if (!nodes.length) {
            event.preventDefault();
            dialog.focus();
            return;
        }
        const first = nodes[0];
        const last = nodes[nodes.length - 1];
        const active = document.activeElement;
        if (!dialog.contains(active)) {
            event.preventDefault();
            (event.shiftKey ? last : first).focus();
            return;
        }
        if (event.shiftKey && active === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && active === last) {
            event.preventDefault();
            first.focus();
        }
    };

    /**
     * Mark every element outside the lock so it cannot be used.
     *
     * @param {HTMLElement} lock
     */
    const lockSiblings = function(lock) {
        const hide = function(root) {
            Array.prototype.forEach.call(root.children, function(child) {
                if (child === lock || child.contains(lock)) {
                    return;
                }
                child.setAttribute('inert', '');
                child.setAttribute('aria-hidden', 'true');
            });
        };
        hide(document.body);
        if (lock.parentElement) {
            hide(lock.parentElement);
        }
    };

    return {
        /**
         * Bind the lock if the gate markup is on the page.
         */
        init: function() {
            const lock = document.querySelector('[data-region="local-dixeo-consent-lock"]');
            if (!lock) {
                return;
            }
            const dialog = lock.querySelector('[role="dialog"]');
            const terms = document.getElementById('local-dixeo-consent-terms');
            const processing = document.getElementById('local-dixeo-consent-processing');
            const button = document.getElementById('local-dixeo-consent-continue');
            const form = document.getElementById('local-dixeo-consent-form');
            if (!dialog || !terms || !processing || !button || !form) {
                return;
            }

            const sync = function() {
                button.disabled = !(terms.checked && processing.checked);
            };
            terms.addEventListener('change', sync);
            processing.addEventListener('change', sync);
            form.addEventListener('submit', function(event) {
                if (!terms.checked || !processing.checked) {
                    event.preventDefault();
                }
            });
            sync();
            document.body.classList.add('local-dixeo-consent-locked');
            lockSiblings(lock);

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    event.stopPropagation();
                    return;
                }
                if (event.key === 'Tab') {
                    trapTab(event, dialog);
                }
            }, true);

            dialog.focus();
        },

        /**
         * Enable Save only while both review checkboxes are ticked.
         *
         * @param {HTMLElement|Document} root Modal or page that contains the review form.
         */
        bindSave: function(root) {
            const scope = root || document;
            const form = scope.querySelector('#local-dixeo-consent-save-form');
            if (!form || form.dataset.bound === '1') {
                return;
            }
            const terms = form.querySelector('[name="terms"]');
            const processing = form.querySelector('[name="processing"]');
            const button = form.querySelector('#local-dixeo-consent-save');
            if (!terms || !processing || !button) {
                return;
            }
            form.dataset.bound = '1';
            const sync = function() {
                button.disabled = !(terms.checked && processing.checked);
            };
            terms.addEventListener('change', sync);
            processing.addEventListener('change', sync);
            form.addEventListener('submit', function(event) {
                if (!terms.checked || !processing.checked) {
                    event.preventDefault();
                }
            });
            sync();
        },

        /**
         * Confirm revoke, then post the withdrawal. Close leaves a standalone page.
         *
         * @param {HTMLElement|Document} root Modal or page that contains the review.
         */
        bindReview: function(root) {
            const scope = root || document;
            const revoke = scope.querySelector('[data-action="revoke-consent"]');
            if (revoke && revoke.dataset.bound !== '1') {
                revoke.dataset.bound = '1';
                revoke.addEventListener('click', function(event) {
                    event.preventDefault();
                    const form = scope.querySelector('#local-dixeo-consent-revoke');
                    Notification.saveCancelPromise(
                        revoke.dataset.confirmTitle || '',
                        revoke.dataset.confirmBody || '',
                        revoke.dataset.confirmYes || revoke.textContent.trim(),
                        {triggerElement: revoke}
                    ).then(function() {
                        if (!form) {
                            return null;
                        }
                        if (typeof form.requestSubmit === 'function') {
                            form.requestSubmit();
                        } else {
                            form.submit();
                        }
                        return null;
                    }).catch(function() {
                        return null;
                    });
                });
            }
            const close = scope.querySelector('[data-action="hide"][data-close-url]');
            if (close && close.dataset.bound !== '1') {
                close.dataset.bound = '1';
                close.addEventListener('click', function() {
                    if (close.closest('.modal')) {
                        return;
                    }
                    window.location.href = close.dataset.closeUrl;
                });
            }
        },
    };
});
