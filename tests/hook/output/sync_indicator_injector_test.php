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

namespace local_dixeo\hook\output;

use core\hook\output\before_standard_top_of_body_html_generation;
use local_dixeo\service\file_sync_policy;

/**
 * The file sync pill opens the course settings modal on Status.
 *
 * @covers \local_dixeo\hook\output\sync_indicator_injector
 */
final class sync_indicator_injector_test extends \advanced_testcase {
    /**
     * A course with a sync block gets a pill that opens the Status tab, and no dropdown.
     */
    public function test_pill_opens_the_status_tab(): void {
        global $COURSE, $DB, $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();

        if (!file_sync_policy::get_sync_block_names()) {
            $this->markTestSkipped('No sync blocks installed in this environment.');
        }

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $DB->insert_record('block_instances', (object) [
            'blockname' => file_sync_policy::get_sync_block_names()[0],
            'parentcontextid' => $context->id,
            'showinsubcontexts' => 0,
            'pagetypepattern' => '*',
            'defaultregion' => 'side-pre',
            'defaultweight' => 0,
            'configdata' => '',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $COURSE = $course;
        $PAGE->set_course($course);
        $PAGE->set_context($context);
        $PAGE->set_pagelayout('course');

        $hook = new before_standard_top_of_body_html_generation($PAGE->get_renderer('core'));
        sync_indicator_injector::callback($hook);
        $html = $hook->get_output();

        $this->assertStringContainsString('data-action="local-dixeo-settings-open"', $html);
        $this->assertStringContainsString('data-initial="status"', $html);
        $this->assertStringContainsString('data-contextid="' . $context->id . '"', $html);
        $this->assertStringContainsString('data-courseid="' . $course->id . '"', $html);
        $this->assertStringContainsString('data-canmanagesync="1"', $html);
        $this->assertStringContainsString('data-course-name="' . s($course->fullname) . '"', $html);
        $this->assertStringNotContainsString('dixeo-sync-dropdown', $html);

        $end = $PAGE->requires->get_end_code();
        $this->assertStringContainsString('local_dixeo/settings_modal', $end);
        $this->assertStringContainsString('local_dixeo/sync_indicator', $end);
    }
}
