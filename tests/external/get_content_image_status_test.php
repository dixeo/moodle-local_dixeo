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
}
