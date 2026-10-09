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

/**
 * Tests for {@see \local_dixeo\settings\config}.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\settings;

/**
 * Storage and capability tests for learning intent.
 *
 * @covers \local_dixeo\settings\config
 */
final class config_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * A course with no row uses the shipped default and ignores plugin config.
     */
    public function test_unset_course_returns_the_shipped_default(): void {
        $this->setAdminUser();
        set_config('learning_intent', 'Should be ignored', 'local_dixeo');

        $course = $this->getDataGenerator()->create_course();
        $resolved = (new config())->get('local_dixeo', 'learning_intent', \context_course::instance($course->id));

        $this->assertSame('', $resolved->get_value());
        $this->assertSame(resolver::LAYER_SITE, $resolved->get_source());
    }

    /**
     * Course text is stored on the course. The shipped default removes that row and is not written to plugin config.
     */
    public function test_course_value_is_stored_and_the_default_is_not(): void {
        global $DB;
        $this->setAdminUser();
        $config = new config();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $config->set('local_dixeo', 'learning_intent', $context, 'Course intent');

        $this->assertTrue($DB->record_exists(config::TABLE, $this->course_conditions($context)));
        $resolved = $config->get('local_dixeo', 'learning_intent', $context);
        $this->assertSame('Course intent', $resolved->get_value());
        $this->assertSame(resolver::LAYER_COURSE, $resolved->get_source());
        $this->assertFalse(get_config('local_dixeo', 'learning_intent'));

        $config->set('local_dixeo', 'learning_intent', $context, '');

        $this->assertFalse($DB->record_exists(config::TABLE, $this->course_conditions($context)));
        $cleared = $config->get('local_dixeo', 'learning_intent', $context);
        $this->assertSame('', $cleared->get_value());
        $this->assertSame(resolver::LAYER_SITE, $cleared->get_source());
    }

    /**
     * Learning intent has no Dixeo level.
     */
    public function test_system_context_is_rejected(): void {
        $this->setAdminUser();

        $this->expectException(\coding_exception::class);
        (new config())->set('local_dixeo', 'learning_intent', \context_system::instance(), 'Site intent');
    }

    /**
     * Course edit requires moodle/course:update.
     */
    public function test_user_without_course_update_cannot_set_the_course(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        (new config())->set(
            'local_dixeo',
            'learning_intent',
            \context_course::instance($course->id),
            'Course intent'
        );
    }

    /**
     * A category context is neither the Dixeo layer nor a course override.
     */
    public function test_other_contexts_are_rejected(): void {
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();

        $this->expectException(\coding_exception::class);
        (new config())->get('local_dixeo', 'learning_intent', \context_coursecat::instance($category->id));
    }

    /**
     * @param \context_course $context Course context.
     * @return array<string, int|string>
     */
    private function course_conditions(\context_course $context): array {
        return [
            'contextid' => $context->id,
            'component' => 'local_dixeo',
            'name' => 'learning_intent',
        ];
    }
}
