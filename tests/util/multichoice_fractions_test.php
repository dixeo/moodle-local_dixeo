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

namespace local_dixeo\util;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/questionlib.php');

/**
 * Tests for multiple choice answer fractions.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\util\multichoice_fractions
 */
final class multichoice_fractions_test extends \basic_testcase {
    /**
     * Every (options, correct) pair with at least one wrong option.
     *
     * @return array<string, array{0: int, 1: int}>
     */
    public static function option_counts_provider(): array {
        $cases = [];
        for ($options = 2; $options <= 8; $options++) {
            for ($correct = 1; $correct < $options; $correct++) {
                $cases[$options . ' options, ' . $correct . ' correct'] = [$options, $correct];
            }
        }
        return $cases;
    }

    /**
     * Fractions are Moodle fraction keys, correct ones add up to 1 and wrong ones to -1.
     *
     * The sums use the tolerance of question_state::graded_state_for_fraction() (1e-6): the snapped value for 1/7 is
     * 0.1428571, so seven correct options add up to 0.9999997.
     *
     * @dataProvider option_counts_provider
     * @param int $options Number of options.
     * @param int $correct Number of correct options.
     */
    public function test_fractions_are_valid_keys_and_balance(int $options, int $correct): void {
        $fractions = multichoice_fractions::for_options($options, range(0, $correct - 1), false);
        $keys = array_map('strval', array_keys(\question_bank::fraction_options_full()));

        $this->assertCount($options, $fractions);
        $positive = 0.0;
        $negative = 0.0;
        foreach ($fractions as $fraction) {
            $this->assertContains($fraction, $keys);
            if ((float) $fraction > 0) {
                $positive += (float) $fraction;
            } else {
                $negative += (float) $fraction;
            }
        }
        $this->assertGreaterThanOrEqual(0.999999, $positive);
        $this->assertLessThanOrEqual(-0.999999, $negative);
    }

    public function test_single_answer_mode(): void {
        $this->assertSame(['0.0', '1.0', '0.0'], multichoice_fractions::for_options(3, [1], true));
    }

    public function test_every_option_correct_gives_no_negative_fraction(): void {
        $this->assertSame(['0.3333333', '0.3333333', '0.3333333'], multichoice_fractions::for_options(3, [0, 1, 2], false));
    }

    /**
     * Invalid arguments.
     *
     * @return array<string, array{0: int, 1: array}>
     */
    public static function invalid_provider(): array {
        return [
            'one option' => [1, [0]],
            'nine options' => [9, [0]],
            'index out of range' => [3, [3]],
            'negative index' => [3, [-1]],
            'duplicate index' => [3, [1, 1]],
            'no correct option' => [3, []],
        ];
    }

    /**
     * Invalid arguments throw a coding exception.
     *
     * @dataProvider invalid_provider
     * @param int $options Number of options.
     * @param array $correct Correct indices.
     */
    public function test_invalid_arguments_throw(int $options, array $correct): void {
        $this->expectException(\coding_exception::class);

        multichoice_fractions::for_options($options, $correct, false);
    }
}
