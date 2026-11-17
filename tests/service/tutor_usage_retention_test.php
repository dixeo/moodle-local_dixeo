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

namespace local_dixeo\service;

use local_dixeo\api\client;
use local_dixeo\dto\tutor_message;
use local_dixeo\external\service_factory;
use local_dixeo\repository\tutor_usage_rollup_repository;
use local_dixeo\task\aggregate_tutor_usage;
use local_dixeo\task\erase_tutor_conversations;
use local_dixeo\util\site_calendar;

/**
 * Tests for the deletion of tutor usage analytics.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_dixeo\service\tutor_usage_retention
 * @covers \local_dixeo\observer\tutor_conversation_observer
 * @covers \local_dixeo\task\aggregate_tutor_usage
 */
final class tutor_usage_retention_test extends \advanced_testcase {
    /**
     * Usage fixtures record events without an analytics choice.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        service_factory::reset();
        tutor_usage_recorder::allow_without_consent_for_fixtures(true);
    }

    /**
     * Restore the analytics consent check and the service factory for later tests.
     */
    public function tearDown(): void {
        tutor_usage_recorder::allow_without_consent_for_fixtures(false);
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * Deleting a course removes its rows from every tutor usage table and leaves other courses alone.
     */
    public function test_delete_courses_removes_every_table_of_that_course_only(): void {
        $coursea = $this->getDataGenerator()->create_course();
        $courseb = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->record_usage([(int) $user->id], [(int) $coursea->id, (int) $courseb->id]);

        foreach (tutor_usage_retention::TABLES as $table) {
            $this->assertSame(1, $this->count_rows($table, (int) $coursea->id), $table);
        }

        tutor_usage_retention::delete_courses([(int) $coursea->id]);

        foreach (tutor_usage_retention::TABLES as $table) {
            $this->assertSame(0, $this->count_rows($table, (int) $coursea->id), $table);
            $this->assertSame(1, $this->count_rows($table, (int) $courseb->id), $table);
        }
    }

    /**
     * User deletions remove only the given users' rows in the given courses.
     */
    public function test_delete_user_and_users_remove_only_their_rows(): void {
        global $DB;

        $coursea = $this->getDataGenerator()->create_course();
        $courseb = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $this->record_usage(
            [(int) $user1->id, (int) $user2->id, (int) $user3->id],
            [(int) $coursea->id, (int) $courseb->id]
        );

        tutor_usage_retention::delete_user((int) $user1->id, [(int) $coursea->id]);
        foreach (tutor_usage_retention::TABLES as $table) {
            $this->assertFalse($DB->record_exists($table, ['userid' => $user1->id, 'courseid' => $coursea->id]), $table);
            $this->assertTrue($DB->record_exists($table, ['userid' => $user1->id, 'courseid' => $courseb->id]), $table);
        }

        tutor_usage_retention::delete_users([(int) $user2->id, (int) $user3->id], (int) $courseb->id);
        foreach (tutor_usage_retention::TABLES as $table) {
            $this->assertFalse($DB->record_exists($table, ['userid' => $user2->id, 'courseid' => $courseb->id]), $table);
            $this->assertFalse($DB->record_exists($table, ['userid' => $user3->id, 'courseid' => $courseb->id]), $table);
            $this->assertTrue($DB->record_exists($table, ['userid' => $user2->id, 'courseid' => $coursea->id]), $table);
            $this->assertTrue($DB->record_exists($table, ['userid' => $user1->id, 'courseid' => $courseb->id]), $table);
        }

        tutor_usage_retention::delete_user((int) $user1->id);
        foreach (tutor_usage_retention::TABLES as $table) {
            $this->assertFalse($DB->record_exists($table, ['userid' => $user1->id]), $table);
            $this->assertTrue($DB->record_exists($table, ['userid' => $user2->id]), $table);
        }
    }

    /**
     * Deleting many users runs the same number of queries as deleting one.
     */
    public function test_delete_users_query_count_is_independent_of_user_count(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $users = [];
        for ($i = 0; $i < 20; $i++) {
            $users[] = (int) $this->getDataGenerator()->create_user()->id;
        }

        $before = $DB->perf_get_queries();
        tutor_usage_retention::delete_users([$users[0]], (int) $course->id);
        $single = $DB->perf_get_queries() - $before;

        $before = $DB->perf_get_queries();
        tutor_usage_retention::delete_users($users, (int) $course->id);
        $many = $DB->perf_get_queries() - $before;

        $this->assertSame($single, $many);
    }

    /**
     * Rows of a course that no longer exists are deleted, and the back-fill does not rebuild them.
     */
    public function test_delete_orphaned_courses_removes_rows_of_missing_courses(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {course}') + 1000;
        $daystart = site_calendar::day_start(site_calendar::day_start(time()) - 1);

        foreach ([$missing, (int) $course->id, 0] as $courseid) {
            $DB->insert_record(tutor_usage_recorder::TABLE_EVENT, (object) [
                'userid' => $user->id,
                'courseid' => $courseid,
                'cmid' => 0,
                'mode' => tutor_message::MODE_NORMAL,
                'eventtype' => tutor_usage_recorder::EVENT_MESSAGE,
                'timecreated' => $daystart + HOURSECS,
            ]);
            $DB->insert_record(tutor_usage_aggregator::TABLE_DAILY, (object) [
                'daystart' => $daystart,
                'courseid' => $courseid,
                'userid' => $user->id,
                'messages' => 1,
                'lastactive' => $daystart + HOURSECS,
            ]);
            $DB->insert_record(tutor_usage_aggregator::TABLE_SESSION, (object) [
                'userid' => $user->id,
                'courseid' => $courseid,
                'timestart' => $daystart + HOURSECS,
                'timeend' => $daystart + HOURSECS + 60,
                'duration' => 60,
                'messagecount' => 1,
            ]);
        }

        $this->assertSame(1, tutor_usage_retention::delete_orphaned_courses());

        foreach (tutor_usage_retention::TABLES as $table) {
            $this->assertSame(0, $this->count_rows($table, $missing), $table);
        }
        $kepttables = [
            tutor_usage_recorder::TABLE_EVENT,
            tutor_usage_aggregator::TABLE_DAILY,
            tutor_usage_aggregator::TABLE_SESSION,
        ];
        foreach ($kepttables as $table) {
            $this->assertSame(1, $this->count_rows($table, (int) $course->id), $table);
            $this->assertSame(1, $this->count_rows($table, 0), $table);
        }
        $this->assertSame(0, tutor_usage_retention::delete_orphaned_courses());

        $aggregator = new tutor_usage_aggregator(new tutor_usage_rollup_repository());
        $aggregator->ensure_rollup_timezone();
        $aggregator->backfill();
        $this->assertSame(0, $this->count_rows(tutor_usage_aggregator::TABLE_DAILY, $missing));
    }

    /**
     * Deleting a course removes its tutor usage rows and still queues the remote conversation erasure.
     */
    public function test_course_deletion_removes_rows_and_queues_erasure(): void {
        $client = $this->createMock(client::class);
        $client->method('is_configured')->willReturn(true);
        service_factory::set_test_client($client);

        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->record_usage([(int) $user->id], [(int) $course->id, (int) $other->id]);

        delete_course($course, false);

        foreach (tutor_usage_retention::TABLES as $table) {
            $this->assertSame(0, $this->count_rows($table, (int) $course->id), $table);
            $this->assertSame(1, $this->count_rows($table, (int) $other->id), $table);
        }
        $scopes = [];
        foreach (\core\task\manager::get_adhoc_tasks(erase_tutor_conversations::class) as $task) {
            $scopes[] = (int) $task->get_custom_data()->courseid;
        }
        $this->assertSame([(int) $course->id], $scopes);
    }

    /**
     * The scheduled task sweeps deleted courses once per site day.
     */
    public function test_task_sweeps_once_per_site_day(): void {
        $clock = $this->mock_clock_with_frozen(site_calendar::day_start(time()) + 2 * HOURSECS);
        $user = $this->getDataGenerator()->create_user();

        $missing = $this->insert_orphan_event((int) $user->id);
        $this->run_task();
        $this->assertSame(0, $this->count_rows(tutor_usage_recorder::TABLE_EVENT, $missing));

        $missing = $this->insert_orphan_event((int) $user->id);
        $this->run_task();
        $this->assertSame(1, $this->count_rows(tutor_usage_recorder::TABLE_EVENT, $missing));

        $clock->bump(DAYSECS);
        $this->run_task();
        $this->assertSame(0, $this->count_rows(tutor_usage_recorder::TABLE_EVENT, $missing));
    }

    /**
     * Record one message yesterday per user and course, build that day and leave open sessions today.
     *
     * Each user then has one row per course in every tutor usage table.
     *
     * @param int[] $userids User ids.
     * @param int[] $courseids Course ids.
     */
    private function record_usage(array $userids, array $courseids): void {
        $yesterday = site_calendar::day_start(site_calendar::day_start(time()) - 1);
        $recorder = new tutor_usage_recorder();
        $aggregator = new tutor_usage_aggregator();
        foreach ($userids as $userid) {
            foreach ($courseids as $courseid) {
                $recorder->record_message($userid, $courseid, tutor_message::MODE_NORMAL, 0, $yesterday + HOURSECS);
            }
        }
        $aggregator->aggregate_day($yesterday, site_calendar::day_start(time()));
        (new tutor_usage_rollup_repository())->build_day($yesterday);
        foreach ($userids as $userid) {
            foreach ($courseids as $courseid) {
                $aggregator->touch_open_session($userid, $courseid, time());
            }
        }
    }

    /**
     * Insert a usage event for a course id that does not exist.
     *
     * @param int $userid User id.
     * @return int The missing course id.
     */
    private function insert_orphan_event(int $userid): int {
        global $DB;

        $missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {course}') + 1000
            + $DB->count_records(tutor_usage_recorder::TABLE_EVENT);
        $DB->insert_record(tutor_usage_recorder::TABLE_EVENT, (object) [
            'userid' => $userid,
            'courseid' => $missing,
            'cmid' => 0,
            'mode' => tutor_message::MODE_NORMAL,
            'eventtype' => tutor_usage_recorder::EVENT_MESSAGE,
            'timecreated' => time(),
        ]);
        return $missing;
    }

    /**
     * Run the aggregate task, discarding its trace output.
     */
    private function run_task(): void {
        ob_start();
        try {
            (new aggregate_tutor_usage())->execute();
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Number of rows of a course in a table.
     *
     * @param string $table Table name.
     * @param int $courseid Course id.
     * @return int
     */
    private function count_rows(string $table, int $courseid): int {
        global $DB;

        return $DB->count_records($table, ['courseid' => $courseid]);
    }
}
