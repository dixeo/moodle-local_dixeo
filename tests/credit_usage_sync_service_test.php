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

namespace local_dixeo;

use local_dixeo\api\exception\api_exception;
use local_dixeo\dto\credit_sync_result;
use local_dixeo\dto\credit_transaction;
use local_dixeo\repository\credit_usage_repository;
use local_dixeo\repository\job_repository;
use local_dixeo\service\credit_service;
use local_dixeo\service\credit_usage_sync_service;
use local_dixeo\task\sync_credit_usage;

/**
 * Tests for credit usage sync service.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_dixeo\service\credit_usage_sync_service
 * @covers \local_dixeo\task\sync_credit_usage
 * @covers \local_dixeo\repository\job_repository
 */
final class credit_usage_sync_service_test extends \advanced_testcase {
    /**
     * Sync upserts and enriches from job bindings.
     */
    public function test_sync_recent_upserts_and_enriches(): void {
        global $DB;

        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $jobrepo = new job_repository();
        $jobrepo->register('job-abc', (int) $course->id, (int) $user->id, 'default', 'module_generate', 'block_dixeo_modulegen');

        $creditservice = $this->createMock(credit_service::class);
        $creditservice->method('is_configured')->willReturn(true);
        $creditservice->method('get_transactions')->willReturn([
            'transactions' => [
                (new credit_transaction(
                    id: 'tx-abc',
                    type: credit_transaction::TYPE_DEDUCTION,
                    amount: -15,
                    balanceafter: 100,
                    createdat: strtotime('2026-01-15T14:30:00+00:00'),
                    description: 'Generated Page module',
                    jobid: 'job-abc',
                    jobtype: 'generate_module',
                    moduletype: 'page',
                ))->to_array(),
            ],
            'pagination' => ['hasMore' => false],
        ]);

        $sync = new credit_usage_sync_service($creditservice);
        $count = $sync->sync_recent(true)->upserted;

        $this->assertSame(1, $count);
        $record = $DB->get_record(credit_usage_repository::TABLE, ['transactionid' => 'tx-abc'], '*', MUST_EXIST);
        $this->assertSame(15, (int) $record->credits);
        $this->assertSame('block_dixeo_modulegen', $record->component);
        $this->assertSame((int) $user->id, (int) $record->userid);
        $this->assertSame((int) $course->id, (int) $record->courseid);

        $count = $sync->sync_recent(true)->upserted;
        $this->assertSame(1, $count);
        $this->assertSame(1, $DB->count_records(credit_usage_repository::TABLE));
    }

    /**
     * Local job moduletype is used when the API transaction omits it.
     */
    public function test_sync_uses_local_job_moduletype_when_api_omits_it(): void {
        global $DB;

        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $metadata = new \local_dixeo\dto\job_binding_metadata(moduletype: 'page');
        $jobrepo = new job_repository();
        $jobrepo->register(
            'job-local-type',
            (int) $course->id,
            (int) $user->id,
            'default',
            'module_generate',
            'block_dixeo_modulegen',
            $metadata
        );

        $creditservice = $this->createMock(credit_service::class);
        $creditservice->method('is_configured')->willReturn(true);
        $creditservice->method('get_transactions')->willReturn([
            'transactions' => [
                (new credit_transaction(
                    id: 'tx-local-type',
                    type: credit_transaction::TYPE_DEDUCTION,
                    amount: -8,
                    balanceafter: 92,
                    createdat: strtotime('2026-01-16T10:00:00+00:00'),
                    jobid: 'job-local-type',
                    jobtype: 'generate_module',
                ))->to_array(),
            ],
            'pagination' => ['hasMore' => false],
        ]);

        $sync = new credit_usage_sync_service($creditservice);
        $sync->sync_recent(true);

        $record = $DB->get_record(credit_usage_repository::TABLE, ['transactionid' => 'tx-local-type'], '*', MUST_EXIST);
        $this->assertSame('page', $record->moduletype);
    }

    /**
     * Post-create metadata enrichment updates synced usage rows.
     */
    public function test_resync_for_job_updates_context_fields(): void {
        global $DB;

        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $modulecontext = \context_module::instance($page->cmid);

        $jobrepo = new job_repository();
        $jobrepo->register(
            'job-enrich',
            (int) $course->id,
            (int) $user->id,
            'default',
            'module_fill',
            'block_dixeo_designer',
            new \local_dixeo\dto\job_binding_metadata(moduletype: 'page')
        );

        $usagerepo = new credit_usage_repository();
        $usagerepo->upsert_from_transaction(
            new credit_transaction(
                id: 'tx-enrich',
                type: credit_transaction::TYPE_DEDUCTION,
                amount: -4,
                balanceafter: 96,
                createdat: time(),
                jobid: 'job-enrich',
                jobtype: 'fill_module',
            ),
            $jobrepo->get_by_jobid('job-enrich')
        );

        $jobrepo->update_metadata(
            'job-enrich',
            new \local_dixeo\dto\job_binding_metadata(
                moduletype: 'page',
                contextid: (int) $modulecontext->id,
                cmid: (int) $page->cmid
            )
        );

        $sync = new credit_usage_sync_service();
        $updated = $sync->resync_for_job('job-enrich');

        $this->assertSame(1, $updated);
        $record = $DB->get_record(credit_usage_repository::TABLE, ['transactionid' => 'tx-enrich'], '*', MUST_EXIST);
        $this->assertSame((int) $page->cmid, (int) $record->cmid);
        $this->assertSame((int) $modulecontext->id, (int) $record->contextid);
    }

    /**
     * Transaction payload as returned by the credit service.
     *
     * @param string $id Transaction id.
     * @param int $createdat Creation time.
     * @param string|null $jobid Job id.
     * @return array
     */
    private function transaction_payload(string $id, int $createdat, ?string $jobid = null): array {
        return (new credit_transaction(
            id: $id,
            type: credit_transaction::TYPE_DEDUCTION,
            amount: -1,
            balanceafter: 100,
            createdat: $createdat,
            jobid: $jobid,
        ))->to_array();
    }

    /**
     * Configured credit service mock answering get_transactions() with a callback.
     *
     * @param callable $pages Callback (int $offset): array returning the get_transactions() result.
     * @return credit_service
     */
    private function credit_service_mock(callable $pages): credit_service {
        $creditservice = $this->createMock(credit_service::class);
        $creditservice->method('is_configured')->willReturn(true);
        $creditservice->method('get_transactions')->willReturnCallback(
            static fn(?string $type, int $limit, int $offset): array => $pages($offset)
        );
        return $creditservice;
    }

    /**
     * Single page result.
     *
     * @param array $transactions Transaction payloads.
     * @param bool $hasmore Whether more pages follow.
     * @return array
     */
    private function page_result(array $transactions, bool $hasmore = false): array {
        return ['transactions' => $transactions, 'pagination' => ['hasMore' => $hasmore]];
    }

    /**
     * A manual refresh stores the transaction and records the time of the successful sync.
     */
    public function test_refresh_now_syncs_and_records_last_success(): void {
        global $DB;

        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1800000000);
        $sync = new credit_usage_sync_service($this->credit_service_mock(
            fn(int $offset): array => $this->page_result([$this->transaction_payload('tx-now', 1799999000)])
        ));

        $this->assertSame(0, credit_usage_sync_service::get_last_success());
        $result = $sync->refresh_now();

        $this->assertSame(credit_sync_result::STATUS_SYNCED, $result->status);
        $this->assertSame(1, $result->upserted);
        $this->assertSame($clock->time(), credit_usage_sync_service::get_last_success());
        $this->assertSame($clock->time(), $result->lastsuccess);
        $this->assertTrue($DB->record_exists(credit_usage_repository::TABLE, ['transactionid' => 'tx-now']));
        $this->assertSame('1799999000', get_config('local_dixeo', credit_usage_sync_service::CONFIG_LAST_SYNCED));
    }

    /**
     * A second manual refresh within a minute makes no API call.
     */
    public function test_refresh_now_is_throttled_for_a_minute(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1800000000);
        $calls = 0;
        $sync = new credit_usage_sync_service($this->credit_service_mock(
            function (int $offset) use (&$calls): array {
                $calls++;
                return $this->page_result([]);
            }
        ));

        $this->assertSame(credit_sync_result::STATUS_SYNCED, $sync->refresh_now()->status);
        $clock->bump(30);
        $this->assertSame(credit_sync_result::STATUS_THROTTLED, $sync->refresh_now()->status);
        $this->assertSame(1, $calls);

        $clock->bump(31);
        $this->assertSame(credit_sync_result::STATUS_SYNCED, $sync->refresh_now()->status);
        $this->assertSame(2, $calls);

        // The scheduled path is not throttled.
        $this->assertSame(credit_sync_result::STATUS_SYNCED, $sync->sync_recent()->status);
        $this->assertSame(3, $calls);
    }

    /**
     * A failed sync keeps the last success, the cursor and the stored rows; the task does not throw.
     */
    public function test_failed_sync_keeps_previous_state(): void {
        global $DB;

        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1800000000);
        set_config(credit_usage_sync_service::CONFIG_LAST_SUCCESS, 1700000000, 'local_dixeo');
        set_config(credit_usage_sync_service::CONFIG_LAST_SYNCED, 1699999000, 'local_dixeo');
        (new credit_usage_repository())->upsert_from_transaction(
            credit_transaction::from_array($this->transaction_payload('tx-stored', 1699999000))
        );
        $rowsbefore = $DB->get_records(credit_usage_repository::TABLE);

        $creditservice = $this->createMock(credit_service::class);
        $creditservice->method('is_configured')->willReturn(true);
        $creditservice->method('get_transactions')->willThrowException(
            new api_exception('network_error', 'Failed to connect to Dixeo API', 0)
        );
        $sync = new credit_usage_sync_service($creditservice);

        $result = $sync->refresh_now();
        $this->assertDebuggingCalled();
        $this->assertSame(credit_sync_result::STATUS_FAILED, $result->status);
        $this->assertSame(1700000000, $result->lastsuccess);
        $this->assertSame(1700000000, credit_usage_sync_service::get_last_success());
        $this->assertSame('1699999000', get_config('local_dixeo', credit_usage_sync_service::CONFIG_LAST_SYNCED));
        $this->assertEquals($rowsbefore, $DB->get_records(credit_usage_repository::TABLE));

        $task = $this->task_with($sync);
        $this->expectOutputRegex('/status=failed/');
        $task->execute();
        $this->assertDebuggingCalled();
    }

    /**
     * The scheduled task syncs while Dixeo is switched off.
     */
    public function test_task_syncs_while_dixeo_is_off(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('enabled', '0', 'local_dixeo');
        $this->assertFalse(external\service_factory::get_settings_config()->enabled_for(\context_system::instance()));
        $sync = new credit_usage_sync_service($this->credit_service_mock(
            fn(int $offset): array => $this->page_result([$this->transaction_payload('tx-task', time() - 60)])
        ));

        $this->expectOutputRegex('/status=synced upserted=1/');
        $this->task_with($sync)->execute();
        $this->assertTrue($DB->record_exists(credit_usage_repository::TABLE, ['transactionid' => 'tx-task']));
    }

    /**
     * While a sync holds the lock, other runs return busy without calling the API.
     */
    public function test_runs_are_busy_while_the_lock_is_held(): void {
        $this->resetAfterTest();
        $innercalls = 0;
        $inner = new credit_usage_sync_service($this->credit_service_mock(
            function (int $offset) use (&$innercalls): array {
                $innercalls++;
                return $this->page_result([]);
            }
        ));
        $statuses = [];
        $outer = new credit_usage_sync_service($this->credit_service_mock(
            function (int $offset) use ($inner, &$statuses): array {
                $statuses[] = $inner->refresh_now()->status;
                $statuses[] = $inner->sync_recent()->status;
                return $this->page_result([]);
            }
        ));

        $this->assertSame(credit_sync_result::STATUS_SYNCED, $outer->sync_recent()->status);
        $this->assertSame([credit_sync_result::STATUS_BUSY, credit_sync_result::STATUS_BUSY], $statuses);
        $this->assertSame(0, $innercalls);
    }

    /**
     * A run that hits the page cap stores the offset; the next run finishes without duplicates.
     */
    public function test_sync_resumes_after_the_page_cap(): void {
        global $DB;

        $this->resetAfterTest();
        $oldcursor = 1700000000;
        $newest = 1800000000;
        set_config(credit_usage_sync_service::CONFIG_LAST_SYNCED, $oldcursor, 'local_dixeo');
        $sync = new credit_usage_sync_service($this->credit_service_mock(
            function (int $offset) use ($newest): array {
                $page = intdiv($offset, 100);
                $transactions = [];
                for ($i = 0; $i < 100; $i++) {
                    $position = $offset + $i;
                    $transactions[] = $this->transaction_payload('tx-' . $position, $newest - $position);
                }
                return $this->page_result($transactions, $page < 24);
            }
        ));

        $first = $sync->sync_recent();
        $this->assertSame(2000, $first->upserted);
        $this->assertSame('2000', get_config('local_dixeo', credit_usage_sync_service::CONFIG_RESUME_OFFSET));
        $this->assertSame((string) $oldcursor, get_config('local_dixeo', credit_usage_sync_service::CONFIG_LAST_SYNCED));

        $second = $sync->sync_recent();
        $this->assertSame(500, $second->upserted);
        $this->assertSame((string) $newest, get_config('local_dixeo', credit_usage_sync_service::CONFIG_LAST_SYNCED));
        $this->assertFalse(get_config('local_dixeo', credit_usage_sync_service::CONFIG_RESUME_OFFSET));
        $this->assertSame(2500, $DB->count_records(credit_usage_repository::TABLE));
    }

    /**
     * A manual refresh reads at most five pages and stores where to resume.
     */
    public function test_refresh_now_reads_at_most_five_pages(): void {
        $this->resetAfterTest();
        $calls = 0;
        $sync = new credit_usage_sync_service($this->credit_service_mock(
            function (int $offset) use (&$calls): array {
                $calls++;
                return $this->page_result([$this->transaction_payload('tx-page-' . $offset, 1800000000 - $offset)], true);
            }
        ));

        $this->assertSame(credit_sync_result::STATUS_SYNCED, $sync->refresh_now()->status);
        $this->assertSame(credit_usage_sync_service::MANUAL_MAX_PAGES, $calls);
        $this->assertSame('500', get_config('local_dixeo', credit_usage_sync_service::CONFIG_RESUME_OFFSET));
    }

    /**
     * Syncing one job's transaction leaves the sync cursor alone.
     */
    public function test_sync_for_job_does_not_move_the_cursor(): void {
        global $DB;

        $this->resetAfterTest();
        set_config(credit_usage_sync_service::CONFIG_LAST_SYNCED, 1700000000, 'local_dixeo');
        $sync = new credit_usage_sync_service($this->credit_service_mock(
            fn(int $offset): array => $this->page_result([$this->transaction_payload('tx-job', 1800000000, 'job-x')])
        ));

        $sync->sync_for_job('job-x');

        $this->assertTrue($DB->record_exists(credit_usage_repository::TABLE, ['transactionid' => 'tx-job']));
        $this->assertSame('1700000000', get_config('local_dixeo', credit_usage_sync_service::CONFIG_LAST_SYNCED));
    }

    /**
     * Job bindings of a page are loaded in one call keyed by job id.
     */
    public function test_get_by_jobids_returns_known_jobs(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $repository = new job_repository();
        $repository->register('job-one', (int) $course->id, (int) $user->id, 'default', 'module_generate');
        $repository->register('job-two', (int) $course->id, (int) $user->id, 'default', 'module_generate');

        $jobs = $repository->get_by_jobids(['job-one', 'job-two', 'job-unknown', '']);

        $this->assertSame(['job-one', 'job-two'], array_keys($jobs));
        $this->assertSame([], $repository->get_by_jobids([]));
    }

    /**
     * Sync task that uses the given sync service.
     *
     * @param credit_usage_sync_service $sync Sync service.
     * @return sync_credit_usage
     */
    private function task_with(credit_usage_sync_service $sync): sync_credit_usage {
        return new class ($sync) extends sync_credit_usage {
            /** @var credit_usage_sync_service Sync service. */
            private credit_usage_sync_service $sync;

            /**
             * Constructor.
             *
             * @param credit_usage_sync_service $sync Sync service.
             */
            public function __construct(credit_usage_sync_service $sync) {
                $this->sync = $sync;
            }

            /**
             * Sync service used by the task.
             *
             * @return credit_usage_sync_service
             */
            protected function sync_service(): credit_usage_sync_service {
                return $this->sync;
            }
        };
    }
}
