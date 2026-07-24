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
use local_dixeo\service\credit_usage_report_service;

/**
 * Tests for credit usage report service.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_dixeo\service\credit_usage_report_service
 */
final class credit_usage_report_service_test extends \advanced_testcase {
    /**
     * Report service aggregates KPIs and filters by period.
     */
    public function test_get_kpis_and_rows_respect_filters(): void {
        global $DB;

        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $now = time();
        $repo = new credit_usage_repository();
        $repo->upsert_from_transaction(
            credit_transaction::from_array([
                'id' => 'tx-1',
                'type' => credit_transaction::TYPE_DEDUCTION,
                'amount' => -10,
                'balanceAfter' => 90,
                'createdAt' => gmdate('c', $now),
                'jobId' => 'job-1',
                'jobType' => 'generate_module',
                'moduleType' => 'page',
            ]),
            (object) [
                'userid' => (int) $user->id,
                'courseid' => (int) $course->id,
                'operation' => 'module_generate',
                'component' => 'block_dixeo_modulegen',
            ]
        );
        $repo->upsert_from_transaction(
            credit_transaction::from_array([
                'id' => 'tx-2',
                'type' => credit_transaction::TYPE_PURCHASE,
                'amount' => 100,
                'balanceAfter' => 190,
                'createdAt' => gmdate('c', $now),
            ]),
            null
        );

        $service = new credit_usage_report_service();
        $filters = [
            'type' => credit_transaction::TYPE_DEDUCTION,
            'timestart' => $now - DAYSECS,
            'timeend' => $now + DAYSECS,
        ];

        $kpis = $service->get_kpis($filters);
        $this->assertSame(10, $kpis['totalcredits']);
        $this->assertSame(1, $kpis['totalusers']);
        $this->assertSame(1, $kpis['totalcourses']);
        $this->assertSame(1, $kpis['totalrows']);

        $rows = $service->get_rows($filters, 0, 10);
        $this->assertSame(1, $rows['total']);
        $this->assertSame('block_dixeo_modulegen', $rows['rows'][0]['component']);
    }

    /**
     * Period resolver returns week boundaries.
     */
    public function test_resolve_period_week(): void {
        $service = new credit_usage_report_service();
        $period = $service->resolve_period(credit_usage_report_service::VIEW_WEEK, '2026-01-15');

        $this->assertSame('2026-01-12', date('Y-m-d', $period['timestart']));
        $this->assertSame('2026-01-18', date('Y-m-d', $period['timeend']));
        $this->assertSame('2026-01-05', $period['prevanchor']);
        $this->assertSame('2026-01-19', $period['nextanchor']);
    }
}
