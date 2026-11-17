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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_dixeo;

use local_dixeo\dto\tutor_message;
use local_dixeo\repository\tutor_usage_rollup_repository;
use local_dixeo\service\tutor_usage_aggregator;
use local_dixeo\service\tutor_usage_recorder;
use local_dixeo\task\aggregate_tutor_usage;
use local_dixeo\util\site_calendar;

/**
 * Tests for the site calendar, the daily tutor usage totals and their back-fill.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_dixeo\util\site_calendar
 * @covers \local_dixeo\repository\tutor_usage_rollup_repository
 * @covers \local_dixeo\service\tutor_usage_aggregator
 * @covers \local_dixeo\task\aggregate_tutor_usage
 */
final class tutor_usage_rollup_test extends \advanced_testcase {
    /**
     * Usage fixtures record events without an analytics choice.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        tutor_usage_recorder::allow_without_consent_for_fixtures(true);
    }

    /**
     * Restore the analytics consent check for later tests.
     */
    public function tearDown(): void {
        tutor_usage_recorder::allow_without_consent_for_fixtures(false);
        parent::tearDown();
    }

    /**
     * Start of the site day $days days before today.
     *
     * @param int $days Number of days back.
     * @return int
     */
    private function days_ago(int $days): int {
        $day = site_calendar::day_start(time());
        for ($i = 0; $i < $days; $i++) {
            $day = site_calendar::day_start($day - 1);
        }
        return $day;
    }

    /**
     * Insert a raw usage event.
     *
     * @param int $userid User id.
     * @param int $courseid Course id.
     * @param string $eventtype Event type.
     * @param string $mode Tutor mode.
     * @param int $timecreated Event time.
     */
    private function insert_event(int $userid, int $courseid, string $eventtype, string $mode, int $timecreated): void {
        global $DB;

        $DB->insert_record(tutor_usage_recorder::TABLE_EVENT, (object) [
            'userid' => $userid,
            'courseid' => $courseid,
            'cmid' => 0,
            'mode' => $mode,
            'eventtype' => $eventtype,
            'timecreated' => $timecreated,
        ]);
    }

    /**
     * Daily rows of a day keyed by "courseid-userid", without ids.
     *
     * @param int $daystart Site day start.
     * @return array<string, array<string, int>>
     */
    private function daily_rows(int $daystart): array {
        global $DB;

        $rows = [];
        foreach ($DB->get_records(tutor_usage_rollup_repository::TABLE_DAILY, ['daystart' => $daystart]) as $record) {
            $values = (array) $record;
            unset($values['id']);
            $rows[$record->courseid . '-' . $record->userid] = array_map('intval', $values);
        }
        ksort($rows);
        return $rows;
    }

    /**
     * Slot boundaries stay ascending across both daylight saving changes.
     */
    public function test_slot_boundaries_across_dst_changes(): void {
        $this->setTimezone('Europe/London');

        foreach ([[2026, 3, 29], [2026, 10, 25], [2026, 6, 10]] as [$year, $month, $day]) {
            $daystart = site_calendar::make($year, $month, $day);
            $boundaries = site_calendar::slot_boundaries($daystart);

            $this->assertCount(9, $boundaries);
            $this->assertSame($daystart, $boundaries[0]);
            $this->assertSame(site_calendar::next_day($daystart), $boundaries[8]);
            for ($k = 1; $k < 9; $k++) {
                $this->assertGreaterThan($boundaries[$k - 1], $boundaries[$k]);
            }
            $this->assertSame(site_calendar::make($year, $month, $day, 21), $boundaries[7]);
        }

        // The spring day is 23 hours long and the autumn day 25 hours long.
        $spring = site_calendar::make(2026, 3, 29);
        $autumn = site_calendar::make(2026, 10, 25);
        $this->assertSame(23 * HOURSECS, site_calendar::next_day($spring) - $spring);
        $this->assertSame(25 * HOURSECS, site_calendar::next_day($autumn) - $autumn);
        $this->assertSame(6, site_calendar::weekday($spring));
    }

    /**
     * A day build writes one row per course and user with the fixture totals, idempotently.
     */
    public function test_build_day_writes_totals_idempotently(): void {
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $daystart = $this->days_ago(2);
        $u1 = (int) $user1->id;
        $u2 = (int) $user2->id;
        $c1 = (int) $course1->id;
        $c2 = (int) $course2->id;
        $msg = tutor_usage_recorder::EVENT_MESSAGE;

        $this->insert_event($u1, $c1, $msg, tutor_message::MODE_NORMAL, $daystart + HOURSECS);
        $this->insert_event($u1, $c1, $msg, tutor_message::MODE_GUIDE, $daystart + 4 * HOURSECS);
        $this->insert_event($u1, $c1, $msg, tutor_message::MODE_QUIZ, $daystart + 4 * HOURSECS + 60);
        $this->insert_event(
            $u1,
            $c1,
            tutor_usage_recorder::EVENT_QUIZ_CREATED,
            tutor_message::MODE_QUIZ,
            $daystart + 5 * HOURSECS
        );
        $this->insert_event($u1, $c2, $msg, tutor_message::MODE_TEACH, $daystart + 22 * HOURSECS);
        $this->insert_event(
            $u2,
            $c1,
            tutor_usage_recorder::EVENT_LESSON_CREATED,
            tutor_message::MODE_TEACH,
            $daystart + 13 * HOURSECS
        );
        // Events of the next day are not counted.
        $this->insert_event($u1, $c1, $msg, tutor_message::MODE_NORMAL, site_calendar::next_day($daystart) + 10);

        $repository = new tutor_usage_rollup_repository();
        $this->assertSame(3, $repository->build_day($daystart));
        $rows = $this->daily_rows($daystart);

        $this->assertCount(3, $rows);
        $row = $rows["{$c1}-{$u1}"];
        $this->assertSame(3, $row['messages']);
        $this->assertSame(1, $row['msgnormal']);
        $this->assertSame(1, $row['msgguide']);
        $this->assertSame(1, $row['msgquiz']);
        $this->assertSame(0, $row['msgteach']);
        $this->assertSame(1, $row['quizcreated']);
        $this->assertSame(0, $row['lessoncreated']);
        $this->assertSame($daystart + 5 * HOURSECS, $row['lastactive']);
        $this->assertSame([1, 2, 0, 0, 0, 0, 0, 0], array_map(fn($k) => $row['slot' . $k], range(0, 7)));

        $row = $rows["{$c2}-{$u1}"];
        $this->assertSame(1, $row['msgteach']);
        $this->assertSame(1, $row['slot7']);

        $row = $rows["{$c1}-{$u2}"];
        $this->assertSame(0, $row['messages']);
        $this->assertSame(1, $row['lessoncreated']);
        $this->assertSame(0, array_sum(array_map(fn($k) => $row['slot' . $k], range(0, 7))));

        $this->assertTrue($repository->is_built($daystart));
        $this->assertSame(3, $repository->build_day($daystart));
        $this->assertSame($rows, $this->daily_rows($daystart));
    }

    /**
     * The viewer's own timezone does not change the site day or slot of an event.
     */
    public function test_viewer_timezone_does_not_change_day_or_slot(): void {
        $this->setTimezone('Europe/London');
        $viewer = $this->getDataGenerator()->create_user(['timezone' => 'Pacific/Auckland']);
        $this->setUser($viewer);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $daystart = $this->days_ago(3);
        $date = usergetdate($daystart, 'Europe/London');
        $late = site_calendar::make((int) $date['year'], (int) $date['mon'], (int) $date['mday'], 23, 30);
        $this->insert_event(
            (int) $user->id,
            (int) $course->id,
            tutor_usage_recorder::EVENT_MESSAGE,
            tutor_message::MODE_NORMAL,
            $late
        );

        $this->assertSame($daystart, site_calendar::day_start($late));
        (new tutor_usage_rollup_repository())->build_day($daystart);
        $rows = $this->daily_rows($daystart);
        $this->assertCount(1, $rows);
        $this->assertSame(1, reset($rows)['slot7']);
        $this->assertSame([], $this->daily_rows(site_calendar::next_day($daystart)));
    }

    /**
     * A backdated event marks its built day dirty; back-fill rebuilds it.
     */
    public function test_backdated_event_marks_day_dirty_and_backfill_rebuilds(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $daystart = $this->days_ago(4);
        $repository = new tutor_usage_rollup_repository();
        $aggregator = new tutor_usage_aggregator($repository);
        $aggregator->ensure_rollup_timezone();

        $recorder = new tutor_usage_recorder();
        $recorder->record_message((int) $user->id, (int) $course->id, tutor_message::MODE_NORMAL, 0, $daystart + HOURSECS);
        $repository->build_day($daystart);
        $this->assertSame([$daystart], $repository->covered_days($daystart, $daystart));

        $recorder->record_message((int) $user->id, (int) $course->id, tutor_message::MODE_GUIDE, 0, $daystart + 2 * HOURSECS);
        $this->assertSame(1, (int) $DB->get_field(tutor_usage_rollup_repository::TABLE_COVER, 'dirty', ['daystart' => $daystart]));
        $this->assertSame([], $repository->covered_days($daystart, $daystart));
        $this->assertSame([$daystart], $repository->dirty_days(10));

        $result = $aggregator->backfill();
        $this->assertSame(1, $result['dirty']);
        $this->assertSame([$daystart], $repository->covered_days($daystart, $daystart));
        $rebuilt = $this->daily_rows($daystart);
        $this->assertSame(2, reset($rebuilt)['messages']);

        $repository->build_day($daystart);
        $this->assertSame($rebuilt, $this->daily_rows($daystart));
    }

    /**
     * Back-fill builds at most 31 days per run, completes in later runs and leaves sessions alone.
     */
    public function test_backfill_is_bounded_and_completes(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        for ($i = 1; $i <= 100; $i++) {
            $this->insert_event(
                (int) $user->id,
                (int) $course->id,
                tutor_usage_recorder::EVENT_MESSAGE,
                tutor_message::MODE_NORMAL,
                $this->days_ago($i) + HOURSECS
            );
        }
        $DB->insert_record(tutor_usage_aggregator::TABLE_OPEN, (object) [
            'userid' => (int) $user->id,
            'courseid' => (int) $course->id,
            'timestart' => $this->days_ago(1) + HOURSECS,
            'lastmessage' => $this->days_ago(1) + HOURSECS,
            'messagecount' => 1,
        ]);
        $sessionsbefore = $DB->get_records(tutor_usage_aggregator::TABLE_SESSION);
        $openbefore = $DB->get_records(tutor_usage_aggregator::TABLE_OPEN);

        $aggregator = new tutor_usage_aggregator();
        $this->assertTrue($aggregator->ensure_rollup_timezone());

        $first = $aggregator->backfill();
        $this->assertSame(tutor_usage_aggregator::BACKFILL_MAX_DAYS, $first['built']);
        $this->assertFalse($first['done']);
        $this->assertSame(31, $DB->count_records(tutor_usage_rollup_repository::TABLE_COVER));

        $aggregator->backfill();
        $aggregator->backfill();
        $last = $aggregator->backfill();
        $this->assertTrue($last['done']);
        $this->assertSame(7, $last['built']);
        $this->assertSame(100, $DB->count_records(tutor_usage_rollup_repository::TABLE_COVER));
        $this->assertSame(100, $DB->count_records(tutor_usage_rollup_repository::TABLE_DAILY));
        $this->assertSame('-1', get_config('local_dixeo', tutor_usage_aggregator::CONFIG_BACKFILL_NEXT));

        $this->assertEquals($sessionsbefore, $DB->get_records(tutor_usage_aggregator::TABLE_SESSION));
        $this->assertEquals($openbefore, $DB->get_records(tutor_usage_aggregator::TABLE_OPEN));

        $again = $aggregator->backfill();
        $this->assertSame(['dirty' => 0, 'built' => 0, 'done' => true], $again);
    }

    /**
     * A change of site timezone resets the daily totals and the built days.
     */
    public function test_timezone_change_resets_rollup(): void {
        global $DB;

        $this->setTimezone('Europe/London');
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $daystart = $this->days_ago(2);
        $this->insert_event(
            (int) $user->id,
            (int) $course->id,
            tutor_usage_recorder::EVENT_MESSAGE,
            tutor_message::MODE_NORMAL,
            $daystart + HOURSECS
        );

        $repository = new tutor_usage_rollup_repository();
        $aggregator = new tutor_usage_aggregator($repository);
        $this->assertTrue($aggregator->ensure_rollup_timezone());
        $repository->build_day($daystart);
        $this->assertFalse($aggregator->ensure_rollup_timezone());
        $this->assertSame(1, $DB->count_records(tutor_usage_rollup_repository::TABLE_DAILY));

        $this->setTimezone('America/New_York');
        $this->assertSame([], $repository->covered_days(0, PHP_INT_MAX));
        $this->assertTrue($aggregator->ensure_rollup_timezone());
        $this->assertSame(0, $DB->count_records(tutor_usage_rollup_repository::TABLE_DAILY));
        $this->assertSame(0, $DB->count_records(tutor_usage_rollup_repository::TABLE_COVER));
        $this->assertSame('America/New_York', get_config('local_dixeo', tutor_usage_rollup_repository::CONFIG_TIMEZONE));
    }

    /**
     * The task builds days while Dixeo is switched off.
     */
    public function test_task_builds_days_while_dixeo_is_off(): void {
        global $DB;

        set_config('enabled', '0', 'local_dixeo');
        $this->assertFalse(external\service_factory::get_settings_config()->enabled_for(\context_system::instance()));

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->insert_event(
            (int) $user->id,
            (int) $course->id,
            tutor_usage_recorder::EVENT_MESSAGE,
            tutor_message::MODE_NORMAL,
            $this->days_ago(3) + HOURSECS
        );

        $this->expectOutputRegex('/Tutor usage back-fill/');
        (new aggregate_tutor_usage())->execute();

        $this->assertTrue((new tutor_usage_rollup_repository())->is_built($this->days_ago(3)));
        $this->assertSame(1, $DB->count_records(tutor_usage_rollup_repository::TABLE_DAILY));
    }

    /**
     * Rows of a user deleted with delete_user() are still built.
     */
    public function test_build_day_keeps_rows_of_deleted_user(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $daystart = $this->days_ago(2);
        $this->insert_event(
            (int) $user->id,
            (int) $course->id,
            tutor_usage_recorder::EVENT_MESSAGE,
            tutor_message::MODE_NORMAL,
            $daystart + HOURSECS
        );

        delete_user($user);

        $this->assertSame(1, (new tutor_usage_rollup_repository())->build_day($daystart));
        $this->assertArrayHasKey($course->id . '-' . $user->id, $this->daily_rows($daystart));
    }

    /**
     * The rollup lock is released when the callback throws.
     */
    public function test_with_lock_releases_lock_on_exception(): void {
        try {
            tutor_usage_rollup_repository::with_lock(static function (): void {
                throw new \RuntimeException('Build failed');
            });
            $this->fail('The exception must propagate');
        } catch (\RuntimeException $e) {
            $this->assertSame('Build failed', $e->getMessage());
        }

        $result = tutor_usage_rollup_repository::with_lock(static fn() => 'locked', 0);
        $this->assertSame('locked', $result);
        $this->assertDebuggingNotCalled();
    }

    /**
     * A caller that cannot take the lock still runs its callback and reports it.
     */
    public function test_with_lock_runs_without_lock_after_timeout(): void {
        $ran = false;
        tutor_usage_rollup_repository::with_lock(static function () use (&$ran): void {
            tutor_usage_rollup_repository::with_lock(static function () use (&$ran): void {
                $ran = true;
            }, 1);
        });

        $this->assertTrue($ran);
        $this->assertDebuggingCalled('Could not take the tutor usage rollup lock; continuing without it.', DEBUG_DEVELOPER);

        $underlock = tutor_usage_rollup_repository::with_lock(static fn() => true, 1);
        $this->assertTrue($underlock);
        $this->assertDebuggingNotCalled();
    }
}
