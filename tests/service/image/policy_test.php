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

namespace local_dixeo\service\image;

use local_dixeo\external\service_factory;

/**
 * Course overrides for image generation modes and the enabled switch.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\service\image\policy
 */
final class policy_test extends \advanced_testcase {
    /**
     * SetUp.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('image_generation_course_mode', policy::MODE_GENERATE_EDIT, 'local_dixeo');
        set_config('image_generation_section_mode', policy::MODE_GENERATE, 'local_dixeo');
        set_config('image_generation_content_mode', policy::MODE_GENERATE, 'local_dixeo');
    }

    /**
     * A course select replaces the Dixeo mode for that course only.
     */
    public function test_course_mode_override_is_used(): void {
        $this->setAdminUser();
        set_config('image_generation_enabled', 1, 'local_dixeo');
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        service_factory::get_settings_config()->set(
            'local_dixeo',
            'image_generation_course_mode',
            $context,
            policy::MODE_DISABLED,
            false
        );

        $this->assertFalse(policy::is_enabled(policy::ENTITY_COURSE, policy::ACTION_GENERATE, $context));
        $this->assertTrue(policy::is_enabled(
            policy::ENTITY_SECTION,
            policy::ACTION_GENERATE,
            $context
        ));
        $this->assertFalse(policy::is_enabled(policy::ENTITY_SECTION, policy::ACTION_EDIT, $context));
        $this->assertTrue(policy::is_enabled(
            policy::ENTITY_COURSE,
            policy::ACTION_EDIT,
            \context_course::instance($other->id)
        ));
        $this->assertTrue(policy::is_enabled(policy::ENTITY_COURSE, policy::ACTION_EDIT));
    }

    /**
     * A course can turn image generation off while the Dixeo layer is on.
     */
    public function test_course_can_disable_image_generation_when_dixeo_is_on(): void {
        $this->setAdminUser();
        set_config('image_generation_enabled', 1, 'local_dixeo');
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        service_factory::get_settings_config()->set(
            'local_dixeo',
            'image_generation_enabled',
            $context,
            '0',
            false
        );

        $this->assertFalse(policy::is_enabled(policy::ENTITY_COURSE, policy::ACTION_GENERATE, $context));
        $this->assertFalse(policy::is_enabled(policy::ENTITY_CONTENT, policy::ACTION_GENERATE, $context));
        $this->assertTrue(policy::is_enabled(
            policy::ENTITY_COURSE,
            policy::ACTION_GENERATE,
            \context_course::instance($other->id)
        ));
        $this->assertTrue(policy::is_globally_enabled());
    }

    /**
     * A course stored as On does not turn image generation on while the Dixeo layer is off.
     */
    public function test_course_cannot_enable_image_generation_when_dixeo_is_off(): void {
        $this->setAdminUser();
        set_config('image_generation_enabled', 0, 'local_dixeo');
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        service_factory::get_settings_config()->set(
            'local_dixeo',
            'image_generation_enabled',
            $context,
            '1',
            false
        );

        $this->assertSame(
            '1',
            service_factory::get_settings_config()->get('local_dixeo', 'image_generation_enabled', $context)->get_value()
        );
        $this->assertFalse(policy::is_enabled(policy::ENTITY_COURSE, policy::ACTION_GENERATE, $context));
        $this->assertFalse(policy::is_enabled(policy::ENTITY_COURSE, policy::ACTION_EDIT, $context));
        $this->assertFalse(policy::is_globally_enabled());
    }
}
