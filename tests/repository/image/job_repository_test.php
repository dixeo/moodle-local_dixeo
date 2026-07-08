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

namespace local_dixeo\repository\image;


use local_dixeo\service\image\content\location;

/**
 * Tests for job_repository.
 *
 * @covers \local_dixeo\repository\image\job_repository
 * @package local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class job_repository_test extends \advanced_testcase {
    /**
     * Test upsert and get active job.
     */
    public function test_upsert_and_get_active_job(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $location = new location(3, 'mod_page', 'content', 0, '/', 'pic.png', 2);
        $record = job_repository::upsert_job(array_merge($location->to_record_fields(), [
            'placeholderid' => 'ph-1',
            'targettable' => 'page',
            'targetfield' => 'content',
            'targetid' => 5,
            'cmid' => 10,
            'origin' => job_repository::ORIGIN_SHORTCODE,
            'prompt' => 'A tree',
            'quality' => 'medium',
            'mode' => 'landscape',
            'jobid' => 'job-1',
            'status' => job_repository::STATUS_PENDING,
            'errormessage' => null,
            'userid' => (int) $USER->id,
        ]));

        $this->assertSame('job-1', $record->jobid);
        $this->assertTrue(job_repository::has_blocking_job($location));

        job_repository::update_status((int) $record->id, job_repository::STATUS_APPLIED);
        $this->assertFalse(job_repository::has_blocking_job($location));
    }

    /**
     * Test rejects second pending job.
     */
    public function test_rejects_second_pending_job(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $location = new location(3, 'mod_page', 'content', 0, '/', 'pic.png', 2);
        job_repository::upsert_job(array_merge($location->to_record_fields(), [
            'jobid' => 'job-1',
            'status' => job_repository::STATUS_PENDING,
            'origin' => job_repository::ORIGIN_MODAL,
            'userid' => (int) $USER->id,
        ]));

        $this->expectException(\moodle_exception::class);
        job_repository::upsert_job(array_merge($location->to_record_fields(), [
            'jobid' => 'job-2',
            'status' => job_repository::STATUS_PENDING,
            'origin' => job_repository::ORIGIN_MODAL,
            'userid' => (int) $USER->id,
        ]));
    }
}
