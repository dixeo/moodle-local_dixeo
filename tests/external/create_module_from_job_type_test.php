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

namespace local_dixeo\external;

use local_dixeo\dto\job_status;
use local_dixeo\repository\job_repository;
use local_dixeo\service\job_service;

/**
 * Tests for the expected module type check of create_module_from_job.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\external\create_module_from_job
 */
final class create_module_from_job_type_test extends \advanced_testcase {
    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    public function test_result_of_another_type_creates_nothing(): void {
        global $DB;

        [$course, $jobid] = $this->completed_page_job();

        $result = create_module_from_job::execute($jobid, (int) $course->id, 0, null, null, null, 'quiz');
        $this->resetDebugging();

        $this->assertFalse($result['success']);
        $this->assertSame('module_type_mismatch', $result['errorcode']);
        $this->assertSame(get_string('error:module_type_mismatch', 'local_dixeo'), $result['errormessage']);
        $this->assertSame(0, $DB->count_records('course_modules', ['course' => $course->id]));
    }

    public function test_result_of_the_expected_type_is_created(): void {
        [$course, $jobid] = $this->completed_page_job();

        $result = create_module_from_job::execute($jobid, (int) $course->id, 0, null, null, null, 'page');
        $this->resetDebugging();

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, (int) $result['cmid']);
    }

    public function test_empty_expected_type_accepts_the_result_type(): void {
        [$course, $jobid] = $this->completed_page_job();

        $result = create_module_from_job::execute($jobid, (int) $course->id);
        $this->resetDebugging();

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, (int) $result['cmid']);
    }

    /**
     * A course, its editing teacher as current user, and a completed page job bound to the course.
     *
     * @return array{0: \stdClass, 1: string} Course and job id.
     */
    private function completed_page_job(): array {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->getDataGenerator()->get_plugin_generator('local_dixeo')->create_terms_acceptance(['userid' => $teacher->id]);
        $this->setUser($teacher);

        $jobid = \core\uuid::generate();
        $repo = new job_repository();
        $repo->register(
            $jobid,
            (int) $course->id,
            (int) $teacher->id,
            'default',
            'module_generate',
            null,
            null,
            \local_dixeo\job_access_mode::COURSE_SHARED
        );

        $poller = $this->getMockBuilder(\local_dixeo\api\job_poller::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_job_status'])
            ->getMock();
        $poller->method('get_job_status')->willReturn(new job_status(
            jobid: $jobid,
            type: 'module',
            status: 'completed',
            progress: 100,
            createdat: time(),
            result: [
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
            ]
        ));
        service_factory::set_test_job_service(new job_service(null, $poller, $repo));

        return [$course, $jobid];
    }
}
