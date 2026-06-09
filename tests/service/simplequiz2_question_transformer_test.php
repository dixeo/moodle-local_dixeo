<?php
/**
 * Unit tests for simplequiz2 question transformer.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\service\simplequiz2_question_transformer;

defined('MOODLE_INTERNAL') || die();

/**
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
     * Missing feedback defaults to empty strings.
     */
    public function test_transform_api_question_empty_feedback_defaults(): void {
        $question = simplequiz2_question_transformer::transform_api_question([
            'text' => 'Q',
            'options' => ['A', 'B'],
            'answer' => 0,
        ]);

        $this->assertSame('', $question->correctfeedback);
        $this->assertSame('', $question->partiallycorrectfeedback);
        $this->assertSame('', $question->incorrectfeedback);
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
}
