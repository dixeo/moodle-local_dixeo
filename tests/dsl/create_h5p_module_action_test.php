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

namespace local_dixeo\dsl;

use local_dixeo\dsl\actions\create_h5p_module_action;
use local_dixeo\dto\job_status;
use local_dixeo\service\h5p_packaging_service;
use local_dixeo\service\job_service;

/**
 * Unit tests for create_h5p_module_action.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\dsl\actions\create_h5p_module_action
 */
final class create_h5p_module_action_test extends \advanced_testcase {
    /**
     * Test execute allows missing intro in ai data.
     */
    public function test_execute_allows_missing_intro_in_ai_data(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Post-creation shortcode processing loads the real record and module context.
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('h5pactivity', ['course' => $course->id]);

        $packaging = $this->createMock(h5p_packaging_service::class);
        $packaging->expects($this->once())
            ->method('create_activity')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                'H5P Quiz',
                '',
                'H5P.QuestionSet 1.20',
                $this->isType('array'),
                $this->anything(),
                null,
                []
            )
            ->willReturn(['id' => (int) $activity->id, 'cmid' => (int) $activity->cmid]);

        $action = new create_h5p_module_action($packaging);
        $resolver = new value_resolver([
            'name' => 'H5P Quiz',
            'content' => ['library' => 'H5P.QuestionSet 1.20', 'params' => []],
        ], [], [
            'courseid' => (int) $course->id,
            'sectionid' => 2,
            'sectionnum' => 1,
        ]);

        $result = $action->execute([
            'main_library' => 'H5P.QuestionSet 1.20',
            'fields' => [
                'name' => ['source' => '$.name'],
                'intro' => ['source' => '$.intro'],
                'content' => ['source' => '$.content'],
            ],
        ], $resolver);

        $this->assertSame((int) $activity->cmid, $result['cmid']);
    }

    /**
     * A result with no image_jobs list strips card images and creates the activity without files.
     */
    public function test_execute_strips_card_images_when_image_jobs_are_absent(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('h5pactivity', ['course' => $course->id]);

        $packaging = $this->createMock(h5p_packaging_service::class);
        $packaging->expects($this->once())
            ->method('create_activity')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                'Flashcards',
                '',
                'H5P.Flashcards 1.7',
                $this->callback(function (array $content): bool {
                    return !isset($content['cards'][0]['image']);
                }),
                $this->anything(),
                null,
                []
            )
            ->willReturn(['id' => (int) $activity->id, 'cmid' => (int) $activity->cmid]);

        $action = new create_h5p_module_action($packaging);
        $resolver = new value_resolver([
            'name' => 'Flashcards',
            'content' => [
                'cards' => [[
                    'text' => 'Leaf',
                    'image' => ['path' => 'images/card-1.jpeg'],
                ]],
            ],
        ], [], [
            'courseid' => (int) $course->id,
            'sectionid' => 2,
            'sectionnum' => 1,
        ]);

        $result = $action->execute([
            'main_library' => 'H5P.Flashcards 1.7',
            'fields' => [
                'name' => ['source' => '$.name'],
                'content' => ['source' => '$.content'],
            ],
        ], $resolver);

        $this->assertSame((int) $activity->cmid, $result['cmid']);
    }

    /**
     * An empty download drops that card image and still creates the activity.
     */
    public function test_execute_drops_a_card_image_when_the_download_is_empty(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('h5pactivity', ['course' => $course->id]);
        $jobid = '11111111-1111-4111-8111-111111111111';

        $jobs = $this->createMock(job_service::class);
        $jobs->expects($this->once())
            ->method('get_job_status')
            ->with($jobid, (int) $course->id, $this->anything())
            ->willReturn(new job_status(
                jobid: $jobid,
                type: 'generate_image',
                status: job_status::STATUS_COMPLETED,
                progress: 100,
                createdat: time(),
                result: []
            ));

        $packaging = $this->createMock(h5p_packaging_service::class);
        $packaging->expects($this->once())
            ->method('create_activity')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                'Flashcards',
                '',
                'H5P.Flashcards 1.7',
                $this->callback(function (array $content): bool {
                    return !isset($content['cards'][0]['image']);
                }),
                $this->anything(),
                null,
                []
            )
            ->willReturn(['id' => (int) $activity->id, 'cmid' => (int) $activity->cmid]);

        $action = new create_h5p_module_action($packaging, $jobs);
        $resolver = new value_resolver([
            'name' => 'Flashcards',
            'content' => [
                'cards' => [[
                    'text' => 'Leaf',
                    'image' => ['path' => 'images/card-1.jpeg'],
                ]],
            ],
            'imageJobs' => [[
                'path' => 'images/card-1.jpeg',
                'jobId' => $jobid,
            ]],
        ], [], [
            'courseid' => (int) $course->id,
            'sectionid' => 2,
            'sectionnum' => 1,
        ]);

        $result = $action->execute([
            'main_library' => 'H5P.Flashcards 1.7',
            'fields' => [
                'name' => ['source' => '$.name'],
                'content' => ['source' => '$.content'],
                'image_jobs' => ['source' => '$.imageJobs'],
            ],
        ], $resolver);

        $this->assertSame((int) $activity->cmid, $result['cmid']);
    }

    /**
     * A completed image job is passed to the package under the path from image_jobs.
     */
    public function test_execute_passes_a_downloaded_image_to_the_package(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('h5pactivity', ['course' => $course->id]);
        $jobid = '22222222-2222-4222-8222-222222222222';

        $jobs = $this->createMock(job_service::class);
        $jobs->expects($this->once())
            ->method('get_job_status')
            ->willReturn(new job_status(
                jobid: $jobid,
                type: 'generate_image',
                status: job_status::STATUS_COMPLETED,
                progress: 100,
                createdat: time(),
                result: ['image_base64' => base64_encode('jpeg-bytes')]
            ));

        $packaging = $this->createMock(h5p_packaging_service::class);
        $packaging->expects($this->once())
            ->method('create_activity')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                'Flashcards',
                '',
                'H5P.Flashcards 1.7',
                $this->callback(function (array $content): bool {
                    return ($content['cards'][0]['image']['path'] ?? '') === 'images/card-1.jpeg';
                }),
                $this->anything(),
                null,
                ['images/card-1.jpeg' => 'jpeg-bytes']
            )
            ->willReturn(['id' => (int) $activity->id, 'cmid' => (int) $activity->cmid]);

        $action = new create_h5p_module_action($packaging, $jobs);
        $resolver = new value_resolver([
            'name' => 'Flashcards',
            'content' => [
                'cards' => [[
                    'text' => 'Leaf',
                    'image' => ['path' => 'images/card-1.jpeg'],
                ]],
            ],
            'imageJobs' => [[
                'path' => 'images/card-1.jpeg',
                'jobId' => $jobid,
            ]],
        ], [], [
            'courseid' => (int) $course->id,
            'sectionid' => 2,
            'sectionnum' => 1,
        ]);

        $action->execute([
            'main_library' => 'H5P.Flashcards 1.7',
            'fields' => [
                'name' => ['source' => '$.name'],
                'content' => ['source' => '$.content'],
                'image_jobs' => ['source' => '$.imageJobs'],
            ],
        ], $resolver);
    }
}
