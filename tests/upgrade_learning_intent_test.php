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
 * Tests for removing the stored values of the retired learning intent setting.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

/**
 * Learning intent rows and config are deleted, and nothing else is.
 *
 * @covers ::local_dixeo_remove_learning_intent
 */
final class upgrade_learning_intent_test extends \advanced_testcase {
    public function test_learning_intent_values_are_deleted_and_other_rows_kept(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        require_once($CFG->dirroot . '/local/dixeo/db/upgrade.php');
        $course = $this->getDataGenerator()->create_course();
        $contextid = \context_course::instance($course->id)->id;
        $DB->insert_record('local_dixeo_setting', (object) [
            'contextid' => $contextid,
            'component' => 'local_dixeo',
            'name' => 'learning_intent',
            'value' => 'Know the basics',
        ]);
        $DB->insert_record('local_dixeo_setting', (object) [
            'contextid' => $contextid,
            'component' => 'local_dixeo',
            'name' => 'filesync_pill',
            'value' => 'footer',
        ]);
        set_config('learning_intent', 'Site intent', 'local_dixeo');
        set_config('filesync_pill', 'hidden', 'local_dixeo');

        local_dixeo_remove_learning_intent();

        $this->assertFalse($DB->record_exists('local_dixeo_setting', ['name' => 'learning_intent']));
        $this->assertFalse(get_config('local_dixeo', 'learning_intent'));
        $this->assertTrue($DB->record_exists('local_dixeo_setting', [
            'contextid' => $contextid,
            'component' => 'local_dixeo',
            'name' => 'filesync_pill',
        ]));
        $this->assertSame('hidden', get_config('local_dixeo', 'filesync_pill'));
    }
}
