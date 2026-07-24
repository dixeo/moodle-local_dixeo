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
 * Credit report page for Dixeo plugin.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_dixeo\output\credit_report_page;
use local_dixeo\service\credit_usage_report_service;

require_login();
$context = context_system::instance();
if (
    !has_capability('local/dixeo:manage', $context)
    && !has_capability('local/dixeo:viewusage', $context)
) {
    require_capability('local/dixeo:manage', $context);
}

$view = optional_param('view', credit_usage_report_service::VIEW_WEEK, PARAM_ALPHA);
$anchor = optional_param('anchor', '', PARAM_TEXT);
$datefromraw = optional_param('datefrom', '', PARAM_TEXT);
$datetoraw = optional_param('dateto', '', PARAM_TEXT);
$datefrom = $datefromraw !== '' ? strtotime($datefromraw . ' 00:00:00') : 0;
$dateto = $datetoraw !== '' ? strtotime($datetoraw . ' 23:59:59') : 0;
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 50, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$creditsmin = optional_param('creditsmin', '', PARAM_RAW_TRIMMED);
$creditsmax = optional_param('creditsmax', '', PARAM_RAW_TRIMMED);
$components = optional_param_array('component', [], PARAM_ALPHANUMEXT);
$jobtypes = optional_param_array('jobtype', [], PARAM_ALPHANUMEXT);
$moduletypes = optional_param_array('moduletype', [], PARAM_ALPHANUMEXT);

$params = array_filter([
    'view' => $view,
    'anchor' => $anchor ?: null,
    'datefrom' => $datefrom ?: null,
    'dateto' => $dateto ?: null,
    'page' => $page ?: null,
    'perpage' => $perpage !== 50 ? $perpage : null,
    'userid' => $userid ?: null,
    'courseid' => $courseid ?: null,
    'creditsmin' => $creditsmin !== '' ? $creditsmin : null,
    'creditsmax' => $creditsmax !== '' ? $creditsmax : null,
], static fn($value) => $value !== null && $value !== '');

foreach ($components as $component) {
    $params['component'][] = $component;
}
foreach ($jobtypes as $jobtype) {
    $params['jobtype'][] = $jobtype;
}
foreach ($moduletypes as $moduletype) {
    $params['moduletype'][] = $moduletype;
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/dixeo/credit_report.php', $params));
$PAGE->set_title(get_string('credit_report', 'local_dixeo'));
$PAGE->set_heading(get_string('credit_report', 'local_dixeo'));
$PAGE->set_pagelayout('admin');
$PAGE->requires->js_call_amd('local_dixeo/credit_report', 'init');

$PAGE->navbar->add(get_string('pluginname', 'local_dixeo'), new moodle_url('/admin/settings.php', ['section' => 'local_dixeo']));
$PAGE->navbar->add(get_string('credit_report', 'local_dixeo'));

$report = new credit_report_page([
    'view' => $view,
    'anchor' => $anchor,
    'datefrom' => $datefrom,
    'dateto' => $dateto,
    'page' => $page,
    'perpage' => $perpage,
    'userid' => $userid,
    'courseid' => $courseid,
    'creditsmin' => $creditsmin,
    'creditsmax' => $creditsmax,
    'components' => $components,
    'jobtypes' => $jobtypes,
    'moduletypes' => $moduletypes,
]);

$output = $PAGE->get_renderer('local_dixeo');
echo $output->header();
echo $output->render($report);
echo $output->footer();
