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
 * Tests for assign_submission_reader.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\service\assign_submission_reader
 */

namespace local_dixeo;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for assign submission reader.
 */
final class assign_submission_reader_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    public function test_get_assignment_context_returns_name_intro_activity(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'name' => 'Reader essay',
            'intro' => '<p>Intro text</p>',
            'introformat' => FORMAT_HTML,
        ]);
        $DB->set_field('assign', 'activity', '<p>Do the work</p>', ['id' => $assign->id]);

        $reader = new \local_dixeo\service\assign_submission_reader();
        $ctx = $reader->get_assignment_context((int) $assign->cmid, (int) $course->id);

        $this->assertSame('Reader essay', $ctx['name']);
        $this->assertStringContainsString('Intro text', $ctx['intro']);
        $this->assertStringContainsString('Do the work', $ctx['activity']);
        $this->assertSame('simple', $ctx['grading_method']);
        $this->assertSame((int) $assign->id, $ctx['assignmentid']);
        $this->assertSame([], $ctx['criteria']);
    }

    public function test_require_assign_cm_rejects_other_course(): void {
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);

        $reader = new \local_dixeo\service\assign_submission_reader();

        $this->expectException(\moodle_exception::class);
        $reader->require_assign_cm((int) $assign->cmid, (int) $other->id);
    }

    public function test_require_assign_cm_rejects_missing_cm(): void {
        $reader = new \local_dixeo\service\assign_submission_reader();

        $this->expectException(\dml_missing_record_exception::class);
        $reader->require_assign_cm(999999999);
    }

    public function test_get_submission_data_includes_onlinetext(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'assignsubmission_onlinetext_enabled' => 1,
            'assignsubmission_file_enabled' => 0,
        ]);

        /** @var \mod_assign_generator $assigngen */
        $assigngen = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $assigngen->create_submission([
            'cmid' => $assign->cmid,
            'userid' => $student->id,
            'onlinetext' => '<p>My draft answer</p>',
        ]);

        $reader = new \local_dixeo\service\assign_submission_reader();
        $data = $reader->get_submission_data((int) $assign->cmid, (int) $student->id, (int) $course->id);

        $this->assertNotNull($data['submission']);
        $this->assertStringContainsString('My draft answer', $data['onlinetext']);
        $this->assertTrue($data['submission_plugins']['onlinetext_enabled']);
        $this->assertFalse($data['submission_plugins']['file_enabled']);
        $this->assertSame([], $data['submission_files']);

        $block = $reader->build_submission_block_for_ai((int) $assign->id, $data);
        $this->assertArrayHasKey('content', $block);
        $this->assertStringContainsString('My draft answer', $block['content']);
        $this->assertNull($block['warning']);
    }

    public function test_validate_submission_files_skips_unsupported_extension(): void {
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $context = \context_module::instance($assign->cmid);

        $fs = get_file_storage();
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'assignsubmission_file',
            'filearea' => 'submission_files',
            'itemid' => 1,
            'filepath' => '/',
            'filename' => 'notes.docx',
        ];
        $docx = $fs->create_file_from_string($filerecord, 'not-a-real-docx');

        $filerecord['filename'] = 'ok.txt';
        $txt = $fs->create_file_from_string($filerecord, 'plain text body');

        $reader = new \local_dixeo\service\assign_submission_reader();
        $result = $reader->validate_submission_files_for_upload([$docx, $txt]);

        $this->assertCount(1, $result['valid']);
        $this->assertSame('ok.txt', $result['valid'][0]->get_filename());
        $this->assertSame(['notes.docx'], $result['notreadable']);
    }

    public function test_validate_plugins_fails_when_neither_enabled(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'assignsubmission_onlinetext_enabled' => 0,
            'assignsubmission_file_enabled' => 0,
        ]);

        // Generator may still leave plugin rows; force both disabled.
        $DB->set_field('assign_plugin_config', 'value', '0', [
            'assignment' => $assign->id,
            'plugin' => 'onlinetext',
            'subtype' => 'assignsubmission',
            'name' => 'enabled',
        ]);
        $DB->set_field('assign_plugin_config', 'value', '0', [
            'assignment' => $assign->id,
            'plugin' => 'file',
            'subtype' => 'assignsubmission',
            'name' => 'enabled',
        ]);

        $reader = new \local_dixeo\service\assign_submission_reader();
        $err = $reader->validate_submission_plugins_for_analysis((int) $assign->id);

        $this->assertNotNull($err);
        $this->assertStringContainsString('online text', strtolower($err));
    }
}
