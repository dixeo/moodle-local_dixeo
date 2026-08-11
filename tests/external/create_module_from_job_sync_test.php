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
 * Tests syncfiles gating on create_module_from_job (DIXEO-SEC-007).
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\external;

use local_dixeo\dto\job_status;
use local_dixeo\repository\course_ai_repository;
use local_dixeo\repository\job_repository;
use local_dixeo\service\file_sync_service;
use local_dixeo\service\job_service;

/**
 * Capability gating for post-module-creation file sync.
 *
 * @covers \local_dixeo\external\create_module_from_job
 */
final class create_module_from_job_sync_test extends \advanced_testcase {
    /**
     * Build a job service mock that returns a completed page-creation job.
     *
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @return array{0: job_service, 1: string} Job service mock and job id.
     */
    private function mock_completed_page_job_service(int $courseid, int $userid): array {
        $jobid = \core\uuid::generate();
        $repo = new job_repository();
        $repo->register(
            $jobid,
            $courseid,
            $userid,
            'default',
            'module_generate',
            null,
            null,
            \local_dixeo\job_access_mode::COURSE_SHARED
        );

        $result = [
            'moduleType' => 'page',
            'creation' => [
                [
                    'action' => 'create_module',
                    'save_as' => 'module',
                    'fields' => [
                        'name' => ['source' => '$.name'],
                        'intro' => ['source' => '$.intro'],
                        'content' => ['source' => '$.content'],
                    ],
                ],
            ],
            'data' => [
                'name' => 'Generated page',
                'intro' => 'Intro',
                'content' => '<p>Body</p>',
            ],
        ];

        $poller = $this->getMockBuilder(\local_dixeo\api\job_poller::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_job_status'])
            ->getMock();
        $poller->expects($this->once())
            ->method('get_job_status')
            ->with($jobid)
            ->willReturn(new job_status(
                jobid: $jobid,
                type: 'module',
                status: 'completed',
                progress: 100,
                createdat: time(),
                result: $result
            ));

        return [new job_service(null, $poller, $repo), $jobid];
    }

    /**
     * Deny syncfiles for editingteacher in a course (overrides archetype allow).
     *
     * @param \context_course $context Course context.
     */
    private function deny_syncfiles_for_editingteacher(\context_course $context): void {
        global $DB;

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('local/dixeo:syncfiles', CAP_PROHIBIT, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
    }

    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    public function test_create_module_succeeds_without_syncfiles_and_skips_sync(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $context = \context_course::instance($course->id);
        $this->deny_syncfiles_for_editingteacher($context);
        $this->setUser($teacher);

        [$jobservice, $jobid] = $this->mock_completed_page_job_service((int) $course->id, (int) $teacher->id);
        service_factory::set_test_job_service($jobservice);

        $syncmock = $this->createMock(file_sync_service::class);
        $syncmock->expects($this->never())->method('enable_and_queue_sync_after_module_creation');
        service_factory::set_test_file_sync_service($syncmock);

        $result = create_module_from_job::execute($jobid, (int) $course->id);
        $this->resetDebugging();

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, (int) $result['cmid']);
        $this->assertNull((new course_ai_repository())->get_by_courseid($course->id));
    }

    public function test_create_module_queues_sync_when_syncfiles_allowed(): void {
        global $DB;

        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $context = \context_course::instance($course->id);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('local/dixeo:syncfiles', CAP_ALLOW, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($teacher);

        [$jobservice, $jobid] = $this->mock_completed_page_job_service((int) $course->id, (int) $teacher->id);
        service_factory::set_test_job_service($jobservice);

        $repository = new course_ai_repository();
        $syncservice = new file_sync_service($repository);
        service_factory::set_test_file_sync_service($syncservice);

        $result = create_module_from_job::execute($jobid, (int) $course->id);
        $this->resetDebugging();

        $this->assertTrue($result['success']);
        $record = $repository->get_by_courseid($course->id);
        $this->assertNotNull($record);
        $this->assertSame(1, (int) $record->enabled);
    }

    /**
     * Fill jobs return content-only data; creation DSL still references $.name and $.intro.
     */
    public function test_create_module_from_fill_job_with_content_only_data(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $jobid = \core\uuid::generate();
        $repo = new job_repository();
        $repo->register(
            $jobid,
            (int) $course->id,
            (int) $teacher->id,
            'default',
            'module_fill',
            null,
            null,
            \local_dixeo\job_access_mode::COURSE_SHARED
        );

        $result = [
            'moduleType' => 'page',
            'creation' => [
                [
                    'action' => 'create_module',
                    'save_as' => 'module',
                    'fields' => [
                        'name' => ['source' => '$.name'],
                        'intro' => ['source' => '$.intro'],
                        'introformat' => ['value' => 1],
                        'content' => ['source' => '$.content'],
                        'contentformat' => ['value' => 1],
                    ],
                ],
            ],
            'data' => [
                'content' => '<p>Fill body</p>',
            ],
        ];

        $poller = $this->getMockBuilder(\local_dixeo\api\job_poller::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_job_status'])
            ->getMock();
        $poller->expects($this->once())
            ->method('get_job_status')
            ->with($jobid)
            ->willReturn(new job_status(
                jobid: $jobid,
                type: 'fill_module',
                status: 'completed',
                progress: 100,
                createdat: time(),
                result: $result
            ));

        service_factory::set_test_job_service(new job_service(null, $poller, $repo));

        $out = create_module_from_job::execute(
            $jobid,
            (int) $course->id,
            1,
            null,
            'Structure page title',
            ''
        );
        $this->resetDebugging();

        $this->assertTrue($out['success']);
        $this->assertGreaterThan(0, (int) $out['cmid']);
    }
}
