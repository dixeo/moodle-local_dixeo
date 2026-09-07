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

    /**
     * Re-queued jobs must reset timecreated so get_active_job() does not instantly time out.
     */
    public function test_reupsert_resets_timecreated_after_stale_lock(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $location = new location(3, 'mod_page', 'content', 0, '/', 'pic.png', 2);
        $record = job_repository::upsert_job(array_merge($location->to_record_fields(), [
            'jobid' => 'job-stale',
            'status' => job_repository::STATUS_PENDING,
            'origin' => job_repository::ORIGIN_MODAL,
            'userid' => (int) $USER->id,
        ]));

        $DB->set_field(
            job_repository::TABLE,
            'timecreated',
            time() - job_repository::TIMEOUT_SECONDS - 120,
            ['id' => $record->id]
        );

        $before = job_repository::get_active_job_for_location($location);
        $this->assertSame(job_repository::STATUS_FAILED, $before->status);

        $refreshed = job_repository::upsert_job(array_merge($location->to_record_fields(), [
            'jobid' => 'job-fresh',
            'status' => job_repository::STATUS_PENDING,
            'origin' => job_repository::ORIGIN_MODAL,
            'userid' => (int) $USER->id,
        ]));

        $this->assertGreaterThan(time() - 10, (int) $refreshed->timecreated);
        $active = job_repository::get_active_job_for_location($location);
        $this->assertSame(job_repository::STATUS_PENDING, $active->status);
        $status = job_repository::get_location_status($location);
        $this->assertSame(job_repository::STATUS_PENDING, $status['status']);
    }

    /**
     * Re-tracking the same remote job inside the lock window refreshes the row; another job still locks.
     */
    public function test_reupsert_same_jobid_inside_lock_window_is_idempotent(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $location = new location(3, 'mod_page', 'content', 0, '/', 'pic.png', 2);
        $fields = array_merge($location->to_record_fields(), [
            'jobid' => 'job-same',
            'status' => job_repository::STATUS_PENDING,
            'origin' => job_repository::ORIGIN_STRUCTURE,
            'userid' => (int) $USER->id,
        ]);
        $record = job_repository::upsert_job($fields);

        $DB->set_field(job_repository::TABLE, 'timecreated', time() - 120, ['id' => $record->id]);

        $refreshed = job_repository::upsert_job($fields);
        $this->assertSame((int) $record->id, (int) $refreshed->id);
        $this->assertSame('job-same', $refreshed->jobid);
        $this->assertSame(job_repository::STATUS_PENDING, $refreshed->status);
        $this->assertGreaterThan(time() - 10, (int) $refreshed->timecreated);

        try {
            job_repository::upsert_job(array_merge($fields, ['jobid' => 'job-other']));
            $this->fail('Expected dixeo_image_job_locked');
        } catch (\moodle_exception $e) {
            $this->assertSame('dixeo_image_job_locked', $e->errorcode);
        }
    }
}
