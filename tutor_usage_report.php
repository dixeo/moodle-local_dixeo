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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Tutor usage report page for Dixeo plugin.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_dixeo\output\tutor_usage_report_request;
use local_dixeo\output\tutor_usage_report_page;
use local_dixeo\service\tutor_usage_report_service;

require_login();

$request = tutor_usage_report_request::from_globals();
$request->require_access();

$usesadminsetup = $request->level === tutor_usage_report_service::LEVEL_SITE
    && tutor_usage_report_service::can_view_site();

if ($usesadminsetup) {
    admin_externalpage_setup('local_dixeo_tutor_usage', '', $request->to_page_url_params());
    $context = context_system::instance();
    $PAGE->set_context($context);
    $PAGE->set_title(get_string('tutor_usage_report_nav', 'local_dixeo'));
    $PAGE->set_heading(get_string('tutor_usage_report_nav', 'local_dixeo'));
} else if ($request->level === tutor_usage_report_service::LEVEL_SITE) {
    $context = context_system::instance();
    $PAGE->set_context($context);
    $PAGE->set_url(new moodle_url('/local/dixeo/tutor_usage_report.php', $request->to_page_url_params()));
    $PAGE->set_pagelayout('report');
    $PAGE->set_title(get_string('tutor_usage_report_nav', 'local_dixeo'));
    $PAGE->set_heading(get_string('tutor_usage_report_nav', 'local_dixeo'));
} else {
    require_login($request->courseid);
    $context = context_course::instance($request->courseid);
    $PAGE->set_context($context);
    $PAGE->set_url(new moodle_url('/local/dixeo/tutor_usage_report.php', $request->to_page_url_params()));
    $PAGE->set_pagelayout('report');
    $PAGE->set_title(get_string('tutor_usage_report_nav', 'local_dixeo'));
    $PAGE->set_heading(format_string(get_course($request->courseid)->fullname));
    $PAGE->navbar->add(get_string('tutor_usage_report_nav', 'local_dixeo'));
}

$PAGE->requires->css(new moodle_url('/local/dixeo/styles.css'));
$PAGE->requires->js_call_amd('local_dixeo/tutor_usage_report', 'init');

$report = new tutor_usage_report_page($request->to_renderable_params());

$output = $PAGE->get_renderer('local_dixeo');
echo $output->header();
echo $output->render($report);
echo $output->footer();
