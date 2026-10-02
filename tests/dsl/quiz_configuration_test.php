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
use mod_quiz\question\display_options;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * Verifies quiz module and question are correctly configured.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\dsl\actions\create_module_action
 * @covers     \local_dixeo\dsl\actions\create_questions_action
 */
final class quiz_configuration_test extends \advanced_testcase {
    public function test_quiz_review_maxmarks_and_question_marks_sum_to_100(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1], '*', MUST_EXIST);

        $context = [
            'courseid' => (int) $course->id,
            'sectionid' => (int) $section->id,
            'sectionnum' => 1,
            'modulename' => 'quiz',
            'userid' => 2,
            'contextid' => \context_course::instance($course->id)->id,
        ];

        $moduleresolver = new value_resolver([
            'name' => 'Test quiz',
            'intro' => 'Intro',
            'introformat' => FORMAT_HTML,
        ], [], $context);

        $moduleaction = new create_module_action();
        $module = $moduleaction->execute(['fields' => [
            'name' => ['source' => '$.name'],
            'intro' => ['source' => '$.intro'],
        ]], $moduleresolver);

        $quiz = $DB->get_record('quiz', ['id' => $module['id']], '*', MUST_EXIST);
        $this->assertEqualsWithDelta(100.0, (float) $quiz->sumgrades, 0.001);
        $this->assertEqualsWithDelta(100.0, (float) $quiz->grade, 0.001);

        $expectedmaxmarks = display_options::IMMEDIATELY_AFTER | display_options::LATER_WHILE_OPEN;
        $this->assertSame($expectedmaxmarks, (int) $quiz->reviewmaxmarks & $expectedmaxmarks);

        $questions = [
            ['question' => 'Q1?', 'answers' => ['A', 'B'], 'rightanswer' => 0],
            ['question' => 'Q2?', 'answers' => ['C', 'D'], 'rightanswer' => 1],
            ['question' => 'Q3?', 'answers' => ['E', 'F'], 'rightanswer' => 0],
            ['question' => 'Q4?', 'answers' => ['G', 'H'], 'rightanswer' => 1],
            ['question' => 'Q5?', 'answers' => ['I', 'J'], 'rightanswer' => 0],
        ];

        $questionresolver = new value_resolver(['questions' => $questions], [], $context);
        $questionaction = new create_questions_action();
        set_debugging(DEBUG_NONE);
        $questionaction->execute([
            'module_ref' => '$module',
            'foreach' => '$.questions',
            'question_type' => 'singlechoice',
            'fields' => [
                'questiontext' => ['source' => '$.question'],
                'answers' => ['source' => '$.answers'],
                'correct_answer' => ['source' => '$.rightanswer'],
            ],
        ], $questionresolver->with_variable('module', $module));

        $slots = $DB->get_records('quiz_slots', ['quizid' => $quiz->id]);
        $this->assertCount(5, $slots);
        $marksum = 0.0;
        foreach ($slots as $slot) {
            $this->assertEqualsWithDelta(20.0, (float) $slot->maxmark, 0.001);
            $marksum += (float) $slot->maxmark;
        }
        $this->assertEqualsWithDelta(100.0, $marksum, 0.001);

        $gradeitem = \grade_item::fetch([
            'courseid' => $course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'iteminstance' => $quiz->id,
            'itemnumber' => 0,
        ]);
        $this->assertNotFalse($gradeitem);
        $this->assertEqualsWithDelta(50.0, (float) $gradeitem->gradepass, 0.001);
    }
}
