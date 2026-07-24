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

require_login();

use local_dixeo\local\credit_report_request;
use local_dixeo\output\credit_report_page;

credit_report_request::require_access();

$request = credit_report_request::from_globals();
$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/dixeo/credit_report.php', $request->to_page_url_params()));
$PAGE->set_title(get_string('credit_report', 'local_dixeo'));
$PAGE->set_heading(get_string('credit_report', 'local_dixeo'));
$PAGE->set_pagelayout('admin');
$PAGE->requires->css(new moodle_url('/local/dixeo/styles.css'));
$PAGE->requires->js_call_amd('local_dixeo/credit_report', 'init');

$PAGE->navbar->add(get_string('pluginname', 'local_dixeo'), new moodle_url('/admin/settings.php', ['section' => 'local_dixeo']));
$PAGE->navbar->add(get_string('credit_report', 'local_dixeo'));

$report = new credit_report_page($request->to_renderable_params());

$output = $PAGE->get_renderer('local_dixeo');
echo $output->header();
echo $output->render($report);
echo $output->footer();
