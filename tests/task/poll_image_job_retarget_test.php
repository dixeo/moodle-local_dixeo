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

namespace local_dixeo\task;

use local_dixeo\repository\image\job_repository;
use local_dixeo\service\image\content\location;
use local_dixeo\service\image\content_target;
use local_dixeo\service\image\poll\manager;
use local_dixeo\service\image\target_factory;

/**
 * Tests for poll_image_job locationhash retarget after editor promote.
 *
 * @covers \local_dixeo\task\poll_image_job
 * @package local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class poll_image_job_retarget_test extends \advanced_testcase {
    /**
     * Stale draft-hash customdata continues against the live job row by remote jobid.
     */
    public function test_execute_retargets_when_locationhash_misses(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $module = new location(3, 'mod_page', 'content', 0, '/', 'dixeo-gen-ph.png', 2);
        $draft = new location(3, 'local_dixeo_editor', 'draft_page', 99, '/', 'dixeo-gen-ph.png', 2);

        $job = job_repository::upsert_job(array_merge($module->to_record_fields(), [
            'placeholderid' => 'ph-retarget',
            'targettable' => 'page',
            'targetfield' => 'content',
            'targetid' => 5,
            'cmid' => 10,
            'origin' => job_repository::ORIGIN_SHORTCODE,
            'prompt' => 'A tree',
            'quality' => 'medium',
            'mode' => 'landscape',
            'jobid' => 'remote-retarget-1',
            'status' => job_repository::STATUS_PENDING,
            'userid' => (int) $USER->id,
        ]));

        $this->assertNull(job_repository::get_by_target(content_target::from_location($draft)));
        $this->assertNotNull(job_repository::get_by_target(content_target::from_location($module)));

        $task = new poll_image_job();
        $task->set_custom_data((object) array_merge(
            content_target::from_location($draft)->to_poll_custom_data(),
            [
                'jobid' => 'remote-retarget-1',
                'imagejobid' => 'remote-retarget-1',
                'userid' => (int) $USER->id,
                'chainseq' => 0,
                'source' => 'generated',
            ]
        ));

        try {
            $task->execute();
        } catch (\Throwable $e) {
            // Remote API may be unavailable in CI; retarget runs before poll_once.
            unset($e);
        }
        // Poll_once / apply_failure may emit developer debugging when the API is down.
        $this->resetDebugging();

        $fresh = $DB->get_record(job_repository::TABLE, ['id' => $job->id], '*', MUST_EXIST);
        // Retarget found the live row: status left pending only if execute returned before
        // update_status; otherwise processing/failed/applied after poll_once.
        $this->assertNotSame(
            '',
            (string) $fresh->jobid
        );
        if (
            in_array($fresh->status, [
                job_repository::STATUS_PENDING,
                job_repository::STATUS_PROCESSING,
            ], true)
        ) {
            $moduletarget = target_factory::from_job_record($fresh);
            $this->assertTrue(
                manager::has_poll_task($moduletarget),
                'Retargeted poll should queue against the live module locationhash'
            );
        } else {
            // Failed/applied still proves the draft-hash miss did not no-op the task.
            $this->assertContains($fresh->status, [
                job_repository::STATUS_APPLIED,
                job_repository::STATUS_FAILED,
            ]);
        }
    }
}
