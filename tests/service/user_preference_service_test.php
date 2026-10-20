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
 * Unit tests for Dixeo user preferences.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\external\set_preferences;
use local_dixeo\service\generation_language_helper;
use local_dixeo\service\user_preference_service;

/**
 * The settings modal reads and stores named user preferences.
 *
 * @covers \local_dixeo\service\user_preference_service
 * @covers \local_dixeo\external\set_preferences
 */
final class user_preference_service_test extends \advanced_testcase {
    /**
     * Generation language is one catalogue preference and starts as the interface language.
     */
    public function test_generation_language_is_listed(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['lang' => 'en']);
        $userid = (int) $user->id;

        $listed = user_preference_service::get_for_user($userid);
        $this->assertCount(1, $listed);
        $this->assertSame(user_preference_service::GENERATION_LANGUAGE, $listed[0]['name']);
        $this->assertSame('en', $listed[0]['value']);
        $this->assertNotEmpty($listed[0]['options']);
    }

    /**
     * A known preference is stored. An unknown name is rejected.
     */
    public function test_store_writes_known_preferences_only(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['lang' => 'en']);
        $userid = (int) $user->id;

        user_preference_service::store_for_user($userid, [
            ['name' => user_preference_service::GENERATION_LANGUAGE, 'value' => 'en'],
        ]);
        $this->assertSame('en', get_user_preferences(generation_language_helper::PREFERENCE, null, $userid));

        $this->expectException(\invalid_parameter_exception::class);
        user_preference_service::store_for_user($userid, [
            ['name' => 'not_a_preference', 'value' => 'en'],
        ]);
    }

    /**
     * Saving from a course context stores the preference for the current user.
     */
    public function test_set_preferences_uses_the_course_context(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        set_preferences::execute(0, (int) $course->id, [
            ['name' => user_preference_service::GENERATION_LANGUAGE, 'value' => 'en'],
        ]);
        $this->assertSame('en', get_user_preferences(generation_language_helper::PREFERENCE));

        set_preferences::execute($context->id, 0, [
            ['name' => user_preference_service::GENERATION_LANGUAGE, 'value' => 'en'],
        ]);
        $this->assertSame('en', get_user_preferences(generation_language_helper::PREFERENCE));

        set_preferences::execute($context->id, 0, []);
        $this->assertSame('en', get_user_preferences(generation_language_helper::PREFERENCE));
    }

    /**
     * Only a system or course context can clear the applied cache.
     */
    public function test_set_preferences_rejects_a_module_context(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $this->expectException(\invalid_parameter_exception::class);
        set_preferences::execute(\context_module::instance($page->cmid)->id, 0, [
            ['name' => user_preference_service::GENERATION_LANGUAGE, 'value' => 'en'],
        ]);
    }
}
