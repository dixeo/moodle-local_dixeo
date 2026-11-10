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
 * Tests for verification quiz normalisation in assign_ux_service.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\service\assign_submission_reader;
use local_dixeo\service\assign_ux_service;
use local_dixeo\service\job_service;

/**
 * A generated quiz is stored only in the shape the evaluation step accepts.
 *
 * @covers \local_dixeo\service\assign_ux_service::map_authorship_result
 */
final class assign_ux_service_quiz_test extends \advanced_testcase {
    /**
     * Map a raw quiz result.
     *
     * @param array $questions Raw questions.
     * @return array Mapping result.
     */
    private function map(array $questions): array {
        $service = new assign_ux_service(
            $this->createStub(job_service::class),
            $this->createStub(assign_submission_reader::class),
            null,
            'test-ns'
        );
        return $service->map_authorship_result(assign_ux_service::MODE_QUIZ, ['questions' => $questions]);
    }

    public function test_markup_in_an_id_is_replaced_by_the_position(): void {
        $mapped = $this->map([
            ['id' => 'x"><img src=x onerror=alert(1)>', 'type' => 'open', 'text' => 'Explain your method.'],
        ]);

        $this->assertSame('q0', $mapped['questions'][0]['id']);
    }

    public function test_safe_ids_are_kept_and_duplicates_replaced(): void {
        $mapped = $this->map([
            ['id' => 'tf-1', 'type' => 'true_false', 'text' => 'A', 'correct' => 1],
            ['id' => 'tf-1', 'type' => 'open', 'text' => 'B'],
            ['id' => 'q1', 'type' => 'open', 'text' => 'C'],
            ['id' => 7, 'type' => 'open', 'text' => 'D'],
        ]);

        $this->assertSame(['tf-1', 'q1', 'q2', '7'], array_column($mapped['questions'], 'id'));
    }

    public function test_over_long_or_missing_ids_are_replaced(): void {
        $mapped = $this->map([
            ['id' => str_repeat('a', 65), 'type' => 'open', 'text' => 'A'],
            ['type' => 'open', 'text' => 'B'],
            ['id' => ['nested'], 'type' => 'open', 'text' => 'C'],
            ['id' => str_repeat('b', 64), 'type' => 'open', 'text' => 'D'],
        ]);

        $this->assertSame(['q0', 'q1', 'q2', str_repeat('b', 64)], array_column($mapped['questions'], 'id'));
    }

    public function test_at_most_fifteen_questions_are_kept(): void {
        $questions = [];
        for ($i = 0; $i < 20; $i++) {
            $questions[] = ['id' => 'q' . $i, 'type' => 'open', 'text' => 'Question ' . $i];
        }

        $mapped = $this->map($questions);

        $this->assertCount(assign_ux_service::QUIZ_MAX_QUESTIONS, $mapped['questions']);
        $this->assertSame('q14', $mapped['questions'][14]['id']);
    }

    public function test_unknown_types_and_empty_text_are_dropped(): void {
        $mapped = $this->map([
            ['id' => 'a', 'type' => 'essay', 'text' => 'Unknown type'],
            ['id' => 'b', 'type' => 'open', 'text' => '   '],
            ['id' => 'c', 'type' => 'open'],
            'not a question',
            ['id' => 'd', 'type' => 'open', 'text' => 'Kept'],
        ]);

        $this->assertSame([['id' => 'd', 'type' => 'open', 'text' => 'Kept']], $mapped['questions']);
    }

    public function test_no_valid_question_reports_a_missing_result(): void {
        $this->assertSame(
            ['error' => get_string('assign_ux_result_missing', 'local_dixeo')],
            $this->map([['id' => 'a', 'type' => 'essay', 'text' => 'x']])
        );
        $this->assertSame(
            ['error' => get_string('assign_ux_result_missing', 'local_dixeo')],
            $this->map([])
        );
    }

    public function test_over_long_text_is_capped(): void {
        $mapped = $this->map([[
            'id' => 'mc',
            'type' => 'multiple_choice',
            'text' => str_repeat('é', 2500),
            'options' => [['value' => str_repeat('v', 100), 'text' => str_repeat('t', 2500)]],
            'correct' => str_repeat('v', 100),
        ]]);

        $question = $mapped['questions'][0];
        $this->assertSame(assign_ux_service::QUIZ_MAX_TEXT_CHARS, \core_text::strlen($question['text']));
        $this->assertSame(str_repeat('v', 64), $question['options'][0]['value']);
        $this->assertSame(assign_ux_service::QUIZ_MAX_TEXT_CHARS, \core_text::strlen($question['options'][0]['text']));
        $this->assertSame($question['options'][0]['value'], $question['correct']);
    }

    public function test_options_are_capped_and_normalised(): void {
        $options = ['plain'];
        for ($i = 1; $i < 15; $i++) {
            $options[] = ['value' => 'v' . $i, 'text' => 'Option ' . $i, 'extra' => 'dropped'];
        }

        $mapped = $this->map([[
            'id' => 'mc',
            'type' => 'multiple_choice',
            'text' => 'Pick one',
            'options' => $options,
            'correct' => 'v2',
            'explanation' => 'dropped',
        ]]);

        $question = $mapped['questions'][0];
        $this->assertSame(['id', 'type', 'text', 'options', 'correct'], array_keys($question));
        $this->assertCount(assign_ux_service::QUIZ_MAX_OPTIONS, $question['options']);
        $this->assertSame(['value' => 'plain', 'text' => 'plain'], $question['options'][0]);
        $this->assertSame(['value' => 'v1', 'text' => 'Option 1'], $question['options'][1]);
    }

    public function test_correct_is_kept_only_as_int_or_string(): void {
        $mapped = $this->map([
            ['id' => 'a', 'type' => 'true_false', 'text' => 'A', 'correct' => 0],
            ['id' => 'b', 'type' => 'true_false', 'text' => 'B', 'correct' => true],
            ['id' => 'c', 'type' => 'true_false', 'text' => 'C', 'correct' => ['1']],
        ]);

        $this->assertSame(0, $mapped['questions'][0]['correct']);
        $this->assertArrayNotHasKey('correct', $mapped['questions'][1]);
        $this->assertArrayNotHasKey('correct', $mapped['questions'][2]);
    }
}
