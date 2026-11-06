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

namespace local_dixeo\service\image;

/**
 * Placeholder id lists are capped, cleaned and deduplicated.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\service\image\placeholder_ids
 */
final class placeholder_ids_test extends \basic_testcase {
    /**
     * One id over the cap stops the request.
     */
    public function test_more_than_the_cap_throws(): void {
        $ids = $this->uuids(placeholder_ids::MAX_PER_REQUEST + 1);

        $this->expectException(\invalid_parameter_exception::class);
        placeholder_ids::normalise($ids);
    }

    /**
     * Exactly the cap of valid ids is returned unchanged.
     */
    public function test_cap_of_valid_ids_is_returned(): void {
        $ids = $this->uuids(placeholder_ids::MAX_PER_REQUEST);

        $this->assertSame($ids, placeholder_ids::normalise($ids));
    }

    /**
     * Invalid, duplicate and non-string items are dropped; valid ids are trimmed and keep their order.
     */
    public function test_invalid_duplicate_and_non_string_items_are_dropped(): void {
        $first = \core\uuid::generate();
        $second = \core\uuid::generate();

        $result = placeholder_ids::normalise([
            ' ' . $first . ' ',
            'not-a-uuid',
            '',
            42,
            null,
            ['nested'],
            $first,
            $second . "\n",
            $second,
            '<script>',
        ]);

        $this->assertSame([$first, $second], $result);
    }

    /**
     * Distinct canonical UUIDs.
     *
     * @param int $count Number of ids.
     * @return string[]
     */
    private function uuids(int $count): array {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = \core\uuid::generate();
        }
        return $ids;
    }

    /**
     * The status cap leaves room for every new image one content may queue.
     */
    public function test_cap_covers_the_images_of_one_content(): void {
        $this->assertGreaterThanOrEqual(
            \local_dixeo\util\input_limits::MAX_IMAGES_PER_CONTENT,
            placeholder_ids::MAX_PER_REQUEST
        );
    }
}
