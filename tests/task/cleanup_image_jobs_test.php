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
use local_dixeo\service\image\content\asset_helper;
use local_dixeo\service\image\content\file_service;
use local_dixeo\service\image\content\location;
use local_dixeo\service\image\content_target;

/**
 * Tests for the image job cleanup task.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\task\cleanup_image_jobs
 */
final class cleanup_image_jobs_test extends \advanced_testcase {
    /**
     * A timed-out shortcode job must fail the row and swap the pending placeholder in the content.
     */
    public function test_timed_out_job_gets_failure_placeholder(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $placeholderid = 'stuck-placeholder-uuid';
        $filename = file_service::stub_filename_for_placeholder($placeholderid);
        $img = '<img src="@@PLUGINFILE@@/' . $filename . '" class="img-fluid dixeo-img-gen-pending" ' .
            'data-dixeo-img-gen="' . $placeholderid . '" alt="" />';
        $label = $this->getDataGenerator()->create_module('label', [
            'course' => $course->id,
            'intro' => '<p>' . $img . '</p>',
            'introformat' => FORMAT_HTML,
        ]);
        $context = \context_module::instance($label->cmid);
        $location = new location($context->id, 'mod_label', 'intro', 0, '/', $filename, (int) $course->id);
        file_service::create_stub($location, (int) $USER->id);

        $job = job_repository::upsert(content_target::from_location($location), 'remote-job-id', (int) $USER->id, [
            'origin' => job_repository::ORIGIN_SHORTCODE,
            'placeholderid' => $placeholderid,
            'targettable' => 'label',
            'targetfield' => 'intro',
            'targetid' => (int) $label->id,
            'cmid' => (int) $label->cmid,
        ]);
        $DB->set_field(job_repository::TABLE, 'timecreated', time() - job_repository::TIMEOUT_SECONDS - 1, ['id' => $job->id]);

        (new cleanup_image_jobs())->execute();

        $fresh = $DB->get_record(job_repository::TABLE, ['id' => $job->id], '*', MUST_EXIST);
        $this->assertSame(job_repository::STATUS_FAILED, $fresh->status);

        $file = $location->get_stored_file();
        $this->assertNotFalse($file);
        $this->assertSame(sha1(asset_helper::get_error_binary()), sha1($file->get_content()));

        $intro = $DB->get_field('label', 'intro', ['id' => $label->id], MUST_EXIST);
        $this->assertStringNotContainsString('dixeo-img-gen-pending', $intro);
        $this->assertStringContainsString('dixeo-img-gen-failed', $intro);
    }

    /**
     * Jobs still within the timeout are left untouched.
     */
    public function test_recent_job_is_left_pending(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $label = $this->getDataGenerator()->create_module('label', ['course' => $course->id]);
        $context = \context_module::instance($label->cmid);
        $filename = file_service::stub_filename_for_placeholder('fresh-placeholder-uuid');
        $location = new location($context->id, 'mod_label', 'intro', 0, '/', $filename, (int) $course->id);
        file_service::create_stub($location, (int) $USER->id);
        $stubhash = $location->get_stored_file()->get_contenthash();

        $job = job_repository::upsert(content_target::from_location($location), 'remote-job-id', (int) $USER->id, [
            'origin' => job_repository::ORIGIN_SHORTCODE,
            'placeholderid' => 'fresh-placeholder-uuid',
        ]);

        (new cleanup_image_jobs())->execute();

        $this->assertSame(job_repository::STATUS_PENDING, $DB->get_field(job_repository::TABLE, 'status', ['id' => $job->id]));
        $this->assertSame($stubhash, $location->get_stored_file()->get_contenthash());
    }
}
