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

use core\task\manager as task_manager;
use local_dixeo\dsl\actions\create_h5p_module_action;
use local_dixeo\repository\image\job_repository;
use local_dixeo\service\h5p_packaging_service;
use local_dixeo\service\image\image_target;
use local_dixeo\service\image\policy;
use local_dixeo\task\poll_image_job;

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
                null
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
     * Each well-formed card_images entry queues one card image job; malformed ones are ignored.
     */
    public function test_execute_queues_one_image_per_card(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('image_generation_enabled', 1, 'local_dixeo');
        set_config('image_generation_content_mode', policy::MODE_GENERATE, 'local_dixeo');

        [$course, $activity] = $this->run_flashcards_action([
            ['card' => 0, 'title' => 'Leaf', 'prompt' => 'A green leaf'],
            ['card' => 2, 'title' => 'Root', 'prompt' => 'Roots in soil'],
            ['card' => 1, 'title' => 'No prompt'],
            ['card' => '1', 'title' => 'String index', 'prompt' => 'Stem'],
            ['card' => 9, 'title' => 'Missing card', 'prompt' => 'Flower'],
            'not an entry',
        ]);

        $rows = $DB->get_records(job_repository::TABLE, ['target_kind' => image_target::KIND_H5P_CARD], 'itemid');
        $this->assertSame([0, 2], array_values(array_map(fn($row) => (int) $row->itemid, $rows)));
        foreach ($rows as $row) {
            $this->assertSame((int) $activity->cmid, (int) $row->objectid);
            $this->assertSame((int) $course->id, (int) $row->courseid);
            $this->assertSame('', $row->jobid);
            $this->assertSame(job_repository::STATUS_PENDING, $row->status);
            $this->assertSame((int) $USER->id, (int) $row->userid);
        }

        $requests = [];
        foreach (task_manager::get_adhoc_tasks(poll_image_job::class) as $task) {
            $data = $task->get_custom_data();
            $requests[(int) $data->cardindex] = $data->request;
        }
        ksort($requests);
        $this->assertSame([0, 2], array_keys($requests));
        $this->assertSame('A green leaf', $requests[0]->prompt);
        $this->assertSame('Leaf', $requests[0]->title);
        $this->assertSame('h5pactivity', $requests[0]->binding->moduletype);
        $this->assertSame((int) $activity->cmid, $requests[0]->binding->cmid);
        $this->assertSame(\context_module::instance($activity->cmid)->id, $requests[0]->binding->contextid);
        $this->assertNotSame($requests[0]->idempotencykey, $requests[2]->idempotencykey);
    }

    /**
     * No card image is queued while content image generation is off.
     */
    public function test_execute_queues_nothing_when_image_generation_is_off(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('image_generation_enabled', 0, 'local_dixeo');

        $this->run_flashcards_action([['card' => 0, 'title' => 'Leaf', 'prompt' => 'A green leaf']]);

        $this->assertSame(0, $DB->count_records(job_repository::TABLE));
        $this->assertSame([], task_manager::get_adhoc_tasks(poll_image_job::class));
    }

    /**
     * Run the action for a three-card flashcards activity with the given card_images.
     *
     * @param mixed $cardimages Value under $.cardImages.
     * @return array [course, activity]
     */
    private function run_flashcards_action(mixed $cardimages): array {
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('h5pactivity', ['course' => $course->id]);

        $packaging = $this->createMock(h5p_packaging_service::class);
        $packaging->expects($this->once())
            ->method('create_activity')
            ->willReturn(['id' => (int) $activity->id, 'cmid' => (int) $activity->cmid]);

        $resolver = new value_resolver([
            'name' => 'Flashcards',
            'content' => ['cards' => [['text' => 'Leaf'], ['text' => 'Stem'], ['text' => 'Root']]],
            'cardImages' => $cardimages,
        ], [], [
            'courseid' => (int) $course->id,
            'sectionid' => 2,
            'sectionnum' => 1,
        ]);

        (new create_h5p_module_action($packaging))->execute([
            'main_library' => 'H5P.Flashcards 1.7',
            'fields' => [
                'name' => ['source' => '$.name'],
                'content' => ['source' => '$.content'],
                'card_images' => ['source' => '$.cardImages'],
            ],
        ], $resolver);

        return [$course, $activity];
    }
}
