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
 * Download credit usage report data in a supported dataformat.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_OUTPUT_BUFFERING', true);

require_once(__DIR__ . '/../../config.php');

require_login();

use local_dixeo\local\credit_report_request;
use local_dixeo\repository\credit_usage_repository;
use local_dixeo\service\credit_usage_report_service;

credit_report_request::require_access();
require_sesskey();

$dataformat = optional_param('dataformat', '', PARAM_ALPHA);
if ($dataformat === '') {
    redirect(new moodle_url('/local/dixeo/credit_report.php'));
}

$request = credit_report_request::from_globals();
$service = new credit_usage_report_service();
$filters = $request->to_filters($service);
$columns = $service->get_export_columns();
$recordids = $service->get_export_record_ids($filters);

\core\dataformat::download_data(
    clean_filename(get_string('credit_report', 'local_dixeo')),
    $dataformat,
    $columns,
    $recordids,
    static function(int $recordid) use ($service): ?array {
        global $DB;

        $record = $DB->get_record(credit_usage_repository::TABLE, ['id' => $recordid], '*', IGNORE_MISSING);
        if (!$record) {
            return null;
        }

        return $service->format_export_row($record);
    }
);
