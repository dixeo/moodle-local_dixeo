<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests for unified content image job repository helpers.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\repository\image\job_repository;
use local_dixeo\service\image\content\location;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_dixeo\repository\image\job_repository
 */
final class image_job_repository_test extends \advanced_testcase {

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    public function test_create_and_acknowledge_terminal_lock(): void {
        global $USER;

        $this->setAdminUser();
        $location = new location(3, 'mod_page', 'content', 0, '/', 'pic.png', 2);

        job_repository::create_job($location, 'job-123', (int) $USER->id);
        $this->assertTrue(job_repository::has_blocking_job($location));

        job_repository::update_status(
            (int) job_repository::get_active_job_for_location($location)->id,
            job_repository::STATUS_APPLIED
        );

        $status = job_repository::get_location_status($location, true);
        $this->assertSame(job_repository::STATUS_APPLIED, $status['status']);
        $this->assertFalse(job_repository::has_blocking_job($location));
    }

    public function test_second_lock_is_rejected_while_pending(): void {
        global $USER;

        $this->setAdminUser();
        $location = new location(3, 'mod_page', 'content', 0, '/', 'pic.png', 2);

        job_repository::create_job($location, 'job-123', (int) $USER->id);

        $this->expectException(\moodle_exception::class);
        job_repository::create_job($location, 'job-456', (int) $USER->id);
    }
}
