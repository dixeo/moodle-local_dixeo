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
    /** @var int Questions accepted per activity when mod_simplequiz2 is not on disk. */
    private const DEFAULT_MAX_QUESTIONS = 25;

    /** @var int Answers accepted per question when mod_simplequiz2 is not on disk. */
    private const DEFAULT_MAX_ANSWERS = 5;

    /** @var array{0: int, 1: int}|null Question and answer limits resolved for this request. */
    private static ?array $limits = null;

    /**
     * Questions accepted per activity.
     *
     * @return int MOD_SIMPLEQUIZ2_MAX_QUESTION_NB when mod_simplequiz2 is on disk, else 25.
     */
    public static function max_questions(): int {
        return self::limits()[0];
    }

    /**
     * Answers accepted per question.
     *
     * @return int MOD_SIMPLEQUIZ2_MAX_ANSWER_NB when mod_simplequiz2 is on disk, else 5.
     */
    public static function max_answers(): int {
        return self::limits()[1];
    }

    /**
     * Resolve the limits once per request from mod_simplequiz2, which owns them.
     *
     * @return array{0: int, 1: int} Maximum questions and maximum answers.
     */
    private static function limits(): array {
        if (self::$limits !== null) {
            return self::$limits;
        }

        if (!defined('MOD_SIMPLEQUIZ2_MAX_QUESTION_NB') || !defined('MOD_SIMPLEQUIZ2_MAX_ANSWER_NB')) {
            $dir = \core_component::get_component_directory('mod_simplequiz2');
            if ($dir !== null && is_readable($dir . '/lib.php')) {
                require_once($dir . '/lib.php');
            }
        }

        self::$limits = [
            defined('MOD_SIMPLEQUIZ2_MAX_QUESTION_NB') ? (int) MOD_SIMPLEQUIZ2_MAX_QUESTION_NB : self::DEFAULT_MAX_QUESTIONS,
            defined('MOD_SIMPLEQUIZ2_MAX_ANSWER_NB') ? (int) MOD_SIMPLEQUIZ2_MAX_ANSWER_NB : self::DEFAULT_MAX_ANSWERS,
        ];
        return self::$limits;
    }

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
        if (count($options) > self::max_answers()) {
            throw new dsl_exception(
                'SimpleQuiz question accepts at most ' . self::max_answers() . ' options',
                'simplequiz2_question_transformer',
                ['options_count' => count($options)]
            );
        }

        $correctindex = is_numeric($correctanswer) ? (int) $correctanswer : 0;

        $question = new \stdClass();
        $question->text = self::clean_html(is_string($questiontext) ? $questiontext : (string) $questiontext);
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
            $answer->text = self::clean_html(is_string($optiontext) ? $optiontext : (string) $optiontext);
            $answer->iscorrect = ($index === $correctindex) ? 1 : 0;
            $question->answers[] = $answer;
        }

        return $question;
    }

    /**
     * Transform a list of API question objects from a completed generation job.
     *
     * The list is refused when it holds more questions than an activity accepts.
     *
     * @param array $apiquestions List of question arrays from job result data.
     * @return array<int, \stdClass> Indexed simplequiz2 question objects.
     * @throws dsl_exception
     */
    public static function transform_job_questions(array $apiquestions): array {
        self::require_question_count(count($apiquestions));
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
     * Stop when a generated question list is longer than an activity accepts.
     *
     * @param int $count Number of generated questions.
     * @return void
     * @throws dsl_exception When $count exceeds {@see self::max_questions()}.
     */
    private static function require_question_count(int $count): void {
        if ($count > self::max_questions()) {
            throw new dsl_exception(
                'SimpleQuiz accepts at most ' . self::max_questions() . ' questions',
                'simplequiz2_question_transformer',
                ['question_count' => $count]
            );
        }
    }

    /**
     * Use localized placeholder feedback when the API omits or sends empty content.
     *
     * Generated feedback is cleaned; the placeholder strings are trusted and returned as they are.
     *
     * @param string $value Raw feedback from the generation payload.
     * @param string $stringkey local_dixeo lang string key.
     * @return string
     */
    private static function feedback_or_default(string $value, string $stringkey): string {
        if (trim($value) !== '') {
            return self::clean_html($value);
        }

        return get_string($stringkey, 'local_dixeo');
    }

    /**
     * Clean generated HTML before it is stored or sent to the embed player.
     *
     * @param string $value Generated HTML.
     * @return string
     */
    private static function clean_html(string $value): string {
        return clean_text($value, FORMAT_HTML);
    }
}
