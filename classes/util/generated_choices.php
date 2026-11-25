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
 * Validation of generated choice lists.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\util;

/**
 * Checks and caps the `choices` list of a generated choice question.
 *
 * The generation response comes from outside Moodle, so every consumer of `choices` (mod_quiz questions,
 * SimpleQuiz questions) validates it here before storing anything. The cap rule matches the Dixeo API's own
 * normaliser: keep the original order, keep correct options up to `max - 1`, and fill the rest with
 * distractors. Nothing is cleaned here; each caller sanitises the texts with its own sanitiser.
 */
final class generated_choices {
    /**
     * Validate and cap a generated choice list.
     *
     * @param mixed $choices Raw `choices` value from the generation payload.
     * @param int $max Maximum options the target activity stores (at least 2).
     * @return array<int, array{text: string, correct: bool, feedback: string}>|null The capped list, or null when the
     *         payload is unusable and the caller must use the legacy fields.
     */
    public static function normalise(mixed $choices, int $max): ?array {
        if (!is_array($choices) || !array_is_list($choices) || $max < 2) {
            return null;
        }

        $list = [];
        foreach ($choices as $choice) {
            if (
                !is_array($choice)
                    || !is_string($choice['text'] ?? null)
                    || trim($choice['text']) === ''
                    || !is_bool($choice['correct'] ?? null)
                    || (array_key_exists('feedback', $choice) && !is_string($choice['feedback']))
            ) {
                return null;
            }
            $list[] = [
                'text' => $choice['text'],
                'correct' => $choice['correct'],
                'feedback' => $choice['feedback'] ?? '',
            ];
        }

        $correct = count(array_filter($list, static fn(array $choice): bool => $choice['correct']));
        if ($correct < 1 || $correct === count($list)) {
            return null;
        }

        return count($list) > $max ? self::cap($list, $max) : $list;
    }

    /**
     * Keep the original order, correct options up to $max - 1, and distractors for the rest.
     *
     * @param array $list Validated choices, each {text, correct, feedback}.
     * @param int $max Maximum options.
     * @return array The capped choices.
     */
    private static function cap(array $list, int $max): array {
        $correct = array_keys(array_filter($list, static fn(array $choice): bool => $choice['correct']));
        $distractors = array_keys(array_filter($list, static fn(array $choice): bool => !$choice['correct']));

        $keepcorrect = array_slice($correct, 0, min(count($correct), $max - 1));
        $keep = array_merge($keepcorrect, array_slice($distractors, 0, $max - count($keepcorrect)));
        sort($keep);

        return array_map(static fn(int $index): array => $list[$index], $keep);
    }
}
