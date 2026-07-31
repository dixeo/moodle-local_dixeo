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
 * AJAX transport for credit report course filter autocomplete.
 *
 * @module     local_dixeo/form_credit_report_course_selector
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax'], function($, Ajax) {

    /**
     * Read period bounds from the select element data attributes.
     *
     * @param {string} selector CSS selector for the select element.
     * @return {{timestart: number, timeend: number}}
     */
    const getPeriodBounds = (selector) => {
        const element = document.querySelector(selector);
        return {
            timestart: parseInt(element?.dataset.timestart || '0', 10),
            timeend: parseInt(element?.dataset.timeend || '0', 10),
        };
    };

    return {
        /**
         * Search courses with credit usage in the active period.
         *
         * @param {string} selector CSS selector for the select element.
         * @param {string} query Search query.
         * @param {Function} success Success callback.
         * @param {Function} failure Failure callback.
         */
        transport: function(selector, query, success, failure) {
            const bounds = getPeriodBounds(selector);
            const request = {
                methodname: 'local_dixeo_search_credit_report_courses',
                args: {
                    query: query,
                    timestart: bounds.timestart,
                    timeend: bounds.timeend,
                },
            };

            const promises = Ajax.call([request]);
            $.when(promises[0]).done(function(response) {
                success(response.list || []);
            }).fail(failure);
        },

        /**
         * Map webservice results to autocomplete options.
         *
         * @param {string} selector CSS selector for the select element.
         * @param {Array} results Webservice results.
         * @return {Array}
         */
        processResults: function(selector, results) {
            if (!Array.isArray(results)) {
                return results;
            }
            return results.map((item) => ({
                value: item.id,
                label: item.label,
            }));
        },
    };
});
