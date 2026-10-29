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
    'core/ajax',
    'core/templates',
    'core/notification',
], function(Ajax, Templates, Notification) {
    const POLL_MS = 2000;
    const MAX_POLLS = 90;

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
     * @return {Promise<Object>}
     */
    const call = (methodname, args) => {
        return Ajax.call([{
            methodname: methodname,
            args: args,
        }])[0];
    };

    /**
     * Wire the course synthesis block.
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
        let polls = 0;

        const poll = () => {
            polls = polls + 1;
            window.setTimeout(() => {
                call('local_dixeo_get_tutor_synthesis', args).then((view) => {
                    if (!view.pending) {
                        return render(root, view);
                    }
                    // The pending body is already shown: rendering it again would repeat the status.
                    if (polls < MAX_POLLS) {
                        poll();
                    }
                    return view;
                }).catch(Notification.exception);
            }, POLL_MS);
        };

        const button = root.querySelector('[data-action="tutor-synthesis"]');
        button.addEventListener('click', () => {
            button.disabled = true;
            return call('local_dixeo_request_tutor_synthesis', args).then((view) => {
                if (view.pending) {
                    polls = 0;
                    poll();
                }
                return render(root, view);
            }).catch((error) => {
                button.disabled = false;
                Notification.exception(error);
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
