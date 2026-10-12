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
 * Storage and capability tests for catalogue settings.
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
        $this->assertSame(resolver::LAYER_DIXEO, $resolved->get_source());
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
        $this->assertSame(resolver::LAYER_DIXEO, $cleared->get_source());
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
     * With nothing stored, Dixeo is on for the site and for a course.
     */
    public function test_unset_enabled_is_on(): void {
        $course = $this->getDataGenerator()->create_course();
        $config = new config();

        $this->assertTrue($config->enabled_for(\context_system::instance()));
        $this->assertTrue($config->enabled_for(\context_course::instance($course->id)));
        $this->assertFalse(get_config('local_dixeo', 'enabled'));
    }

    /**
     * Dixeo off stays off even when the course stores On.
     */
    public function test_dixeo_off_wins_over_a_course_on_override(): void {
        $this->setAdminUser();
        $config = new config();
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);

        $config->set('local_dixeo', 'enabled', \context_system::instance(), '0');
        $config->set('local_dixeo', 'enabled', $coursecontext, '1');

        $this->assertSame('1', $config->get('local_dixeo', 'enabled', $coursecontext)->get_value());
        $this->assertFalse($config->enabled_for($coursecontext));
        $this->assertFalse($config->enabled_for(\context_system::instance()));
    }

    /**
     * Course off applies to that course and its modules, and leaves other courses on.
     */
    public function test_course_off_applies_to_that_course_only(): void {
        $this->setAdminUser();
        $config = new config();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $coursecontext = \context_course::instance($course->id);

        $config->set('local_dixeo', 'enabled', $coursecontext, '0');

        $this->assertFalse($config->enabled_for($coursecontext));
        $this->assertFalse($config->enabled_for(\context_module::instance($page->cmid)));
        $this->assertTrue($config->enabled_for(\context_course::instance($other->id)));
        $this->assertTrue($config->enabled_for(\context_system::instance()));
    }

    /**
     * Saving On at the Dixeo layer removes the plugin-config row.
     */
    public function test_setting_dixeo_on_deletes_the_plugin_config_row(): void {
        $this->setAdminUser();
        $config = new config();
        $system = \context_system::instance();

        $config->set('local_dixeo', 'enabled', $system, '0');
        $this->assertSame('0', get_config('local_dixeo', 'enabled'));

        $config->set('local_dixeo', 'enabled', $system, '1');

        $this->assertFalse(get_config('local_dixeo', 'enabled'));
        $this->assertTrue($config->enabled_for($system));
    }

    /**
     * Editing the Dixeo layer requires moodle/site:config.
     */
    public function test_user_without_site_config_cannot_set_the_dixeo_layer(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        (new config())->set('local_dixeo', 'enabled', \context_system::instance(), '0');
    }

    /**
     * Editing the course layer of the switch requires moodle/course:update.
     */
    public function test_user_without_course_update_cannot_set_enabled(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        (new config())->set('local_dixeo', 'enabled', \context_course::instance($course->id), '0');
    }

    /**
     * require_enabled throws when the Dixeo layer is off and passes when it is on.
     */
    public function test_require_enabled_throws_when_dixeo_is_off(): void {
        $this->setAdminUser();
        $config = new config();
        $system = \context_system::instance();

        $config->require_enabled($system);

        $config->set('local_dixeo', 'enabled', $system, '0');
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error:dixeo_disabled', 'local_dixeo'));
        $config->require_enabled($system);
    }

    /**
     * Reading the switch does not require an edit capability.
     */
    public function test_enabled_for_does_not_require_an_edit_capability(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $this->assertTrue((new config())->enabled_for(\context_course::instance($course->id)));
    }

    /**
     * The Dixeo layer lists settings declared there, and requires moodle/site:config.
     */
    public function test_system_layers_list_dixeo_settings(): void {
        $this->setAdminUser();
        $rows = (new config())->get_layers(\context_system::instance());
        $names = array_column($rows, 'name');

        $this->assertContains('enabled', $names);
        $this->assertContains('filesync_enabled_default', $names);
        $this->assertContains('filesync_pill', $names);
        $this->assertNotContains('learning_intent', $names);
        $this->assertNotContains('filesync_enabled', $names);
        $this->assertSame([], $this->layer_named($rows, 'filesync_enabled_default')['options']);
        $this->assertSame(
            ['hidden', 'coursetitle', 'footer'],
            $this->layer_named($rows, 'filesync_pill')['options']
        );

        $enabled = $this->layer_named($rows, 'enabled');
        $this->assertFalse($enabled['coremissing']);
        $this->assertSame('1', $enabled['value']);
        $this->assertSame(resolver::LAYER_DIXEO, $enabled['source']);
        $this->assertSame(
            [resolver::LAYER_DIXEO],
            array_column($enabled['layers'], 'level')
        );
        $pill = $this->layer_named($rows, 'filesync_pill');
        $this->assertSame(
            [resolver::LAYER_DIXEO],
            array_column($pill['layers'], 'level')
        );
        $this->assertSame(resolver::LAYER_DIXEO, $pill['source']);
        $dixeo = $this->layer_level($enabled, resolver::LAYER_DIXEO);
        $this->assertNull($dixeo['stored']);
        $this->assertFalse($dixeo['customised']);
        $this->assertSame('1', $dixeo['value']);
    }

    /**
     * Opening the Dixeo layer requires moodle/site:config.
     */
    public function test_user_without_site_config_cannot_read_layers(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        (new config())->get_layers(\context_system::instance());
    }

    /**
     * A course lists both settings for a teacher, and rejects a student.
     */
    public function test_course_layers_require_course_update(): void {
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $names = array_column((new config())->get_layers($context), 'name');
        $this->assertContains('enabled', $names);
        $this->assertContains('learning_intent', $names);
        $this->assertContains('filesync_pill', $names);
        $this->assertContains('filesync_enabled', $names);
        $this->assertNotContains('filesync_enabled_default', $names);

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        (new config())->get_layers($context);
    }

    /**
     * A course Off row is customised, and matching the parent removes that flag.
     */
    public function test_course_override_is_customised_until_it_matches_the_parent(): void {
        $this->setAdminUser();
        $config = new config();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $config->set('local_dixeo', 'enabled', $context, '0');
        $enabled = $this->layer_named($config->get_layers($context), 'enabled');
        $this->assertSame('0', $enabled['value']);
        $this->assertSame(resolver::LAYER_COURSE, $enabled['source']);
        $courseoff = $this->layer_level($enabled, resolver::LAYER_COURSE);
        $this->assertSame('0', $courseoff['stored']);
        $this->assertTrue($courseoff['customised']);
        $parent = $this->layer_level($enabled, resolver::LAYER_DIXEO);
        $this->assertSame('1', $parent['value']);
        $this->assertNull($parent['stored']);
        $this->assertFalse($parent['customised']);

        $config->set('local_dixeo', 'enabled', $context, '1');
        $cleared = $this->layer_named($config->get_layers($context), 'enabled');
        $coursecleared = $this->layer_level($cleared, resolver::LAYER_COURSE);
        $this->assertNull($coursecleared['stored']);
        $this->assertFalse($coursecleared['customised']);
        $this->assertSame('1', $cleared['value']);
        $this->assertSame(resolver::LAYER_DIXEO, $cleared['source']);
        $this->assertNotContains(resolver::LAYER_SITE, array_column($cleared['layers'], 'level'));
    }

    /**
     * File sync on a course stays stored when it is Off or equal to the new-course default.
     */
    public function test_course_file_sync_stays_stored_when_off_or_equal_to_the_default(): void {
        global $DB;

        $this->setAdminUser();
        $config = new config();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $conditions = $this->filesync_conditions($context);

        $config->set('local_dixeo', 'filesync_enabled', $context, '0');
        $this->assertTrue($DB->record_exists('local_dixeo_setting', $conditions));
        $stored = $config->get('local_dixeo', 'filesync_enabled', $context);
        $this->assertSame('0', $stored->get_value());
        $this->assertSame(resolver::LAYER_DIXEO, $stored->get_source());

        $config->set('local_dixeo', 'filesync_enabled', $context, '0');
        $this->assertTrue($DB->record_exists('local_dixeo_setting', $conditions));

        $DB->delete_records('local_dixeo_setting', $conditions);
        $missing = $config->get('local_dixeo', 'filesync_enabled', $context);
        $this->assertSame('0', $missing->get_value());
        $this->assertSame(resolver::LAYER_DIXEO, $missing->get_source());

        $config->set('local_dixeo', 'filesync_enabled_default', \context_system::instance(), '1');
        $unchanged = $config->get('local_dixeo', 'filesync_enabled', $context);
        $this->assertSame('0', $unchanged->get_value());
        $this->assertSame(resolver::LAYER_COURSE, $unchanged->get_source());
        $this->assertFalse($DB->record_exists('local_dixeo_setting', $conditions));

        $row = $this->layer_named($config->get_layers($context), 'filesync_enabled');
        $this->assertSame('0', $row['value']);
        $this->assertSame(resolver::LAYER_COURSE, $row['source']);
        $this->assertNotContains(resolver::LAYER_SITE, array_column($row['layers'], 'level'));
        $parent = $this->layer_level($row, resolver::LAYER_DIXEO);
        $this->assertSame('1', $parent['value']);
        $this->assertNull($parent['stored']);
        $this->assertFalse($parent['customised']);
        $courseoff = $this->layer_level($row, resolver::LAYER_COURSE);
        $this->assertSame('0', $courseoff['value']);
        $this->assertNull($courseoff['stored']);
        $this->assertFalse($courseoff['customised']);

        $config->set('local_dixeo', 'filesync_enabled', $context, '1');
        $this->assertTrue($DB->record_exists('local_dixeo_setting', $conditions));
        $matched = $config->get('local_dixeo', 'filesync_enabled', $context);
        $this->assertSame('1', $matched->get_value());
        $this->assertSame(resolver::LAYER_DIXEO, $matched->get_source());
        $kept = $this->layer_named($config->get_layers($context), 'filesync_enabled');
        $this->assertSame('1', $this->layer_level($kept, resolver::LAYER_COURSE)['stored']);
        $this->assertFalse($this->layer_level($kept, resolver::LAYER_COURSE)['customised']);
    }

    /**
     * A select stores a course override and rejects a value that is not an option.
     */
    public function test_select_stores_an_option_and_rejects_any_other_value(): void {
        $this->setAdminUser();
        $config = new config();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $shipped = $config->get('local_dixeo', 'filesync_pill', $context);
        $this->assertSame('coursetitle', $shipped->get_value());
        $this->assertSame(resolver::LAYER_DIXEO, $shipped->get_source());

        $config->set('local_dixeo', 'filesync_pill', $context, 'footer');
        $override = $config->get('local_dixeo', 'filesync_pill', $context);
        $this->assertSame('footer', $override->get_value());
        $this->assertSame(resolver::LAYER_COURSE, $override->get_source());

        $config->set('local_dixeo', 'filesync_pill', $context, 'coursetitle');
        $cleared = $config->get('local_dixeo', 'filesync_pill', $context);
        $this->assertSame('coursetitle', $cleared->get_value());
        $this->assertSame(resolver::LAYER_DIXEO, $cleared->get_source());

        $this->expectException(\coding_exception::class);
        $config->set('local_dixeo', 'filesync_pill', $context, 'sidebar');
    }

    /**
     * An empty learning intent has no course row and no Dixeo layer.
     */
    public function test_empty_learning_intent_is_not_customised(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $rows = (new config())->get_layers(\context_course::instance($course->id));
        $intent = $this->layer_named($rows, 'learning_intent');

        $this->assertSame('', $intent['value']);
        $this->assertSame(resolver::LAYER_DIXEO, $intent['source']);
        $this->assertSame(
            [resolver::LAYER_COURSE],
            array_column($intent['layers'], 'level')
        );
        $course = $this->layer_level($intent, resolver::LAYER_COURSE);
        $this->assertNull($course['stored']);
        $this->assertFalse($course['customised']);
        $this->assertSame('', $course['value']);
    }

    /**
     * Layers are not available outside the system and course contexts.
     */
    public function test_layers_reject_other_contexts(): void {
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();

        $this->expectException(\coding_exception::class);
        (new config())->get_layers(\context_coursecat::instance($category->id));
    }

    /**
     * A stored change is logged once, without the text, and a repeat is not logged.
     */
    public function test_set_records_a_change_and_ignores_a_repeat(): void {
        $this->setAdminUser();
        $config = new config();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $sink = $this->redirectEvents();
        $config->set('local_dixeo', 'learning_intent', $context, 'Course intent');
        $events = $this->setting_events($sink);
        $this->assertCount(1, $events);
        $this->assertStringNotContainsString('Course intent', $events[0]->get_description());
        $this->assertSame('local_dixeo', $events[0]->other['component']);
        $this->assertSame('learning_intent', $events[0]->other['name']);
        $this->assertArrayNotHasKey('value', $events[0]->other);
        $this->assertEquals($context->id, $events[0]->contextid);

        $sink->clear();
        $config->set('local_dixeo', 'learning_intent', $context, 'Course intent');
        $this->assertCount(0, $this->setting_events($sink));

        $sink->clear();
        $config->set('local_dixeo', 'learning_intent', $context, '');
        $cleared = $this->setting_events($sink);
        $this->assertCount(1, $cleared);
        $this->assertStringNotContainsString('Course intent', $cleared[0]->get_description());

        $sink->clear();
        $config->set('local_dixeo', 'learning_intent', $context, '');
        $this->assertCount(0, $this->setting_events($sink));
    }

    /**
     * @param \phpunit_event_sink $sink Event sink.
     * @return \local_dixeo\event\setting_updated[]
     */
    private function setting_events(\phpunit_event_sink $sink): array {
        return array_values(array_filter(
            $sink->get_events(),
            static fn($event) => $event instanceof \local_dixeo\event\setting_updated
        ));
    }

    /**
     * @param array[] $rows Layer rows.
     * @param string $name Catalogue key.
     * @return array
     */
    private function layer_named(array $rows, string $name): array {
        foreach ($rows as $row) {
            if ($row['name'] === $name) {
                return $row;
            }
        }
        $this->fail("Setting {$name} was not returned.");
    }

    /**
     * @param array $row One catalogue row from get_layers.
     * @param string $level Site, Dixeo, or course.
     * @return array
     */
    private function layer_level(array $row, string $level): array {
        foreach ($row['layers'] as $layer) {
            if ($layer['level'] === $level) {
                return $layer;
            }
        }
        $this->fail("Layer {$level} was not returned.");
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

    /**
     * @param \context_course $context Course context.
     * @return array<string, int|string>
     */
    private function filesync_conditions(\context_course $context): array {
        return [
            'contextid' => $context->id,
            'component' => 'local_dixeo',
            'name' => 'filesync_enabled',
        ];
    }
}
