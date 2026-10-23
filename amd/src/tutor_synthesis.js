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
            {key: 'tutor_usage_report_synthesis_intro', component: 'local_dixeo'},
            {key: 'tutor_usage_report_synthesis_unchanged', component: 'local_dixeo'},
            {key: 'error:tutor_synthesis_unavailable', component: 'local_dixeo'},
            {key: 'tutor_usage_report_synthesis_failed', component: 'local_dixeo'},
            {key: 'tutor_usage_report_synthesis_empty', component: 'local_dixeo'},
            {key: 'tutor_usage_report_synthesis_truncated', component: 'local_dixeo'},
        ]).then((values) => {
            return {
                button: values[0],
                update: values[1],
                generating: values[2],
                intro: values[3],
                unchanged: values[4],
                unavailable: values[5],
                failed: values[6],
                empty: values[7],
                truncated: values[8],
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
     * Replace the block body from a service result.
     *
     * result.summary is HTML already cleaned by the service.
     *
     * @param {HTMLElement} root Block root.
     * @param {Object} result Synthesis payload.
     * @param {Object} strings Localised labels.
     */
    const render = (root, result, strings) => {
        const body = root.querySelector('[data-region="synthesis-body"]');
        const button = root.querySelector('[data-action="tutor-synthesis"]');
        const hasContent = Boolean(result.summary);
        const showWindow = hasContent && Boolean(result.window);
        body.textContent = '';

        if (result.state === 'pending' || result.unchanged || result.unavailable || result.state === 'failed' || showWindow) {
            const row = document.createElement('div');
            row.className = 'dixeo-tutor-synthesis-meta';
            if (result.state === 'pending') {
                const line = document.createElement('p');
                line.className = 'dixeo-tutor-synthesis-note dixeo-tutor-synthesis-status text-muted mb-0';
                const spinner = document.createElement('span');
                spinner.className = 'spinner-border spinner-border-sm';
                spinner.setAttribute('role', 'status');
                spinner.setAttribute('aria-hidden', 'true');
                line.appendChild(spinner);
                line.appendChild(document.createTextNode(strings.generating));
                row.appendChild(line);
            } else if (result.unchanged) {
                addParagraph(row, strings.unchanged, 'dixeo-tutor-synthesis-note text-muted mb-0');
            } else if (result.unavailable) {
                addParagraph(row, strings.unavailable, 'dixeo-tutor-synthesis-note text-muted mb-0');
            } else if (result.state === 'failed') {
                addParagraph(row, strings.failed, 'dixeo-tutor-synthesis-note text-danger mb-0');
            }
            if (showWindow) {
                addParagraph(row, result.window, 'dixeo-tutor-synthesis-window text-muted mb-0');
            }
            body.appendChild(row);
        }
        if (result.state === 'empty' && !result.unavailable) {
            addParagraph(body, strings.intro, 'text-muted mb-0');
        }
        if (result.empty) {
            addParagraph(body, strings.empty, 'mb-0');
        }
        if (hasContent) {
            const summary = document.createElement('div');
            summary.className = 'dixeo-tutor-synthesis-summary';
            summary.innerHTML = result.summary;
            body.appendChild(summary);
        }
        if (result.truncated) {
            addParagraph(body, strings.truncated, 'text-muted small mb-0');
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
        const requestSynthesis = () => {
            button.disabled = true;
            return call('local_dixeo_request_tutor_synthesis', args).then((result) => {
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
        };
        loadStrings().then((loaded) => {
            strings = loaded;
            if (root.dataset.pending === '1') {
                poll();
            }
            button.addEventListener('click', requestSynthesis);
            return loaded;
        }).catch(Notification.exception);
    };

    return {
        init: init,
    };
});
