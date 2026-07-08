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
use local_dixeo\service\h5p_packaging_service;

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
}
