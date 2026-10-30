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
 * Tests for queued image generations submitted by the poll task.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\task;

use local_dixeo\api\exception\api_exception;
use local_dixeo\api\exception\payment_required_exception;
use local_dixeo\api\exception\rate_limit_exception;
use local_dixeo\dto\operation_result;
use local_dixeo\external\service_factory;
use local_dixeo\repository\image\job_repository;
use local_dixeo\service\image\content\file_service;
use local_dixeo\service\image\content\location;
use local_dixeo\service\image\content_target;
use local_dixeo\service\image\failure_message;
use local_dixeo\service\image\generation_request;
use local_dixeo\service\image\job_orchestrator;
use local_dixeo\service\image\policy;
use local_dixeo\service\image\poll\engine;
use local_dixeo\service\image\poll\manager;
use local_dixeo\service\image_generation_service;
use local_dixeo\service\job_service;

/**
 * Tests for the submit step of poll_image_job.
 *
 * @covers \local_dixeo\task\poll_image_job
 * @covers \local_dixeo\service\image\job_orchestrator
 * @covers \local_dixeo\service\image\poll\manager
 * @covers \local_dixeo\service\image\poll\engine
 */
final class poll_image_job_submit_test extends \advanced_testcase {
    /** @var location Placeholder location of the queued image. */
    private location $location;

    /**
     * Queue one content image generation.
     */
    protected function setUp(): void {
        global $USER;

        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('image_generation_enabled', 1, 'local_dixeo');
        set_config('image_generation_content_mode', policy::MODE_GENERATE, 'local_dixeo');

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $context = \context_module::instance($page->cmid);
        $this->location = new location(
            $context->id,
            'mod_page',
            'content',
            0,
            '/',
            file_service::stub_filename_for_placeholder('queued-placeholder'),
            (int) $course->id
        );
        file_service::create_stub($this->location, (int) $USER->id);

        $request = generation_request::for_content('local_dixeo', (int) $course->id, 'Cells', 'A cell', '1024x1024', 'medium');
        job_orchestrator::queue_generation(content_target::from_location($this->location), (int) $USER->id, $request, [
            'origin' => job_repository::ORIGIN_SHORTCODE,
            'placeholderid' => 'queued-placeholder',
        ]);
    }

    /**
     * Restore the shared services.
     */
    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * Queueing stores a pending row with no remote job and a task carrying the request.
     */
    public function test_queue_generation_creates_a_pending_row_and_a_submit_task(): void {
        $job = $this->job();
        $this->assertSame('', $job->jobid);
        $this->assertSame(job_repository::STATUS_PENDING, $job->status);
        $this->assertSame('pending', job_repository::get_status_for_target($this->target())['status']);

        $data = $this->queued_task()->get_custom_data();
        $this->assertSame('', $data->jobid);
        $this->assertSame('A cell', $data->request->prompt);
        $this->assertGreaterThan(0, $data->queuedat);
    }

    /**
     * An accepted submission stores the remote job id and queues the first poll.
     */
    public function test_accepted_submission_hands_over_to_polling(): void {
        $this->api_returns(operation_result::pending('remote-ok', 'pending'));

        $this->queued_task()->execute();

        $this->assertSame('remote-ok', $this->job()->jobid);
        $this->assertSame(job_repository::STATUS_PENDING, $this->job()->status);
        $data = $this->queued_task()->get_custom_data();
        $this->assertSame('remote-ok', $data->jobid);
        $this->assertObjectNotHasProperty('request', $data);
    }

    /**
     * A 429 reschedules the same request after Retry-After and keeps the image pending.
     */
    public function test_rate_limit_reschedules_after_retry_after(): void {
        $this->api_throws(new rate_limit_exception('busy', 120));
        $before = $this->queued_task()->get_custom_data();

        $start = time();
        $this->queued_task()->execute();

        $this->assertSame('', $this->job()->jobid);
        $this->assertSame(job_repository::STATUS_PENDING, $this->job()->status);
        $task = $this->queued_task();
        $this->assertGreaterThanOrEqual($start + 120, $task->get_next_run_time());
        $this->assertLessThanOrEqual(time() + 120, $task->get_next_run_time());
        $this->assertEquals($before->request, $task->get_custom_data()->request);
        $this->assertSame($before->queuedat, $task->get_custom_data()->queuedat);
    }

    /**
     * A lost connection or a 5xx is retried like a 429, with the shortest delay.
     */
    public function test_unreachable_api_is_retried(): void {
        $this->api_throws(new api_exception('connection_error', 'refused', 0));

        $start = time();
        $this->queued_task()->execute();

        $this->assertSame(job_repository::STATUS_PENDING, $this->job()->status);
        $this->assertGreaterThanOrEqual($start + engine::SUBMIT_RETRY_MIN_SECONDS, $this->queued_task()->get_next_run_time());
    }

    /**
     * The retry delay honours Retry-After within bounds.
     */
    public function test_retry_delay_is_clamped(): void {
        $this->assertSame(engine::SUBMIT_RETRY_MIN_SECONDS, engine::submit_retry_delay(null));
        $this->assertSame(engine::SUBMIT_RETRY_MIN_SECONDS, engine::submit_retry_delay(5));
        $this->assertSame(120, engine::submit_retry_delay(120));
        // A Unix timestamp sent by mistake must not park the task for decades.
        $this->assertSame(engine::SUBMIT_RETRY_MAX_SECONDS, engine::submit_retry_delay(time()));
    }

    /**
     * Past the submit window the job fails with the reason of the last refusal.
     */
    public function test_gives_up_after_the_submit_window(): void {
        $this->api_throws(new rate_limit_exception('busy', 60));
        $this->set_queuedat(time() - job_repository::SUBMIT_TIMEOUT_SECONDS + 30);

        $task = $this->queued_task();
        $task->execute();
        $this->resetDebugging();

        $job = $this->job();
        $this->assertSame(job_repository::STATUS_FAILED, $job->status);
        $this->assertSame(failure_message::CODE_RATE_LIMITED, $job->errormessage);
        $this->assertSame(
            get_string('error:busy', 'local_dixeo'),
            job_repository::get_status_for_target($this->target())['errormessage']
        );
        // Nothing new queued: only the record of the task that ran remains.
        $this->assertSame($task->get_id(), $this->queued_task()->get_id());
    }

    /**
     * A refusal retrying cannot fix fails the job at once with its API code.
     */
    public function test_non_retryable_refusal_fails_at_once(): void {
        $this->api_throws(new payment_required_exception('no credits'));

        $task = $this->queued_task();
        $task->execute();
        $this->resetDebugging();

        $job = $this->job();
        $this->assertSame(job_repository::STATUS_FAILED, $job->status);
        $this->assertSame('payment_required', $job->errormessage);
        $this->assertSame($task->get_id(), $this->queued_task()->get_id());
    }

    /**
     * A queued row whose task is gone ends failed instead of pending forever.
     */
    public function test_queued_row_without_task_fails(): void {
        manager::delete_queued($this->target());

        $this->assertFalse(manager::ensure_poll_for_job($this->job()));
        $this->resetDebugging();

        $this->assertSame(job_repository::STATUS_FAILED, $this->job()->status);
        $this->assertSame(failure_message::CODE_FAILED, $this->job()->errormessage);
    }

    /**
     * A queued row outlives the poll timeout while it waits for the API.
     */
    public function test_queued_row_is_not_expired_by_the_poll_timeout(): void {
        global $DB;

        $DB->set_field(job_repository::TABLE, 'timecreated', time() - job_repository::TIMEOUT_SECONDS - 60, []);
        (new cleanup_image_jobs())->execute();

        $this->assertSame(job_repository::STATUS_PENDING, $this->job()->status);
        $this->assertSame('pending', job_repository::get_status_for_target($this->target())['status']);
    }

    /**
     * Target of the queued image.
     *
     * @return content_target
     */
    private function target(): content_target {
        return content_target::from_location($this->location);
    }

    /**
     * Current job row.
     *
     * @return \stdClass
     */
    private function job(): \stdClass {
        return job_repository::get_by_target($this->target());
    }

    /**
     * The single queued poll task.
     *
     * @return poll_image_job
     */
    private function queued_task(): poll_image_job {
        $tasks = \core\task\manager::get_adhoc_tasks(poll_image_job::class);
        $this->assertCount(1, $tasks);
        return reset($tasks);
    }

    /**
     * Rewrite the queue time of the queued task.
     *
     * @param int $queuedat
     */
    private function set_queuedat(int $queuedat): void {
        global $DB;

        $task = $this->queued_task();
        $data = $task->get_custom_data();
        $data->queuedat = $queuedat;
        $DB->set_field('task_adhoc', 'customdata', json_encode($data), ['id' => $task->get_id()]);
    }

    /**
     * Make the API accept the submission.
     *
     * @param operation_result $result
     */
    private function api_returns(operation_result $result): void {
        $jobmock = $this->createMock(job_service::class);
        $jobmock->expects($this->once())->method('submit_job')->willReturn($result);
        service_factory::set_test_image_generation_service(new image_generation_service($jobmock));
    }

    /**
     * Make the API refuse the submission.
     *
     * @param \Throwable $exception
     */
    private function api_throws(\Throwable $exception): void {
        $jobmock = $this->createMock(job_service::class);
        $jobmock->expects($this->once())->method('submit_job')->willThrowException($exception);
        service_factory::set_test_image_generation_service(new image_generation_service($jobmock));
    }
}
