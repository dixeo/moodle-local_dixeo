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
use local_dixeo\dto\credit_transaction;
use local_dixeo\local\credit_report_request;
use local_dixeo\service\credit_service;
use local_dixeo\service\credit_usage_report_service;
use local_dixeo\service\credit_usage_sync_service;
use local_dixeo\util\credit_component_mapper;

/**
 * Renderable for the credit report page.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credit_report_page implements renderable, templatable {
    /** @var array Request parameters. */
    protected array $params;

    /**
     * Constructor.
     *
     * @param array $params Report request parameters.
     */
    public function __construct(array $params) {
        $this->params = $params;
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
            return $this->build_template_data($creditservice, $output);
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
     * @param credit_service $creditservice Credit service.
     * @param renderer_base $output The renderer.
     * @return array
     */
    protected function build_template_data(credit_service $creditservice, renderer_base $output): array {
        $reportservice = new credit_usage_report_service();
        $view = $this->params['view'] ?? credit_usage_report_service::VIEW_WEEK;
        $period = $reportservice->resolve_period(
            $view,
            $this->params['anchor'] ?? null,
            !empty($this->params['datefrom']) ? (int) $this->params['datefrom'] : null,
            !empty($this->params['dateto']) ? (int) $this->params['dateto'] : null
        );

        $filters = [
            'type' => credit_transaction::TYPE_DEDUCTION,
            'timestart' => $period['timestart'],
            'timeend' => $period['timeend'],
            'components' => $this->params['components'] ?? [],
            'jobtypes' => $this->params['jobtypes'] ?? [],
            'moduletypes' => $this->params['moduletypes'] ?? [],
            'userids' => $this->params['userids'] ?? [],
            'courseids' => $this->params['courseids'] ?? [],
        ];

        $periodfilters = [
            'type' => credit_transaction::TYPE_DEDUCTION,
            'timestart' => $period['timestart'],
            'timeend' => $period['timeend'],
        ];

        $page = max(0, (int) ($this->params['page'] ?? 0));
        $perpage = max(1, (int) ($this->params['perpage'] ?? 50));
        $rowsresult = $reportservice->get_rows($filters, $page, $perpage);
        $kpis = $reportservice->get_kpis($filters);
        $histogram = $reportservice->get_histogram($filters);
        $breakdown = $reportservice->get_breakdown($filters);
        $filteroptions = $reportservice->get_filter_options($periodfilters);
        $balance = $creditservice->get_balance();

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

        $exportselector = $this->build_export_selector(
            $output,
            credit_report_request::from_renderable_params($this->params)
        );

        return [
            'configured' => true,
            'error' => null,
            'balance' => [
                'formatted' => $balance->get_formatted_balance(),
                'state' => $balance->state,
                'stateclass' => $this->get_state_class($balance->state),
                'statedescription' => $balance->get_state_description(),
                'isfrozen' => $balance->is_frozen(),
                'issuspended' => $balance->is_suspended(),
            ],
            'period' => [
                'label' => $period['label'],
                'view' => $view,
                'isweek' => $view === credit_usage_report_service::VIEW_WEEK,
                'ismonth' => $view === credit_usage_report_service::VIEW_MONTH,
                'iscustom' => $view === credit_usage_report_service::VIEW_CUSTOM,
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
                    'id' => credit_usage_report_service::VIEW_WEEK,
                    'label' => get_string('credit_report_view_week', 'local_dixeo'),
                    'url' => $this->report_url(['view' => credit_usage_report_service::VIEW_WEEK]),
                    'active' => $view === credit_usage_report_service::VIEW_WEEK,
                ],
                [
                    'id' => credit_usage_report_service::VIEW_MONTH,
                    'label' => get_string('credit_report_view_month', 'local_dixeo'),
                    'url' => $this->report_url(['view' => credit_usage_report_service::VIEW_MONTH]),
                    'active' => $view === credit_usage_report_service::VIEW_MONTH,
                ],
                [
                    'id' => credit_usage_report_service::VIEW_CUSTOM,
                    'label' => get_string('credit_report_view_custom', 'local_dixeo'),
                    'url' => $this->report_url(['view' => credit_usage_report_service::VIEW_CUSTOM]),
                    'active' => $view === credit_usage_report_service::VIEW_CUSTOM,
                ],
            ],
            'filters' => [
                'action' => $this->report_url([]),
                'datefrom' => !empty($this->params['datefrom']) ? (int) $this->params['datefrom'] : $period['timestart'],
                'dateto' => !empty($this->params['dateto']) ? (int) $this->params['dateto'] : $period['timeend'],
                'datefromformatted' => credit_usage_report_service::format_date_param(
                    !empty($this->params['datefrom']) ? (int) $this->params['datefrom'] : $period['timestart']
                ),
                'datetoformatted' => credit_usage_report_service::format_date_param(
                    !empty($this->params['dateto']) ? (int) $this->params['dateto'] : $period['timeend']
                ),
                'view' => $view,
                'anchor' => $this->params['anchor'] ?? '',
                'components' => $this->build_filter_options(
                    $filteroptions['components'],
                    $this->params['components'] ?? [],
                    'credit_component_'
                ),
                'jobtypes' => $this->build_filter_options(
                    $filteroptions['jobtypes'],
                    $this->params['jobtypes'] ?? [],
                    'credit_action_'
                ),
                'moduletypes' => $this->build_filter_options(
                    $filteroptions['moduletypes'],
                    $this->params['moduletypes'] ?? [],
                    null
                ),
                'users' => $this->build_named_filter_options(
                    $filteroptions['users'],
                    $this->params['userids'] ?? []
                ),
                'courses' => $this->build_named_filter_options(
                    $filteroptions['courses'],
                    $this->params['courseids'] ?? []
                ),
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
     * moodle_url rejects array parameter values, so multi-value filters are appended manually.
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
        $params = [
            'view' => $view,
            'perpage' => (int) ($this->params['perpage'] ?? 50),
        ];

        if ($view === credit_usage_report_service::VIEW_CUSTOM) {
            $params['datefrom'] = credit_usage_report_service::format_date_param(
                !empty($this->params['datefrom']) ? (int) $this->params['datefrom'] : $period['timestart']
            );
            $params['dateto'] = credit_usage_report_service::format_date_param(
                !empty($this->params['dateto']) ? (int) $this->params['dateto'] : $period['timeend']
            );
        } else if (!empty($this->params['anchor'])) {
            $params['anchor'] = $this->params['anchor'];
        }

        foreach (['userids' => 'userid', 'courseids' => 'courseid'] as $source => $param) {
            foreach ($this->params[$source] ?? [] as $value) {
                $params[$param][] = $value;
            }
        }

        foreach (['components' => 'component', 'jobtypes' => 'jobtype', 'moduletypes' => 'moduletype'] as $source => $param) {
            foreach ($this->params[$source] ?? [] as $value) {
                $params[$param][] = $value;
            }
        }

        return $params;
    }

    /**
     * Build select options for filters.
     *
     * @param array $values Available values.
     * @param array $selected Selected values.
     * @param string|null $stringprefix Lang string prefix without trailing code.
     * @return array
     */
    protected function build_filter_options(array $values, array $selected, ?string $stringprefix): array {
        $options = [];
        foreach ($values as $value) {
            if ($stringprefix === 'credit_component_') {
                $label = credit_component_mapper::get_label($value);
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

    /**
     * Build select options from pre-labelled value rows.
     *
     * @param array $items Rows with value and label keys.
     * @param array $selected Selected values.
     * @return array
     */
    protected function build_named_filter_options(array $items, array $selected): array {
        $options = [];
        foreach ($items as $item) {
            $value = (string) ($item['value'] ?? '');
            if ($value === '') {
                continue;
            }
            $options[] = [
                'value' => $value,
                'label' => (string) ($item['label'] ?? $value),
                'selected' => in_array((int) $value, array_map('intval', $selected), true),
            ];
        }
        return $options;
    }

    /**
     * Get CSS class for account state.
     *
     * @param string $state Account state.
     * @return string
     */
    protected function get_state_class(string $state): string {
        return match ($state) {
            'active' => 'success',
            'frozen' => 'warning',
            'suspended' => 'danger',
            default => 'secondary',
        };
    }
}
