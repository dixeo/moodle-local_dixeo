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
 * Access mode is persisted at registration (default initiator_scoped;
 * opt-in course_shared). Enforcement reads the stored mode.
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
 * @covers \local_dixeo\job_access_mode
 */
final class job_binding_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_repository_register_defaults_to_initiator_scoped(): void {
        $repo = new job_repository();
        $repo->register('job-a', 10, 5, 'default', 'module_edit');

        $record = $repo->get_by_jobid('job-a');
        $this->assertSame(job_access_mode::INITIATOR_SCOPED->value, $record->accessmode);
        $this->assertSame(job_access_mode::INITIATOR_SCOPED, $repo->get_access_mode('job-a'));
    }

    public function test_repository_register_persists_course_shared(): void {
        $repo = new job_repository();
        $repo->register(
            'job-shared',
            10,
            5,
            'default',
            'module_generate',
            null,
            null,
            job_access_mode::COURSE_SHARED
        );

        $this->assertSame(job_access_mode::COURSE_SHARED, $repo->get_access_mode('job-shared'));
    }

    public function test_submit_job_registers_binding_from_payload(): void {
        $this->setAdminUser();

        $client = $this->createMock(client::class);
        $client->expects($this->once())
            ->method('post')
            ->with('/v1/modules/generate', $this->isType('array'))
            ->willReturn(['id' => 'remote-job-123']);

        $service = new job_service($client, null, new job_repository());
        $result = $service->submit_job(
            '/v1/modules/generate',
            [
                'courseId' => '42',
                'userId' => '7',
                'namespace' => 'ns-test',
                'moduleType' => 'page',
                'instructions' => 'Write something',
                'context' => 'ctx',
            ],
            null,
            null,
            job_access_mode::COURSE_SHARED
        );

        $this->assertEquals('remote-job-123', $result->jobid);
        $repo = new job_repository();
        $this->assertTrue($repo->belongs_to_course('remote-job-123', 42));
        $record = $repo->get_by_jobid('remote-job-123');
        $this->assertEquals(7, (int) $record->userid);
        $this->assertEquals('ns-test', $record->namespace);
        $this->assertEquals('module_generate', $record->operation);
        $this->assertSame(job_access_mode::COURSE_SHARED->value, $record->accessmode);
    }

    public function test_get_job_status_rejects_foreign_course_for_shared_job(): void {
        $repo = new job_repository();
        $repo->register(
            'job-bound',
            11,
            3,
            'default',
            'module_generate',
            null,
            null,
            job_access_mode::COURSE_SHARED
        );

        $client = $this->createMock(client::class);
        $client->expects($this->never())->method('get');

        $service = new job_service($client, null, $repo);

        $this->expectException(\moodle_exception::class);
        $service->get_job_status('job-bound', 99, 3);
    }

    public function test_get_job_status_allows_same_course_peer_for_shared_job(): void {
        $repo = new job_repository();
        $repo->register(
            'job-peer',
            15,
            3,
            'default',
            'module_generate',
            null,
            null,
            job_access_mode::COURSE_SHARED
        );

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
        // Peer userid 99 may poll a course_shared job in course 15.
        $status = $service->get_job_status('job-peer', 15, 99);
        $this->assertEquals('job-peer', $status->jobid);
        $this->assertEquals(40, $status->progress);
    }

    public function test_cancel_job_rejects_unregistered_job(): void {
        $client = $this->createMock(client::class);
        $client->expects($this->never())->method('post');
        $service = new job_service($client, null, new job_repository());

        $this->expectException(\moodle_exception::class);
        $service->cancel_job('never-registered', 5, 1);
    }

    public function test_cancel_job_allows_peer_for_shared_job(): void {
        $repo = new job_repository();
        $repo->register(
            'job-cancel',
            20,
            8,
            'default',
            'module_generate',
            null,
            null,
            job_access_mode::COURSE_SHARED
        );

        $client = $this->createMock(client::class);
        $client->expects($this->once())
            ->method('post')
            ->with('/v1/jobs/job-cancel/cancel', [])
            ->willReturn(['status' => 'cancelled']);

        $service = new job_service($client, null, $repo);
        $result = $service->cancel_job('job-cancel', 20, 99);
        $this->assertEquals('cancelled', $result['status']);
    }

    public function test_get_job_status_rejects_peer_for_initiator_scoped_job(): void {
        $repo = new job_repository();
        $repo->register('job-edit', 15, 3, 'default', 'module_edit');

        $client = $this->createMock(client::class);
        $service = new job_service($client, null, $repo);

        $this->expectException(\moodle_exception::class);
        $service->get_job_status('job-edit', 15, 99);
    }

    public function test_get_job_status_allows_owner_for_initiator_scoped_job(): void {
        $repo = new job_repository();
        $repo->register('job-edit-ok', 15, 3, 'default', 'module_edit');

        $poller = $this->getMockBuilder(\local_dixeo\api\job_poller::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_job_status'])
            ->getMock();
        $poller->expects($this->once())
            ->method('get_job_status')
            ->with('job-edit-ok')
            ->willReturn(new job_status(
                jobid: 'job-edit-ok',
                type: 'module',
                status: 'completed',
                progress: 100,
                createdat: time()
            ));

        $service = new job_service(null, $poller, $repo);
        $status = $service->get_job_status('job-edit-ok', 15, 3);
        $this->assertEquals('job-edit-ok', $status->jobid);
        $this->assertTrue($status->is_completed());
    }

    public function test_cancel_job_rejects_peer_for_initiator_scoped_job(): void {
        $repo = new job_repository();
        $repo->register('job-edit-cancel', 20, 8, 'default', 'module_edit');

        $client = $this->createMock(client::class);
        $client->expects($this->never())->method('post');
        $service = new job_service($client, null, $repo);

        $this->expectException(\moodle_exception::class);
        $service->cancel_job('job-edit-cancel', 20, 99);
    }

    public function test_initiator_scoped_without_userid_fails_closed(): void {
        $repo = new job_repository();
        $repo->register('job-needs-user', 15, 3, 'default', 'module_edit');

        $client = $this->createMock(client::class);
        $client->expects($this->never())->method('get');
        $service = new job_service($client, null, $repo);

        $this->expectException(\moodle_exception::class);
        $service->get_job_status('job-needs-user', 15);
    }

    public function test_submit_job_defaults_to_initiator_scoped(): void {
        $this->setAdminUser();

        $client = $this->createMock(client::class);
        $client->method('post')->willReturn(['id' => 'job-default-mode']);

        $service = new job_service($client, null, new job_repository());
        $service->submit_job('/v1/modules/edit', [
            'courseId' => '10',
            'userId' => '2',
        ]);

        $repo = new job_repository();
        $this->assertSame(
            job_access_mode::INITIATOR_SCOPED,
            $repo->get_access_mode('job-default-mode')
        );
    }

    public function test_is_valid_job_uuid(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/dixeo/lib.php');

        $this->assertTrue(local_dixeo_is_valid_job_uuid('550e8400-e29b-41d4-a716-446655440000'));
        $this->assertFalse(local_dixeo_is_valid_job_uuid('job-peer'));
        $this->assertFalse(local_dixeo_is_valid_job_uuid(''));
        $this->assertFalse(local_dixeo_is_valid_job_uuid('<script>'));
    }
}
