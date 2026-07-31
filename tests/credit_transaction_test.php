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

/**
 * Tests for credit transaction DTO.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_dixeo\dto\credit_transaction
 */
final class credit_transaction_test extends \advanced_testcase {
    /**
     * Parse full API payload.
     */
    public function test_from_array_parses_job_fields(): void {
        $transaction = credit_transaction::from_array([
            'id' => 'tx-1',
            'type' => 'deduction',
            'amount' => -12,
            'balanceAfter' => 1988,
            'createdAt' => '2026-01-15T14:30:00+00:00',
            'description' => 'Generated Page module',
            'jobId' => 'job-1',
            'jobType' => 'generate_module',
            'moduleType' => 'page',
        ]);

        $this->assertSame('tx-1', $transaction->id);
        $this->assertSame('deduction', $transaction->type);
        $this->assertSame(-12, $transaction->amount);
        $this->assertSame(12, $transaction->get_usage_credits());
        $this->assertSame('job-1', $transaction->jobid);
        $this->assertSame('generate_module', $transaction->jobtype);
        $this->assertSame('page', $transaction->moduletype);
    }

    /**
     * Null job fields are preserved as null.
     */
    public function test_from_array_handles_null_job_fields(): void {
        $transaction = credit_transaction::from_array([
            'id' => 'tx-2',
            'type' => 'purchase',
            'amount' => 5000,
            'balanceAfter' => 5000,
            'createdAt' => '2026-01-01T00:00:00+00:00',
            'description' => 'Credit allocation',
            'jobId' => null,
            'jobType' => null,
            'moduleType' => null,
        ]);

        $this->assertNull($transaction->jobid);
        $this->assertNull($transaction->jobtype);
        $this->assertNull($transaction->moduletype);
    }
}
