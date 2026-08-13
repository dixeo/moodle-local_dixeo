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
 * Tutor usage report charts and filters.
 *
 * @module     local_dixeo/tutor_usage_report
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'jquery',
    'core/chartjs',
], function($, Chart) {
    const palette = {
        normal: 'rgba(37, 99, 235, 0.8)',
        guide: 'rgba(16, 185, 129, 0.8)',
        quiz: 'rgba(245, 158, 11, 0.8)',
        teach: 'rgba(139, 92, 246, 0.8)',
    };

    /**
     * Wire period prev/next controls to submit the filter form with updated anchor.
     */
    const initPeriodNav = () => {
        const anchorInput = document.getElementById('tutor-usage-report-anchor');
        if (!anchorInput) {
            return;
        }

        document.querySelectorAll('[data-tutor-usage-report-anchor]').forEach((button) => {
            button.addEventListener('click', () => {
                anchorInput.value = button.getAttribute('data-tutor-usage-report-anchor') || '';
            });
        });
    };

    /**
     * Render heatmap table cells from JSON data.
     *
     * @param {Object} heatmap Heatmap payload.
     */
    const renderHeatmap = (heatmap) => {
        const tbody = document.getElementById('tutorUsageHeatmapBody');
        if (!tbody || !heatmap.rows) {
            return;
        }

        tbody.innerHTML = '';
        heatmap.rows.forEach((row) => {
            const tr = document.createElement('tr');
            const label = document.createElement('th');
            label.scope = 'row';
            label.textContent = row.label;
            tr.appendChild(label);

            row.cells.forEach((cell) => {
                const td = document.createElement('td');
                td.textContent = cell.value;
                td.title = String(cell.value);
                td.className = 'dixeo-tutor-usage-report-heatmap-cell';
                const intensity = Number(cell.intensity);
                if (intensity > 0) {
                    const alpha = Math.min(0.15 + (intensity * 0.75), 0.92);
                    td.style.backgroundColor = `rgba(37, 99, 235, ${alpha.toFixed(2)})`;
                }
                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        });
    };

    /**
     * Initialize stacked bar chart and heatmap.
     */
    const initCharts = () => {
        const stackedNode = document.getElementById('tutor-usage-stacked-bar-data');
        const heatmapNode = document.getElementById('tutor-usage-heatmap-data');
        if (!stackedNode && !heatmapNode) {
            return;
        }

        const stacked = stackedNode
            ? JSON.parse(stackedNode.textContent || '{"labels":[],"datasets":[]}')
            : {labels: [], datasets: []};

        const heatmap = heatmapNode
            ? JSON.parse(heatmapNode.textContent || '{"rows":[],"max":0}')
            : {rows: [], max: 0};

        renderHeatmap(heatmap);

        const canvas = document.getElementById('tutorUsageStackedBar');
        if (canvas && stacked.labels && stacked.labels.length) {
            const ctx = canvas.getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: stacked.labels,
                    datasets: (stacked.datasets || []).map((dataset) => ({
                        label: dataset.label,
                        data: dataset.data,
                        backgroundColor: palette[dataset.mode] || palette.normal,
                        stack: 'modes',
                    })),
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {legend: {position: 'bottom'}},
                    scales: {
                        x: {stacked: true},
                        y: {stacked: true, beginAtZero: true},
                    },
                },
            });
        }
    };

    /**
     * Enable Bootstrap tooltips on KPI cards and summary table cells.
     */
    const initTooltips = () => {
        const nodes = document.querySelectorAll(
            '.dixeo-tutor-usage-report-kpi[data-toggle="tooltip"],' +
            '.dixeo-tutor-usage-report-stat[data-toggle="tooltip"]'
        );
        if (!nodes.length || typeof $.fn.tooltip !== 'function') {
            return;
        }
        $(nodes).tooltip({
            container: 'body',
            placement: 'bottom',
            trigger: 'hover focus',
        });
    };

    /**
     * Initialize tutor usage report UI.
     */
    const init = () => {
        initPeriodNav();
        initTooltips();
        initCharts();
    };

    return {
        init: init,
    };
});
