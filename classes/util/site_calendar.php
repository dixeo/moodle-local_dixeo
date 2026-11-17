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

namespace local_dixeo\util;

/**
 * Day, slot and label maths for tutor usage statistics, always in the site timezone.
 *
 * Every producer and reader of tutor usage figures uses this class, so a day or a
 * three-hour slot means the same thing for every viewer, whatever their own timezone.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class site_calendar {
    /** @var int Number of three-hour slots in a day. */
    public const SLOTS = 8;

    /** @var array{0: mixed, 1: string}|null Raw site timezone setting and its normalised name. */
    private static ?array $memo = null;

    /**
     * Site timezone name.
     *
     * @return string
     */
    public static function timezone(): string {
        global $CFG;

        $raw = $CFG->timezone ?? null;
        if (self::$memo === null || self::$memo[0] !== $raw) {
            self::$memo = [$raw, \core_date::get_server_timezone()];
        }
        return self::$memo[1];
    }

    /**
     * Start of the site day that contains a timestamp.
     *
     * @param int $timestamp Unix timestamp.
     * @return int
     */
    public static function day_start(int $timestamp): int {
        return (int) usergetmidnight($timestamp, self::timezone());
    }

    /**
     * Last second (23:59:59) of the site day that starts at $daystart.
     *
     * @param int $daystart Site day start.
     * @return int
     */
    public static function day_end(int $daystart): int {
        $date = usergetdate($daystart, self::timezone());
        return self::make((int) $date['year'], (int) $date['mon'], (int) $date['mday'], 23, 59, 59);
    }

    /**
     * Start of the site day after the one that starts at $daystart.
     *
     * @param int $daystart Site day start.
     * @return int
     */
    public static function next_day(int $daystart): int {
        return self::day_start(self::day_end($daystart) + 1);
    }

    /**
     * Site day starts from the day of $from to the day of $to.
     *
     * @param int $from First timestamp.
     * @param int $to Last timestamp.
     * @return int[] Ascending day starts.
     */
    public static function days(int $from, int $to): array {
        $days = [];
        $last = self::day_start($to);
        for ($day = self::day_start($from); $day <= $last; $day = self::next_day($day)) {
            $days[] = $day;
        }
        return $days;
    }

    /**
     * Boundaries of the eight three-hour slots of a site day.
     *
     * Slot k covers [b[k], b[k + 1]). Boundaries are wall-clock hours in the site timezone,
     * so a slot is shorter or longer than three hours on a daylight saving change.
     *
     * @param int $daystart Site day start.
     * @return int[] Nine ascending timestamps; b[0] is the day start and b[8] the next day start.
     */
    public static function slot_boundaries(int $daystart): array {
        $date = usergetdate($daystart, self::timezone());
        $year = (int) $date['year'];
        $month = (int) $date['mon'];
        $day = (int) $date['mday'];

        $boundaries = [$daystart];
        for ($k = 1; $k < self::SLOTS; $k++) {
            $boundaries[] = max($boundaries[$k - 1], self::make($year, $month, $day, 3 * $k));
        }
        $boundaries[] = self::day_end($daystart) + 1;
        return $boundaries;
    }

    /**
     * Weekday of a site day.
     *
     * @param int $daystart Site day start.
     * @return int 0 = Monday to 6 = Sunday.
     */
    public static function weekday(int $daystart): int {
        $date = usergetdate($daystart, self::timezone());
        return ((int) $date['wday'] + 6) % 7;
    }

    /**
     * Timestamp of a wall-clock time in the site timezone.
     *
     * @param int $year Year.
     * @param int $month Month.
     * @param int $day Day of month.
     * @param int $hour Hour.
     * @param int $minute Minute.
     * @param int $second Second.
     * @return int
     */
    public static function make(int $year, int $month, int $day, int $hour = 0, int $minute = 0, int $second = 0): int {
        return (int) make_timestamp($year, $month, $day, $hour, $minute, $second, self::timezone());
    }

    /**
     * Format a timestamp in the site timezone.
     *
     * @param int $timestamp Unix timestamp.
     * @param string $format strftime format (usually a langconfig string).
     * @return string
     */
    public static function format(int $timestamp, string $format): string {
        return userdate($timestamp, $format, self::timezone());
    }
}
