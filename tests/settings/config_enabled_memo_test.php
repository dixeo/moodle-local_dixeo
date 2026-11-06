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
 * Tests for the course memo of {@see \local_dixeo\settings\config::enabled_for()}.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\settings;

/**
 * The course layer of the master switch is read once per request, and writes are visible at once.
 *
 * @covers \local_dixeo\settings\config
 */
final class config_enabled_memo_test extends \advanced_testcase {
    /**
     * A second call for the same course does no database read.
     */
    public function test_second_call_for_the_same_course_reads_nothing(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $config = new config();

        $this->assertTrue($config->enabled_for(\context_course::instance((int) $course->id)));
        $modulecontext = \context_module::instance((int) $page->cmid);
        $reads = $DB->perf_get_reads();
        $this->assertTrue($config->enabled_for($modulecontext));
        $this->assertSame(0, $DB->perf_get_reads() - $reads);
    }

    /**
     * A course write of Off is visible on the next call.
     */
    public function test_course_off_is_visible_at_once(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance((int) $course->id);
        $config = new config();

        $this->assertTrue($config->enabled_for($context));
        $config->set('local_dixeo', 'enabled', $context, '0');
        $this->assertFalse($config->enabled_for($context));
        $config->set('local_dixeo', 'enabled', $context, '1');
        $this->assertTrue($config->enabled_for($context));
    }

    /**
     * A Dixeo layer change through set_config() is visible on the next call.
     */
    public function test_site_off_through_set_config_is_visible_at_once(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance((int) $course->id);
        $config = new config();

        $this->assertTrue($config->enabled_for($context));
        set_config('enabled', '0', 'local_dixeo');
        $this->assertFalse($config->enabled_for($context));
        set_config('enabled', '1', 'local_dixeo');
        $this->assertTrue($config->enabled_for($context));
    }

    /**
     * A course value written by another process is seen once the memo expires.
     */
    public function test_memo_expires(): void {
        global $DB;

        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance((int) $course->id);
        $config = new config();

        $this->assertTrue($config->enabled_for($context));
        $DB->insert_record(config::TABLE, (object) [
            'contextid' => $context->id,
            'component' => 'local_dixeo',
            'name' => 'enabled',
            'value' => '0',
        ]);
        $this->assertTrue($config->enabled_for($context));

        $clock->bump(31);
        $this->assertFalse($config->enabled_for($context));
    }
}
