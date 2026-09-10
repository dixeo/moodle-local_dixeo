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

namespace local_dixeo\service\image\poll;

use local_dixeo\repository\image\job_repository;
use local_dixeo\service\image\content\location;
use local_dixeo\service\image\content_target;

/**
 * Tests for image poll manager orphan requeue.
 *
 * @covers \local_dixeo\service\image\poll\manager
 * @package local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class manager_test extends \advanced_testcase {
    /**
     * ensure_poll_for_job queues when no adhoc exists.
     */
    public function test_ensure_poll_queues_when_orphaned(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $location = new location(3, 'mod_page', 'content', 0, '/', 'pic.png', 2);
        $job = job_repository::upsert_job(array_merge($location->to_record_fields(), [
            'placeholderid' => 'ph-orphan',
            'targettable' => 'page',
            'targetfield' => 'content',
            'targetid' => 5,
            'cmid' => 10,
            'origin' => job_repository::ORIGIN_SHORTCODE,
            'prompt' => 'A tree',
            'quality' => 'medium',
            'mode' => 'landscape',
            'jobid' => 'remote-orphan-1',
            'status' => job_repository::STATUS_PROCESSING,
            'userid' => (int) $USER->id,
        ]));

        $target = content_target::from_location($location);
        $this->assertFalse(manager::has_poll_task($target));
        $this->assertTrue(manager::ensure_poll_for_job($job));
        $this->assertTrue(manager::has_poll_task($target));
        $this->assertFalse(manager::ensure_poll_for_job($job));
    }
}
