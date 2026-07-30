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
use local_dixeo\event\credit_report_viewed;
use local_dixeo\service\credit_service;
use local_dixeo\service\credit_usage_report_service;
use local_dixeo\service\credit_usage_sync_service;
use local_dixeo\util\credit_component_mapper;
use local_dixeo\util\credit_moduletype_mapper;

/**
 * Renderable for the credit report page.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credit_report_page implements renderable, templatable {
    /** @var credit_report_request Parsed request. */
    protected credit_report_request $request;

    /**
     * Constructor.
     *
     * @param array $params Report request parameters.
     */
    public function __construct(array $params) {
        $this->request = credit_report_request::from_renderable_params($params);
    }

    /**
     * Export data for the template.
     *
     * @param renderer_base $output The renderer.
     * @return array The template data.
     */
    public function export_for_template(renderer_base $output): array {
        $creditservice = new credit_service();
        if (!$creditservice->is_configured()) {
            return [
                'configured' => false,
                'error' => get_string('api_key_not_configured', 'local_dixeo'),
                'settingsurl' => (new \moodle_url('/admin/settings.php', ['section' => 'local_dixeo']))->out(false),
            ];
        }

        try {
            (new credit_usage_sync_service($creditservice))->sync_recent();
            return $this->build_template_data($output);
        } catch (\Exception $e) {
            return [
                'configured' => true,
                'error' => get_string('api_error', 'local_dixeo', $e->getMessage()),
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
        $reportservice = new credit_usage_report_service();
        $request = $this->request;
        $filters = $request->filters;
        $view = $request->view;
        $period = $reportservice->resolve_period(
            $view,
            $request->anchor ?: null,
            $request->datefrom ?: null,
            $request->dateto ?: null
        );

        $servicefilters = $filters->to_service_filters($period);
        $filteroptions = $reportservice->get_filter_options($filters->to_period_filters($period));
        $entityoptions = $filters->get_applied_entity_options($reportservice);

        $page = max(0, $request->page);
        $perpage = max(1, $request->perpage);
        $rowsresult = $reportservice->get_rows($servicefilters, $page, $perpage);
        $kpis = $reportservice->get_kpis($servicefilters);
        $histogram = $reportservice->get_histogram($servicefilters);
        $breakdown = $reportservice->get_breakdown($servicefilters);

        $baseparams = $this->base_url_params($view, $period);
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

        credit_report_viewed::create_for_request($request, (int) $rowsresult['total'])->trigger();

        return [
            'configured' => true,
            'error' => null,
            'period' => [
                'label' => $period['label'],
                'view' => $view,
                'isweek' => $view === credit_usage_report_service::VIEW_WEEK,
                'ismonth' => $view === credit_usage_report_service::VIEW_MONTH,
                'iscustom' => $view === credit_usage_report_service::VIEW_CUSTOM,
                'prevurl' => $period['prevanchor']
                    ? $this->report_url(array_merge($baseparams, ['anchor' => $period['prevanchor']]))
                    : null,
                'prevanchor' => $period['prevanchor'] ?? null,
                'nexturl' => $period['nextanchor']
                    ? $this->report_url(array_merge($baseparams, ['anchor' => $period['nextanchor']]))
                    : null,
                'nextanchor' => $period['nextanchor'] ?? null,
                'hasprev' => !empty($period['prevanchor']),
                'hasnext' => !empty($period['nextanchor']),
            ],
            'views' => [
                [
                    'id' => credit_usage_report_service::VIEW_WEEK,
                    'label' => get_string('credit_report_view_week', 'local_dixeo'),
                    'url' => $this->report_url(array_merge(
                        $filters->to_query_params(),
                        credit_usage_report_service::build_view_switch_params(
                            credit_usage_report_service::VIEW_WEEK,
                            $period
                        )
                    )),
                    'active' => $view === credit_usage_report_service::VIEW_WEEK,
                ],
                [
                    'id' => credit_usage_report_service::VIEW_MONTH,
                    'label' => get_string('credit_report_view_month', 'local_dixeo'),
                    'url' => $this->report_url(array_merge(
                        $filters->to_query_params(),
                        credit_usage_report_service::build_view_switch_params(
                            credit_usage_report_service::VIEW_MONTH,
                            $period
                        )
                    )),
                    'active' => $view === credit_usage_report_service::VIEW_MONTH,
                ],
                [
                    'id' => credit_usage_report_service::VIEW_CUSTOM,
                    'label' => get_string('credit_report_view_custom', 'local_dixeo'),
                    'url' => $this->report_url(array_merge(
                        $filters->to_query_params(),
                        credit_usage_report_service::build_view_switch_params(
                            credit_usage_report_service::VIEW_CUSTOM,
                            $period
                        )
                    )),
                    'active' => $view === credit_usage_report_service::VIEW_CUSTOM,
                ],
            ],
            'filters' => [
                'action' => $this->report_url([]),
                'datefrom' => $request->datefrom ?: $period['timestart'],
                'dateto' => $request->dateto ?: $period['timeend'],
                'datefromformatted' => credit_usage_report_service::format_date_param(
                    $request->datefrom ?: $period['timestart']
                ),
                'datetoformatted' => credit_usage_report_service::format_date_param(
                    $request->dateto ?: $period['timeend']
                ),
                'timestart' => (int) $period['timestart'],
                'timeend' => (int) $period['timeend'],
                'view' => $view,
                'anchor' => $request->anchor,
                'components' => $this->build_filter_options(
                    $filteroptions['components'],
                    $filters->components,
                    'credit_component_'
                ),
                'jobtypes' => $this->build_filter_options(
                    $filteroptions['jobtypes'],
                    $filters->jobtypes,
                    'credit_action_'
                ),
                'moduletypes' => $this->build_filter_options(
                    $filteroptions['moduletypes'],
                    $filters->moduletypes,
                    'credit_moduletype_'
                ),
                'users' => $entityoptions['users'],
                'courses' => $entityoptions['courses'],
            ],
            'kpis' => [
                'credits' => credit_service::format_credits($kpis['totalcredits']),
                'users' => number_format($kpis['totalusers']),
                'courses' => number_format($kpis['totalcourses']),
                'rows' => number_format($kpis['totalrows']),
            ],
            'histogramdata' => json_encode($histogram),
            'breakdowndata' => json_encode($breakdown),
            'haschartdata' => !empty($histogram['values']) || !empty($breakdown['values']),
            'rows' => $rowsresult['rows'],
            'hasrows' => !empty($rowsresult['rows']),
            'paginationbar' => $paginationbar,
            'haspagination' => $haspagination,
            'exportselector' => $exportselector,
            'reseturl' => $this->report_url([]),
        ];
    }

    /**
     * Build a credit report page URL.
     *
     * @param array $params URL parameters.
     * @return string Rendered URL.
     */
    protected function report_url(array $params): string {
        return credit_usage_report_service::build_report_url($params);
    }

    /**
     * Build the Moodle dataformat export selector for the current report filters.
     *
     * @param renderer_base $output Renderer.
     * @param credit_report_request $request Current report request.
     * @return string Rendered HTML.
     */
    protected function build_export_selector(renderer_base $output, credit_report_request $request): string {
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
            'base' => (new \moodle_url('/local/dixeo/credit_report_download.php'))->out(false),
            'name' => 'dataformat',
            'params' => $request->to_export_hidden_params(),
            'options' => $options,
            'sesskey' => sesskey(),
            'submit' => get_string('download'),
        ]);
    }

    /**
     * Build base URL params preserving active filters.
     *
     * @param string $view View mode.
     * @param array $period Period data.
     * @return array
     */
    protected function base_url_params(string $view, array $period): array {
        $params = array_merge(
            [
                'view' => $view,
                'perpage' => $this->request->perpage,
            ],
            $this->request->filters->to_query_params()
        );

        if ($view === credit_usage_report_service::VIEW_CUSTOM) {
            $params['datefrom'] = credit_usage_report_service::format_date_param(
                $this->request->datefrom ?: $period['timestart']
            );
            $params['dateto'] = credit_usage_report_service::format_date_param(
                $this->request->dateto ?: $period['timeend']
            );
        } else if ($this->request->anchor !== '') {
            $params['anchor'] = $this->request->anchor;
        }

        return $params;
    }

    /**
     * Build select options for enum filters.
     *
     * @param array $values Available values.
     * @param array $selected Selected values.
     * @param string|null $stringprefix Lang string prefix without trailing code.
     * @return array
     */
    protected function build_filter_options(array $values, array $selected, ?string $stringprefix): array {
        if ($stringprefix === 'credit_action_') {
            $values = credit_component_mapper::normalize_action_list($values);
            $selected = credit_component_mapper::normalize_action_list($selected);
        }

        $values = array_values(array_unique(array_merge($values, $selected)));
        sort($values);

        $options = [];
        foreach ($values as $value) {
            if ($stringprefix === 'credit_component_') {
                $label = credit_component_mapper::get_label($value);
            } else if ($stringprefix === 'credit_moduletype_') {
                $label = credit_moduletype_mapper::get_label($value);
            } else if ($stringprefix) {
                $key = $stringprefix . $value;
                $label = get_string($key, 'local_dixeo');
                if ($label === "[[$key]]") {
                    $label = ucwords(str_replace('_', ' ', $value));
                }
            } else {
                $label = $value;
            }
            $options[] = [
                'value' => $value,
                'label' => $label,
                'selected' => in_array($value, $selected, true),
            ];
        }
        return $options;
    }
}
