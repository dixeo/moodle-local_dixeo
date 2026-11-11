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
 * Tests for the daily cleanup of stored Dixeo settings.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\task;

use local_dixeo\settings\catalogue;
use local_dixeo\settings\catalogue_fixture_trait;
use local_dixeo\settings\config;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/dixeo/tests/settings/catalogue_fixture_trait.php');

/**
 * Rows the catalogue no longer reads are removed; valid rows and unrelated config are kept.
 *
 * @covers \local_dixeo\task\cleanup_settings
 */
final class cleanup_settings_test extends \advanced_testcase {
    use catalogue_fixture_trait;

    public function test_rows_of_a_deleted_course_and_module_are_removed(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $coursecontextid = \context_course::instance($course->id)->id;
        $modulecontextid = \context_module::instance($page->cmid)->id;
        $this->insert_row($coursecontextid, 'local_dixeo', 'filesync_pill', 'footer');
        $this->insert_row($modulecontextid, 'local_dixeo', 'filesync_pill', 'footer');
        $DB->delete_records('context', ['id' => $coursecontextid]);
        $DB->delete_records('context', ['id' => $modulecontextid]);

        $this->run_task();

        $this->assertFalse($DB->record_exists(config::TABLE, ['contextid' => $coursecontextid]));
        $this->assertFalse($DB->record_exists(config::TABLE, ['contextid' => $modulecontextid]));
    }

    public function test_rows_with_an_unknown_key_or_an_undeclared_level_are_removed(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $coursecontextid = \context_course::instance($course->id)->id;
        $modulecontextid = \context_module::instance($page->cmid)->id;
        $DB->delete_records(config::TABLE, ['contextid' => $coursecontextid]);
        $DB->delete_records(config::TABLE, ['contextid' => $modulecontextid]);
        $this->insert_row($coursecontextid, 'local_dixeo', 'no_such_setting', '1');
        $this->insert_row($coursecontextid, 'local_dixeo_nosuchplugin', 'filesync_pill', '1');
        $this->insert_row($modulecontextid, 'local_dixeo', 'filesync_pill', 'footer');
        $this->insert_row($coursecontextid, 'local_dixeo', 'filesync_pill', 'footer');
        $activitykey = $this->activity_key();
        if ($activitykey !== null) {
            $this->insert_row($modulecontextid, $activitykey[0], $activitykey[1], $activitykey[2]);
        }

        $this->run_task();

        $this->assertFalse($DB->record_exists(config::TABLE, ['name' => 'no_such_setting']));
        $this->assertFalse($DB->record_exists(config::TABLE, ['component' => 'local_dixeo_nosuchplugin']));
        $this->assertFalse($DB->record_exists(config::TABLE, [
            'contextid' => $modulecontextid,
            'component' => 'local_dixeo',
            'name' => 'filesync_pill',
        ]));
        $this->assertTrue($DB->record_exists(config::TABLE, [
            'contextid' => $coursecontextid,
            'component' => 'local_dixeo',
            'name' => 'filesync_pill',
        ]));
        if ($activitykey !== null) {
            $this->assertTrue($DB->record_exists(config::TABLE, [
                'contextid' => $modulecontextid,
                'component' => $activitykey[0],
                'name' => $activitykey[1],
            ]));
        }
    }

    public function test_a_dixeo_key_dropped_between_runs_is_unset(): void {
        $this->resetAfterTest();
        set_config('api_url', 'https://example.com', 'local_dixeo');
        set_config('api_key', 'secret', 'local_dixeo');
        set_config('test_course_switch', '0', 'local_dixeo');

        $this->prime_catalogue_fixtures();
        $this->run_task();
        $this->assertSame('0', get_config('local_dixeo', 'test_course_switch'));

        catalogue::reset();
        $this->run_task();

        $this->assertFalse(get_config('local_dixeo', 'test_course_switch'));
        $this->assertSame('https://example.com', get_config('local_dixeo', 'api_url'));
        $this->assertSame('secret', get_config('local_dixeo', 'api_key'));
        $this->assertNotFalse(get_config('local_dixeo', 'filesync_pill'));
    }

    public function test_the_first_run_only_records_the_keys(): void {
        $this->resetAfterTest();
        unset_config(cleanup_settings::CONFIG_CATALOGUE_KEYS, 'local_dixeo');
        set_config('test_course_switch', '0', 'local_dixeo');

        $this->run_task();

        $this->assertSame('0', get_config('local_dixeo', 'test_course_switch'));
        $keys = json_decode(get_config('local_dixeo', cleanup_settings::CONFIG_CATALOGUE_KEYS), true);
        $this->assertContains('enabled', $keys['local_dixeo']);
    }

    public function test_the_query_count_does_not_grow_with_the_orphan_rows(): void {
        global $DB;

        $this->resetAfterTest();
        catalogue::load();
        $this->run_task();
        $this->run_task();

        $this->insert_row(990001, 'local_dixeo', 'filesync_pill', 'footer');
        $one = $this->count_queries();
        $this->assertFalse($DB->record_exists(config::TABLE, ['contextid' => 990001]));

        for ($i = 0; $i < 50; $i++) {
            $this->insert_row(990100 + $i, 'local_dixeo', 'filesync_pill', 'footer');
        }
        $fifty = $this->count_queries();

        $this->assertFalse($DB->record_exists_select(config::TABLE, 'contextid >= 990100'));
        $this->assertSame($one, $fifty);
    }

    /**
     * Run the task and return the database reads and writes it made.
     *
     * @return array{0: int, 1: int}
     */
    private function count_queries(): array {
        global $DB;

        $reads = $DB->perf_get_reads();
        $writes = $DB->perf_get_writes();
        $this->run_task();
        return [$DB->perf_get_reads() - $reads, $DB->perf_get_writes() - $writes];
    }

    /**
     * An installed activity-level setting with an allowed value, or null when none exists.
     *
     * @return array{0: string, 1: string, 2: string}|null Component, key and value.
     */
    private function activity_key(): ?array {
        foreach (catalogue::load() as $definition) {
            if (in_array('activity', $definition->get_levels(), true)) {
                return [$definition->get_component(), $definition->get_key(), $definition->get_default()];
            }
        }
        return null;
    }

    /**
     * Store a raw setting row.
     *
     * @param int $contextid Context id.
     * @param string $component Component.
     * @param string $name Setting key.
     * @param string $value Value.
     */
    private function insert_row(int $contextid, string $component, string $name, string $value): void {
        global $DB;

        $DB->insert_record(config::TABLE, (object) [
            'contextid' => $contextid,
            'component' => $component,
            'name' => $name,
            'value' => $value,
        ]);
    }

    /**
     * Run the cleanup task.
     */
    private function run_task(): void {
        (new cleanup_settings())->execute();
    }
}
