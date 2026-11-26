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

/**
 * Tests for generated choice list validation.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\util\generated_choices
 */
final class generated_choices_test extends \basic_testcase {
    public function test_ten_entries_are_capped_at_eight_keeping_order_and_correct_ones(): void {
        $result = generated_choices::normalise($this->choices(10, [3, 9]), 8);

        $this->assertCount(8, $result);
        $this->assertSame(['c0', 'c1', 'c2', 'c3', 'c4', 'c5', 'c6', 'c9'], array_column($result, 'text'));
        $this->assertCount(2, array_filter(array_column($result, 'correct')));
    }

    public function test_cap_keeps_at_least_one_distractor(): void {
        $result = generated_choices::normalise($this->choices(6, [0, 1, 2, 3, 4]), 5);

        $this->assertSame(['c0', 'c1', 'c2', 'c3', 'c5'], array_column($result, 'text'));
    }

    public function test_all_correct_is_rejected(): void {
        $this->assertNull(generated_choices::normalise($this->choices(4, [0, 1, 2, 3]), 8));
    }

    public function test_no_correct_is_rejected(): void {
        $this->assertNull(generated_choices::normalise($this->choices(4, []), 8));
    }

    public function test_non_boolean_correct_is_rejected(): void {
        $choices = $this->choices(3, [0]);
        $choices[1]['correct'] = 'yes';

        $this->assertNull(generated_choices::normalise($choices, 8));
    }

    public function test_blank_text_is_rejected(): void {
        $choices = $this->choices(3, [0]);
        $choices[2]['text'] = '   ';

        $this->assertNull(generated_choices::normalise($choices, 8));
    }

    public function test_non_string_feedback_is_rejected(): void {
        $choices = $this->choices(3, [0]);
        $choices[2]['feedback'] = ['x'];

        $this->assertNull(generated_choices::normalise($choices, 8));
    }

    public function test_not_a_list_is_rejected(): void {
        $this->assertNull(generated_choices::normalise('choices', 8));
        $this->assertNull(generated_choices::normalise(null, 8));
        $this->assertNull(generated_choices::normalise(['a' => ['text' => 'x', 'correct' => true]], 8));
    }

    public function test_missing_feedback_becomes_empty(): void {
        $result = generated_choices::normalise([['text' => 'a', 'correct' => true], ['text' => 'b', 'correct' => false]], 8);

        $this->assertSame([
            ['text' => 'a', 'correct' => true, 'feedback' => ''],
            ['text' => 'b', 'correct' => false, 'feedback' => ''],
        ], $result);
    }

    /**
     * Numbered choices.
     *
     * @param int $count Number of choices.
     * @param int[] $correct Correct indices.
     * @return array
     */
    private function choices(int $count, array $correct): array {
        $choices = [];
        for ($i = 0; $i < $count; $i++) {
            $choices[] = ['text' => 'c' . $i, 'correct' => in_array($i, $correct, true), 'feedback' => 'f' . $i];
        }
        return $choices;
    }
}
