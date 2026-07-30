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

use local_dixeo\dto\credit_transaction;
use local_dixeo\util\credit_component_mapper;

/**
 * Repository for synced credit usage rows.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credit_usage_repository {
    /** @var string Database table name. */
    public const TABLE = 'local_dixeo_credit_usage';

    /**
     * Upsert a transaction row enriched with job binding data.
     *
     * @param credit_transaction $transaction API transaction.
     * @param \stdClass|null $job Local job binding.
     * @return \stdClass Stored record.
     */
    public function upsert_from_transaction(credit_transaction $transaction, ?\stdClass $job = null): \stdClass {
        global $DB;

        $now = time();
        $component = credit_component_mapper::resolve(
            $job->component ?? null,
            $transaction->jobtype,
            $job->operation ?? null
        );

        $moduletype = $transaction->moduletype;
        if (empty($moduletype) && $job !== null && !empty($job->moduletype)) {
            $moduletype = (string) $job->moduletype;
        }

        $record = (object) [
            'transactionid' => $transaction->id,
            'jobid' => $transaction->jobid,
            'type' => $transaction->type,
            'amount' => $transaction->amount,
            'credits' => $transaction->get_usage_credits(),
            'jobtype' => $transaction->jobtype,
            'moduletype' => $moduletype,
            'component' => $component,
            'operation' => $job->operation ?? null,
            'description' => $transaction->description,
            'userid' => (int) ($job->userid ?? 0),
            'courseid' => (int) ($job->courseid ?? 0),
            'contextid' => $job !== null ? (int) ($job->contextid ?? 0) : 0,
            'cmid' => $job !== null ? (int) ($job->cmid ?? 0) : 0,
            'timecreated' => $transaction->createdat > 0 ? $transaction->createdat : $now,
            'timesynced' => $now,
        ];

        $existing = $DB->get_record(self::TABLE, ['transactionid' => $transaction->id]);
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record(self::TABLE, $record);
            return $DB->get_record(self::TABLE, ['id' => $existing->id], '*', MUST_EXIST);
        }

        $record->id = $DB->insert_record(self::TABLE, $record);
        return $DB->get_record(self::TABLE, ['id' => $record->id], '*', MUST_EXIST);
    }

    /**
     * Refresh stored context fields from an updated job binding.
     *
     * @param string $jobid Remote job UUID.
     * @param \stdClass $job Updated job binding row.
     * @return int Number of usage rows updated.
     */
    public function enrich_from_job(string $jobid, \stdClass $job): int {
        global $DB;

        $jobid = trim($jobid);
        if ($jobid === '') {
            return 0;
        }

        $records = $DB->get_records(self::TABLE, ['jobid' => $jobid]);
        if ($records === []) {
            return 0;
        }

        $now = time();
        $updated = 0;
        foreach ($records as $record) {
            $patch = (object) ['id' => $record->id, 'timesynced' => $now];
            $changed = false;

            if (!empty($job->moduletype) && ($record->moduletype ?? '') !== $job->moduletype) {
                $patch->moduletype = $job->moduletype;
                $changed = true;
            }
            if (!empty($job->contextid) && (int) ($record->contextid ?? 0) !== (int) $job->contextid) {
                $patch->contextid = (int) $job->contextid;
                $changed = true;
            }
            if (!empty($job->cmid) && (int) ($record->cmid ?? 0) !== (int) $job->cmid) {
                $patch->cmid = (int) $job->cmid;
                $changed = true;
            }

            if ($changed) {
                $DB->update_record(self::TABLE, $patch);
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Delete usage rows for a user.
     *
     * @param int $userid User ID.
     */
    public function delete_for_user(int $userid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['userid' => $userid]);
    }

    /**
     * Return user IDs that have at least one credit usage row.
     *
     * @param int[] $userids Candidate user IDs.
     * @return int[]
     */
    public function filter_user_ids_with_usage(array $userids): array {
        global $DB;

        $userids = array_values(array_unique(array_filter(array_map('intval', $userids))));
        if ($userids === []) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $found = $DB->get_fieldset_sql(
            "SELECT DISTINCT userid
               FROM {" . self::TABLE . "}
              WHERE userid {$insql}
                AND userid > 0",
            $params
        );

        return array_map('intval', $found);
    }

    /**
     * Return course IDs that have at least one credit usage row.
     *
     * @param int[] $courseids Candidate course IDs.
     * @return int[]
     */
    public function filter_course_ids_with_usage(array $courseids): array {
        global $DB;

        $courseids = array_values(array_unique(array_filter(array_map('intval', $courseids))));
        if ($courseids === []) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
        $found = $DB->get_fieldset_sql(
            "SELECT DISTINCT courseid
               FROM {" . self::TABLE . "}
              WHERE courseid {$insql}
                AND courseid > 0",
            $params
        );

        return array_map('intval', $found);
    }

    /**
     * Search users with credit usage in a period.
     *
     * @param string $query Search query.
     * @param int $timestart Period start timestamp.
     * @param int $timeend Period end timestamp.
     * @param int $limit Maximum results.
     * @return array<int, array{id: int, label: string}>
     */
    public function search_users_with_usage(string $query, int $timestart, int $timeend, int $limit = 20): array {
        global $DB;

        $params = [
            'type' => \local_dixeo\dto\credit_transaction::TYPE_DEDUCTION,
            'timestart' => $timestart,
            'timeend' => $timeend,
        ];
        $conditions = [
            'cu.type = :type',
            'cu.timecreated >= :timestart',
            'cu.timecreated <= :timeend',
            'cu.userid > 0',
            'u.deleted = 0',
        ];

        $query = trim($query);
        if ($query !== '') {
            if (ctype_digit($query)) {
                $conditions[] = 'u.id = :exactid';
                $params['exactid'] = (int) $query;
            } else {
                $like = '%' . $DB->sql_like_escape($query) . '%';
                $conditions[] = '(' . $DB->sql_like('u.firstname', ':firstname', false) .
                    ' OR ' . $DB->sql_like('u.lastname', ':lastname', false) .
                    ' OR ' . $DB->sql_like('u.email', ':email', false) . ')';
                $params['firstname'] = $like;
                $params['lastname'] = $like;
                $params['email'] = $like;
            }
        }

        $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                       u.middlename, u.alternatename
                  FROM {" . self::TABLE . "} cu
                  JOIN {user} u ON u.id = cu.userid
                 WHERE " . implode(' AND ', $conditions) . "
              ORDER BY u.lastname ASC, u.firstname ASC";

        $records = $DB->get_records_sql($sql, $params, 0, $limit);
        $results = [];
        foreach ($records as $record) {
            $results[(int) $record->id] = [
                'id' => (int) $record->id,
                'label' => fullname($record),
            ];
        }

        return array_values($results);
    }

    /**
     * Search courses with credit usage in a period.
     *
     * @param string $query Search query.
     * @param int $timestart Period start timestamp.
     * @param int $timeend Period end timestamp.
     * @param int $limit Maximum results.
     * @return array<int, array{id: int, label: string}>
     */
    public function search_courses_with_usage(string $query, int $timestart, int $timeend, int $limit = 20): array {
        global $DB;

        $params = [
            'type' => \local_dixeo\dto\credit_transaction::TYPE_DEDUCTION,
            'timestart' => $timestart,
            'timeend' => $timeend,
        ];
        $conditions = [
            'cu.type = :type',
            'cu.timecreated >= :timestart',
            'cu.timecreated <= :timeend',
            'cu.courseid > 0',
        ];

        $query = trim($query);
        if ($query !== '') {
            if (ctype_digit($query)) {
                $conditions[] = 'c.id = :exactid';
                $params['exactid'] = (int) $query;
            } else {
                $like = '%' . $DB->sql_like_escape($query) . '%';
                $conditions[] = $DB->sql_like('c.fullname', ':fullname', false);
                $params['fullname'] = $like;
            }
        }

        $sql = "SELECT DISTINCT c.id, c.fullname
                  FROM {" . self::TABLE . "} cu
                  JOIN {course} c ON c.id = cu.courseid
                 WHERE " . implode(' AND ', $conditions) . "
              ORDER BY c.fullname ASC";

        $records = $DB->get_records_sql($sql, $params, 0, $limit);
        $results = [];
        foreach ($records as $record) {
            $results[(int) $record->id] = [
                'id' => (int) $record->id,
                'label' => format_string($record->fullname),
            ];
        }

        return array_values($results);
    }

    /**
     * Load labels for applied user and course filters.
     *
     * @param int[] $userids User IDs.
     * @param int[] $courseids Course IDs.
     * @return array{users: array<int, string>, courses: array<int, string>}
     */
    public function get_entity_labels(array $userids, array $courseids): array {
        global $DB;

        $users = [];
        $userids = array_values(array_unique(array_filter(array_map('intval', $userids))));
        if ($userids !== []) {
            [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
            $records = $DB->get_records_sql(
                "SELECT id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename
                   FROM {user}
                  WHERE id {$insql}
                    AND deleted = 0",
                $params
            );
            foreach ($records as $record) {
                $users[(int) $record->id] = fullname($record);
            }
        }

        $courses = [];
        $courseids = array_values(array_unique(array_filter(array_map('intval', $courseids))));
        if ($courseids !== []) {
            [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
            $records = $DB->get_records_sql(
                "SELECT id, fullname
                   FROM {course}
                  WHERE id {$insql}",
                $params
            );
            foreach ($records as $record) {
                $courses[(int) $record->id] = format_string($record->fullname);
            }
        }

        return [
            'users' => $users,
            'courses' => $courses,
        ];
    }
}
