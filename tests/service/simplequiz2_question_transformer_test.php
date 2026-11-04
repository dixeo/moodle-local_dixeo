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
        $this->assertSame(5, simplequiz2_question_transformer::max_answers());
        $this->assertSame(25, simplequiz2_question_transformer::max_questions());
    }

    /**
     * A question with more options than the player accepts is refused.
     */
    public function test_too_many_options_are_rejected(): void {
        $this->expectException(dsl_exception::class);
        simplequiz2_question_transformer::transform_api_question([
            'text' => 'Q',
            'options' => ['1', '2', '3', '4', '5', '6'],
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
}
