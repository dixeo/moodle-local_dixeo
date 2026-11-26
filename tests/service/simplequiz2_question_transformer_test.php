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
 * Unit tests for simplequiz2 question transformer.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\dsl\dsl_exception;
use local_dixeo\service\simplequiz2_question_transformer;

/**
 * Tests for simplequiz2_question_transformer_test.
 * @covers \local_dixeo\service\simplequiz2_question_transformer
 */
final class simplequiz2_question_transformer_test extends \advanced_testcase {
    /**
     * API shape transforms to simplequiz2 JSON with iscorrect flags.
     */
    public function test_transform_api_question(): void {
        $question = simplequiz2_question_transformer::transform_api_question([
            'text' => 'What is 2+2?',
            'options' => ['3', '4', '5'],
            'answer' => 1,
        ]);

        $this->assertEquals('What is 2+2?', $question->text);
        $this->assertCount(3, $question->answers);
        $this->assertEquals(0, $question->answers[0]->iscorrect);
        $this->assertEquals(1, $question->answers[1]->iscorrect);
        $this->assertEquals(0, $question->answers[2]->iscorrect);
    }

    /**
     * Moodle quiz combined feedback field names are stored on simplequiz2 questions.
     */
    public function test_transform_api_question_feedback_fields(): void {
        $question = simplequiz2_question_transformer::transform_api_question([
            'text' => 'Pick one',
            'options' => ['A', 'B'],
            'answer' => 0,
            'correctfeedback' => '<p>Nice</p>',
            'partiallycorrectfeedback' => '<p>Almost</p>',
            'incorrectfeedback' => '<p>Oops</p>',
        ]);

        $this->assertEquals('<p>Nice</p>', $question->correctfeedback);
        $this->assertEquals('<p>Almost</p>', $question->partiallycorrectfeedback);
        $this->assertEquals('<p>Oops</p>', $question->incorrectfeedback);
    }

    /**
     * Missing feedback uses localized placeholders.
     */
    public function test_transform_api_question_empty_feedback_placeholders(): void {
        $question = simplequiz2_question_transformer::transform_api_question([
            'text' => 'Q',
            'options' => ['A', 'B'],
            'answer' => 0,
        ]);

        $this->assertSame(get_string('feedback_correct', 'local_dixeo'), $question->correctfeedback);
        $this->assertSame(get_string('feedback_partial', 'local_dixeo'), $question->partiallycorrectfeedback);
        $this->assertSame(get_string('feedback_incorrect', 'local_dixeo'), $question->incorrectfeedback);
    }

    /**
     * Whitespace-only feedback is treated as empty and replaced with placeholders.
     */
    public function test_transform_api_question_whitespace_feedback_placeholders(): void {
        $question = simplequiz2_question_transformer::transform_api_question([
            'text' => 'Q',
            'options' => ['A', 'B'],
            'answer' => 0,
            'correctfeedback' => '   ',
            'partiallycorrectfeedback' => "\n",
            'incorrectfeedback' => '',
        ]);

        $this->assertSame(get_string('feedback_correct', 'local_dixeo'), $question->correctfeedback);
        $this->assertSame(get_string('feedback_partial', 'local_dixeo'), $question->partiallycorrectfeedback);
        $this->assertSame(get_string('feedback_incorrect', 'local_dixeo'), $question->incorrectfeedback);
    }

    /**
     * Batch transform preserves order.
     */
    public function test_transform_job_questions(): void {
        $out = simplequiz2_question_transformer::transform_job_questions([
            ['text' => 'Q1', 'options' => ['A', 'B'], 'answer' => 0],
            ['questiontext' => 'Q2', 'options' => ['X', 'Y'], 'correct_answer' => 1],
        ]);

        $this->assertCount(2, $out);
        $this->assertEquals('Q1', $out[0]->text);
        $this->assertEquals('Q2', $out[1]->text);
    }

    /** @var string Markup with an allowed paragraph and two script vectors. */
    private const HOSTILE = '<p>ok</p><img src="x" onerror="alert(1)"><script>x</script>';

    /**
     * Script vectors are removed from the question, every feedback and every answer.
     */
    public function test_transform_cleans_every_html_field(): void {
        $question = simplequiz2_question_transformer::transform_api_question([
            'text' => self::HOSTILE,
            'options' => [self::HOSTILE, 'Plain'],
            'answer' => 0,
            'correctfeedback' => self::HOSTILE,
            'partiallycorrectfeedback' => self::HOSTILE,
            'incorrectfeedback' => self::HOSTILE,
        ]);

        $values = [
            $question->text,
            $question->correctfeedback,
            $question->partiallycorrectfeedback,
            $question->incorrectfeedback,
            $question->answers[0]->text,
        ];
        foreach ($values as $value) {
            $this->assertStringContainsString('<p>ok</p>', $value);
            $this->assertStringNotContainsStringIgnoringCase('<script', $value);
            $this->assertStringNotContainsStringIgnoringCase('onerror', $value);
        }
        $this->assertSame('Plain', $question->answers[1]->text);
        $this->assertSame(1, $question->answers[0]->iscorrect);
    }

    /**
     * Default feedback strings are returned as they are.
     */
    public function test_default_feedback_is_unchanged(): void {
        $question = simplequiz2_question_transformer::transform_api_question([
            'text' => 'Q',
            'options' => ['A', 'B'],
        ]);

        $this->assertSame(get_string('feedback_correct', 'local_dixeo'), $question->correctfeedback);
        $this->assertSame(get_string('feedback_incorrect', 'local_dixeo'), $question->incorrectfeedback);
    }

    /**
     * The limits match what the simplequiz2 embed player accepts, with or without the module.
     */
    public function test_limits(): void {
        simplequiz2_question_transformer::max_answers();
        $expected = defined('MOD_SIMPLEQUIZ2_MAX_ANSWER_NB') ? (int) MOD_SIMPLEQUIZ2_MAX_ANSWER_NB : 5;
        $this->assertSame($expected, simplequiz2_question_transformer::max_answers());
        $this->assertSame(25, simplequiz2_question_transformer::max_questions());
    }

    /**
     * A question with more options than the player accepts is refused.
     */
    public function test_too_many_options_are_rejected(): void {
        $this->expectException(dsl_exception::class);
        simplequiz2_question_transformer::transform_api_question([
            'text' => 'Q',
            'options' => array_map('strval', range(1, simplequiz2_question_transformer::max_answers() + 1)),
        ]);
    }

    /**
     * A job with more questions than an activity accepts is refused.
     */
    public function test_too_many_questions_are_rejected(): void {
        $questions = array_fill(0, 26, ['text' => 'Q', 'options' => ['A', 'B'], 'answer' => 0]);

        $this->expectException(dsl_exception::class);
        simplequiz2_question_transformer::transform_job_questions($questions);
    }

    /**
     * Reset the cached limits after each test that forces them.
     */
    protected function tearDown(): void {
        $this->set_limits(null);
        parent::tearDown();
    }

    /**
     * Eight choices with three correct are kept whole when the activity accepts eight answers.
     */
    public function test_choices_with_several_correct_answers(): void {
        $this->set_limits([25, 8]);

        $question = simplequiz2_question_transformer::transform_api_question($this->choices_fields(8, [1, 4, 6]));

        $this->assertCount(8, $question->answers);
        $this->assertSame([0, 1, 0, 0, 1, 0, 1, 0], array_column($question->answers, 'iscorrect'));
        $this->assertSame('o0', $question->answers[0]->text);
    }

    /**
     * On a site whose mod_simplequiz2 accepts five answers, choices are capped and keep the correct ones.
     */
    public function test_choices_are_capped_at_the_activity_limit(): void {
        $this->set_limits([25, 5]);

        $question = simplequiz2_question_transformer::transform_api_question($this->choices_fields(8, [1, 4, 6]));

        $this->assertSame(['o0', 'o1', 'o2', 'o4', 'o6'], array_column($question->answers, 'text'));
        $this->assertSame([0, 1, 0, 1, 1], array_column($question->answers, 'iscorrect'));
    }

    /**
     * Without a valid choices list the legacy fields give the same result as before.
     */
    public function test_legacy_fields_are_unchanged(): void {
        $this->set_limits([25, 8]);
        $fields = ['text' => 'Q', 'options' => ['A', 'B', 'C'], 'answer' => 2, 'correctfeedback' => 'Yes'];

        $question = simplequiz2_question_transformer::transform_api_question($fields);

        $this->assertSame(['A', 'B', 'C'], array_column($question->answers, 'text'));
        $this->assertSame([0, 0, 1], array_column($question->answers, 'iscorrect'));
        $this->assertSame('Yes', $question->correctfeedback);
    }

    /**
     * Choices that are all correct are unusable, so the legacy fields are used.
     */
    public function test_all_correct_choices_fall_back_to_the_legacy_fields(): void {
        $this->set_limits([25, 8]);
        $fields = $this->choices_fields(4, [0, 1, 2, 3]);

        $question = simplequiz2_question_transformer::transform_api_question($fields);

        $this->assertSame(['o0', 'o1', 'o2'], array_column($question->answers, 'text'));
        $this->assertSame([0, 1, 0], array_column($question->answers, 'iscorrect'));
    }

    /**
     * The partially correct feedback is stored cleaned.
     */
    public function test_partial_feedback_is_stored_cleaned(): void {
        $fields = $this->choices_fields(3, [0, 1]);
        $fields['partiallycorrectfeedback'] = '<p>Half</p><script>alert(1)</script>';

        $question = simplequiz2_question_transformer::transform_api_question($fields);

        $this->assertSame('<p>Half</p>', $question->partiallycorrectfeedback);
    }

    /**
     * Generated choice texts are cleaned before they are stored.
     */
    public function test_choice_text_is_cleaned(): void {
        $fields = $this->choices_fields(3, [0]);
        $fields['choices'][0]['text'] = '<strong>A</strong><script>alert(1)</script><img src=x onerror=alert(1)>'
            . '<a href="javascript:alert(1)">x</a>';

        $question = simplequiz2_question_transformer::transform_api_question($fields);

        $text = $question->answers[0]->text;
        $this->assertStringNotContainsStringIgnoringCase('<script', $text);
        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $text);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $text);
        $this->assertStringContainsString('<strong>A</strong>', $text);
    }

    /**
     * The DSL action stores several correct answers from a response with choices.
     */
    public function test_dsl_action_stores_several_correct_answers(): void {
        global $DB;

        if (\core_component::get_component_directory('mod_simplequiz2') === null) {
            $this->markTestSkipped('mod_simplequiz2 is not installed.');
        }
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1], '*', MUST_EXIST);
        $context = [
            'courseid' => (int) $course->id,
            'sectionid' => (int) $section->id,
            'sectionnum' => 1,
            'modulename' => 'simplequiz2',
            'userid' => 2,
            'contextid' => \context_course::instance($course->id)->id,
        ];
        $module = (new \local_dixeo\dsl\actions\create_module_action())->execute(
            ['fields' => ['name' => ['source' => '$.name'], 'intro' => ['source' => '$.intro']]],
            new \local_dixeo\dsl\value_resolver(['name' => 'Generated quiz', 'intro' => 'Intro'], [], $context)
        );
        $fields = $this->choices_fields(4, [0, 2]);
        $fields['options'] = ['o0', 'o1', 'o3'];
        $fields['answer'] = 0;
        $resolver = new \local_dixeo\dsl\value_resolver(['questions' => [$fields]], [], $context);

        (new \local_dixeo\dsl\actions\create_questions_simplequiz2_action())->execute([
            'module_ref' => '$module',
            'foreach' => '$.questions',
            'fields' => [
                'questiontext' => ['source' => '$.text'],
                'choices' => ['source' => '$.choices'],
                'options' => ['source' => '$.options'],
                'correct_answer' => ['source' => '$.answer'],
                'correctfeedback' => ['source' => '$.correctfeedback'],
                'partiallycorrectfeedback' => ['source' => '$.partiallycorrectfeedback'],
                'incorrectfeedback' => ['source' => '$.incorrectfeedback'],
            ],
        ], $resolver->with_variable('module', $module));

        $stored = json_decode($DB->get_field('simplequiz2', 'questions', ['id' => $module['id']]), true);
        $this->assertSame([1, 0, 1, 0], array_column($stored[0]['answers'], 'iscorrect'));
        $this->assertSame('Partly', $stored[0]['partiallycorrectfeedback']);
    }

    /**
     * Fields of a question carrying a choices list.
     *
     * @param int $count Number of choices.
     * @param int[] $correct Correct indices.
     * @return array
     */
    private function choices_fields(int $count, array $correct): array {
        $choices = [];
        for ($i = 0; $i < $count; $i++) {
            $choices[] = ['text' => 'o' . $i, 'correct' => in_array($i, $correct, true)];
        }
        return [
            'text' => 'Question',
            'choices' => $choices,
            'options' => ['o0', 'o1', 'o2'],
            'answer' => 1,
            'correctfeedback' => 'Right',
            'partiallycorrectfeedback' => 'Partly',
            'incorrectfeedback' => 'Wrong',
        ];
    }

    /**
     * Force the transformer's cached question and answer limits, or clear them with null.
     *
     * @param array|null $limits [max questions, max answers], or null to resolve them again.
     */
    private function set_limits(?array $limits): void {
        $property = new \ReflectionProperty(simplequiz2_question_transformer::class, 'limits');
        $property->setValue(null, $limits);
    }
}
