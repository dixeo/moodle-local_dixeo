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

namespace local_dixeo\hook\output;

use core\hook\output\before_http_headers;
use core\hook\output\before_standard_top_of_body_html_generation;
use local_dixeo\service\consent_notice;
use local_dixeo\service\consent_service;

/**
 * Locks a Dixeo course until the user accepts the current Terms of Use.
 *
 * The dialog has no close control. Exit leaves without recording a choice.
 * Continue stays disabled until both boxes are ticked.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class consent_gate {
    /** @var string[] Layouts that must not trap an embedded frame. */
    private const SKIP_LAYOUTS = ['embedded', 'popup', 'print', 'frametop', 'secure', 'maintenance'];

    /**
     * Stop the page from scrolling before the body tag is printed.
     *
     * Body classes cannot be added once header output has started.
     *
     * @param before_http_headers $hook The hook instance.
     */
    public static function lock_body(before_http_headers $hook): void {
        global $PAGE;

        if (self::courseid() === null) {
            return;
        }
        $PAGE->add_body_class('local-dixeo-consent-locked');
    }

    /**
     * Inject the lock at the top of the body when this user still needs the current terms.
     *
     * @param before_standard_top_of_body_html_generation $hook The hook instance.
     */
    public static function callback(before_standard_top_of_body_html_generation $hook): void {
        global $PAGE;

        $courseid = self::courseid();
        if ($courseid === null) {
            return;
        }

        $notice = new consent_notice();
        $context = $notice->template_context();
        $context['actionurl'] = (new \moodle_url('/local/dixeo/consent.php'))->out(false);
        $context['sesskey'] = sesskey();
        $context['courseid'] = $courseid;
        $context['returnurl'] = self::return_url($courseid);
        $context['exiturl'] = (new \moodle_url('/'))->out(false);

        $PAGE->requires->js_call_amd('local_dixeo/consent_gate', 'init');
        $hook->add_html($PAGE->get_renderer('core')->render_from_template('local_dixeo/consent_gate', $context));
    }

    /**
     * Course id when this page must show the terms lock, otherwise null.
     *
     * @return int|null
     */
    private static function courseid(): ?int {
        global $PAGE, $USER;

        if (!isloggedin() || isguestuser()) {
            return null;
        }
        if (in_array($PAGE->pagelayout, self::SKIP_LAYOUTS, true)) {
            return null;
        }
        if (!$PAGE->context instanceof \context) {
            return null;
        }

        $service = new consent_service();
        if (!$service->gate_required((int) $USER->id, $PAGE->context)) {
            return null;
        }

        $coursecontext = $PAGE->context->get_course_context(false);
        if (!$coursecontext instanceof \context_course) {
            return null;
        }

        return (int) $coursecontext->instanceid;
    }

    /**
     * Local URL to reopen after the user continues.
     *
     * @param int $courseid Course id.
     * @return string
     */
    private static function return_url(int $courseid): string {
        global $PAGE;

        try {
            return $PAGE->url->out_as_local_url(false);
        } catch (\moodle_exception $e) {
            return (new \moodle_url('/course/view.php', ['id' => $courseid]))->out_as_local_url(false);
        }
    }
}
