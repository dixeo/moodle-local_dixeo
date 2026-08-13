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

namespace local_dixeo\service;

/**
 * Aggregates tutor usage events into daily rollups and closed sessions.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tutor_usage_aggregator {
    /** @var string Daily rollup table. */
    public const TABLE_DAILY = 'local_dixeo_tutor_usage_daily';

    /** @var string Closed sessions table. */
    public const TABLE_SESSION = 'local_dixeo_tutor_usage_session';

    /** @var string Open sessions table. */
    public const TABLE_OPEN = 'local_dixeo_tutor_usage_open';

    /** @var string Plugin config key for last successfully aggregated daystart. */
    public const CONFIG_LAST_AGGREGATED_DAY = 'tutor_usage_last_aggregated_day';

    /** @var int Idle timeout before a session closes (seconds). */
    public const SESSION_TIMEOUT = 3600;

    /** @var int Minimum session duration (seconds). */
    public const SESSION_MIN_DURATION = 300;

    /** @var int Maximum days to catch up in one task run (and on first run). */
    public const MAX_CATCHUP_DAYS = 180;

    /**
     * Aggregate all pending calendar days from the last watermark through yesterday.
     *
     * Advances the watermark after each successful day so a failed run can resume.
     *
     * @param int|null $now Reference timestamp (defaults to now).
     * @return array{
     *   from: int,
     *   to: int,
     *   days: array<int, array{daystart: int, dailyrows: int, sessionsclosed: int}>
     * }
     */
    public function aggregate_pending_days(?int $now = null): array {
        $now = $now ?? time();
        $yesterday = $this->get_previous_day_start($now);
        $start = $this->resolve_catchup_start($yesterday);

        if ($start === 0 || $start > $yesterday) {
            // Nothing pending; keep watermark at yesterday so future runs stay current.
            if ($this->get_last_aggregated_day() < $yesterday) {
                $this->set_last_aggregated_day($yesterday);
            }
            return [
                'from' => $start,
                'to' => $yesterday,
                'days' => [],
            ];
        }

        $days = [];
        $processed = 0;
        for ($daystart = $start; $daystart <= $yesterday; $daystart = $this->get_next_day_start($daystart)) {
            if ($processed >= self::MAX_CATCHUP_DAYS) {
                break;
            }
            $result = $this->aggregate_day($daystart);
            $this->set_last_aggregated_day($daystart);
            $days[] = $result;
            $processed++;
        }

        return [
            'from' => $start,
            'to' => $days !== [] ? (int) end($days)['daystart'] : $yesterday,
            'days' => $days,
        ];
    }

    /**
     * Last successfully aggregated daystart watermark (0 when never set).
     *
     * @return int
     */
    public function get_last_aggregated_day(): int {
        return (int) get_config('local_dixeo', self::CONFIG_LAST_AGGREGATED_DAY);
    }

    /**
     * Persist the last successfully aggregated daystart watermark.
     *
     * @param int $daystart Day start timestamp.
     */
    public function set_last_aggregated_day(int $daystart): void {
        set_config(self::CONFIG_LAST_AGGREGATED_DAY, (string) $this->get_day_start($daystart), 'local_dixeo');
    }

    /**
     * Resolve the first daystart that still needs aggregation.
     *
     * @param int $yesterday Previous calendar daystart.
     * @return int Daystart to begin from, or 0 when nothing to do.
     */
    protected function resolve_catchup_start(int $yesterday): int {
        $last = $this->get_last_aggregated_day();
        if ($last > 0) {
            $next = $this->get_next_day_start($last);
            return $next <= $yesterday ? $next : 0;
        }

        $earliest = $this->get_earliest_event_daystart();
        if ($earliest === 0) {
            return 0;
        }

        $earliestallowed = $yesterday;
        for ($i = 1; $i < self::MAX_CATCHUP_DAYS; $i++) {
            $earliestallowed = $this->get_previous_day_start($earliestallowed);
        }

        return max($earliest, $earliestallowed);
    }

    /**
     * Earliest event daystart, or 0 when there are no events.
     *
     * @return int
     */
    protected function get_earliest_event_daystart(): int {
        global $DB;

        $mintime = $DB->get_field_sql(
            'SELECT MIN(timecreated) FROM {' . tutor_usage_recorder::TABLE_EVENT . '}'
        );
        if ($mintime === false || $mintime === null || (int) $mintime < 1) {
            return 0;
        }

        return $this->get_day_start((int) $mintime);
    }

    /**
     * Previous calendar daystart relative to a timestamp.
     *
     * @param int $timestamp Reference timestamp.
     * @return int
     */
    public function get_previous_day_start(int $timestamp): int {
        $todaystart = $this->get_day_start($timestamp);
        return $this->get_day_start($todaystart - 1);
    }

    /**
     * Next calendar daystart after a daystart.
     *
     * @param int $daystart Day start timestamp.
     * @return int
     */
    public function get_next_day_start(int $daystart): int {
        return $this->get_day_start($this->get_day_end($daystart) + 1);
    }

    /**
     * Idempotently aggregate a single calendar day and close timed-out sessions.
     *
     * @param int $daystart Site-timezone midnight for the day.
     * @param int|null $asof Close open sessions against this timestamp (defaults to day end + 1).
     * @return array{daystart: int, dailyrows: int, sessionsclosed: int}
     */
    public function aggregate_day(int $daystart, ?int $asof = null): array {
        global $DB;

        $daystart = $this->get_day_start($daystart);
        $dayend = $this->get_day_end($daystart);
        $asof = $asof ?? ($dayend + 1);

        $transaction = $DB->start_delegated_transaction();
        try {
            $DB->delete_records(self::TABLE_DAILY, ['daystart' => $daystart]);
            $dailyrows = $this->rebuild_daily_rows($daystart, $dayend);
            $sessionsclosed = $this->process_sessions_for_day($daystart, $dayend, $asof);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            throw $e;
        }

        return [
            'daystart' => $daystart,
            'dailyrows' => $dailyrows,
            'sessionsclosed' => $sessionsclosed,
        ];
    }

    /**
     * Rebuild daily rollup rows for a day from events.
     *
     * @param int $daystart Day start timestamp.
     * @param int $dayend Day end timestamp.
     * @return int Number of daily rows inserted.
     */
    protected function rebuild_daily_rows(int $daystart, int $dayend): int {
        global $DB;

        $sql = "SELECT courseid, userid, cmid, mode,
                       SUM(CASE WHEN eventtype = :msg THEN 1 ELSE 0 END) AS messages,
                       SUM(CASE WHEN eventtype = :quiz THEN 1 ELSE 0 END) AS quizcreated,
                       SUM(CASE WHEN eventtype = :lesson THEN 1 ELSE 0 END) AS lessoncreated,
                       MAX(timecreated) AS lastactive
                  FROM {" . tutor_usage_recorder::TABLE_EVENT . "}
                 WHERE timecreated >= :timestart AND timecreated <= :timeend
              GROUP BY courseid, userid, cmid, mode";

        $rows = $DB->get_recordset_sql($sql, [
            'msg' => tutor_usage_recorder::EVENT_MESSAGE,
            'quiz' => tutor_usage_recorder::EVENT_QUIZ_CREATED,
            'lesson' => tutor_usage_recorder::EVENT_LESSON_CREATED,
            'timestart' => $daystart,
            'timeend' => $dayend,
        ]);

        $count = 0;
        foreach ($rows as $row) {
            $DB->insert_record(self::TABLE_DAILY, (object) [
                'daystart' => $daystart,
                'courseid' => (int) $row->courseid,
                'userid' => (int) $row->userid,
                'cmid' => (int) $row->cmid,
                'mode' => (string) $row->mode,
                'messages' => (int) $row->messages,
                'quizcreated' => (int) $row->quizcreated,
                'lessoncreated' => (int) $row->lessoncreated,
                'lastactive' => (int) $row->lastactive,
            ]);
            $count++;
        }
        $rows->close();

        return $count;
    }

    /**
     * Process message events into open/closed sessions for a day (idempotent).
     *
     * @param int $daystart Day start.
     * @param int $dayend Day end.
     * @param int $asof Reference time for timeout checks.
     * @return int Number of sessions closed.
     */
    protected function process_sessions_for_day(int $daystart, int $dayend, int $asof): int {
        global $DB;

        // Undo prior run output for this day so re-aggregation is safe.
        $continuations = $DB->get_records_select(
            self::TABLE_SESSION,
            'timeend >= :daystart AND timeend <= :dayend AND timestart < :daystart2',
            ['daystart' => $daystart, 'dayend' => $dayend, 'daystart2' => $daystart]
        );
        foreach ($continuations as $session) {
            $this->upsert_open_session((object) [
                'userid' => (int) $session->userid,
                'courseid' => (int) $session->courseid,
                'timestart' => (int) $session->timestart,
                'lastmessage' => (int) $session->timestart,
                'messagecount' => 1,
            ]);
        }

        $DB->delete_records_select(
            self::TABLE_SESSION,
            'timeend >= :daystart AND timeend <= :dayend',
            ['daystart' => $daystart, 'dayend' => $dayend]
        );
        $DB->delete_records_select(
            self::TABLE_OPEN,
            'timestart >= :daystart AND timestart <= :dayend',
            ['daystart' => $daystart, 'dayend' => $dayend]
        );
        $this->rewind_open_sessions_before_day($daystart);

        $sql = "SELECT userid, courseid, timecreated
                  FROM {" . tutor_usage_recorder::TABLE_EVENT . "}
                 WHERE eventtype = :eventtype
                   AND timecreated >= :timestart
                   AND timecreated <= :timeend
              ORDER BY userid ASC, courseid ASC, timecreated ASC, id ASC";

        $events = $DB->get_recordset_sql($sql, [
            'eventtype' => tutor_usage_recorder::EVENT_MESSAGE,
            'timestart' => $daystart,
            'timeend' => $dayend,
        ]);

        $closed = 0;
        $currentuserid = 0;
        $currentcourseid = 0;
        $open = null;

        $flushpair = function () use (&$open, &$closed, $asof): void {
            if ($open === null) {
                return;
            }
            $closed += $this->persist_open_or_close($open, $asof);
            $open = null;
        };

        foreach ($events as $event) {
            $userid = (int) $event->userid;
            $courseid = (int) $event->courseid;
            $timecreated = (int) $event->timecreated;

            if ($userid !== $currentuserid || $courseid !== $currentcourseid) {
                $flushpair();
                $currentuserid = $userid;
                $currentcourseid = $courseid;
                $open = $this->load_open_session($userid, $courseid);
            }

            if ($open === null) {
                $open = (object) [
                    'userid' => $userid,
                    'courseid' => $courseid,
                    'timestart' => $timecreated,
                    'lastmessage' => $timecreated,
                    'messagecount' => 1,
                ];
                continue;
            }

            if (($timecreated - (int) $open->lastmessage) > self::SESSION_TIMEOUT) {
                $closed += $this->close_session_object($open);
                $open = (object) [
                    'userid' => $userid,
                    'courseid' => $courseid,
                    'timestart' => $timecreated,
                    'lastmessage' => $timecreated,
                    'messagecount' => 1,
                ];
                continue;
            }

            $open->lastmessage = $timecreated;
            $open->messagecount = (int) $open->messagecount + 1;
        }
        $events->close();
        $flushpair();

        // Close any remaining open sessions that timed out by $asof (including no events that day).
        $closed += $this->close_timed_out_open_sessions($asof);

        return $closed;
    }

    /**
     * Rewind open sessions that started before $daystart to their pre-day state.
     *
     * @param int $daystart Day start timestamp.
     */
    protected function rewind_open_sessions_before_day(int $daystart): void {
        global $DB;

        $opens = $DB->get_records_select(self::TABLE_OPEN, 'timestart < :daystart', ['daystart' => $daystart]);
        foreach ($opens as $open) {
            $stats = $DB->get_record_sql(
                "SELECT MAX(timecreated) AS lastmessage, COUNT(1) AS messagecount
                   FROM {" . tutor_usage_recorder::TABLE_EVENT . "}
                  WHERE eventtype = :eventtype
                    AND userid = :userid
                    AND courseid = :courseid
                    AND timecreated >= :timestart
                    AND timecreated < :daystart",
                [
                    'eventtype' => tutor_usage_recorder::EVENT_MESSAGE,
                    'userid' => (int) $open->userid,
                    'courseid' => (int) $open->courseid,
                    'timestart' => (int) $open->timestart,
                    'daystart' => $daystart,
                ]
            );

            $open->lastmessage = (int) ($stats->lastmessage ?? $open->timestart);
            $open->messagecount = (int) ($stats->messagecount ?? 0);
            if ($open->messagecount < 1) {
                $open->messagecount = 1;
                $open->lastmessage = (int) $open->timestart;
            }
            $DB->update_record(self::TABLE_OPEN, $open);
        }
    }

    /**
     * Update live open-session state after a message is recorded.
     *
     * Closes the previous open session when the idle timeout has elapsed.
     *
     * @param int $userid User id.
     * @param int $courseid Course id.
     * @param int $timecreated Message timestamp.
     */
    public function touch_open_session(int $userid, int $courseid, int $timecreated): void {
        if ($userid < 1 || $courseid < 1) {
            return;
        }

        $open = $this->load_open_session($userid, $courseid);
        if ($open === null) {
            $this->upsert_open_session((object) [
                'userid' => $userid,
                'courseid' => $courseid,
                'timestart' => $timecreated,
                'lastmessage' => $timecreated,
                'messagecount' => 1,
            ]);
            return;
        }

        if (($timecreated - (int) $open->lastmessage) > self::SESSION_TIMEOUT) {
            $this->close_session_object($open);
            $this->upsert_open_session((object) [
                'userid' => $userid,
                'courseid' => $courseid,
                'timestart' => $timecreated,
                'lastmessage' => $timecreated,
                'messagecount' => 1,
            ]);
            return;
        }

        $open->lastmessage = $timecreated;
        $open->messagecount = (int) $open->messagecount + 1;
        $this->upsert_open_session($open);
    }

    /**
     * Persist an in-memory open session or close it if timed out.
     *
     * @param \stdClass $open Open session state.
     * @param int $asof Reference timestamp.
     * @return int 1 when closed, 0 when left open.
     */
    protected function persist_open_or_close(\stdClass $open, int $asof): int {
        if (($asof - (int) $open->lastmessage) > self::SESSION_TIMEOUT) {
            return $this->close_session_object($open);
        }

        $this->upsert_open_session($open);
        return 0;
    }

    /**
     * Close all open sessions whose last message is older than the timeout.
     *
     * @param int $asof Reference timestamp.
     * @return int Number closed.
     */
    public function close_timed_out_open_sessions(int $asof): int {
        global $DB;

        $cutoff = $asof - self::SESSION_TIMEOUT;
        $records = $DB->get_records_select(
            self::TABLE_OPEN,
            'lastmessage < :cutoff',
            ['cutoff' => $cutoff]
        );

        $closed = 0;
        foreach ($records as $record) {
            $closed += $this->close_session_object($record);
        }
        return $closed;
    }

    /**
     * Close a session object and remove any matching open row.
     *
     * @param \stdClass $open Open session.
     * @return int Always 1.
     */
    public function close_session_object(\stdClass $open): int {
        global $DB;

        $timestart = (int) $open->timestart;
        $timeend = (int) $open->lastmessage;
        $duration = max($timeend - $timestart, self::SESSION_MIN_DURATION);

        $DB->insert_record(self::TABLE_SESSION, (object) [
            'userid' => (int) $open->userid,
            'courseid' => (int) $open->courseid,
            'timestart' => $timestart,
            'timeend' => $timeend,
            'duration' => $duration,
            'messagecount' => (int) $open->messagecount,
        ]);

        $DB->delete_records(self::TABLE_OPEN, [
            'userid' => (int) $open->userid,
            'courseid' => (int) $open->courseid,
        ]);

        return 1;
    }

    /**
     * Load an open session for a user/course pair.
     *
     * @param int $userid User id.
     * @param int $courseid Course id.
     * @return \stdClass|null
     */
    protected function load_open_session(int $userid, int $courseid): ?\stdClass {
        global $DB;

        $record = $DB->get_record(self::TABLE_OPEN, [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);
        return $record ?: null;
    }

    /**
     * Insert or update the open session row for a user/course.
     *
     * @param \stdClass $open Open session state.
     */
    protected function upsert_open_session(\stdClass $open): void {
        global $DB;

        $existing = $DB->get_record(self::TABLE_OPEN, [
            'userid' => (int) $open->userid,
            'courseid' => (int) $open->courseid,
        ]);

        $record = (object) [
            'userid' => (int) $open->userid,
            'courseid' => (int) $open->courseid,
            'timestart' => (int) $open->timestart,
            'lastmessage' => (int) $open->lastmessage,
            'messagecount' => (int) $open->messagecount,
        ];

        if ($existing) {
            $record->id = (int) $existing->id;
            $DB->update_record(self::TABLE_OPEN, $record);
            return;
        }

        $DB->insert_record(self::TABLE_OPEN, $record);
    }

    /**
     * Site-timezone midnight for the day containing $timestamp.
     *
     * @param int $timestamp Unix timestamp.
     * @return int
     */
    public function get_day_start(int $timestamp): int {
        return usergetmidnight($timestamp);
    }

    /**
     * Site-timezone end of day for the day starting at $daystart.
     *
     * @param int $daystart Day start timestamp.
     * @return int
     */
    public function get_day_end(int $daystart): int {
        $date = usergetdate($daystart);
        return make_timestamp($date['year'], $date['mon'], $date['mday'], 23, 59, 59);
    }

    /**
     * Compute session duration from first/last message timestamps.
     *
     * @param int $timestart First message time.
     * @param int $timeend Last message time.
     * @return int Duration in seconds.
     */
    public static function calculate_duration(int $timestart, int $timeend): int {
        return max($timeend - $timestart, self::SESSION_MIN_DURATION);
    }
}
