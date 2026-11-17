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

namespace local_dixeo\external;

/**
 * Tests for get_content_image_status idle contract.
 *
 * @covers \local_dixeo\external\get_content_image_status
 * @package local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_content_image_status_test extends \advanced_testcase {
    /**
     * Missing placeholder ids return idle items so the page poller can stop.
     */
    public function test_missing_placeholder_returns_idle_item(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $result = get_content_image_status::execute([
            '00000000-0000-0000-0000-000000000099',
        ]);
        $result = get_content_image_status::clean_returnvalue(
            get_content_image_status::execute_returns(),
            $result
        );

        $this->assertCount(1, $result['items']);
        $this->assertSame('idle', $result['items'][0]['status']);
        $this->assertSame('00000000-0000-0000-0000-000000000099', $result['items'][0]['placeholderid']);
        $this->assertSame('', $result['items'][0]['imageurl']);
    }

    /**
     * More ids than one request accepts are refused before any database query.
     */
    public function test_oversized_list_is_refused_before_any_query(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $ids = [];
        for ($i = 0; $i < \local_dixeo\service\image\placeholder_ids::MAX_PER_REQUEST + 1; $i++) {
            $ids[] = \core\uuid::generate();
        }

        $before = $DB->perf_get_queries();
        try {
            get_content_image_status::execute($ids);
            $this->fail('An oversized placeholder id list must be refused.');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString('Too many placeholder ids', $e->getMessage());
        }
        $this->assertSame($before, $DB->perf_get_queries());
    }

    /**
     * Repeated ids produce a single item each.
     */
    public function test_duplicate_ids_return_one_item_each(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $first = \core\uuid::generate();
        $second = \core\uuid::generate();
        $result = get_content_image_status::execute([$first, $second, $first]);
        $result = get_content_image_status::clean_returnvalue(
            get_content_image_status::execute_returns(),
            $result
        );

        $this->assertCount(2, $result['items']);
        $this->assertSame([$first, $second], array_column($result['items'], 'placeholderid'));
    }

    /**
     * Ids that are not well-formed UUIDs produce no item.
     */
    public function test_malformed_ids_produce_no_item(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $result = get_content_image_status::execute([
            'abc',
            '00000000-0000-0000-0000-0000000000999',
        ]);
        $result = get_content_image_status::clean_returnvalue(
            get_content_image_status::execute_returns(),
            $result
        );

        $this->assertSame([], $result['items']);
    }
}
