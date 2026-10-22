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
 * Course tutor synthesis block on the usage report.
 *
 * @module     local_dixeo/tutor_synthesis
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'core/ajax',
    'core/str',
    'core/notification',
], function(Ajax, Str, Notification) {
    const POLL_MS = 2000;
    const MAX_POLLS = 90;

    /**
     * Load the strings the block renders after a click.
     *
     * @return {Promise<Object>}
     */
    const loadStrings = () => {
        return Str.get_strings([
            {key: 'tutor_usage_report_synthesis_button', component: 'local_dixeo'},
            {key: 'tutor_usage_report_synthesis_update', component: 'local_dixeo'},
            {key: 'tutor_usage_report_synthesis_generating', component: 'local_dixeo'},
            {key: 'tutor_usage_report_synthesis_unchanged', component: 'local_dixeo'},
            {key: 'tutor_usage_report_synthesis_failed', component: 'local_dixeo'},
            {key: 'tutor_usage_report_synthesis_empty', component: 'local_dixeo'},
            {key: 'tutor_usage_report_synthesis_truncated', component: 'local_dixeo'},
        ]).then((values) => {
            return {
                button: values[0],
                update: values[1],
                generating: values[2],
                unchanged: values[3],
                failed: values[4],
                empty: values[5],
                truncated: values[6],
            };
        });
    };

    /**
     * Button label for the current result.
     *
     * @param {Object} result Synthesis payload.
     * @param {Object} strings Localised labels.
     * @return {string}
     */
    const buttonLabel = (result, strings) => {
        if (result.summary || result.empty || result.state === 'ready') {
            return strings.update;
        }
        return strings.button;
    };

    /**
     * Append a paragraph.
     *
     * @param {HTMLElement} parent Parent node.
     * @param {string} text Text content.
     * @param {string} className Optional class.
     */
    const addParagraph = (parent, text, className) => {
        const paragraph = document.createElement('p');
        if (className) {
            paragraph.className = className;
        }
        paragraph.textContent = text;
        parent.appendChild(paragraph);
    };

    /**
     * Replace the block body and status from a service result.
     *
     * @param {HTMLElement} root Block root.
     * @param {Object} result Synthesis payload.
     * @param {Object} strings Localised labels.
     */
    const render = (root, result, strings) => {
        const status = root.querySelector('[data-region="synthesis-status"]');
        const body = root.querySelector('[data-region="synthesis-body"]');
        const button = root.querySelector('[data-action="tutor-synthesis"]');
        status.textContent = '';
        body.textContent = '';

        if (result.state === 'pending') {
            const line = document.createElement('p');
            line.className = 'dixeo-tutor-synthesis-status text-muted mb-2';
            const spinner = document.createElement('span');
            spinner.className = 'spinner-border spinner-border-sm';
            spinner.setAttribute('role', 'status');
            spinner.setAttribute('aria-hidden', 'true');
            line.appendChild(spinner);
            line.appendChild(document.createTextNode(strings.generating));
            status.appendChild(line);
        }

        if (result.unchanged) {
            addParagraph(body, strings.unchanged, 'text-muted');
        }
        if (result.state === 'failed') {
            addParagraph(body, strings.failed, 'text-danger');
        }
        if (result.empty) {
            addParagraph(body, strings.empty, '');
        }
        if (result.summary) {
            addParagraph(body, result.summary, 'dixeo-tutor-synthesis-summary');
        }
        if (result.truncated) {
            addParagraph(body, strings.truncated, 'text-muted small');
        }

        const icon = root.querySelector('.dixeo-tutor-usage-report-info');
        if (icon && result.window) {
            icon.dataset.info = result.window;
        }

        button.textContent = buttonLabel(result, strings);
        button.disabled = result.state === 'pending';
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
        };
        let polls = 0;
        let strings = null;

        const poll = () => {
            polls = polls + 1;
            window.setTimeout(() => {
                call('local_dixeo_get_tutor_synthesis', args).then((result) => {
                    render(root, result, strings);
                    if (result.state === 'pending' && polls < MAX_POLLS) {
                        poll();
                    }
                    return result;
                }).catch(Notification.exception);
            }, POLL_MS);
        };

        const button = root.querySelector('[data-action="tutor-synthesis"]');
        loadStrings().then((loaded) => {
            strings = loaded;
            if (root.dataset.pending === '1') {
                poll();
            }
            button.addEventListener('click', () => {
                button.disabled = true;
                call('local_dixeo_request_tutor_synthesis', args).then((result) => {
                    render(root, result, strings);
                    if (result.state === 'pending') {
                        polls = 0;
                        poll();
                    }
                    return result;
                }).catch((error) => {
                    button.disabled = false;
                    Notification.exception(error);
                });
            });
            return loaded;
        }).catch(Notification.exception);
    };

    return {
        init: init,
    };
});
