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
 * Tests for counting a user's recent jobs on a course module.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\dto\job_binding_metadata;
use local_dixeo\repository\job_repository;
use local_dixeo\service\assign_ux_service;
use local_dixeo\service\job_service;

/**
 * Only jobs of this user, module and component since the given time are counted.
 *
 * @covers \local_dixeo\repository\job_repository::count_for_module_since
 * @covers \local_dixeo\service\assign_ux_service::count_recent_jobs
 */
final class job_repository_count_test extends \advanced_testcase {
    public function test_count_filters_by_user_module_component_and_time(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $other = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $peer = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $repository = new job_repository();

        $register = function (string $jobid, int $userid, int $cmid, string $component) use ($repository, $course): void {
            $repository->register(
                $jobid,
                (int) $course->id,
                $userid,
                'default',
                'assign_review',
                $component,
                job_binding_metadata::for_module('assign', $cmid)
            );
        };
        $register('mine-1', (int) $student->id, (int) $assign->cmid, 'plagiarism_dixeo');
        $register('mine-2', (int) $student->id, (int) $assign->cmid, 'plagiarism_dixeo');
        $register('mine-old', (int) $student->id, (int) $assign->cmid, 'plagiarism_dixeo');
        $register('peer', (int) $peer->id, (int) $assign->cmid, 'plagiarism_dixeo');
        $register('other-cm', (int) $student->id, (int) $other->cmid, 'plagiarism_dixeo');
        $register('other-component', (int) $student->id, (int) $assign->cmid, 'block_dixeo_tutor');
        $since = time() - HOURSECS;
        $DB->set_field('local_dixeo_jobs', 'timecreated', $since - 1, ['jobid' => 'mine-old']);

        $this->assertSame(
            2,
            $repository->count_for_module_since((int) $student->id, (int) $assign->cmid, 'plagiarism_dixeo', $since)
        );
        $this->assertSame(
            3,
            $repository->count_for_module_since((int) $student->id, (int) $assign->cmid, 'plagiarism_dixeo', $since - 1)
        );

        $job = $this->createMock(job_service::class);
        $job->method('get_job_repository')->willReturn($repository);
        $service = new assign_ux_service($job, null, null, 'test-ns');
        $this->assertSame(2, $service->count_recent_jobs((int) $student->id, (int) $assign->cmid, $since));
        $this->assertSame(1, $service->count_recent_jobs((int) $peer->id, (int) $assign->cmid, $since));
    }
}
