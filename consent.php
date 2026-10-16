<?php
// This file is part of Moodle - https://moodle.org/
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
 * Records Terms of Use acceptance and hosts the page where that choice is withdrawn.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_dixeo\service\consent_service;

require_login(null, false);
if (isguestuser()) {
    throw new moodle_exception('noguest');
}

$action = optional_param('action', '', PARAM_ALPHA);
if ($action !== '') {
    require_sesskey();
    local_dixeo_consent_handle($action);
}

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/dixeo/consent.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('consent_manage', 'local_dixeo'));
$PAGE->set_heading(get_string('consent_title', 'local_dixeo'));

$template = (new consent_service())->manage_context((int) $USER->id);
$PAGE->requires->js_call_amd('local_dixeo/consent_gate', 'bindSave');
$PAGE->requires->js_call_amd('local_dixeo/consent_gate', 'bindReview');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_dixeo/consent_manage', $template);
echo $OUTPUT->footer();

/**
 * Apply a posted choice and redirect.
 *
 * @param string $action accept, saveprocessing, or withdrawterms.
 */
function local_dixeo_consent_handle(string $action): void {
    global $USER;

    $service = new consent_service();
    $userid = (int) $USER->id;
    $manage = new moodle_url('/local/dixeo/consent.php');

    if ($action === 'saveprocessing') {
        $processing = (bool) optional_param('processing', 0, PARAM_BOOL);
        $terms = (bool) optional_param('terms', 0, PARAM_BOOL);
        if (!$processing || !$terms) {
            redirect(
                $manage,
                get_string('consent_error_terms_required', 'local_dixeo'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
        $service->set_processing($userid, true);
        redirect($manage, get_string('consent_saved_processing', 'local_dixeo'));
    }

    if ($action === 'withdrawterms') {
        $service->withdraw_terms($userid);
        redirect($manage, get_string('consent_withdrawn_terms', 'local_dixeo'));
    }

    if ($action !== 'accept') {
        throw new \invalid_parameter_exception('action');
    }

    $courseid = optional_param('courseid', 0, PARAM_INT);
    $returnurl = local_dixeo_consent_return_url($courseid);
    $version = optional_param('version', '', PARAM_TEXT);
    $terms = (bool) optional_param('terms', 0, PARAM_BOOL);

    if ($version !== consent_service::VERSION) {
        redirect($returnurl, get_string('consent_error_version', 'local_dixeo'), null, \core\output\notification::NOTIFY_ERROR);
    }
    $processing = (bool) optional_param('processing', 0, PARAM_BOOL);
    if (!$terms || !$processing) {
        redirect(
            $returnurl,
            get_string('consent_error_terms_required', 'local_dixeo'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    if ($courseid < 2) {
        throw new moodle_exception('invalidcourseid');
    }
    $course = get_course($courseid);
    if (!can_access_course($course)) {
        throw new moodle_exception('nopermissions', 'error', '', get_string('course'));
    }

    $service->accept($userid);
    redirect($returnurl);
}

/**
 * Local URL to return to after Continue.
 *
 * @param int $courseid Course id from the form.
 * @return moodle_url
 */
function local_dixeo_consent_return_url(int $courseid): moodle_url {
    $raw = optional_param('returnurl', '', PARAM_LOCALURL);
    if ($raw !== '') {
        return new moodle_url($raw);
    }
    if ($courseid > 1) {
        return new moodle_url('/course/view.php', ['id' => $courseid]);
    }
    return new moodle_url('/');
}
