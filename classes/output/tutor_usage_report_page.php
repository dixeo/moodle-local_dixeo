<?php
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

namespace local_dixeo\output;

use renderable;
use templatable;
use renderer_base;
use core\output\paging_bar;
use core\plugin_manager;
use local_dixeo\event\tutor_usage_report_viewed;
use local_dixeo\service\tutor_usage_performance_service;
use local_dixeo\service\tutor_usage_report_service;

/**
 * Renderable for the tutor usage report page.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tutor_usage_report_page implements renderable, templatable {
    /** @var tutor_usage_report_request Parsed request. */
    protected tutor_usage_report_request $request;

    /**
     * Constructor.
     *
     * @param array $params Report request parameters.
     */
    public function __construct(array $params) {
        $this->request = tutor_usage_report_request::from_renderable_params($params);
    }

    /**
     * Export data for the template.
     *
     * @param renderer_base $output The renderer.
     * @return array The template data.
     */
    public function export_for_template(renderer_base $output): array {
        try {
            return $this->build_template_data($output);
        } catch (\moodle_exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build template data.
     *
     * @param renderer_base $output The renderer.
     * @return array
     */
    protected function build_template_data(renderer_base $output): array {
        global $DB;

        $reportservice = new tutor_usage_report_service();
        $request = $this->request;
        $view = $request->view;
        $roleids = $request->resolved_roleids();

        $period = $reportservice->resolve_period(
            $view,
            $request->anchor ?: null,
            $request->datefrom ?: null,
            $request->dateto ?: null
        );

        $prevtimestart = $period['prevtimestart'] ?? null;
        $prevtimeend = $period['prevtimeend'] ?? null;

        $kpis = $reportservice->get_kpis(
            $request->level,
            $request->courseid,
            $request->userid,
            (int) $period['timestart'],
            (int) $period['timeend'],
            $roleids,
            $prevtimestart,
            $prevtimeend,
            $view
        );

        $stackedbar = $reportservice->get_stacked_bar_data(
            $request->level,
            $request->courseid,
            $request->userid,
            (int) $period['timestart'],
            (int) $period['timeend'],
            $roleids
        );
        $heatmap = $reportservice->get_heatmap_data(
            $request->level,
            $request->courseid,
            $request->userid,
            (int) $period['timestart'],
            (int) $period['timeend'],
            $roleids
        );

        $page = max(0, $request->page);
        $perpage = max(1, $request->perpage);
        $baseparams = $this->base_url_params($view, $period);
        $rowsresult = $reportservice->get_rows(
            $request->level,
            $request->courseid,
            $request->userid,
            (int) $period['timestart'],
            (int) $period['timeend'],
            $roleids,
            $page,
            $perpage,
            $baseparams,
            $request->sort,
            $request->sortdir
        );

        $totalpages = $perpage > 0 ? (int) ceil($rowsresult['total'] / $perpage) : 1;
        $haspagination = $totalpages > 1;

        $paginationbar = '';
        if ($haspagination) {
            $baseurl = new \moodle_url($this->report_url($baseparams));
            $baseurl->remove_params('page');
            $paginationbar = $output->render(new paging_bar(
                $rowsresult['total'],
                $page,
                $perpage,
                $baseurl
            ));
        }

        $exportselector = $this->build_export_selector($output, $request);
        $breadcrumbs = $this->build_breadcrumbs($request, $baseparams);

        $performanceservice = new tutor_usage_performance_service();
        $performance = $performanceservice->get_section_context(
            $request->level,
            $request->courseid,
            $request->userid,
            $request->rolescope
        );

        tutor_usage_report_viewed::create_for_request($request, (int) $rowsresult['total'])->trigger();

        $haschartdata = $this->has_stacked_bar_data($stackedbar) || ($heatmap['max'] ?? 0) > 0;

        return [
            'error' => null,
            'level' => $request->level,
            'issite' => $request->level === tutor_usage_report_service::LEVEL_SITE,
            'iscourse' => $request->level === tutor_usage_report_service::LEVEL_COURSE,
            'isuser' => $request->level === tutor_usage_report_service::LEVEL_USER,
            'period' => [
                'label' => $period['label'],
                'view' => $view,
                'isweek' => $view === tutor_usage_report_service::VIEW_WEEK,
                'ismonth' => $view === tutor_usage_report_service::VIEW_MONTH,
                'iscustom' => $view === tutor_usage_report_service::VIEW_CUSTOM,
                'prevurl' => $period['prevanchor']
                    ? $this->report_url(array_merge($baseparams, ['anchor' => $period['prevanchor']]))
                    : null,
                'nexturl' => $period['nextanchor']
                    ? $this->report_url(array_merge($baseparams, ['anchor' => $period['nextanchor']]))
                    : null,
                'hasprev' => !empty($period['prevanchor']),
                'hasnext' => !empty($period['nextanchor']),
            ],
            'views' => [
                [
                    'id' => tutor_usage_report_service::VIEW_WEEK,
                    'label' => get_string('tutor_usage_report_view_week', 'local_dixeo'),
                    'url' => $this->report_url(array_merge(
                        $baseparams,
                        tutor_usage_report_service::build_view_switch_params(
                            tutor_usage_report_service::VIEW_WEEK,
                            $period
                        )
                    )),
                    'active' => $view === tutor_usage_report_service::VIEW_WEEK,
                ],
                [
                    'id' => tutor_usage_report_service::VIEW_MONTH,
                    'label' => get_string('tutor_usage_report_view_month', 'local_dixeo'),
                    'url' => $this->report_url(array_merge(
                        $baseparams,
                        tutor_usage_report_service::build_view_switch_params(
                            tutor_usage_report_service::VIEW_MONTH,
                            $period
                        )
                    )),
                    'active' => $view === tutor_usage_report_service::VIEW_MONTH,
                ],
                [
                    'id' => tutor_usage_report_service::VIEW_CUSTOM,
                    'label' => get_string('tutor_usage_report_view_custom', 'local_dixeo'),
                    'url' => $this->report_url(array_merge(
                        $baseparams,
                        tutor_usage_report_service::build_view_switch_params(
                            tutor_usage_report_service::VIEW_CUSTOM,
                            $period
                        )
                    )),
                    'active' => $view === tutor_usage_report_service::VIEW_CUSTOM,
                ],
            ],
            'rolescopes' => $this->build_role_scope_items($reportservice, $baseparams),
            'filters' => [
                'action' => $this->report_url([]),
                'datefromformatted' => tutor_usage_report_service::format_date_param(
                    $request->datefrom ?: $period['timestart']
                ),
                'datetoformatted' => tutor_usage_report_service::format_date_param(
                    $request->dateto ?: $period['timeend']
                ),
                'view' => $view,
                'level' => $request->level,
                'courseid' => $request->courseid,
                'userid' => $request->userid,
                'rolescope' => $request->rolescope,
            ],
            'breadcrumbs' => $breadcrumbs,
            'hasbreadcrumbs' => $breadcrumbs !== [],
            'kpis' => $kpis,
            'haschange' => $prevtimestart !== null,
            'stackedbardata' => json_encode($stackedbar),
            'heatmapdata' => json_encode($heatmap),
            'haschartdata' => $haschartdata,
            'columns' => $this->build_column_items($reportservice, $baseparams),
            'rows' => $rowsresult['rows'],
            'hasrows' => !empty($rowsresult['rows']),
            'paginationbar' => $paginationbar,
            'haspagination' => $haspagination,
            'exportselector' => $exportselector,
            'tabletitle' => $this->get_table_title($request),
            'hasperformance' => $performance !== null,
            'performance' => $performance,
        ];
    }

    /**
     * Build a tutor usage report page URL.
     *
     * @param array $params URL parameters.
     * @return string Rendered URL.
     */
    protected function report_url(array $params): string {
        $request = $this->request;
        $merged = array_merge([
            'level' => $request->level,
            'courseid' => $request->courseid ?: null,
            'userid' => $request->userid ?: null,
            'view' => $request->view,
            'perpage' => $request->perpage,
        ], $params);

        // An explicit rolescope param (including null) wins over the active request scope.
        if (
            !array_key_exists('rolescope', $params)
            && $request->rolescope !== tutor_usage_report_service::ROLE_SCOPE_DEFAULT
        ) {
            $merged['rolescope'] = $request->rolescope;
        }

        return tutor_usage_report_service::build_report_url($merged);
    }

    /**
     * Build the Moodle dataformat export selector for the current report filters.
     *
     * @param renderer_base $output Renderer.
     * @param tutor_usage_report_request $request Current report request.
     * @return string Rendered HTML.
     */
    protected function build_export_selector(renderer_base $output, tutor_usage_report_request $request): string {
        $options = [];
        foreach (plugin_manager::instance()->get_plugins_of_type('dataformat') as $format) {
            if ($format->is_enabled()) {
                $options[] = [
                    'value' => $format->name,
                    'label' => get_string('dataformat', $format->component),
                ];
            }
        }

        return $output->render_from_template('core/dataformat_selector', [
            'label' => get_string('downloadas', 'table'),
            'base' => (new \moodle_url('/local/dixeo/tutor_usage_report_download.php'))->out(false),
            'name' => 'dataformat',
            'params' => $request->to_export_hidden_params(),
            'options' => $options,
            'sesskey' => sesskey(),
            'submit' => get_string('download'),
        ]);
    }

    /**
     * Build the user role-scope selector links, keeping the active period.
     *
     * @param tutor_usage_report_service $service Report service.
     * @param array $baseparams Base URL params for the active period.
     * @return array
     */
    protected function build_role_scope_items(tutor_usage_report_service $service, array $baseparams): array {
        $items = [];
        foreach ($service->get_role_scope_options($this->request->rolescope) as $option) {
            $scope = $option['id'];
            $items[] = [
                'id' => $scope,
                'name' => $option['name'],
                'url' => $this->report_url(array_merge($baseparams, [
                    // The default scope stays out of the URL so it reads as the plain report URL.
                    'rolescope' => $scope === tutor_usage_report_service::ROLE_SCOPE_DEFAULT ? null : $scope,
                ])),
                'active' => !empty($option['selected']),
            ];
        }

        return $items;
    }

    /**
     * Build the sortable summary table headers for the active level.
     *
     * @param tutor_usage_report_service $service Report service.
     * @param array $baseparams Base URL params for the active period and filters.
     * @return array
     */
    protected function build_column_items(tutor_usage_report_service $service, array $baseparams): array {
        $request = $this->request;
        $items = [];

        foreach ($service->get_columns($request->level) as $key => $label) {
            $active = $key === $request->sort;
            $ascending = $active && $request->sortdir === tutor_usage_report_service::SORT_ASC;
            // Clicking the sorted column reverses it; any other column starts in its natural direction.
            $nextdir = $active
                ? ($ascending ? tutor_usage_report_service::SORT_DESC : tutor_usage_report_service::SORT_ASC)
                : tutor_usage_report_service::default_sort_direction($key);

            $items[] = [
                'key' => $key,
                'label' => $label,
                'url' => $this->report_url(array_merge($baseparams, [
                    'sort' => $key,
                    'sortdir' => $nextdir,
                ])),
                'sortlabel' => get_string('sortby') . ' ' . $label . ' ' . get_string(
                    $nextdir === tutor_usage_report_service::SORT_ASC ? 'asc' : 'desc'
                ),
                'active' => $active,
                'ascending' => $ascending,
                'descending' => $active && !$ascending,
                'ariasort' => $active ? ($ascending ? 'ascending' : 'descending') : 'none',
            ];
        }

        return $items;
    }

    /**
     * Build base URL params preserving active filters.
     *
     * @param string $view View mode.
     * @param array $period Period data.
     * @return array
     */
    protected function base_url_params(string $view, array $period): array {
        $params = [
            'view' => $view,
            'perpage' => $this->request->perpage,
        ];

        if ($this->request->rolescope !== tutor_usage_report_service::ROLE_SCOPE_DEFAULT) {
            $params['rolescope'] = $this->request->rolescope;
        }

        if (!$this->request->has_default_sort()) {
            $params['sort'] = $this->request->sort;
            $params['sortdir'] = $this->request->sortdir;
        }

        if ($view === tutor_usage_report_service::VIEW_CUSTOM) {
            $params['datefrom'] = tutor_usage_report_service::format_date_param(
                $this->request->datefrom ?: $period['timestart']
            );
            $params['dateto'] = tutor_usage_report_service::format_date_param(
                $this->request->dateto ?: $period['timeend']
            );
        } else if ($this->request->anchor !== '') {
            $params['anchor'] = $this->request->anchor;
        }

        return $params;
    }

    /**
     * Build breadcrumb navigation for drill-down levels.
     *
     * @param tutor_usage_report_request $request Current request.
     * @param array $baseparams Base URL params for the active period and filters.
     * @return array
     */
    protected function build_breadcrumbs(tutor_usage_report_request $request, array $baseparams = []): array {
        global $DB;

        // Site level has no drill-down crumbs (avoids leftover courseid from prior navigation).
        if ($request->level === tutor_usage_report_service::LEVEL_SITE) {
            return [];
        }

        $items = [[
            'label' => get_string('tutor_usage_report_level_site', 'local_dixeo'),
            'url' => $this->report_url(array_merge($baseparams, [
                'level' => tutor_usage_report_service::LEVEL_SITE,
                'courseid' => null,
                'userid' => null,
            ])),
            'active' => false,
        ]];

        if ($request->courseid > 0) {
            $course = $DB->get_record('course', ['id' => $request->courseid], 'id, fullname', IGNORE_MISSING);
            $items[] = [
                'label' => $course ? format_string($course->fullname) : get_string('course'),
                'url' => $request->level === tutor_usage_report_service::LEVEL_USER
                    ? $this->report_url(array_merge($baseparams, [
                        'level' => tutor_usage_report_service::LEVEL_COURSE,
                        'courseid' => $request->courseid,
                        'userid' => null,
                    ]))
                    : null,
                'active' => $request->level === tutor_usage_report_service::LEVEL_COURSE,
            ];
        }

        if ($request->level === tutor_usage_report_service::LEVEL_USER && $request->userid > 0) {
            $user = \core_user::get_user($request->userid, 'id, firstname, lastname', IGNORE_MISSING);
            $items[] = [
                'label' => $user ? fullname($user) : get_string('user'),
                'url' => null,
                'active' => true,
            ];
        }

        return $items;
    }

    /**
     * Summary table title for the active level.
     *
     * @param tutor_usage_report_request $request Current request.
     * @return string
     */
    protected function get_table_title(tutor_usage_report_request $request): string {
        if ($request->level === tutor_usage_report_service::LEVEL_COURSE) {
            return get_string('tutor_usage_report_table_users', 'local_dixeo');
        }
        if ($request->level === tutor_usage_report_service::LEVEL_USER) {
            return get_string('tutor_usage_report_table_modules', 'local_dixeo');
        }
        return get_string('tutor_usage_report_table_courses', 'local_dixeo');
    }

    /**
     * Whether stacked bar data contains any non-zero values.
     *
     * @param array $stackedbar Stacked bar chart data.
     * @return bool
     */
    protected function has_stacked_bar_data(array $stackedbar): bool {
        foreach ($stackedbar['datasets'] ?? [] as $dataset) {
            foreach ($dataset['data'] ?? [] as $value) {
                if ((int) $value > 0) {
                    return true;
                }
            }
        }
        return false;
    }
}
