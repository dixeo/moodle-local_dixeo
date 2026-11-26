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
 * Answer fractions for multiple choice questions.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\util;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/questionlib.php');

/**
 * Grades for the options of a qtype_multichoice question.
 *
 * In multiple-answer mode each correct option is worth 1/C and each wrong option -1/D (C correct, D wrong
 * options), snapped to the fractions Moodle offers. qtype_multichoice clamps the response total to [0, 1], so
 * ticking every option scores 0, the exact correct set scores 1, and a score never goes below 0. When every
 * option is correct there is no wrong option to penalise: each option is worth 1/C.
 */
final class multichoice_fractions {
    /** @var int Fewest options a question may have. */
    private const MIN_OPTIONS = 2;

    /** @var int Most options a question may have. */
    private const MAX_OPTIONS = 8;

    /**
     * Fraction for each option.
     *
     * @param int $optioncount Number of options (2..8).
     * @param int[] $correctindices Zero-based indices of correct options (at least 1).
     * @param bool $singleanswer True for one-answer mode.
     * @return array<int, string> Fraction per option index, each a key of question_bank::fraction_options_full().
     * @throws \coding_exception When the arguments are outside the documented ranges.
     */
    public static function for_options(int $optioncount, array $correctindices, bool $singleanswer): array {
        if ($optioncount < self::MIN_OPTIONS || $optioncount > self::MAX_OPTIONS) {
            throw new \coding_exception('A multiple choice question needs between 2 and 8 options.');
        }
        $correctcount = count($correctindices);
        if ($correctcount < 1 || $correctcount > $optioncount) {
            throw new \coding_exception('A multiple choice question needs at least one correct option.');
        }
        foreach ($correctindices as $index) {
            if (!is_int($index) || $index < 0 || $index >= $optioncount) {
                throw new \coding_exception('A correct option index is out of range.');
            }
        }
        if (count(array_unique($correctindices)) !== $correctcount) {
            throw new \coding_exception('A correct option index is repeated.');
        }

        $wrongcount = $optioncount - $correctcount;
        if ($singleanswer) {
            $correctvalue = '1.0';
            $wrongvalue = '0.0';
        } else {
            $correctvalue = self::snap(1 / $correctcount);
            $wrongvalue = $wrongcount > 0 ? self::snap(-1 / $wrongcount) : '0.0';
        }

        $fractions = [];
        for ($index = 0; $index < $optioncount; $index++) {
            $fractions[$index] = in_array($index, $correctindices, true) ? $correctvalue : $wrongvalue;
        }
        return $fractions;
    }

    /**
     * Nearest key of question_bank::fraction_options_full().
     *
     * @param float $value Exact fraction.
     * @return string
     */
    private static function snap(float $value): string {
        $best = null;
        $bestdistance = PHP_FLOAT_MAX;
        foreach (array_keys(\question_bank::fraction_options_full()) as $key) {
            $distance = abs((float) $key - $value);
            if ($distance < $bestdistance) {
                $best = (string) $key;
                $bestdistance = $distance;
            }
        }
        return $best;
    }
}
