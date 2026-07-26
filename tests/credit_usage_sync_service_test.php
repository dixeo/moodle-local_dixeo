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

use local_dixeo\dto\credit_transaction;
use local_dixeo\repository\credit_usage_repository;
use local_dixeo\repository\job_repository;
use local_dixeo\service\credit_service;
use local_dixeo\service\credit_usage_sync_service;

/**
 * Tests for credit usage sync service.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_dixeo\service\credit_usage_sync_service
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
        $count = $sync->sync_recent(true);

        $this->assertSame(1, $count);
        $record = $DB->get_record(credit_usage_repository::TABLE, ['transactionid' => 'tx-abc'], '*', MUST_EXIST);
        $this->assertSame(15, (int) $record->credits);
        $this->assertSame('block_dixeo_modulegen', $record->component);
        $this->assertSame((int) $user->id, (int) $record->userid);
        $this->assertSame((int) $course->id, (int) $record->courseid);

        $count = $sync->sync_recent(true);
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
}
