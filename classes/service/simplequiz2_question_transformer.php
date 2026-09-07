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
 * Transform AI API question payloads into simplequiz2 JSON format.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service;

use local_dixeo\dsl\dsl_exception;


/**
 * Shared transformer for simplequiz2 question JSON.
 */
class simplequiz2_question_transformer {
    /**
     * Transform resolved DSL field values to SimpleQuiz format.
     *
     * @param array $fields questiontext, options, correct_answer
     * @return \stdClass
     * @throws dsl_exception
     */
    public static function transform_api_question(array $fields): \stdClass {
        $questiontext = $fields['questiontext'] ?? $fields['text'] ?? '';
        $options = $fields['options'] ?? [];
        $correctanswer = $fields['correct_answer'] ?? $fields['answer'] ?? 0;

        if (!is_array($options) || count($options) < 2) {
            throw new dsl_exception(
                'SimpleQuiz question requires at least 2 options',
                'simplequiz2_question_transformer',
                ['options_count' => is_array($options) ? count($options) : 0]
            );
        }

        $correctindex = is_numeric($correctanswer) ? (int) $correctanswer : 0;

        $question = new \stdClass();
        $question->text = is_string($questiontext) ? $questiontext : (string) $questiontext;
        $question->correctfeedback = self::feedback_or_default(
            (string) ($fields['correctfeedback'] ?? ''),
            'feedback_correct'
        );
        $question->partiallycorrectfeedback = self::feedback_or_default(
            (string) ($fields['partiallycorrectfeedback'] ?? ''),
            'feedback_partial'
        );
        $question->incorrectfeedback = self::feedback_or_default(
            (string) ($fields['incorrectfeedback'] ?? ''),
            'feedback_incorrect'
        );
        $question->answers = [];

        foreach ($options as $index => $optiontext) {
            $answer = new \stdClass();
            $answer->text = is_string($optiontext) ? $optiontext : (string) $optiontext;
            $answer->iscorrect = ($index === $correctindex) ? 1 : 0;
            $question->answers[] = $answer;
        }

        return $question;
    }

    /**
     * Transform a list of API question objects from a completed generation job.
     *
     * @param array $apiquestions List of question arrays from job result data.
     * @return array<int, \stdClass> Indexed simplequiz2 question objects.
     * @throws dsl_exception
     */
    public static function transform_job_questions(array $apiquestions): array {
        $out = [];
        foreach ($apiquestions as $index => $questionitem) {
            $itemdata = is_object($questionitem) ? (array) $questionitem : $questionitem;
            if (!is_array($itemdata)) {
                throw new dsl_exception(
                    "Question at index $index is not an array or object",
                    'simplequiz2_question_transformer',
                    ['index' => $index]
                );
            }
            $out[$index] = self::transform_api_question($itemdata);
        }
        return $out;
    }

    /**
     * Use localized placeholder feedback when the API omits or sends empty content.
     *
     * @param string $value Raw feedback from the generation payload.
     * @param string $stringkey local_dixeo lang string key.
     * @return string
     */
    private static function feedback_or_default(string $value, string $stringkey): string {
        if (trim($value) !== '') {
            return $value;
        }

        return get_string($stringkey, 'local_dixeo');
    }
}
