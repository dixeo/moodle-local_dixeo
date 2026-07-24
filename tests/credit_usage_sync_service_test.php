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
}
