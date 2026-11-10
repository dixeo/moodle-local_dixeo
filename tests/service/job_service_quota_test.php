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
use local_dixeo\api\exception\api_exception;
use local_dixeo\api\job_poller;
use local_dixeo\dto\job_binding_metadata;
use local_dixeo\dto\job_quota;
use local_dixeo\dto\operation_result;
use local_dixeo\exception\job_quota_exceeded_exception;
use local_dixeo\repository\job_repository;
use local_dixeo\service\job_service;

/**
 * Job quotas are enforced atomically around the count, the POST and the registration.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\service\job_service
 */
final class job_service_quota_test extends \advanced_testcase {
    /** @var string Component the test jobs are registered for. */
    private const COMPONENT = 'local_dixeo';

    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass Page module record. */
    private \stdClass $page;

    /** @var \stdClass Acting user. */
    private \stdClass $user;

    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        $this->resetAfterTest();
        $CFG->lock_factory = '\\core\\lock\\db_record_lock_factory';
        $this->course = $this->getDataGenerator()->create_course();
        $this->page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        $this->user = $this->getDataGenerator()->create_user();
        $this->setUser($this->user);
    }

    /**
     * Below the limit, the job is posted and registered.
     */
    public function test_below_limit_posts_and_registers(): void {
        global $DB;

        $this->register_existing(2);
        $client = $this->createMock(client::class);
        $client->expects($this->once())->method('post')->willReturn(['id' => 'job-new']);

        $result = $this->service($client)->submit_job(
            '/v1/modules/edit',
            $this->payload(),
            self::COMPONENT,
            $this->metadata(),
            quota: new job_quota(3, HOURSECS)
        );

        $this->assertSame('job-new', $result->jobid);
        $this->assertTrue($DB->record_exists('local_dixeo_jobs', ['jobid' => 'job-new', 'cmid' => $this->page->cmid]));
    }

    /**
     * At the limit, nothing is posted.
     */
    public function test_at_limit_refuses_without_post(): void {
        $this->register_existing(3);

        $this->assert_refused(new job_quota(3, HOURSECS));
    }

    /**
     * Jobs outside the window do not count.
     */
    public function test_jobs_outside_window_do_not_count(): void {
        global $DB;

        $this->register_existing(3);
        $DB->set_field('local_dixeo_jobs', 'timecreated', time() - 2 * HOURSECS);
        $client = $this->createMock(client::class);
        $client->expects($this->once())->method('post')->willReturn(['id' => 'job-after-window']);

        $result = $this->service($client)->submit_job(
            '/v1/modules/edit',
            $this->payload(),
            self::COMPONENT,
            $this->metadata(),
            quota: new job_quota(3, HOURSECS)
        );

        $this->assertSame('job-after-window', $result->jobid);
    }

    /**
     * A flow that needs two jobs is refused when only one fits.
     */
    public function test_jobs_needed_reserves_room(): void {
        $this->register_existing(2);

        $this->assert_refused(new job_quota(3, HOURSECS, 2));
    }

    /**
     * A concurrent send holding the quota lock refuses the send without posting.
     */
    public function test_held_lock_refuses_without_post(): void {
        $factory = \core\lock\lock_config::get_lock_factory(job_quota::LOCK_TYPE);
        $held = $factory->get_lock(job_quota::lock_key(self::COMPONENT, (int) $this->user->id, (int) $this->page->cmid), 0);
        $this->assertNotFalse($held);

        try {
            $this->assert_refused(new job_quota(3, HOURSECS, 1, 0));
        } finally {
            $held->release();
        }
    }

    /**
     * A failed POST releases the lock, so the next send can take it.
     */
    public function test_failed_post_releases_lock(): void {
        $client = $this->createMock(client::class);
        $client->expects($this->exactly(2))
            ->method('post')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new api_exception('server_error', 'Upstream failure')),
                ['id' => 'job-retry']
            );
        $service = $this->service($client);
        $quota = new job_quota(3, HOURSECS, 1, 0);

        try {
            $service->submit_job('/v1/modules/edit', $this->payload(), self::COMPONENT, $this->metadata(), quota: $quota);
            $this->fail('The API failure must propagate.');
        } catch (api_exception $e) {
            $this->assertSame('server_error', $e->get_error_type());
        }

        $result = $service->submit_job('/v1/modules/edit', $this->payload(), self::COMPONENT, $this->metadata(), quota: $quota);
        $this->assertSame('job-retry', $result->jobid);
    }

    /**
     * Without a quota the send is unchanged, also when no course module is bound.
     */
    public function test_no_quota_is_unchanged(): void {
        global $DB;

        $this->register_existing(5);
        $client = $this->createMock(client::class);
        $client->expects($this->exactly(2))
            ->method('post')
            ->willReturnOnConsecutiveCalls(['id' => 'job-free-module'], ['id' => 'job-free-course']);
        $service = $this->service($client);

        $service->submit_job('/v1/modules/edit', $this->payload(), self::COMPONENT, $this->metadata());
        $service->submit_job('/v1/modules/edit', $this->payload(), self::COMPONENT);

        $this->assertTrue($DB->record_exists('local_dixeo_jobs', ['jobid' => 'job-free-module']));
        $this->assertSame(0, (int) $DB->get_field('local_dixeo_jobs', 'cmid', ['jobid' => 'job-free-course']));
    }

    /**
     * A quota needs a bound course module and a component.
     */
    public function test_quota_without_module_or_component_is_a_coding_error(): void {
        $client = $this->createMock(client::class);
        $client->expects($this->never())->method('post');
        $service = $this->service($client);
        $quota = new job_quota(3, HOURSECS);

        $calls = [
            fn() => $service->submit_job('/v1/modules/edit', $this->payload(), self::COMPONENT, null, quota: $quota),
            fn() => $service->submit_job('/v1/modules/edit', $this->payload(), null, $this->metadata(), quota: $quota),
        ];
        foreach ($calls as $call) {
            try {
                $call();
                $this->fail('A quota without a module or component must be refused.');
            } catch (\coding_exception $e) {
                $this->assertStringContainsString('job quota', $e->getMessage());
            }
        }
    }

    /**
     * submit_and_wait() releases the quota lock before it polls.
     */
    public function test_submit_and_wait_releases_lock_before_polling(): void {
        $client = $this->createMock(client::class);
        $client->expects($this->once())->method('post')->willReturn(['id' => 'job-wait']);
        $key = job_quota::lock_key(self::COMPONENT, (int) $this->user->id, (int) $this->page->cmid);
        $lockfree = null;
        $poller = $this->getMockBuilder(job_poller::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['poll'])
            ->getMock();
        $poller->expects($this->once())
            ->method('poll')
            ->willReturnCallback(function (string $jobid) use ($key, &$lockfree): operation_result {
                $lock = \core\lock\lock_config::get_lock_factory(job_quota::LOCK_TYPE)->get_lock($key, 0);
                $lockfree = $lock !== false;
                if ($lock) {
                    $lock->release();
                }
                return operation_result::completed($jobid, []);
            });
        $service = new job_service($client, $poller, new job_repository());

        $result = $service->submit_and_wait(
            '/v1/modules/edit',
            $this->payload(),
            'module',
            self::COMPONENT,
            $this->metadata(),
            quota: new job_quota(3, HOURSECS)
        );

        $this->assertTrue($result->completed);
        $this->assertTrue($lockfree);
    }

    /**
     * Assert that both submit methods refuse with the quota error and post nothing.
     *
     * @param job_quota $quota Quota to apply.
     */
    private function assert_refused(job_quota $quota): void {
        global $DB;

        $before = $DB->count_records('local_dixeo_jobs');
        $client = $this->createMock(client::class);
        $client->expects($this->never())->method('post');
        $client->expects($this->never())->method('post_multipart');
        $service = $this->service($client);

        $calls = [
            fn() => $service->submit_job('/v1/modules/edit', $this->payload(), self::COMPONENT, $this->metadata(), quota: $quota),
            fn() => $service->submit_and_wait(
                '/v1/modules/edit',
                $this->payload(),
                'module',
                self::COMPONENT,
                $this->metadata(),
                quota: $quota
            ),
        ];
        foreach ($calls as $call) {
            try {
                $call();
                $this->fail('A send over the quota must be refused.');
            } catch (job_quota_exceeded_exception $e) {
                $this->assertSame('error:job_quota_exceeded', $e->errorcode);
                $this->assertSame('local_dixeo', $e->module);
            }
        }
        $this->assertSame($before, $DB->count_records('local_dixeo_jobs'));
    }

    /**
     * Register jobs the acting user already started for the page.
     *
     * @param int $count Number of jobs.
     */
    private function register_existing(int $count): void {
        $repo = new job_repository();
        for ($i = 0; $i < $count; $i++) {
            $repo->register(
                'job-existing-' . $i,
                (int) $this->course->id,
                (int) $this->user->id,
                'default',
                'module_edit',
                self::COMPONENT,
                $this->metadata()
            );
        }
    }

    /**
     * Build a job service over the given client.
     *
     * @param client $client Mocked API client.
     * @return job_service
     */
    private function service(client $client): job_service {
        return new job_service($client, null, new job_repository());
    }

    /**
     * Request payload for the test course.
     *
     * @return array
     */
    private function payload(): array {
        return ['courseId' => (int) $this->course->id];
    }

    /**
     * Binding metadata for the test page.
     *
     * @return job_binding_metadata
     */
    private function metadata(): job_binding_metadata {
        return job_binding_metadata::for_module('page', (int) $this->page->cmid);
    }
}
