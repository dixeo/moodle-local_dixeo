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

namespace local_dixeo\dsl;

use local_dixeo\dsl\actions\create_module_action;
use local_dixeo\dsl\actions\create_questions_action;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * Tests for generated quiz questions built from `choices`, per-option feedback and negative grading.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\dsl\actions\create_questions_action
 */
final class create_questions_choices_test extends \advanced_testcase {
    /** @var string HTML payloads that must never be stored as they are. */
    private const XSS = '<script>alert(1)</script><img src=x onerror=alert(1)><a href="javascript:alert(1)">x</a>'
        . '<strong>kept</strong>';

    /** @var string SVG animation and form payloads that must never be stored as they are. */
    private const ACTIVE = '<svg><a><animate attributeName="href" values="javascript:alert(1)"/><text>a</text></a></svg>'
        . '<svg><a><set attributeName="href" to="javascript:alert(1)"/><text>b</text></a></svg>'
        . '<form action="https://example.com"><input name="p"></form>';

    public function test_choices_store_six_options_feedback_and_negative_fractions(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [, $ids] = $this->generate([$this->multichoice(6, [0, 2, 4])]);

        $questionid = $ids[0];
        $answers = array_values($DB->get_records('question_answers', ['question' => $questionid], 'id'));
        $this->assertCount(6, $answers);
        $options = $DB->get_record('qtype_multichoice_options', ['questionid' => $questionid], '*', MUST_EXIST);
        $this->assertEquals(0, $options->single);
        $fractions = array_map(static fn($answer): string => sprintf('%.7f', $answer->fraction), $answers);
        $this->assertSame(['0.3333333', '-0.3333333', '0.3333333', '-0.3333333', '0.3333333', '-0.3333333'], $fractions);
        foreach ($answers as $index => $answer) {
            $this->assertSame('Option ' . $index, $answer->answer);
            $this->assertSame('<p>Feedback ' . $index . '</p>', $answer->feedback);
        }
        $this->assertSame('<p>Partially right</p>', $options->partiallycorrectfeedback);
        $this->assertSame('<p>Wrong</p>', $options->incorrectfeedback);
    }

    public function test_grading_a_six_option_three_correct_question(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $all = ['Option 0', 'Option 1', 'Option 2', 'Option 3', 'Option 4', 'Option 5'];
        $this->assertEqualsWithDelta(0.0, $this->grade_response($all), 0.0001);
        $this->assertEqualsWithDelta(1.0, $this->grade_response(['Option 0', 'Option 2', 'Option 4']), 0.0001);
        $partial = $this->grade_response(['Option 0', 'Option 2', 'Option 1']);
        $this->assertGreaterThan(0.0, $partial);
        $this->assertLessThan(1.0, $partial);
        $this->assertEqualsWithDelta(0.0, $this->grade_response(['Option 1', 'Option 3', 'Option 5']), 0.0001);
    }

    public function test_legacy_payload_keeps_answers_and_now_grades_distractors_negatively(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [, $ids] = $this->generate([
            ['question_type' => 'singlechoice', 'question' => 'Q1', 'answers' => ['A', 'B', 'C'], 'rightanswer' => 1,
                'wrong_feedback' => 'W'],
            ['question_type' => 'multichoice', 'question' => 'Q2', 'answers' => ['A', 'B', 'C', 'D'], 'rightanswer' => [0, 3],
                'wrong_feedback' => 'W'],
        ]);

        $this->assertCount(2, $ids);
        $first = array_values($DB->get_records('question_answers', ['question' => $ids[0]], 'id'));
        $this->assertSame(['A', 'B', 'C'], array_column($first, 'answer'));
        $this->assertSame([0.0, 1.0, 0.0], array_map(static fn($a): float => (float) $a->fraction, $first));
        $this->assertEquals(1, $DB->get_field('qtype_multichoice_options', 'single', ['questionid' => $ids[0]]));

        $second = array_values($DB->get_records('question_answers', ['question' => $ids[1]], 'id'));
        $this->assertSame([0.5, -0.5, -0.5, 0.5], array_map(static fn($a): float => (float) $a->fraction, $second));
        // No partial_feedback in the payload: the partially correct text falls back to wrong_feedback.
        $this->assertSame('W', $DB->get_field('qtype_multichoice_options', 'partiallycorrectfeedback', ['questionid' => $ids[1]]));
    }

    public function test_legacy_all_correct_question_keeps_positive_fractions(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [, $ids] = $this->generate([
            ['question_type' => 'multichoice', 'question' => 'Q', 'answers' => ['A', 'B', 'C'], 'rightanswer' => [0, 1, 2],
                'wrong_feedback' => 'W'],
        ]);

        $fractions = array_map(
            static fn($a): float => (float) $a->fraction,
            array_values($DB->get_records('question_answers', ['question' => $ids[0]], 'id'))
        );
        $this->assertSame([0.3333333, 0.3333333, 0.3333333], $fractions);
    }

    public function test_ten_choices_are_capped_at_eight(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [, $ids] = $this->generate([$this->multichoice(10, [1, 8])]);

        $answers = array_values($DB->get_records('question_answers', ['question' => $ids[0]], 'id'));
        $this->assertCount(8, $answers);
        $this->assertCount(2, array_filter($answers, static fn($a): bool => $a->fraction > 0));
    }

    public function test_all_correct_choices_fall_back_to_the_legacy_fields(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $question = $this->multichoice(4, [0, 1, 2, 3]);
        $question['answers'] = ['L0', 'L1', 'L2'];
        $question['rightanswer'] = [2];
        [, $ids] = $this->generate([$question]);

        $answers = array_values($DB->get_records('question_answers', ['question' => $ids[0]], 'id'));
        $this->assertSame(['L0', 'L1', 'L2'], array_column($answers, 'answer'));
        $this->assertSame([-0.5, -0.5, 1.0], array_map(static fn($a): float => (float) $a->fraction, $answers));
    }

    public function test_singlechoice_label_with_two_correct_choices_is_stored_as_multiple_answer(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $question = $this->multichoice(4, [1, 3]);
        $question['question_type'] = 'singlechoice';
        [, $ids] = $this->generate([$question]);

        $this->assertEquals(0, $DB->get_field('qtype_multichoice_options', 'single', ['questionid' => $ids[0]]));
        $this->assertSame(2, $DB->count_records_select(
            'question_answers',
            'question = ? AND fraction > 0.49 AND fraction < 0.51',
            [$ids[0]]
        ));
    }

    public function test_legacy_payload_with_nine_answers_keeps_todays_fractions(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $answers = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
        [, $ids] = $this->generate([
            ['question_type' => 'multichoice', 'question' => 'Q', 'answers' => $answers, 'rightanswer' => [0, 4],
                'wrong_feedback' => 'W'],
        ]);

        $fractions = array_map(
            static fn($a): float => (float) $a->fraction,
            array_values($DB->get_records('question_answers', ['question' => $ids[0]], 'id'))
        );
        $this->assertCount(9, $fractions);
        $this->assertSame([0.5, 0.0, 0.0, 0.0, 0.5, 0.0, 0.0, 0.0, 0.0], $fractions);
    }

    public function test_truefalse_choices_feedback_is_stored(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [, $ids] = $this->generate([[
            'question_type' => 'truefalse',
            'question' => 'The sky is green.',
            'answers' => ['True', 'False'],
            'rightanswer' => 1,
            'choices' => [
                ['text' => 'True', 'correct' => false, 'feedback' => 'Not green.'],
                ['text' => 'False', 'correct' => true, 'feedback' => 'Indeed.'],
            ],
            'wrong_feedback' => 'W',
        ]]);

        $answers = $DB->get_records_menu('question_answers', ['question' => $ids[0]], 'id', 'answer, feedback');
        $this->assertSame('Not green.', $answers[get_string('true', 'qtype_truefalse')]);
        $this->assertSame('Indeed.', $answers[get_string('false', 'qtype_truefalse')]);
    }

    public function test_svg_animation_and_form_payloads_are_not_stored(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $question = $this->multichoice(3, [0]);
        $question['choices'][0]['text'] = 'Choice ' . self::ACTIVE;
        $question['choices'][1]['feedback'] = 'Feedback ' . self::ACTIVE;
        [, $ids] = $this->generate([$question]);

        foreach ($DB->get_records('question_answers', ['question' => $ids[0]]) as $answer) {
            foreach ([$answer->answer, $answer->feedback] as $stored) {
                $this->assertStringNotContainsStringIgnoringCase('javascript:', $stored);
                $this->assertStringNotContainsStringIgnoringCase('<animate', $stored);
                $this->assertStringNotContainsStringIgnoringCase('<set', $stored);
                $this->assertStringNotContainsStringIgnoringCase('<form', $stored);
            }
        }
    }

    public function test_generated_html_is_cleaned_in_every_stored_question_part(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $choice = $this->multichoice(3, [0, 1]);
        $choice['question'] = 'Question ' . self::XSS;
        $choice['choices'][0]['text'] = 'Choice ' . self::XSS;
        $choice['choices'][2]['feedback'] = 'Feedback ' . self::XSS;
        $choice['partial_feedback'] = 'Partial ' . self::XSS;
        $choice['wrong_feedback'] = 'Wrong ' . self::XSS;
        $truefalse = [
            'question_type' => 'truefalse',
            'question' => 'Statement ' . self::XSS,
            'answers' => ['True', 'False'],
            'rightanswer' => 0,
            'choices' => [
                ['text' => 'True', 'correct' => true, 'feedback' => 'True ' . self::XSS],
                ['text' => 'False', 'correct' => false, 'feedback' => 'False ' . self::XSS],
            ],
            'wrong_feedback' => 'Wrong ' . self::XSS,
        ];
        $match = [
            'question_type' => 'match',
            'question' => 'Match ' . self::XSS,
            'pairs' => [['item' => 'One <script>alert(1)</script>', 'match' => '1'], ['item' => 'Two', 'match' => '2']],
            'wrong_feedback' => 'W',
            'partial_feedback' => 'P',
        ];
        $gapselect = [
            'question_type' => 'gapselect',
            'question' => 'The {{sky}} is blue.',
            'gaps' => [['answer' => 'sky<script>alert(1)</script>', 'distractors' => ['sea', 'grass']]],
            'wrong_feedback' => 'W',
            'partial_feedback' => 'P',
        ];
        [, $ids] = $this->generate([$choice, $truefalse, $match, $gapselect]);

        $stored = [];
        foreach ($ids as $id) {
            $stored[] = $DB->get_field('question', 'questiontext', ['id' => $id]);
            foreach ($DB->get_records('question_answers', ['question' => $id]) as $answer) {
                $stored[] = $answer->answer;
                $stored[] = $answer->feedback;
            }
        }
        $options = $DB->get_record('qtype_multichoice_options', ['questionid' => $ids[0]], '*', MUST_EXIST);
        $stored[] = $options->partiallycorrectfeedback;
        $stored[] = $options->incorrectfeedback;
        foreach ($DB->get_records('qtype_match_subquestions', ['questionid' => $ids[2]]) as $sub) {
            $stored[] = $sub->questiontext;
        }

        foreach ($stored as $value) {
            $this->assertStringNotContainsStringIgnoringCase('<script', $value);
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $value);
            $this->assertStringNotContainsStringIgnoringCase('javascript:', $value);
        }
        $this->assertStringContainsString('<strong>kept</strong>', $DB->get_field('question', 'questiontext', ['id' => $ids[0]]));
        $this->assertStringContainsString('<strong>kept</strong>', $options->partiallycorrectfeedback);
    }

    /**
     * A multichoice question with numbered options and per-option feedback.
     *
     * @param int $count Number of options.
     * @param int[] $correct Correct option indices.
     * @return array
     */
    private function multichoice(int $count, array $correct): array {
        $choices = [];
        for ($i = 0; $i < $count; $i++) {
            $choices[] = [
                'text' => 'Option ' . $i,
                'correct' => in_array($i, $correct, true),
                'feedback' => '<p>Feedback ' . $i . '</p>',
            ];
        }
        return [
            'question_type' => count($correct) > 1 ? 'multichoice' : 'singlechoice',
            'question' => 'Which options?',
            'choices' => $choices,
            'answers' => array_column($choices, 'text'),
            'rightanswer' => count($correct) > 1 ? $correct : $correct[0],
            'wrong_feedback' => '<p>Wrong</p>',
            'partial_feedback' => '<p>Partially right</p>',
        ];
    }

    /**
     * Create a quiz in a new course and run the create_questions action with the quiz DSL fields.
     *
     * @param array $questions Generated questions.
     * @return array [quiz cmid, created question ids]
     */
    private function generate(array $questions): array {
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

        $resolver = new value_resolver(['questions' => $questions], [], $context);
        set_debugging(DEBUG_NONE);
        $ids = (new create_questions_action())->execute([
            'module_ref' => '$module',
            'foreach' => '$.questions',
            'question_type' => ['source' => '$.question_type'],
            'fields' => [
                'questiontext' => ['source' => '$.question'],
                'wrong_feedback' => ['source' => '$.wrong_feedback'],
                'partial_feedback' => ['source' => '$.partial_feedback'],
                'choices' => ['source' => '$.choices'],
                'answers' => ['source' => '$.answers'],
                'correct_answer' => ['source' => '$.rightanswer'],
                'pairs' => ['source' => '$.pairs'],
                'items' => ['source' => '$.items'],
                'gaps' => ['source' => '$.gaps'],
            ],
        ], $resolver->with_variable('module', $module));
        set_debugging(DEBUG_DEVELOPER);

        return [(int) $module['cmid'], $ids];
    }

    /**
     * Attempt a one-question quiz (6 options, 3 correct) as a student and return the attempt's fraction.
     *
     * @param string[] $ticked Texts of the options the student ticks.
     * @return float Score between 0 and 1.
     */
    private function grade_response(array $ticked): float {
        global $DB;

        [$cmid] = $this->generate([$this->multichoice(6, [0, 2, 4])]);
        $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
        $student = $this->getDataGenerator()->create_and_enrol($DB->get_record('course', ['id' => $cm->course]), 'student');
        $this->setUser($student);

        $quizobj = \mod_quiz\quiz_settings::create((int) $cm->instance, (int) $student->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $timenow = time();
        $attempt = quiz_create_attempt($quizobj, 1, false, $timenow, false, (int) $student->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);

        $attemptobj = \mod_quiz\quiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, [1 => array_fill_keys($ticked, '1')]);
        $attemptobj = \mod_quiz\quiz_attempt::create($attempt->id);
        if (method_exists($attemptobj, 'process_submit')) {
            $attemptobj->process_submit($timenow, false);
            $attemptobj->process_grade_submission($timenow);
        } else {
            $attemptobj->process_finish($timenow, false);
        }

        $sumgrades = (float) $DB->get_field('quiz_attempts', 'sumgrades', ['id' => $attempt->id]);
        $this->setAdminUser();
        return $sumgrades / (float) $DB->get_field('quiz', 'sumgrades', ['id' => $cm->instance]);
    }
}
