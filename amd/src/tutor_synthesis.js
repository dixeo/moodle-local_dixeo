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
 * Course tutor synthesis block on the usage report, for the period the report shows.
 *
 * @module     local_dixeo/tutor_synthesis
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'core/templates',
    'local_dixeo/network',
], function(Templates, Network) {
    const POLL_MS = 2000;
    const MAX_POLLS = 90;

    const connection = Network.forComponent({
        component: 'local_dixeo',
        keys: ['error_connection_synthesis'],
    });

    /**
     * Render the block body from a web service view model and update the button.
     *
     * The body comes from the same template as the page, so both renders match.
     *
     * @param {HTMLElement} root Block root.
     * @param {Object} view Synthesis view model.
     * @return {Promise}
     */
    const render = (root, view) => {
        const body = root.querySelector('[data-region="synthesis-body"]');
        const button = root.querySelector('[data-action="tutor-synthesis"]');
        button.textContent = view.buttonlabel;
        button.disabled = view.pending;
        return Templates.renderForPromise('local_dixeo/tutor_synthesis', view).then(({html, js}) => {
            Templates.replaceNodeContents(body, html, js);
            return view;
        });
    };

    /**
     * Call one synthesis web service.
     *
     * @param {string} methodname Web service name.
     * @param {Object} args Arguments.
     * @param {number} timeout Milliseconds.
     * @return {Promise<Object>}
     */
    const call = (methodname, args, timeout) => {
        return Network.call({
            methodname: methodname,
            args: args,
        }, {timeout: timeout});
    };

    /**
     * Wire the course synthesis block.
     *
     * A request whose answer is lost is never sent again: the block follows the server state
     * instead, which reports a pending job when the request arrived.
     */
    const init = () => {
        const root = document.getElementById('dixeo-tutor-synthesis');
        if (!root) {
            return;
        }

        const args = {
            courseid: Number(root.dataset.courseid),
            timestart: Number(root.dataset.timestart),
            timeend: Number(root.dataset.timeend),
        };
        const button = root.querySelector('[data-action="tutor-synthesis"]');
        const retryWindow = Network.createRetryWindow();
        let polls = 0;
        // Set while following the server state after a request whose answer was lost.
        let stallWatch = null;
        // Whether the server reported the lost request as a pending job.
        let lostSeenPending = false;

        /**
         * Stop following a lost request and tell the user it may have started anyway.
         *
         * @param {Object|null} view Last view read from the server.
         * @return {Promise}
         */
        const giveUpLostRequest = (view) => {
            stallWatch = null;
            const rendered = view ? render(root, view) : Promise.resolve();
            button.disabled = false;
            return rendered.then(() => connection.alertString('error_connection_synthesis'));
        };

        const poll = () => {
            polls = polls + 1;
            window.setTimeout(() => {
                call('local_dixeo_get_tutor_synthesis', args, Network.TIMEOUT_STATUS_MS).then((view) => {
                    retryWindow.succeeded();
                    if (stallWatch) {
                        stallWatch.observe(String(view.pending), view.pending);
                        lostSeenPending = lostSeenPending || view.pending;
                        if (stallWatch.stalled() || polls >= MAX_POLLS) {
                            return giveUpLostRequest(view);
                        }
                        if (!view.pending && !lostSeenPending) {
                            // The lost request has not shown up on the server yet.
                            poll();
                            return view;
                        }
                    }
                    if (!view.pending) {
                        stallWatch = null;
                        return render(root, view);
                    }
                    // The pending body is already shown: rendering it again would repeat the status.
                    if (polls < MAX_POLLS) {
                        poll();
                    }
                    return view;
                }).catch((error) => {
                    if (retryWindow.shouldRetry(error) && polls < MAX_POLLS) {
                        poll();
                        return null;
                    }
                    if (stallWatch) {
                        return giveUpLostRequest(null);
                    }
                    button.disabled = false;
                    return connection.exception(error);
                });
            }, POLL_MS);
        };

        button.addEventListener('click', () => {
            button.disabled = true;
            return call('local_dixeo_request_tutor_synthesis', args, Network.TIMEOUT_ACTION_MS).then((view) => {
                if (view.pending) {
                    polls = 0;
                    poll();
                }
                return render(root, view);
            }).catch((error) => {
                if (Network.isConnectionError(error)) {
                    stallWatch = Network.createStallWatch();
                    lostSeenPending = false;
                    polls = 0;
                    retryWindow.succeeded();
                    poll();
                    return null;
                }
                button.disabled = false;
                return connection.exception(error);
            });
        });
        if (root.dataset.pending === '1') {
            poll();
        }
    };

    return {
        init: init,
    };
});
