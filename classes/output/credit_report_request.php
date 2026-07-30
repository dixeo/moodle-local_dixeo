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

use local_dixeo\service\credit_usage_report_service;

/**
 * Parsed credit report request parameters.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class credit_report_request {
    /** @var string View mode. */
    public string $view;

    /** @var string Anchor date. */
    public string $anchor;

    /** @var int Custom range start timestamp. */
    public int $datefrom;

    /** @var int Custom range end timestamp. */
    public int $dateto;

    /** @var int Current page (0-based). */
    public int $page;

    /** @var int Rows per page. */
    public int $perpage;

    /** @var credit_report_filters Validated filter state. */
    public credit_report_filters $filters;

    /**
     * Require report access capability.
     */
    public static function require_access(): void {
        $context = \context_system::instance();
        if (
            !has_capability('local/dixeo:manage', $context)
            && !has_capability('local/dixeo:viewusage', $context)
        ) {
            require_capability('local/dixeo:manage', $context);
        }
    }

    /**
     * Parse the current HTTP request.
     *
     * @return self
     */
    public static function from_globals(): self {
        $request = new self();
        $request->view = optional_param('view', credit_usage_report_service::VIEW_WEEK, PARAM_ALPHA);
        $request->anchor = optional_param('anchor', '', PARAM_TEXT);
        $request->datefrom = credit_usage_report_service::parse_date_from_param(
            optional_param('datefrom', '', PARAM_TEXT)
        );
        $request->dateto = credit_usage_report_service::parse_date_to_param(
            optional_param('dateto', '', PARAM_TEXT)
        );
        $request->page = optional_param('page', 0, PARAM_INT);
        $request->perpage = optional_param('perpage', 50, PARAM_INT);
        $request->filters = credit_report_filters::from_raw([
            'components' => optional_param_array('component', [], PARAM_ALPHANUMEXT),
            'jobtypes' => optional_param_array('jobtype', [], PARAM_ALPHANUMEXT),
            'moduletypes' => optional_param_array('moduletype', [], PARAM_ALPHANUMEXT),
            'userids' => array_values(array_filter(optional_param_array('userid', [], PARAM_INT))),
            'courseids' => array_values(array_filter(optional_param_array('courseid', [], PARAM_INT))),
        ]);
        return $request;
    }

    /**
     * Build a request object from renderable parameters.
     *
     * @param array $params Renderable parameters.
     * @return self
     */
    public static function from_renderable_params(array $params): self {
        $request = new self();
        $request->view = $params['view'] ?? credit_usage_report_service::VIEW_WEEK;
        $request->anchor = $params['anchor'] ?? '';
        $request->datefrom = (int) ($params['datefrom'] ?? 0);
        $request->dateto = (int) ($params['dateto'] ?? 0);
        $request->page = (int) ($params['page'] ?? 0);
        $request->perpage = (int) ($params['perpage'] ?? 50);
        $request->filters = credit_report_filters::from_raw([
            'components' => $params['components'] ?? [],
            'jobtypes' => $params['jobtypes'] ?? [],
            'moduletypes' => $params['moduletypes'] ?? [],
            'userids' => $params['userids'] ?? [],
            'courseids' => $params['courseids'] ?? [],
        ]);
        return $request;
    }

    /**
     * Build renderable constructor parameters.
     *
     * @return array
     */
    public function to_renderable_params(): array {
        return [
            'view' => $this->view,
            'anchor' => $this->anchor,
            'datefrom' => $this->datefrom,
            'dateto' => $this->dateto,
            'page' => $this->page,
            'perpage' => $this->perpage,
            'components' => $this->filters->components,
            'jobtypes' => $this->filters->jobtypes,
            'moduletypes' => $this->filters->moduletypes,
            'userids' => $this->filters->userids,
            'courseids' => $this->filters->courseids,
        ];
    }

    /**
     * Build scalar URL parameters for $PAGE->set_url().
     *
     * @return array
     */
    public function to_page_url_params(): array {
        return array_filter([
            'view' => $this->view,
            'anchor' => $this->anchor ?: null,
            'datefrom' => $this->view === credit_usage_report_service::VIEW_CUSTOM && $this->datefrom
                ? credit_usage_report_service::format_date_param($this->datefrom)
                : null,
            'dateto' => $this->view === credit_usage_report_service::VIEW_CUSTOM && $this->dateto
                ? credit_usage_report_service::format_date_param($this->dateto)
                : null,
            'page' => $this->page ?: null,
            'perpage' => $this->perpage !== 50 ? $this->perpage : null,
        ], static fn($value) => $value !== null && $value !== '');
    }

    /**
     * Build report query filters for the usage service.
     *
     * @param credit_usage_report_service $service Report service.
     * @return array
     */
    public function to_filters(credit_usage_report_service $service): array {
        $period = $service->resolve_period(
            $this->view,
            $this->anchor ?: null,
            $this->datefrom ?: null,
            $this->dateto ?: null
        );

        return $this->filters->to_service_filters($period);
    }

    /**
     * Build hidden form fields for the export selector, including multi-value filters.
     *
     * @return array<int, array{name: string, value: string}>
     */
    public function to_export_hidden_params(): array {
        $params = array_merge(
            [
                'view' => $this->view,
            ],
            $this->filters->to_query_params()
        );

        if ($this->anchor !== '') {
            $params['anchor'] = $this->anchor;
        }
        if ($this->view === credit_usage_report_service::VIEW_CUSTOM) {
            if ($this->datefrom) {
                $params['datefrom'] = credit_usage_report_service::format_date_param($this->datefrom);
            }
            if ($this->dateto) {
                $params['dateto'] = credit_usage_report_service::format_date_param($this->dateto);
            }
        }

        $hidden = [];
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    if ($item === '' || $item === null) {
                        continue;
                    }
                    $hidden[] = [
                        'name' => $key . '[]',
                        'value' => (string) $item,
                    ];
                }
                continue;
            }
            if ($value !== null && $value !== '') {
                $hidden[] = [
                    'name' => $key,
                    'value' => (string) $value,
                ];
            }
        }

        return $hidden;
    }

    /**
     * Build audit event metadata without personal data.
     *
     * @param int $rowcount Total or exported row count.
     * @param string|null $dataformat Export format when applicable.
     * @return array
     */
    public function to_event_other(int $rowcount = 0, ?string $dataformat = null): array {
        $other = [
            'view' => $this->view,
            'rowcount' => $rowcount,
            'page' => $this->page,
            'filtercomponents' => count($this->filters->components),
            'filterjobtypes' => count($this->filters->jobtypes),
            'filtermoduletypes' => count($this->filters->moduletypes),
            'filterusers' => count($this->filters->userids),
            'filtercourses' => count($this->filters->courseids),
        ];

        if ($dataformat !== null && $dataformat !== '') {
            $other['dataformat'] = $dataformat;
        }

        return $other;
    }
}
