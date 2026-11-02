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

namespace local_dixeo\dsl;

use local_dixeo\dsl\actions\create_module_action;
use local_dixeo\dsl\actions\create_questions_action;
use mod_quiz\question\bank\qbank_helper;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/local/dixeo/db/upgrade.php');

/**
 * Verifies generated quizzes keep a question category tree that survives a course backup and restore.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\dsl\actions\create_questions_action
 * @covers     ::local_dixeo_repair_question_categories
 */
final class quiz_question_category_test extends \advanced_testcase {
    public function test_generated_quiz_questions_sit_under_the_top_category_and_restore(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $cmid] = $this->generate_quiz();
        $contextid = \context_module::instance($cmid)->id;

        $top = question_get_top_category($contextid);
        $default = question_get_default_category($contextid);
        $this->assertSame('top', $top->name);
        $this->assertEquals($top->id, $default->parent);
        $this->assertEquals(2, $DB->count_records('question_bank_entries', ['questioncategoryid' => $default->id]));

        $this->assert_restored_quiz_has_questions($this->backup_and_restore((int) $course->id), 2);
    }

    public function test_repair_moves_a_broken_category_under_a_new_top_category(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $cmid] = $this->generate_quiz();
        $contextid = \context_module::instance($cmid)->id;

        // Recreate what quiz generation used to leave behind: no top category, an orphan default one.
        $category = question_get_default_category($contextid);
        $DB->delete_records('question_categories', ['id' => $category->parent]);
        $DB->update_record('question_categories', (object) [
            'id' => $category->id,
            'parent' => 0,
            'name' => '[[defaultcategory]]',
        ]);

        local_dixeo_repair_question_categories();

        $top = question_get_top_category($contextid);
        $repaired = $DB->get_record('question_categories', ['id' => $category->id], '*', MUST_EXIST);
        $this->assertSame('top', $top->name);
        $this->assertEquals($top->id, $repaired->parent);
        $this->assertStringNotContainsString('[[', $repaired->name);
        $this->assertEquals(2, $DB->count_records('question_categories', ['contextid' => $contextid]));
        $this->assertEquals(2, $DB->count_records('question_bank_entries', ['questioncategoryid' => $category->id]));

        $this->assert_restored_quiz_has_questions($this->backup_and_restore((int) $course->id), 2);

        // A second run finds nothing left to repair.
        local_dixeo_repair_question_categories();
        $this->assertEquals(2, $DB->count_records('question_categories', ['contextid' => $contextid]));
    }

    /**
     * Generate a quiz with two questions through the DSL actions.
     *
     * @return array The course and the quiz course module id.
     */
    private function generate_quiz(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1], '*', MUST_EXIST);
        $context = [
            'courseid' => (int) $course->id,
            'sectionid' => (int) $section->id,
            'sectionnum' => 1,
            'modulename' => 'quiz',
            'userid' => 2,
            'contextid' => \context_course::instance($course->id)->id,
        ];

        $module = (new create_module_action())->execute(
            ['fields' => ['name' => ['source' => '$.name'], 'intro' => ['source' => '$.intro']]],
            new value_resolver(['name' => 'Generated quiz', 'intro' => 'Intro', 'introformat' => FORMAT_HTML], [], $context)
        );

        $questions = [
            ['question' => 'Q1?', 'answers' => ['A', 'B'], 'rightanswer' => 0],
            ['question' => 'Q2?', 'answers' => ['C', 'D'], 'rightanswer' => 1],
        ];
        $resolver = new value_resolver(['questions' => $questions], [], $context);
        set_debugging(DEBUG_NONE);
        (new create_questions_action())->execute([
            'module_ref' => '$module',
            'foreach' => '$.questions',
            'question_type' => 'singlechoice',
            'fields' => [
                'questiontext' => ['source' => '$.question'],
                'answers' => ['source' => '$.answers'],
                'correct_answer' => ['source' => '$.rightanswer'],
            ],
        ], $resolver->with_variable('module', $module));
        set_debugging(DEBUG_DEVELOPER);

        return [$course, (int) $module['cmid']];
    }

    /**
     * Back up a course and restore it as a new course, as a course shared through a library is.
     *
     * @param int $courseid The course to back up.
     * @return int The restored course id.
     */
    private function backup_and_restore(int $courseid): int {
        global $CFG, $DB, $USER;

        $CFG->keeptempdirectoriesonbackup = true;
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $courseid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        $categoryid = $DB->get_field_sql('SELECT MIN(id) FROM {course_categories}');
        $newcourseid = \restore_dbops::create_new_course('Restored', 'restored', $categoryid);
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return (int) $newcourseid;
    }

    /**
     * Assert the single quiz of a restored course holds the expected number of questions.
     *
     * @param int $courseid The restored course id.
     * @param int $expected The expected question count.
     */
    private function assert_restored_quiz_has_questions(int $courseid, int $expected): void {
        $quizzes = array_values(get_fast_modinfo($courseid)->get_instances_of('quiz'));
        $this->assertCount(1, $quizzes);
        $structure = qbank_helper::get_question_structure($quizzes[0]->instance, $quizzes[0]->context);
        $this->assertCount($expected, $structure);
    }
}
