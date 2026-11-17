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
use local_dixeo\dto\credit_sync_result;
use local_dixeo\dto\credit_transaction;
use local_dixeo\repository\credit_usage_repository;
use local_dixeo\repository\job_repository;

/**
 * Syncs Dixeo credit transactions into the local usage table.
 *
 * A run reads the transactions newest first, page by page, until it reaches the stored cursor
 * or the end of the list. A run that hits its page cap stores the next offset and keeps the
 * cursor, so the next run resumes without skipping a row; upserts are idempotent by transaction
 * id. Runs are serialised by a core lock, and a manual refresh is throttled site-wide.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credit_usage_sync_service {
    /** @var int Page size when fetching transactions from the API. */
    private const SYNC_PAGE_SIZE = 100;

    /** @var int Maximum pages per scheduled run. */
    public const MAX_SYNC_PAGES = 20;

    /** @var int Maximum pages per manual refresh; the scheduled run finishes the rest. */
    public const MANUAL_MAX_PAGES = 5;

    /** @var int Seconds between two manual refreshes, site-wide. */
    public const MANUAL_REFRESH_INTERVAL = 60;

    /** @var string Plugin config key: creation time of the newest synced transaction (the cursor). */
    public const CONFIG_LAST_SYNCED = 'credit_usage_last_synced_at';

    /** @var string Plugin config key: time of the last successful sync run. */
    public const CONFIG_LAST_SUCCESS = 'credit_usage_last_sync_success';

    /** @var string Plugin config key: time of the last sync attempt. */
    public const CONFIG_LAST_ATTEMPT = 'credit_usage_last_sync_attempt';

    /** @var string Plugin config key: offset the next run resumes from. */
    public const CONFIG_RESUME_OFFSET = 'credit_usage_sync_resume_offset';

    /** @var string Plugin config key: newest transaction time seen by an unfinished pass. */
    public const CONFIG_PENDING_CURSOR = 'credit_usage_sync_pending_cursor';

    /** @var string Core lock resource serialising sync runs. */
    private const LOCK_RESOURCE = 'credit_usage_sync';

    /** @var \core\lock\lock_factory|null Lock factory shared by every run in this process. */
    private static ?\core\lock\lock_factory $lockfactory = null;

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
     * Sync transactions since the cursor (scheduled path, not throttled).
     *
     * @param bool $full When true, ignore the cursor and import every available page.
     * @return credit_sync_result
     */
    public function sync_recent(bool $full = false): credit_sync_result {
        return $this->run(self::MAX_SYNC_PAGES, false, $full);
    }

    /**
     * Sync transactions since the cursor on a user's request (throttled site-wide).
     *
     * @return credit_sync_result
     */
    public function refresh_now(): credit_sync_result {
        return $this->run(self::MANUAL_MAX_PAGES, true, false);
    }

    /**
     * Time of the last successful sync run.
     *
     * @return int Timestamp, or 0 when never synced.
     */
    public static function get_last_success(): int {
        return (int) get_config('local_dixeo', self::CONFIG_LAST_SUCCESS);
    }

    /**
     * Run one sync under the sync lock.
     *
     * @param int $maxpages Page cap.
     * @param bool $throttled Whether the manual throttle applies.
     * @param bool $full Whether to ignore the cursor.
     * @return credit_sync_result
     */
    private function run(int $maxpages, bool $throttled, bool $full): credit_sync_result {
        if (!$this->creditservice->is_configured()) {
            return new credit_sync_result(credit_sync_result::STATUS_NOT_CONFIGURED, 0, self::get_last_success());
        }

        if (self::$lockfactory === null) {
            self::$lockfactory = \core\lock\lock_config::get_lock_factory('local_dixeo');
        }
        $lock = self::$lockfactory->get_lock(self::LOCK_RESOURCE, 0);
        if (!$lock) {
            return new credit_sync_result(credit_sync_result::STATUS_BUSY, 0, self::get_last_success());
        }

        try {
            $now = \core\di::get(\core\clock::class)->time();
            if ($throttled) {
                $lastattempt = (int) get_config('local_dixeo', self::CONFIG_LAST_ATTEMPT);
                if ($lastattempt > 0 && ($now - $lastattempt) < self::MANUAL_REFRESH_INTERVAL) {
                    return new credit_sync_result(credit_sync_result::STATUS_THROTTLED, 0, self::get_last_success());
                }
            }
            set_config(self::CONFIG_LAST_ATTEMPT, $now, 'local_dixeo');
            return $this->sync_pages($maxpages, $full, $now);
        } finally {
            $lock->release();
        }
    }

    /**
     * Read and store transaction pages from the resume offset.
     *
     * @param int $maxpages Page cap.
     * @param bool $full Whether to ignore the cursor.
     * @param int $now Run time.
     * @return credit_sync_result
     */
    private function sync_pages(int $maxpages, bool $full, int $now): credit_sync_result {
        $sincetime = $full ? 0 : (int) get_config('local_dixeo', self::CONFIG_LAST_SYNCED);
        $offset = max(0, (int) get_config('local_dixeo', self::CONFIG_RESUME_OFFSET));
        $pending = (int) get_config('local_dixeo', self::CONFIG_PENDING_CURSOR);
        $upserted = 0;
        $pages = 0;
        $finished = false;

        try {
            while ($pages < $maxpages) {
                $result = $this->creditservice->get_transactions(null, self::SYNC_PAGE_SIZE, $offset);
                $pages++;
                $offset += self::SYNC_PAGE_SIZE;

                $transactions = [];
                foreach ($result['transactions'] as $raw) {
                    $transaction = credit_transaction::from_array($raw);
                    if ($transaction->id !== '') {
                        $transactions[] = $transaction;
                    }
                }
                $jobids = array_filter(array_map(static fn(credit_transaction $t): string => (string) $t->jobid, $transactions));
                $jobs = $this->jobrepository->get_by_jobids(array_values($jobids));

                $reachedcursor = false;
                foreach ($transactions as $transaction) {
                    $pending = max($pending, $transaction->createdat);
                    if (!$full && $sincetime > 0 && $transaction->createdat > 0 && $transaction->createdat <= $sincetime) {
                        $reachedcursor = true;
                        continue;
                    }
                    $this->usagerepository->upsert_from_transaction($transaction, $jobs[$transaction->jobid] ?? null);
                    $upserted++;
                }

                if ($reachedcursor || $result['transactions'] === [] || empty($result['pagination']['hasMore'])) {
                    $finished = true;
                    break;
                }
            }
        } catch (api_exception $e) {
            debugging('Credit usage sync failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return new credit_sync_result(credit_sync_result::STATUS_FAILED, $upserted, self::get_last_success());
        }

        if ($finished) {
            if ($pending > $sincetime) {
                set_config(self::CONFIG_LAST_SYNCED, $pending, 'local_dixeo');
            }
            unset_config(self::CONFIG_RESUME_OFFSET, 'local_dixeo');
            unset_config(self::CONFIG_PENDING_CURSOR, 'local_dixeo');
        } else {
            set_config(self::CONFIG_RESUME_OFFSET, $offset, 'local_dixeo');
            set_config(self::CONFIG_PENDING_CURSOR, $pending, 'local_dixeo');
        }
        set_config(self::CONFIG_LAST_SUCCESS, $now, 'local_dixeo');

        return new credit_sync_result(credit_sync_result::STATUS_SYNCED, $upserted, $now);
    }

    /**
     * Store the transaction of one job after it completes.
     *
     * Leaves the sync cursor alone, so the scheduled sync still reads every older transaction.
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
