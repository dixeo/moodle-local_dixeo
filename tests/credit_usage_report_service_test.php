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

use local_dixeo\local\credit_report_request;
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
     * Filter options are scoped to the active period.
     */
    public function test_get_filter_options_scoped_to_period(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['firstname' => 'Alice', 'lastname' => 'Report']);
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Credit Course']);
        $now = time();
        $repo = new credit_usage_repository();
        $repo->upsert_from_transaction(
            credit_transaction::from_array([
                'id' => 'tx-filter-1',
                'type' => credit_transaction::TYPE_DEDUCTION,
                'amount' => -5,
                'balanceAfter' => 95,
                'createdAt' => gmdate('c', $now),
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

        $service = new credit_usage_report_service();
        $options = $service->get_filter_options([
            'type' => credit_transaction::TYPE_DEDUCTION,
            'timestart' => $now - DAYSECS,
            'timeend' => $now + DAYSECS,
        ]);

        $this->assertContains('block_dixeo_modulegen', $options['components']);
        $this->assertContains('generate_module', $options['jobtypes']);
        $this->assertContains('page', $options['moduletypes']);
        $this->assertSame((int) $user->id, $options['users'][0]['value']);
        $this->assertSame(fullname($user), $options['users'][0]['label']);
        $this->assertSame((int) $course->id, $options['courses'][0]['value']);
    }

    /**
     * Missing courses in usage rows must not break filter options or row formatting.
     */
    public function test_missing_course_is_skipped_gracefully(): void {
        global $DB;

        $this->resetAfterTest();

        $now = time();
        $repo = new credit_usage_repository();
        $repo->upsert_from_transaction(
            credit_transaction::from_array([
                'id' => 'tx-missing-course',
                'type' => credit_transaction::TYPE_DEDUCTION,
                'amount' => -3,
                'balanceAfter' => 97,
                'createdAt' => gmdate('c', $now),
            ]),
            (object) [
                'userid' => 0,
                'courseid' => 999999999,
                'operation' => 'module_generate',
                'component' => 'block_dixeo_modulegen',
            ]
        );

        $service = new credit_usage_report_service();
        $filters = [
            'type' => credit_transaction::TYPE_DEDUCTION,
            'timestart' => $now - DAYSECS,
            'timeend' => $now + DAYSECS,
        ];

        $options = $service->get_filter_options($filters);
        $this->assertSame([], $options['courses']);

        $rows = $service->get_rows($filters, 0, 10);
        $this->assertSame(1, $rows['total']);
        $this->assertSame(get_string('credit_context_site', 'local_dixeo'), $rows['rows'][0]['courselabel']);
    }

    /**
     * Export hidden params preserve multi-value filters.
     */
    public function test_to_export_hidden_params_includes_array_filters(): void {
        $request = credit_report_request::from_renderable_params([
            'view' => credit_usage_report_service::VIEW_CUSTOM,
            'datefrom' => credit_usage_report_service::parse_date_from_param('2026-06-08'),
            'dateto' => credit_usage_report_service::parse_date_to_param('2026-08-29'),
            'jobtypes' => ['course_structure'],
            'components' => [],
            'moduletypes' => [],
            'userids' => [],
            'courseids' => [],
        ]);

        $hidden = $request->to_export_hidden_params();
        $serialized = json_encode($hidden);

        $this->assertStringContainsString('jobtype[]', $serialized);
        $this->assertStringContainsString('course_structure', $serialized);
        $this->assertStringContainsString('datefrom', $serialized);
        $this->assertStringContainsString('2026-06-08', $serialized);
    }

    /**
     * Export columns match the report table.
     */
    public function test_get_export_columns_match_table_headers(): void {
        $service = new credit_usage_report_service();
        $columns = $service->get_export_columns();

        $this->assertArrayHasKey('credits', $columns);
        $this->assertArrayHasKey('component', $columns);
        $this->assertArrayHasKey('action', $columns);
        $this->assertArrayHasKey('date', $columns);
        $this->assertArrayHasKey('user', $columns);
        $this->assertArrayHasKey('course', $columns);
    }

    /**
     * Report URLs support multi-value filter query parameters.
     */
    public function test_build_report_url_supports_array_filters(): void {
        $url = credit_usage_report_service::build_report_url([
            'view' => 'custom',
            'datefrom' => '2026-06-08',
            'dateto' => '2026-08-29',
            'jobtype' => ['course_structure'],
        ]);

        $this->assertStringContainsString('view=custom', $url);
        $this->assertStringContainsString('datefrom=2026-06-08', $url);
        $this->assertStringContainsString('dateto=2026-08-29', $url);
        $this->assertStringContainsString('jobtype%5B0%5D=course_structure', $url);
    }

    /**
     * Parse and format custom date params in the user timezone.
     */
    public function test_date_param_roundtrip_uses_user_timezone(): void {
        global $USER;

        $this->resetAfterTest();
        $USER->timezone = 'America/New_York';

        $raw = '2026-01-15';
        $from = credit_usage_report_service::parse_date_from_param($raw);
        $to = credit_usage_report_service::parse_date_to_param($raw);

        $this->assertSame($raw, credit_usage_report_service::format_date_param($from));
        $this->assertSame($raw, credit_usage_report_service::format_date_param($to));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', credit_usage_report_service::format_date_param($from));
    }

    /**
     * Legacy unix timestamps in URLs still parse correctly.
     */
    public function test_date_param_accepts_legacy_unix_timestamp(): void {
        $this->resetAfterTest();

        $from = credit_usage_report_service::parse_date_from_param('2026-01-15');
        $legacy = (string) $from;

        $this->assertSame($from, credit_usage_report_service::parse_date_from_param($legacy));
    }

    /**
     * Custom range without dates defaults to the current week.
     */
    public function test_resolve_period_custom_defaults_to_week(): void {
        $service = new credit_usage_report_service();
        $period = $service->resolve_period(credit_usage_report_service::VIEW_CUSTOM, '2026-01-15');

        $this->assertSame('2026-01-12', date('Y-m-d', $period['timestart']));
        $this->assertSame('2026-01-18', date('Y-m-d', $period['timeend']));
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

    /**
     * Histogram includes every day in the period, with zero for days without usage.
     */
    public function test_get_histogram_fills_empty_days_in_period(): void {
        $this->resetAfterTest();

        $service = new credit_usage_report_service();
        $period = $service->resolve_period(credit_usage_report_service::VIEW_WEEK, '2026-01-15');
        $filters = [
            'type' => credit_transaction::TYPE_DEDUCTION,
            'timestart' => $period['timestart'],
            'timeend' => $period['timeend'],
        ];

        $repo = new credit_usage_repository();
        $repo->upsert_from_transaction(
            credit_transaction::from_array([
                'id' => 'tx-histogram-1',
                'type' => credit_transaction::TYPE_DEDUCTION,
                'amount' => -7,
                'balanceAfter' => 93,
                'createdAt' => gmdate('c', strtotime('2026-01-14 12:00:00')),
            ]),
            (object) [
                'userid' => 0,
                'courseid' => 0,
                'operation' => 'module_generate',
                'component' => 'block_dixeo_modulegen',
            ]
        );

        $histogram = $service->get_histogram($filters);

        $this->assertCount(7, $histogram['labels']);
        $this->assertCount(7, $histogram['values']);
        $this->assertSame(7, array_sum($histogram['values']));
        $this->assertSame(1, count(array_filter($histogram['values'])));
    }
}
