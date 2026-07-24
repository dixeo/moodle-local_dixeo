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
 * Credit report charts.
 *
 * @module     local_dixeo/credit_report
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Chart from 'core/chartjs';
import AutoComplete from 'core/form-autocomplete';
import Notification from 'core/notification';
import {getStrings} from 'core/str';

const FILTER_SELECTORS = [
    '#component',
    '#jobtype',
    '#moduletype',
    '#userid',
    '#courseid',
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
 * Enhance filter dropdowns with Moodle autocomplete widgets.
 */
const initFilters = async() => {
    const selects = FILTER_SELECTORS
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
 * Initialize credit report charts.
 */
export const init = () => {
    initFilters();

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
