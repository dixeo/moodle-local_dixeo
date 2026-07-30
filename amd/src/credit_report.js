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
 * Credit report charts and filters.
 *
 * @module     local_dixeo/credit_report
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'core/chartjs',
    'core/form-autocomplete',
    'core/notification',
    'core/str',
], function(Chart, AutoComplete, Notification, Str) {
    const getStrings = Str.get_strings;

    const ENUM_FILTER_SELECTORS = [
        '#component',
        '#jobtype',
        '#moduletype',
    ];

    const ENTITY_FILTER_CONFIG = [
        {
            selector: '#userid',
            ajax: 'local_dixeo/form_credit_report_user_selector',
            placeholderKey: 'credit_report_filter_user_placeholder',
        },
        {
            selector: '#courseid',
            ajax: 'local_dixeo/form_credit_report_course_selector',
            placeholderKey: 'credit_report_filter_course_placeholder',
        },
    ];

    const palette = [
        'rgba(37, 99, 235, 0.8)',
        'rgba(16, 185, 129, 0.8)',
        'rgba(245, 158, 11, 0.8)',
        'rgba(239, 68, 68, 0.8)',
        'rgba(139, 92, 246, 0.8)',
        'rgba(20, 184, 166, 0.8)',
    ];

    /**
     * Enhance bounded enum filter dropdowns.
     */
    const initEnumFilters = async() => {
        const selects = ENUM_FILTER_SELECTORS
            .map((selector) => document.querySelector(selector))
            .filter((node) => node !== null);

        if (selects.length === 0) {
            return;
        }

        const [placeholder] = await getStrings([
            {key: 'credit_report_filter_placeholder', component: 'local_dixeo'},
        ]).catch(() => getStrings([
            {key: 'filter', component: 'moodle'},
        ]));

        await Promise.all(selects.map((select) => AutoComplete.enhance(
            '#' + select.id,
            false,
            '',
            placeholder,
            false,
            true,
            '',
            true,
        ))).catch(Notification.exception);
    };

    /**
     * Enhance user and course filters with period-scoped AJAX search.
     */
    const initEntityFilters = async() => {
        const configs = ENTITY_FILTER_CONFIG.filter((config) => document.querySelector(config.selector) !== null);
        if (configs.length === 0) {
            return;
        }

        const placeholders = await getStrings(configs.map((config) => ({
            key: config.placeholderKey,
            component: 'local_dixeo',
        }))).catch(() => configs.map(() => ''));

        await Promise.all(configs.map((config, index) => AutoComplete.enhance(
            config.selector,
            false,
            config.ajax,
            placeholders[index],
            false,
            true,
            '',
            true,
        ))).catch(Notification.exception);
    };

    /**
     * Wire period prev/next controls to submit the filter form with updated anchor.
     */
    const initPeriodNav = () => {
        const anchorInput = document.getElementById('credit-report-anchor');
        if (!anchorInput) {
            return;
        }

        document.querySelectorAll('[data-credit-report-anchor]').forEach((button) => {
            button.addEventListener('click', () => {
                anchorInput.value = button.getAttribute('data-credit-report-anchor') || '';
            });
        });
    };

    /**
     * Initialize credit report charts.
     */
    const init = () => {
        initEnumFilters();
        initEntityFilters();
        initPeriodNav();

        const histogramNode = document.getElementById('credit-report-histogram-data');
        const breakdownNode = document.getElementById('credit-report-breakdown-data');
        if (!histogramNode && !breakdownNode) {
            return;
        }

        const histogram = histogramNode
            ? JSON.parse(histogramNode.textContent || '{"labels":[],"values":[]}')
            : {labels: [], values: []};
        const breakdown = breakdownNode
            ? JSON.parse(breakdownNode.textContent || '{"labels":[],"values":[]}')
            : {labels: [], values: []};

        const histogramCanvas = document.getElementById('creditUsageHistogram');
        if (histogramCanvas && histogram.values.length > 0) {
            new Chart(histogramCanvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: histogram.labels,
                    datasets: [{
                        label: 'Credits',
                        data: histogram.values,
                        backgroundColor: 'rgba(37, 99, 235, 0.7)',
                        borderRadius: 4,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {legend: {display: false}},
                    scales: {y: {beginAtZero: true}},
                },
            });
        }

        const breakdownCanvas = document.getElementById('creditUsageBreakdown');
        if (breakdownCanvas && breakdown.values.length > 0) {
            new Chart(breakdownCanvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: breakdown.labels,
                    datasets: [{
                        data: breakdown.values,
                        backgroundColor: breakdown.values.map((value, index) => palette[index % palette.length]),
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {legend: {position: 'bottom'}},
                },
            });
        }
    };

    return {
        init: init,
    };
});
