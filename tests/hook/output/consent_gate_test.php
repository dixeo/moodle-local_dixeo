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
use local_dixeo\service\consent_service;

/**
 * The course lock is shown only until the current terms are accepted.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_dixeo\hook\output\consent_gate
 */
final class consent_gate_test extends \advanced_testcase {
    /**
     * A Dixeo course locks the page with an unticked terms box and a disabled Continue button.
     */
    public function test_gate_renders_until_terms_are_accepted(): void {
        global $PAGE;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->open_course($user, $course);

        $headers = new before_http_headers($PAGE->get_renderer('core'));
        consent_gate::lock_body($headers);
        $this->assertStringContainsString('local-dixeo-consent-locked', $PAGE->bodyclasses);

        $hook = new before_standard_top_of_body_html_generation($PAGE->get_renderer('core'));
        consent_gate::callback($hook);
        $html = $hook->get_output();

        $this->assertStringContainsString('data-region="local-dixeo-consent-lock"', $html);
        $this->assertStringContainsString(get_string('consent_title', 'local_dixeo'), $html);
        $this->assertStringContainsString(get_string('consent_terms_heading', 'local_dixeo'), $html);
        $this->assertStringContainsString(get_string('consent_privacy_heading', 'local_dixeo'), $html);
        $this->assertStringContainsString(get_string('consent_processing_heading', 'local_dixeo'), $html);
        $this->assertStringContainsString('name="terms" id="local-dixeo-consent-terms" value="1" required', $html);
        $this->assertStringContainsString(
            'name="processing" id="local-dixeo-consent-processing" value="1" required',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/id="local-dixeo-consent-continue"\s+disabled/',
            $html
        );
        $this->assertStringContainsString(get_string('consent_exit', 'local_dixeo'), $html);
        $this->assertStringNotContainsString('checked', $html);
        $this->assertStringNotContainsString('data-action="hide"', $html);
        $this->assertStringContainsString('local_dixeo/consent_gate', $PAGE->requires->get_end_code());

        (new consent_service())->accept((int) $user->id);
        $again = new before_standard_top_of_body_html_generation($PAGE->get_renderer('core'));
        consent_gate::callback($again);
        $this->assertSame('', $again->get_output());
    }

    /**
     * Embedded frames do not get a second lock.
     */
    public function test_embedded_layout_is_skipped(): void {
        global $PAGE;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->open_course($user, $course);
        $PAGE->set_pagelayout('embedded');

        $headers = new before_http_headers($PAGE->get_renderer('core'));
        consent_gate::lock_body($headers);
        $this->assertStringNotContainsString('local-dixeo-consent-locked', $PAGE->bodyclasses);

        $hook = new before_standard_top_of_body_html_generation($PAGE->get_renderer('core'));
        consent_gate::callback($hook);
        $this->assertSame('', $hook->get_output());
    }

    /**
     * Prepare a course page for one user.
     *
     * @param \stdClass $user User.
     * @param \stdClass $course Course.
     */
    private function open_course(\stdClass $user, \stdClass $course): void {
        global $COURSE, $PAGE;

        $this->resetAfterTest();
        $this->setUser($user);
        $context = \context_course::instance($course->id);
        $COURSE = $course;
        $PAGE->set_course($course);
        $PAGE->set_context($context);
        $PAGE->set_pagelayout('course');
        $PAGE->set_url(new \moodle_url('/course/view.php', ['id' => $course->id]));
    }
}
