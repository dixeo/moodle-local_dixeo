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
}
