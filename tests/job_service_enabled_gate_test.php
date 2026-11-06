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

namespace local_dixeo;

use local_dixeo\api\client;
use local_dixeo\dto\job_binding_metadata;
use local_dixeo\external\service_factory;
use local_dixeo\repository\job_repository;
use local_dixeo\service\job_service;

/**
 * Remote job submissions stop while Dixeo is off; stopping a job does not.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\service\job_service
 */
final class job_service_enabled_gate_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass Page module record. */
    private \stdClass $page;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
        $this->page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
    }

    /**
     * Site off stops both submit methods before any request or job row.
     */
    public function test_site_off_stops_submissions(): void {
        $this->set_enabled(\context_system::instance(), '0');

        $this->assert_submissions_refused(['courseId' => (int) $this->course->id], null);
    }

    /**
     * Course off stops a submission bound to a module context of that course.
     */
    public function test_course_off_stops_module_bound_submissions(): void {
        $this->set_enabled(\context_course::instance($this->course->id), '0');

        $this->assert_submissions_refused(
            ['courseId' => (int) $this->course->id],
            job_binding_metadata::for_module('page', (int) $this->page->cmid)
        );
    }

    /**
     * Course off stops a submission that only names the course in its payload.
     */
    public function test_course_off_stops_payload_course_submissions(): void {
        $this->set_enabled(\context_course::instance($this->course->id), '0');

        $this->assert_submissions_refused(['courseId' => (int) $this->course->id], null);
    }

    /**
     * Another course that is still on submits and registers its job.
     */
    public function test_other_course_still_submits(): void {
        global $DB;

        $other = $this->getDataGenerator()->create_course();
        $this->set_enabled(\context_course::instance($this->course->id), '0');
        $client = $this->createMock(client::class);
        $client->expects($this->once())->method('post')->willReturn(['id' => 'job-other-course']);
        $service = new job_service($client, null, new job_repository());

        $result = $service->submit_job('/v1/modules/edit', ['courseId' => (int) $other->id], 'local_dixeo');

        $this->assertSame('job-other-course', $result->jobid);
        $this->assertTrue($DB->record_exists('local_dixeo_jobs', ['jobid' => 'job-other-course']));
    }

    /**
     * Cancelling a job still reaches the API while Dixeo is off.
     */
    public function test_cancel_posts_while_dixeo_is_off(): void {
        $repo = new job_repository();
        $repo->register('job-cancel-off', (int) $this->course->id, (int) get_admin()->id, 'default', 'module_edit');
        $this->set_enabled(\context_system::instance(), '0');
        $client = $this->createMock(client::class);
        $client->expects($this->once())
            ->method('post')
            ->with('/v1/jobs/job-cancel-off/cancel', [])
            ->willReturn(['status' => 'cancelled']);

        $result = (new job_service($client, null, $repo))->cancel_job('job-cancel-off');

        $this->assertSame('cancelled', $result['status']);
    }

    /**
     * Assert that submit_job() and submit_and_wait() both stop with error:dixeo_disabled.
     *
     * @param array $payload Request payload.
     * @param job_binding_metadata|null $metadata Caller metadata.
     */
    private function assert_submissions_refused(array $payload, ?job_binding_metadata $metadata): void {
        global $DB;

        $client = $this->createMock(client::class);
        $client->expects($this->never())->method('post');
        $client->expects($this->never())->method('post_multipart');
        $service = new job_service($client, null, new job_repository());

        $calls = [
            fn() => $service->submit_job('/v1/modules/edit', $payload, 'local_dixeo', $metadata),
            fn() => $service->submit_and_wait('/v1/modules/edit', $payload, 'module', 'local_dixeo', $metadata),
        ];
        foreach ($calls as $call) {
            try {
                $call();
                $this->fail('A submission while Dixeo is off must stop.');
            } catch (\moodle_exception $e) {
                $this->assertSame('error:dixeo_disabled', $e->errorcode);
                $this->assertSame('local_dixeo', $e->module);
            }
        }
        $this->assertSame(0, $DB->count_records('local_dixeo_jobs'));
    }

    /**
     * Store the Dixeo switch at a context.
     *
     * @param \context $context System or course context.
     * @param string $value '1' or '0'.
     */
    private function set_enabled(\context $context, string $value): void {
        service_factory::get_settings_config()->set('local_dixeo', 'enabled', $context, $value, false);
    }
}
