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
    // Mode colors mirror the tutor block mode variables in blocks/dixeo_tutor/styles.css.
    const palette = {
        normal: 'rgba(16, 142, 238, 0.8)',
        guide: 'rgba(124, 92, 191, 0.8)',
        quiz: 'rgba(220, 120, 0, 0.8)',
        teach: 'rgba(47, 158, 68, 0.8)',
        peer: 'rgba(37, 99, 235, 0.65)',
        current: 'rgba(220, 38, 38, 0.9)',
        mean: 'rgba(100, 116, 139, 0.85)',
    };

    /**
     * Reload the report as soon as a custom range date changes, so no apply button is needed.
     */
    const initDateRange = () => {
        const form = document.querySelector('[data-tutor-usage-report-dates]');
        if (!form) {
            return;
        }

        form.querySelectorAll('input[type="date"]').forEach((input) => {
            input.addEventListener('change', () => form.submit());
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
                    td.style.backgroundColor = `rgba(22, 163, 74, ${alpha.toFixed(2)})`;
                }
                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        });
    };

    /**
     * Draw mean usage (vertical) and mean grade (horizontal) reference lines.
     *
     * @param {Object} chart Chart.js instance.
     * @param {number} meanx Mean messages.
     * @param {number} meany Mean grade percent.
     */
    const drawMeanLines = (chart, meanx, meany) => {
        const {ctx, chartArea, scales} = chart;
        if (!chartArea || !scales.x || !scales.y) {
            return;
        }

        const xPos = scales.x.getPixelForValue(meanx);
        const yPos = scales.y.getPixelForValue(meany);

        ctx.save();
        ctx.strokeStyle = palette.mean;
        ctx.lineWidth = 1.5;
        ctx.setLineDash([6, 4]);

        if (xPos >= chartArea.left && xPos <= chartArea.right) {
            ctx.beginPath();
            ctx.moveTo(xPos, chartArea.top);
            ctx.lineTo(xPos, chartArea.bottom);
            ctx.stroke();
        }
        if (yPos >= chartArea.top && yPos <= chartArea.bottom) {
            ctx.beginPath();
            ctx.moveTo(chartArea.left, yPos);
            ctx.lineTo(chartArea.right, yPos);
            ctx.stroke();
        }
        ctx.restore();
    };

    /**
     * Initialize performance scatter chart.
     */
    const initPerformanceScatter = () => {
        const dataNode = document.getElementById('tutor-usage-performance-scatter-data');
        const canvas = document.getElementById('tutorUsagePerformanceScatter');
        if (!dataNode || !canvas) {
            return;
        }

        const payload = JSON.parse(dataNode.textContent || '{}');
        const points = Array.isArray(payload.points) ? payload.points : [];
        if (!points.length) {
            return;
        }

        const peers = [];
        const current = [];
        points.forEach((point) => {
            const datum = {
                x: Number(point.x),
                y: Number(point.y),
                name: point.name || '',
            };
            if (point.iscurrent) {
                current.push(datum);
            } else {
                peers.push(datum);
            }
        });

        const xmax = Math.max(Number(payload.xmax) || 0, ...points.map((p) => Number(p.x) || 0), 0);
        const meanx = Number(payload.meanx) || 0;
        const meany = Number(payload.meany) || 0;

        const datasets = [{
            label: payload.xaxislabel || 'Peers',
            data: peers,
            backgroundColor: palette.peer,
            borderColor: palette.peer,
            pointRadius: 5,
            pointHoverRadius: 6,
        }];
        if (current.length) {
            datasets.push({
                label: 'Current user',
                data: current,
                backgroundColor: palette.current,
                borderColor: palette.current,
                pointRadius: 7,
                pointHoverRadius: 8,
            });
        }

        const ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: 'scatter',
            data: {datasets: datasets},
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {display: false},
                    tooltip: {
                        callbacks: {
                            title: (items) => {
                                const raw = items[0] && items[0].raw;
                                return (raw && raw.name) ? raw.name : '';
                            },
                            label: (context) => {
                                const x = context.parsed.x;
                                const y = context.parsed.y;
                                return `${x}, ${y}%`;
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        min: 0,
                        max: xmax > 0 ? xmax : 1,
                        title: {
                            display: true,
                            text: payload.xaxislabel || '',
                        },
                        ticks: {precision: 0},
                    },
                    y: {
                        min: 0,
                        max: 100,
                        title: {
                            display: true,
                            text: payload.yaxislabel || '',
                        },
                    },
                },
            },
            plugins: [{
                id: 'dixeoMeanLines',
                afterDatasetsDraw: (chart) => drawMeanLines(chart, meanx, meany),
            }],
        });
    };

    /**
     * Initialize stacked bar chart and heatmap.
     */
    const initCharts = () => {
        const stackedNode = document.getElementById('tutor-usage-stacked-bar-data');
        const heatmapNode = document.getElementById('tutor-usage-heatmap-data');
        if (stackedNode || heatmapNode) {
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
                            y: {stacked: true, beginAtZero: true, suggestedMax: 10},
                        },
                    },
                });
            }
        }

        initPerformanceScatter();
    };

    /**
     * Enable Bootstrap tooltips on KPI cards, KPI info icons, and summary table cells.
     */
    const initTooltips = () => {
        if (typeof $.fn.tooltip !== 'function') {
            return;
        }

        const cards = document.querySelectorAll(
            '.dixeo-tutor-usage-report-kpi[data-toggle="tooltip"],' +
            '.dixeo-tutor-usage-report-stat[data-toggle="tooltip"]'
        );
        if (cards.length) {
            $(cards).tooltip({
                container: 'body',
                placement: 'bottom',
                trigger: 'hover focus',
            });
        }

        // These deliberately avoid data-toggle="tooltip" and title: the theme delegates a hover
        // tooltip to every such element, which would reintroduce hover on the info icons.
        const infoicons = document.querySelectorAll('.dixeo-tutor-usage-report-info[data-info]');
        if (!infoicons.length) {
            return;
        }

        infoicons.forEach((icon) => {
            $(icon).tooltip({
                container: 'body',
                placement: 'top',
                trigger: 'click',
                title: icon.dataset.info || '',
            });
        });

        const $infoicons = $(infoicons);

        // A click only toggles its own tooltip, so close any other one that is open.
        $infoicons.on('show.bs.tooltip', (event) => {
            $infoicons.not(event.currentTarget).tooltip('hide');
        });

        document.addEventListener('click', (event) => {
            if (!event.target.closest('.dixeo-tutor-usage-report-info')) {
                $infoicons.tooltip('hide');
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                $infoicons.tooltip('hide');
            }
        });
    };

    /**
     * Initialize tutor usage report UI.
     */
    const init = () => {
        initDateRange();
        initTooltips();
        initCharts();
    };

    return {
        init: init,
    };
});
