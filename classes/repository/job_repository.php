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
 * Repository for local Dixeo job ownership records.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\repository;

/**
 * CRUD helpers for {@see local_dixeo_jobs}.
 */
class job_repository {
    /** @var string Database table name. */
    public const TABLE = 'local_dixeo_jobs';

    /**
     * Persist a newly created remote job binding.
     *
     * Idempotent for the same jobid (updates course/user metadata if re-inserted).
     *
     * @param string $jobid Remote job UUID.
     * @param int $courseid Course ID (0 when not yet known, e.g. pre-course structure).
     * @param int $userid User who initiated the job.
     * @param string $namespace API namespace.
     * @param string $operation Logical operation name (e.g. module_generate).
     * @return \stdClass The stored record.
     */
    public function register(
        string $jobid,
        int $courseid,
        int $userid,
        string $namespace,
        string $operation
    ): \stdClass {
        global $DB;

        $jobid = trim($jobid);
        if ($jobid === '') {
            throw new \invalid_parameter_exception('Job ID is required');
        }

        $existing = $DB->get_record(self::TABLE, ['jobid' => $jobid]);
        if ($existing) {
            $update = (object) [
                'id' => $existing->id,
                'courseid' => $courseid,
                'userid' => $userid,
                'namespace' => $namespace,
                'operation' => $operation,
            ];
            $DB->update_record(self::TABLE, $update);
            return $DB->get_record(self::TABLE, ['id' => $existing->id], '*', MUST_EXIST);
        }

        $record = (object) [
            'jobid' => $jobid,
            'courseid' => $courseid,
            'userid' => $userid,
            'namespace' => $namespace,
            'operation' => $operation,
            'timecreated' => time(),
        ];
        $record->id = $DB->insert_record(self::TABLE, $record);

        return $record;
    }

    /**
     * Fetch a job binding by remote job UUID.
     *
     * @param string $jobid Remote job UUID.
     * @return \stdClass|null
     */
    public function get_by_jobid(string $jobid): ?\stdClass {
        global $DB;

        $record = $DB->get_record(self::TABLE, ['jobid' => trim($jobid)]);
        return $record ?: null;
    }

    /**
     * Whether the job is registered to the given course.
     *
     * @param string $jobid Remote job UUID.
     * @param int $courseid Expected course ID.
     * @return bool
     */
    public function belongs_to_course(string $jobid, int $courseid): bool {
        $record = $this->get_by_jobid($jobid);
        if ($record === null) {
            return false;
        }

        return (int) $record->courseid === $courseid;
    }

    /**
     * Whether the job is registered to the given course and initiating user.
     *
     * @param string $jobid Remote job UUID.
     * @param int $courseid Expected course ID.
     * @param int $userid Expected initiating user ID.
     * @return bool
     */
    public function belongs_to_user_and_course(string $jobid, int $courseid, int $userid): bool {
        $record = $this->get_by_jobid($jobid);
        if ($record === null) {
            return false;
        }

        return (int) $record->courseid === $courseid
            && (int) $record->userid === $userid;
    }

    /**
     * Delete job rows for a user (optionally limited to courses).
     *
     * @param int $userid User ID.
     * @param int[] $courseids Optional course ID filter.
     */
    public function delete_for_user(int $userid, array $courseids = []): void {
        global $DB;

        if ($courseids === []) {
            $DB->delete_records(self::TABLE, ['userid' => $userid]);
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $params['userid'] = $userid;
        $DB->delete_records_select(
            self::TABLE,
            "userid = :userid AND courseid {$insql}",
            $params
        );
    }

    /**
     * Delete all job rows for a course.
     *
     * @param int $courseid Course ID.
     */
    public function delete_for_course(int $courseid): void {
        global $DB;

        $DB->delete_records(self::TABLE, ['courseid' => $courseid]);
    }

    /**
     * Delete job rows older than the given timestamp.
     *
     * @param int $beforeunix Unix timestamp cutoff.
     * @return int Number of deleted rows.
     */
    public function delete_older_than(int $beforeunix): int {
        global $DB;

        return $DB->delete_records_select(
            self::TABLE,
            'timecreated < :beforeunix',
            ['beforeunix' => $beforeunix]
        );
    }
}
