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

use local_dixeo\service\tutor_usage_report_service;

/**
 * Parsed tutor usage report request parameters.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tutor_usage_report_request {
    /** @var string Report level (site|course|user). */
    public string $level;

    /** @var int Course id for course/user levels. */
    public int $courseid;

    /** @var int User id for user level. */
    public int $userid;

    /** @var string View mode. */
    public string $view;

    /** @var string Anchor date. */
    public string $anchor;

    /** @var int Custom range start timestamp. */
    public int $datefrom;

    /** @var int Custom range end timestamp. */
    public int $dateto;

    /** @var string Role scope (all|teachers|students). */
    public string $rolescope;

    /** @var int Current page (0-based). */
    public int $page;

    /** @var int Rows per page. */
    public int $perpage;

    /**
     * Require report access for the current level.
     */
    public function require_access(): void {
        if ($this->level === tutor_usage_report_service::LEVEL_SITE) {
            if (tutor_usage_report_service::can_view_site()) {
                return;
            }
            $courseids = tutor_usage_report_service::get_accessible_courseids();
            if ($courseids !== []) {
                return;
            }
            require_capability('local/dixeo:viewtutorusagesite', \context_system::instance());
            return;
        }

        if ($this->courseid < 1) {
            throw new \moodle_exception('invalidcourseid');
        }

        if (!tutor_usage_report_service::can_view_course($this->courseid)) {
            require_capability('local/dixeo:viewtutorusage', \context_course::instance($this->courseid));
        }
    }

    /**
     * Parse the current HTTP request.
     *
     * @return self
     */
    public static function from_globals(): self {
        $request = new self();
        $request->level = optional_param('level', tutor_usage_report_service::LEVEL_SITE, PARAM_ALPHA);
        $request->courseid = optional_param('courseid', 0, PARAM_INT);
        $request->userid = optional_param('userid', 0, PARAM_INT);
        $request->view = optional_param('view', tutor_usage_report_service::VIEW_WEEK, PARAM_ALPHA);
        $request->anchor = optional_param('anchor', '', PARAM_TEXT);
        $request->datefrom = tutor_usage_report_service::parse_date_from_param(
            optional_param('datefrom', '', PARAM_TEXT)
        );
        $request->dateto = tutor_usage_report_service::parse_date_to_param(
            optional_param('dateto', '', PARAM_TEXT)
        );
        $request->rolescope = tutor_usage_report_service::normalize_role_scope(
            optional_param('rolescope', tutor_usage_report_service::ROLE_SCOPE_ALL, PARAM_ALPHA)
        );
        $request->page = optional_param('page', 0, PARAM_INT);
        $request->perpage = optional_param('perpage', 50, PARAM_INT);

        if (
            !in_array(
                $request->level,
                [
                    tutor_usage_report_service::LEVEL_SITE,
                    tutor_usage_report_service::LEVEL_COURSE,
                    tutor_usage_report_service::LEVEL_USER,
                ],
                true
            )
        ) {
            $request->level = tutor_usage_report_service::LEVEL_SITE;
        }

        if ($request->level === tutor_usage_report_service::LEVEL_USER && $request->userid < 1) {
            $request->level = tutor_usage_report_service::LEVEL_COURSE;
        }

        if ($request->level !== tutor_usage_report_service::LEVEL_SITE && $request->courseid < 1) {
            $request->level = tutor_usage_report_service::LEVEL_SITE;
            $request->userid = 0;
        }

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
        $request->level = $params['level'] ?? tutor_usage_report_service::LEVEL_SITE;
        $request->courseid = (int) ($params['courseid'] ?? 0);
        $request->userid = (int) ($params['userid'] ?? 0);
        $request->view = $params['view'] ?? tutor_usage_report_service::VIEW_WEEK;
        $request->anchor = $params['anchor'] ?? '';
        $request->datefrom = (int) ($params['datefrom'] ?? 0);
        $request->dateto = (int) ($params['dateto'] ?? 0);
        $request->rolescope = tutor_usage_report_service::normalize_role_scope(
            (string) ($params['rolescope'] ?? tutor_usage_report_service::ROLE_SCOPE_ALL)
        );
        $request->page = (int) ($params['page'] ?? 0);
        $request->perpage = (int) ($params['perpage'] ?? 50);
        return $request;
    }

    /**
     * Build renderable constructor parameters.
     *
     * @return array
     */
    public function to_renderable_params(): array {
        return [
            'level' => $this->level,
            'courseid' => $this->courseid,
            'userid' => $this->userid,
            'view' => $this->view,
            'anchor' => $this->anchor,
            'datefrom' => $this->datefrom,
            'dateto' => $this->dateto,
            'rolescope' => $this->rolescope,
            'page' => $this->page,
            'perpage' => $this->perpage,
        ];
    }

    /**
     * Build scalar URL parameters for $PAGE->set_url().
     *
     * @return array
     */
    public function to_page_url_params(): array {
        return array_filter([
            'level' => $this->level !== tutor_usage_report_service::LEVEL_SITE ? $this->level : null,
            'courseid' => $this->courseid ?: null,
            'userid' => $this->userid ?: null,
            'view' => $this->view,
            'anchor' => $this->anchor ?: null,
            'datefrom' => $this->view === tutor_usage_report_service::VIEW_CUSTOM && $this->datefrom
                ? tutor_usage_report_service::format_date_param($this->datefrom)
                : null,
            'dateto' => $this->view === tutor_usage_report_service::VIEW_CUSTOM && $this->dateto
                ? tutor_usage_report_service::format_date_param($this->dateto)
                : null,
            'rolescope' => $this->rolescope !== tutor_usage_report_service::ROLE_SCOPE_ALL
                ? $this->rolescope
                : null,
            'page' => $this->page ?: null,
            'perpage' => $this->perpage !== 50 ? $this->perpage : null,
        ], static fn($value) => $value !== null && $value !== '');
    }

    /**
     * Resolved role ids for queries. Empty means all roles (no role filter).
     *
     * @return int[]
     */
    public function resolved_roleids(): array {
        return tutor_usage_report_service::get_roleids_for_scope($this->rolescope);
    }

    /**
     * Build hidden form fields for the export selector, including multi-value filters.
     *
     * @return array<int, array{name: string, value: string}>
     */
    public function to_export_hidden_params(): array {
        $params = [
            'level' => $this->level,
            'view' => $this->view,
        ];

        if ($this->courseid > 0) {
            $params['courseid'] = $this->courseid;
        }
        if ($this->userid > 0) {
            $params['userid'] = $this->userid;
        }
        if ($this->anchor !== '') {
            $params['anchor'] = $this->anchor;
        }
        if ($this->view === tutor_usage_report_service::VIEW_CUSTOM) {
            if ($this->datefrom) {
                $params['datefrom'] = tutor_usage_report_service::format_date_param($this->datefrom);
            }
            if ($this->dateto) {
                $params['dateto'] = tutor_usage_report_service::format_date_param($this->dateto);
            }
        }
        if ($this->rolescope !== tutor_usage_report_service::ROLE_SCOPE_ALL) {
            $params['rolescope'] = $this->rolescope;
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
            'level' => $this->level,
            'view' => $this->view,
            'rowcount' => $rowcount,
            'page' => $this->page,
            'rolescope' => $this->rolescope,
        ];

        if ($dataformat !== null && $dataformat !== '') {
            $other['dataformat'] = $dataformat;
        }

        return $other;
    }
}
