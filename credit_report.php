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
require_once($CFG->libdir . '/adminlib.php');

use local_dixeo\output\credit_report_request;
use local_dixeo\output\credit_report_page;

$request = credit_report_request::from_globals();

admin_externalpage_setup('local_dixeo_credit_usage', '', $request->to_page_url_params());

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_title(get_string('credit_report', 'local_dixeo'));
$PAGE->set_heading(get_string('credit_report', 'local_dixeo'));
$PAGE->requires->css(new moodle_url('/local/dixeo/styles.css'));
$PAGE->requires->js_call_amd('local_dixeo/credit_report', 'init');

$report = new credit_report_page($request->to_renderable_params());

$output = $PAGE->get_renderer('local_dixeo');
echo $output->header();
echo $output->render($report);
echo $output->footer();
