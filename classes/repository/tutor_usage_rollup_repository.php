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

namespace local_dixeo\repository;

use local_dixeo\dto\tutor_message;
use local_dixeo\service\tutor_usage_recorder;
use local_dixeo\util\site_calendar;

/**
 * Daily tutor usage totals per site day, course and user, and the list of built days.
 *
 * This class is the only writer of the daily and cover tables. The per-day SQL in
 * day_rows_sql() is shared by the builder and by the report for days that are not
 * built, so built and live figures are always computed the same way.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tutor_usage_rollup_repository {
    /** @var string Daily totals table. */
    public const TABLE_DAILY = 'local_dixeo_tutor_usage_daily';

    /** @var string Built days table. */
    public const TABLE_COVER = 'local_dixeo_tutor_usage_cover';

    /** @var string Plugin config key holding the timezone the built days were computed in. */
    public const CONFIG_TIMEZONE = 'tutor_usage_rollup_timezone';

    /** @var string Core lock resource that serialises day builds with tutor usage deletions. */
    public const LOCK_RESOURCE = 'tutor_usage_rollup';

    /** @var string[] Count columns of a daily row, in table order. */
    public const COUNT_COLUMNS = [
        'messages', 'msgnormal', 'msgguide', 'msgquiz', 'msgteach', 'quizcreated', 'lessoncreated',
    ];

    /** @var int Counter that keeps parameter names unique across day_rows_sql() calls. */
    private static int $paramcounter = 0;

    /** @var \core\lock\lock_factory|null Lock factory shared by every caller in this process. */
    private static ?\core\lock\lock_factory $lockfactory = null;

    /**
     * SELECT of per-(courseid, userid) totals for one site day.
     *
     * Events in [daystart, min(day end, until)] are counted. Parameter names carry a unique
     * suffix, so several results can be combined in one UNION ALL statement.
     *
     * @param int $daystart Site day start.
     * @param int|null $until Optional upper bound (inclusive) inside the day.
     * @return array{0: string, 1: array<string, int|string>} SQL and parameters.
     */
    public function day_rows_sql(int $daystart, ?int $until = null): array {
        $suffix = 'r' . (++self::$paramcounter);
        $params = [];
        $n = 0;
        $bind = function ($value) use (&$params, &$n, $suffix): string {
            $name = $suffix . 'p' . (++$n);
            $params[$name] = $value;
            return ':' . $name;
        };

        $msg = tutor_usage_recorder::EVENT_MESSAGE;
        $modecase = function (string $mode) use ($bind, $msg): string {
            if ($mode === tutor_message::MODE_NORMAL) {
                // Any stored mode other than the three named ones counts as normal, as normalize_mode() does.
                $condition = 'mode NOT IN (' . $bind(tutor_message::MODE_GUIDE) . ', '
                    . $bind(tutor_message::MODE_QUIZ) . ', ' . $bind(tutor_message::MODE_TEACH) . ')';
            } else {
                $condition = 'mode = ' . $bind($mode);
            }
            return 'SUM(CASE WHEN eventtype = ' . $bind($msg) . ' AND ' . $condition . ' THEN 1 ELSE 0 END)';
        };

        $columns = [
            '(' . $bind($daystart) . ' + 0) AS daystart',
            'courseid',
            'userid',
            'SUM(CASE WHEN eventtype = ' . $bind($msg) . ' THEN 1 ELSE 0 END) AS messages',
            $modecase(tutor_message::MODE_NORMAL) . ' AS msgnormal',
            $modecase(tutor_message::MODE_GUIDE) . ' AS msgguide',
            $modecase(tutor_message::MODE_QUIZ) . ' AS msgquiz',
            $modecase(tutor_message::MODE_TEACH) . ' AS msgteach',
            'SUM(CASE WHEN eventtype = ' . $bind(tutor_usage_recorder::EVENT_QUIZ_CREATED) . ' THEN 1 ELSE 0 END) AS quizcreated',
            'SUM(CASE WHEN eventtype = ' . $bind(tutor_usage_recorder::EVENT_LESSON_CREATED)
                . ' THEN 1 ELSE 0 END) AS lessoncreated',
            'MAX(timecreated) AS lastactive',
        ];

        $boundaries = site_calendar::slot_boundaries($daystart);
        for ($k = 0; $k < site_calendar::SLOTS; $k++) {
            $columns[] = 'SUM(CASE WHEN eventtype = ' . $bind($msg)
                . ' AND timecreated >= ' . $bind($boundaries[$k])
                . ' AND timecreated < ' . $bind($boundaries[$k + 1])
                . ' THEN 1 ELSE 0 END) AS slot' . $k;
        }

        $dayend = site_calendar::day_end($daystart);
        $to = $until === null ? $dayend : min($dayend, $until);

        $sql = 'SELECT ' . implode(', ', $columns) . '
                  FROM {' . tutor_usage_recorder::TABLE_EVENT . '}
                 WHERE timecreated >= ' . $bind($daystart) . ' AND timecreated <= ' . $bind($to) . '
              GROUP BY courseid, userid';

        return [$sql, $params];
    }

    /**
     * Rebuild the daily rows of one site day from the events and mark the day built.
     *
     * Callers that may race tutor usage deletions run this inside with_lock().
     *
     * @param int $daystart Site day start.
     * @return int Rows written.
     */
    public function build_day(int $daystart): int {
        global $DB;

        $daystart = site_calendar::day_start($daystart);
        $transaction = $DB->start_delegated_transaction();
        try {
            $DB->delete_records(self::TABLE_DAILY, ['daystart' => $daystart]);

            [$sql, $params] = $this->day_rows_sql($daystart);
            $rows = $DB->get_recordset_sql($sql, $params);
            $batch = [];
            $count = 0;
            foreach ($rows as $row) {
                $batch[] = $this->to_daily_record($daystart, $row);
                $count++;
                if (count($batch) >= 500) {
                    $DB->insert_records(self::TABLE_DAILY, $batch);
                    $batch = [];
                }
            }
            $rows->close();
            if ($batch !== []) {
                $DB->insert_records(self::TABLE_DAILY, $batch);
            }

            $now = \core\di::get(\core\clock::class)->time();
            $cover = $DB->get_record(self::TABLE_COVER, ['daystart' => $daystart], 'id');
            if ($cover) {
                $DB->update_record(self::TABLE_COVER, (object) [
                    'id' => (int) $cover->id,
                    'dirty' => 0,
                    'timebuilt' => $now,
                ]);
            } else {
                $DB->insert_record(self::TABLE_COVER, (object) [
                    'daystart' => $daystart,
                    'dirty' => 0,
                    'timebuilt' => $now,
                ]);
            }
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            throw $e;
        }

        return $count;
    }

    /**
     * Built, non-dirty day starts in a range.
     *
     * @param int $from First day start (inclusive).
     * @param int $to Last day start (inclusive).
     * @return int[] Ascending day starts; empty when the built days use another timezone.
     */
    public function covered_days(int $from, int $to): array {
        global $DB;

        if ((string) get_config('local_dixeo', self::CONFIG_TIMEZONE) !== site_calendar::timezone()) {
            return [];
        }

        $days = $DB->get_fieldset_select(
            self::TABLE_COVER,
            'daystart',
            'daystart >= :from AND daystart <= :to AND dirty = 0',
            ['from' => $from, 'to' => $to]
        );
        $days = array_map('intval', $days);
        sort($days);
        return $days;
    }

    /**
     * Mark the built day that contains a timestamp as needing a rebuild.
     *
     * Does nothing when that day is not built.
     *
     * @param int $timestamp Event time.
     */
    public function mark_dirty(int $timestamp): void {
        global $DB;

        $DB->set_field(self::TABLE_COVER, 'dirty', 1, ['daystart' => site_calendar::day_start($timestamp)]);
    }

    /**
     * Day starts that need a rebuild, newest first.
     *
     * @param int $limit Maximum number of days.
     * @return int[]
     */
    public function dirty_days(int $limit): array {
        global $DB;

        if ($limit < 1) {
            return [];
        }
        $records = $DB->get_records(self::TABLE_COVER, ['dirty' => 1], 'daystart DESC', 'id, daystart', 0, $limit);
        return array_values(array_map(static fn($record) => (int) $record->daystart, $records));
    }

    /**
     * Whether a day has a cover row (built, dirty or not).
     *
     * @param int $daystart Site day start.
     * @return bool
     */
    public function is_built(int $daystart): bool {
        global $DB;

        return $DB->record_exists(self::TABLE_COVER, ['daystart' => $daystart]);
    }

    /**
     * Delete every daily and cover row.
     */
    public function reset(): void {
        global $DB;

        $DB->delete_records(self::TABLE_DAILY);
        $DB->delete_records(self::TABLE_COVER);
    }

    /**
     * Run a callback while holding the rollup lock.
     *
     * The lock serialises day builds with tutor usage deletions. When the lock cannot be taken
     * within the timeout, the callback still runs, without the lock, and a developer debugging
     * message is emitted: deletions must never fail, and privacy discovery covers any daily row
     * a racing build leaves behind.
     *
     * @param callable $fn Callback.
     * @param int $timeout Seconds to wait for the lock.
     * @return mixed The callback result.
     */
    public static function with_lock(callable $fn, int $timeout = 30): mixed {
        if (self::$lockfactory === null) {
            self::$lockfactory = \core\lock\lock_config::get_lock_factory('local_dixeo');
        }
        $lock = self::$lockfactory->get_lock(self::LOCK_RESOURCE, $timeout);
        if (!$lock) {
            debugging('Could not take the tutor usage rollup lock; continuing without it.', DEBUG_DEVELOPER);
            return $fn();
        }
        try {
            return $fn();
        } finally {
            $lock->release();
        }
    }

    /**
     * Daily table record from a day_rows_sql() row.
     *
     * @param int $daystart Site day start.
     * @param \stdClass $row Result row.
     * @return \stdClass
     */
    private function to_daily_record(int $daystart, \stdClass $row): \stdClass {
        $record = (object) [
            'daystart' => $daystart,
            'courseid' => (int) $row->courseid,
            'userid' => (int) $row->userid,
        ];
        foreach (self::COUNT_COLUMNS as $column) {
            $record->{$column} = (int) $row->{$column};
        }
        $record->lastactive = (int) $row->lastactive;
        for ($k = 0; $k < site_calendar::SLOTS; $k++) {
            $record->{'slot' . $k} = (int) $row->{'slot' . $k};
        }
        return $record;
    }
}
