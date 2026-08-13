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

/**
 * Download tutor usage report data in a supported dataformat.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_OUTPUT_BUFFERING', true);

require_once(__DIR__ . '/../../config.php');

require_login();

use local_dixeo\event\tutor_usage_report_exported;
use local_dixeo\output\tutor_usage_report_request;
use local_dixeo\service\tutor_usage_report_service;

$dataformat = optional_param('dataformat', '', PARAM_ALPHA);
if ($dataformat === '') {
    redirect(new moodle_url('/local/dixeo/tutor_usage_report.php'));
}

$request = tutor_usage_report_request::from_globals();
$request->require_access();
require_sesskey();

$service = new tutor_usage_report_service();
$period = $service->resolve_period(
    $request->view,
    $request->anchor ?: null,
    $request->datefrom ?: null,
    $request->dateto ?: null
);
$roleids = $request->resolved_roleids();
$columns = $service->get_export_columns($request->level);
$rows = $service->get_export_rows(
    $request->level,
    $request->courseid,
    $request->userid,
    (int) $period['timestart'],
    (int) $period['timeend'],
    $roleids
);

tutor_usage_report_exported::create_for_request($request, count($rows), $dataformat)->trigger();

$rowkeys = array_keys($rows);
\core\dataformat::download_data(
    clean_filename(get_string('tutor_usage_report_nav', 'local_dixeo')),
    $dataformat,
    $columns,
    $rowkeys,
    static function (int $index) use ($rows): ?array {
        return $rows[$index] ?? null;
    }
);
