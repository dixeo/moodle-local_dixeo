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

namespace local_dixeo\service;

use local_dixeo\api\exception\api_exception;
use local_dixeo\dto\credit_transaction;
use local_dixeo\repository\credit_usage_repository;
use local_dixeo\repository\job_repository;

/**
 * Syncs Dixeo credit transactions into the local usage table.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credit_usage_sync_service {
    /** @var int Page size when fetching transactions from the API. */
    private const SYNC_PAGE_SIZE = 100;

    /** @var int Maximum pages per sync run to avoid long requests. */
    private const MAX_SYNC_PAGES = 20;

    /** @var string Plugin config key for last sync timestamp. */
    public const CONFIG_LAST_SYNCED = 'credit_usage_last_synced_at';

    /** @var credit_service */
    private credit_service $creditservice;

    /** @var credit_usage_repository */
    private credit_usage_repository $usagerepository;

    /** @var job_repository */
    private job_repository $jobrepository;

    /**
     * Constructor.
     *
     * @param credit_service|null $creditservice Optional credit service.
     * @param credit_usage_repository|null $usagerepository Optional usage repository.
     * @param job_repository|null $jobrepository Optional job repository.
     */
    public function __construct(
        ?credit_service $creditservice = null,
        ?credit_usage_repository $usagerepository = null,
        ?job_repository $jobrepository = null
    ) {
        $this->creditservice = $creditservice ?? new credit_service();
        $this->usagerepository = $usagerepository ?? new credit_usage_repository();
        $this->jobrepository = $jobrepository ?? new job_repository();
    }

    /**
     * Incrementally sync transactions since the last cursor.
     *
     * @param bool $full When true, ignore cursor and import all available pages.
     * @return int Number of rows upserted.
     */
    public function sync_recent(bool $full = false): int {
        if (!$this->creditservice->is_configured()) {
            return 0;
        }

        $sincetime = $full ? 0 : (int) get_config('local_dixeo', self::CONFIG_LAST_SYNCED);
        $upserted = 0;
        $offset = 0;
        $pages = 0;
        $latesttime = $sincetime;

        try {
            do {
                $result = $this->creditservice->get_transactions(null, self::SYNC_PAGE_SIZE, $offset);
                $transactions = $result['transactions'];
                if ($transactions === []) {
                    break;
                }

                $oldestinthepage = PHP_INT_MAX;
                foreach ($transactions as $raw) {
                    $transaction = credit_transaction::from_array($raw);
                    if ($transaction->id === '') {
                        continue;
                    }

                    if ($transaction->createdat > 0) {
                        $oldestinthepage = min($oldestinthepage, $transaction->createdat);
                    }

                    if (!$full && $sincetime > 0 && $transaction->createdat > 0 && $transaction->createdat <= $sincetime) {
                        continue;
                    }

                    $job = null;
                    if (!empty($transaction->jobid)) {
                        $job = $this->jobrepository->get_by_jobid($transaction->jobid);
                    }

                    $this->usagerepository->upsert_from_transaction($transaction, $job);
                    $upserted++;

                    if ($transaction->createdat > $latesttime) {
                        $latesttime = $transaction->createdat;
                    }
                }

                if (!$full && $sincetime > 0 && $oldestinthepage <= $sincetime) {
                    break;
                }

                $offset += self::SYNC_PAGE_SIZE;
                $pages++;
            } while ($result['pagination']['hasMore'] && $pages < self::MAX_SYNC_PAGES);

            if ($latesttime > $sincetime) {
                set_config(self::CONFIG_LAST_SYNCED, $latesttime, 'local_dixeo');
            }
        } catch (api_exception $e) {
            debugging('Credit usage sync failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $upserted;
    }

    /**
     * Fast-path sync after a job completes.
     *
     * @param string $jobid Remote job UUID.
     */
    public function sync_for_job(string $jobid): void {
        if (!$this->creditservice->is_configured() || trim($jobid) === '') {
            return;
        }

        try {
            $offset = 0;
            $pages = 0;
            do {
                $result = $this->creditservice->get_transactions(null, self::SYNC_PAGE_SIZE, $offset);
                $found = false;

                foreach ($result['transactions'] as $raw) {
                    $transaction = credit_transaction::from_array($raw);
                    if ($transaction->jobid !== $jobid) {
                        continue;
                    }

                    $job = $this->jobrepository->get_by_jobid($jobid);
                    $this->usagerepository->upsert_from_transaction($transaction, $job);
                    $found = true;

                    if ($transaction->createdat > 0) {
                        $lastsynced = (int) get_config('local_dixeo', self::CONFIG_LAST_SYNCED);
                        if ($transaction->createdat > $lastsynced) {
                            set_config(self::CONFIG_LAST_SYNCED, $transaction->createdat, 'local_dixeo');
                        }
                    }
                }

                if ($found) {
                    return;
                }

                $offset += self::SYNC_PAGE_SIZE;
                $pages++;
            } while ($result['pagination']['hasMore'] && $pages < 3);
        } catch (api_exception $e) {
            debugging('Credit usage job sync failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Re-apply job binding metadata to already-synced usage rows.
     *
     * @param string $jobid Remote job UUID.
     * @return int Number of usage rows updated.
     */
    public function resync_for_job(string $jobid): int {
        $jobid = trim($jobid);
        if ($jobid === '') {
            return 0;
        }

        $job = $this->jobrepository->get_by_jobid($jobid);
        if ($job === null) {
            return 0;
        }

        return $this->usagerepository->enrich_from_job($jobid, $job);
    }
}
