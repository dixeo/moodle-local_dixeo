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

use local_dixeo\repository\tutor_usage_rollup_repository;

/**
 * The only place that deletes personal tutor usage rows.
 *
 * Course deletion, consent withdrawal and privacy requests all delegate here. Every deletion
 * is set-based and runs under the rollup lock, so a day build cannot write daily rows from
 * events it read before the deletion. Tutor usage analytics have no time-based expiry.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tutor_usage_retention {
    /** @var string[] Tables holding personal tutor usage data, in deletion order. */
    public const TABLES = [
        tutor_usage_recorder::TABLE_EVENT,
        tutor_usage_aggregator::TABLE_DAILY,
        tutor_usage_aggregator::TABLE_SESSION,
        tutor_usage_aggregator::TABLE_OPEN,
    ];

    /** @var int Maximum course ids bound in one deletion statement. */
    private const CHUNK_SIZE = 500;

    /**
     * Delete every tutor usage row of the given courses.
     *
     * Course id 0 stands for the site-level rows. Ids are processed in chunks, one rollup lock per chunk.
     *
     * @param int[] $courseids Course ids.
     * @return void
     */
    public static function delete_courses(array $courseids): void {
        $courseids = self::clean_ids($courseids, 0);
        foreach (array_chunk($courseids, self::CHUNK_SIZE) as $chunk) {
            self::delete_where('courseid', $chunk, []);
        }
    }

    /**
     * Delete one user's tutor usage rows in the given courses, or in every course when $courseids is null.
     *
     * @param int $userid User id.
     * @param int[]|null $courseids Course ids (0 for site-level rows), or null for every course.
     * @return void
     */
    public static function delete_user(int $userid, ?array $courseids = null): void {
        global $DB;

        if ($userid < 1) {
            return;
        }
        if ($courseids === null) {
            self::delete_where('userid', [$userid], []);
            return;
        }
        $courseids = self::clean_ids($courseids, 0);
        foreach (array_chunk($courseids, self::CHUNK_SIZE) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'tuc');
            self::delete_select("userid = :tuuserid AND courseid {$insql}", $params + ['tuuserid' => $userid]);
        }
    }

    /**
     * Delete several users' tutor usage rows in one course, or in every course when $courseid is null.
     *
     * @param int[] $userids User ids.
     * @param int|null $courseid Course id (0 for site-level rows), or null for every course.
     * @return void
     */
    public static function delete_users(array $userids, ?int $courseid = null): void {
        $userids = self::clean_ids($userids, 1);
        $extra = $courseid === null ? [] : ['courseid' => $courseid];
        self::delete_where('userid', $userids, $extra);
    }

    /**
     * Delete the rows of courses that no longer exist.
     *
     * Site-level rows (course id 0) are never touched.
     *
     * @return int Number of course ids purged.
     */
    public static function delete_orphaned_courses(): int {
        global $DB;

        $courseids = [];
        foreach (self::TABLES as $table) {
            $courseids = array_merge($courseids, $DB->get_fieldset_sql(
                "SELECT DISTINCT t.courseid
                   FROM {" . $table . "} t
              LEFT JOIN {course} c ON c.id = t.courseid
                  WHERE t.courseid > 0 AND c.id IS NULL"
            ));
        }
        $courseids = self::clean_ids($courseids, 1);
        self::delete_courses($courseids);
        return count($courseids);
    }

    /**
     * Delete the rows whose column is in the given ids, with optional extra equality conditions.
     *
     * @param string $column Column matched against the ids.
     * @param int[] $ids Cleaned ids; nothing happens when empty.
     * @param array $extra Extra column => value equality conditions.
     * @return void
     */
    private static function delete_where(string $column, array $ids, array $extra): void {
        global $DB;

        if ($ids === []) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'tuid');
        $select = "{$column} {$insql}";
        foreach ($extra as $name => $value) {
            $select .= " AND {$name} = :tux{$name}";
            $params['tux' . $name] = $value;
        }
        self::delete_select($select, $params);
    }

    /**
     * Run one deletion per table under the rollup lock.
     *
     * @param string $select WHERE clause.
     * @param array $params Named parameters.
     * @return void
     */
    private static function delete_select(string $select, array $params): void {
        global $DB;

        tutor_usage_rollup_repository::with_lock(static function () use ($DB, $select, $params): void {
            foreach (self::TABLES as $table) {
                $DB->delete_records_select($table, $select, $params);
            }
        });
    }

    /**
     * Distinct integer ids at or above a minimum.
     *
     * @param array $ids Raw ids.
     * @param int $min Smallest accepted id.
     * @return int[]
     */
    private static function clean_ids(array $ids, int $min): array {
        $clean = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id >= $min) {
                $clean[$id] = $id;
            }
        }
        return array_values($clean);
    }
}
