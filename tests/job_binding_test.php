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

/**
 * Tests for local Dixeo job ownership binding.
 *
 * Course-work jobs are isolated by course + capability, not by initiating user.
 * Userid is still stored for attribution and privacy export.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\api\client;
use local_dixeo\dto\job_status;
use local_dixeo\repository\job_repository;
use local_dixeo\service\job_service;

/**
 * Tests for local Dixeo job ownership binding and course access checks.
 *
 * @covers \local_dixeo\repository\job_repository
 * @covers \local_dixeo\service\job_service
 */
final class job_binding_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_repository_register_and_belongs_to_course(): void {
        $repo = new job_repository();
        $repo->register('job-a', 10, 5, 'default', 'module_generate');

        $this->assertTrue($repo->belongs_to_course('job-a', 10));
        $this->assertFalse($repo->belongs_to_course('job-a', 99));
        $this->assertFalse($repo->belongs_to_course('missing', 10));

        $record = $repo->get_by_jobid('job-a');
        $this->assertNotNull($record);
        $this->assertEquals(5, (int) $record->userid);
        $this->assertEquals('module_generate', $record->operation);
    }

    public function test_submit_job_registers_binding_from_payload(): void {
        $this->setAdminUser();

        $client = $this->createMock(client::class);
        $client->expects($this->once())
            ->method('post')
            ->with('/v1/modules/generate', $this->isType('array'))
            ->willReturn(['id' => 'remote-job-123']);

        $service = new job_service($client, null, new job_repository());
        $result = $service->submit_job('/v1/modules/generate', [
            'courseId' => '42',
            'userId' => '7',
            'namespace' => 'ns-test',
            'moduleType' => 'page',
            'instructions' => 'Write something',
            'context' => 'ctx',
        ]);

        $this->assertEquals('remote-job-123', $result->jobid);
        $repo = new job_repository();
        $this->assertTrue($repo->belongs_to_course('remote-job-123', 42));
        $record = $repo->get_by_jobid('remote-job-123');
        $this->assertEquals(7, (int) $record->userid);
        $this->assertEquals('ns-test', $record->namespace);
        $this->assertEquals('module_generate', $record->operation);
    }

    public function test_get_job_status_rejects_foreign_course(): void {
        $repo = new job_repository();
        $repo->register('job-bound', 11, 3, 'default', 'tutor_message');

        $client = $this->createMock(client::class);
        $client->expects($this->never())->method('get');

        $service = new job_service($client, null, $repo);

        $this->expectException(\moodle_exception::class);
        $service->get_job_status('job-bound', 99);
    }

    public function test_get_job_status_allows_same_course_other_user(): void {
        // Course-work model: any caller who may operate in the course can poll a peer's job.
        $repo = new job_repository();
        $repo->register('job-peer', 15, 3, 'default', 'module_generate');

        $poller = $this->getMockBuilder(\local_dixeo\api\job_poller::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_job_status'])
            ->getMock();
        $poller->expects($this->once())
            ->method('get_job_status')
            ->with('job-peer')
            ->willReturn(new job_status(
                jobid: 'job-peer',
                type: 'module',
                status: 'processing',
                progress: 40,
                createdat: time()
            ));

        $service = new job_service(null, $poller, $repo);
        $status = $service->get_job_status('job-peer', 15);
        $this->assertEquals('job-peer', $status->jobid);
        $this->assertEquals(40, $status->progress);
    }

    public function test_get_job_status_allows_matching_course(): void {
        $repo = new job_repository();
        $repo->register('job-ok', 15, 3, 'default', 'tutor_message');

        $poller = $this->getMockBuilder(\local_dixeo\api\job_poller::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_job_status'])
            ->getMock();
        $poller->expects($this->once())
            ->method('get_job_status')
            ->with('job-ok')
            ->willReturn(new job_status(
                jobid: 'job-ok',
                type: 'tutor',
                status: 'completed',
                progress: 100,
                createdat: time()
            ));

        $service = new job_service(null, $poller, $repo);
        $status = $service->get_job_status('job-ok', 15);
        $this->assertEquals('job-ok', $status->jobid);
        $this->assertTrue($status->is_completed());
    }

    public function test_cancel_job_rejects_unregistered_job(): void {
        $client = $this->createMock(client::class);
        $client->expects($this->never())->method('post');
        $service = new job_service($client, null, new job_repository());

        $this->expectException(\moodle_exception::class);
        $service->cancel_job('never-registered', 5);
    }

    public function test_get_job_status_rejects_same_course_peer_when_userid_required(): void {
        $repo = new job_repository();
        $repo->register('job-peer', 20, 3, 'default', 'module_edit');

        $poller = $this->getMockBuilder(\local_dixeo\api\job_poller::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_job_status'])
            ->getMock();
        $poller->expects($this->never())->method('get_job_status');

        $service = new job_service(null, $poller, $repo);

        $this->expectException(\moodle_exception::class);
        $service->get_job_status('job-peer', 20, 99);
    }

    public function test_cancel_job_rejects_same_course_peer_when_userid_required(): void {
        $repo = new job_repository();
        $repo->register('job-cancel-peer', 20, 3, 'default', 'module_edit');

        $client = $this->createMock(client::class);
        $client->expects($this->never())->method('post');

        $service = new job_service($client, null, $repo);

        $this->expectException(\moodle_exception::class);
        $service->cancel_job('job-cancel-peer', 20, 99);
    }
}
