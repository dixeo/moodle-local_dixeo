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

use local_dixeo\output\credit_report_request;
use local_dixeo\dto\credit_transaction;
use local_dixeo\repository\credit_usage_repository;
use local_dixeo\service\credit_usage_report_service;
use local_dixeo\util\credit_component_mapper;
use local_dixeo\util\credit_moduletype_mapper;

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
     * Filter options merge canonical lists with period-scoped database values.
     */
    public function test_get_filter_options_merges_canonical_and_database_values(): void {
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
        foreach (credit_component_mapper::get_known_components() as $component) {
            $this->assertContains($component, $options['components']);
        }
        foreach (credit_component_mapper::get_known_actions() as $action) {
            $this->assertContains($action, $options['jobtypes']);
        }
        foreach (credit_moduletype_mapper::get_known_moduletypes() as $moduletype) {
            $this->assertContains($moduletype, $options['moduletypes']);
        }
        $this->assertArrayNotHasKey('users', $options);
        $this->assertArrayNotHasKey('courses', $options);
    }

    /**
     * Database-only filter values are merged into canonical option lists.
     */
    public function test_get_filter_options_includes_database_only_values(): void {
        $this->resetAfterTest();

        $now = time();
        $repo = new credit_usage_repository();
        $repo->upsert_from_transaction(
            credit_transaction::from_array([
                'id' => 'tx-custom-moduletype',
                'type' => credit_transaction::TYPE_DEDUCTION,
                'amount' => -4,
                'balanceAfter' => 96,
                'createdAt' => gmdate('c', $now),
                'jobType' => 'custom_action',
                'moduleType' => 'custommod',
            ]),
            (object) [
                'userid' => 0,
                'courseid' => 0,
                'operation' => 'custom_action',
                'component' => 'custom_component',
            ]
        );

        $service = new credit_usage_report_service();
        $options = $service->get_filter_options([
            'type' => credit_transaction::TYPE_DEDUCTION,
            'timestart' => $now - DAYSECS,
            'timeend' => $now + DAYSECS,
        ]);

        $this->assertContains('custom_component', $options['components']);
        $this->assertContains('custom_action', $options['jobtypes']);
        $this->assertContains('custommod', $options['moduletypes']);
    }

    /**
     * Canonical filter values are present even when the period has no usage rows.
     */
    public function test_get_filter_options_includes_canonical_values_without_rows(): void {
        $this->resetAfterTest();

        $service = new credit_usage_report_service();
        $options = $service->get_filter_options([
            'type' => credit_transaction::TYPE_DEDUCTION,
            'timestart' => time() + (10 * YEARSECS),
            'timeend' => time() + (10 * YEARSECS) + DAYSECS,
        ]);

        $this->assertSame(credit_component_mapper::get_known_components(), $options['components']);
        $this->assertSame(credit_component_mapper::get_known_actions(), $options['jobtypes']);
        $this->assertSame(credit_moduletype_mapper::get_known_moduletypes(), $options['moduletypes']);
        $this->assertArrayNotHasKey('users', $options);
        $this->assertArrayNotHasKey('courses', $options);
    }

    /**
     * Canonical action codes must not produce duplicate human-readable labels.
     */
    public function test_get_known_actions_has_unique_labels(): void {
        $labels = [];
        foreach (credit_component_mapper::get_known_actions() as $action) {
            $label = get_string('credit_action_' . $action, 'local_dixeo');
            $this->assertNotContains($label, $labels, "Duplicate action label for {$action}");
            $labels[] = $label;
        }
    }

    /**
     * Operation aliases stored in usage rows are normalized in filter options.
     */
    public function test_get_filter_options_normalizes_operation_aliases(): void {
        $this->resetAfterTest();

        $now = time();
        $repo = new credit_usage_repository();
        $repo->upsert_from_transaction(
            credit_transaction::from_array([
                'id' => 'tx-operation-only',
                'type' => credit_transaction::TYPE_DEDUCTION,
                'amount' => -3,
                'balanceAfter' => 97,
                'createdAt' => gmdate('c', $now),
            ]),
            (object) [
                'userid' => 0,
                'courseid' => 0,
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

        $this->assertContains('generate_module', $options['jobtypes']);
        $this->assertNotContains('module_generate', $options['jobtypes']);
    }

    /**
     * Action filter by canonical action codes matches stored operation aliases.
     */
    public function test_action_filter_matches_operation_aliases(): void {
        $this->resetAfterTest();

        $now = time();
        $repo = new credit_usage_repository();
        $repo->upsert_from_transaction(
            credit_transaction::from_array([
                'id' => 'tx-alias-filter',
                'type' => credit_transaction::TYPE_DEDUCTION,
                'amount' => -6,
                'balanceAfter' => 94,
                'createdAt' => gmdate('c', $now),
            ]),
            (object) [
                'userid' => 0,
                'courseid' => 0,
                'operation' => 'module_generate',
                'component' => 'block_dixeo_modulegen',
            ]
        );

        $service = new credit_usage_report_service();
        $filters = [
            'type' => credit_transaction::TYPE_DEDUCTION,
            'timestart' => $now - DAYSECS,
            'timeend' => $now + DAYSECS,
            'jobtypes' => ['generate_module'],
        ];

        $rows = $service->get_rows($filters, 0, 10);
        $this->assertSame(1, $rows['total']);
    }

    /**
     * User and course filter IDs must exist in credit usage data.
     */
    public function test_filter_valid_entity_ids_require_usage_rows(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $otheruser = $this->getDataGenerator()->create_user();
        $now = time();
        $repo = new credit_usage_repository();
        $repo->upsert_from_transaction(
            credit_transaction::from_array([
                'id' => 'tx-valid-user',
                'type' => credit_transaction::TYPE_DEDUCTION,
                'amount' => -2,
                'balanceAfter' => 98,
                'createdAt' => gmdate('c', $now),
            ]),
            (object) [
                'userid' => (int) $user->id,
                'courseid' => (int) $course->id,
                'operation' => 'module_generate',
                'component' => 'block_dixeo_modulegen',
            ]
        );

        $service = new credit_usage_report_service();
        $this->assertSame([(int) $user->id], $service->filter_valid_user_ids([(int) $user->id, (int) $otheruser->id, 99999]));
        $this->assertSame([(int) $course->id], $service->filter_valid_course_ids([(int) $course->id, 99999]));
    }

    /**
     * Period-scoped user search returns matching users with usage in the period.
     */
    public function test_search_filter_users_is_period_scoped(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['firstname' => 'Searchable', 'lastname' => 'User']);
        $now = time();
        $repo = new credit_usage_repository();
        $repo->upsert_from_transaction(
            credit_transaction::from_array([
                'id' => 'tx-search-user-service',
                'type' => credit_transaction::TYPE_DEDUCTION,
                'amount' => -1,
                'balanceAfter' => 99,
                'createdAt' => gmdate('c', $now),
            ]),
            (object) [
                'userid' => (int) $user->id,
                'courseid' => 0,
                'operation' => 'module_generate',
                'component' => 'block_dixeo_modulegen',
            ]
        );

        $service = new credit_usage_report_service();
        $results = $service->search_filter_users('Searchable', $now - DAYSECS, $now + DAYSECS);
        $this->assertCount(1, $results);
        $this->assertSame((int) $user->id, $results[0]['id']);
        $this->assertSame(fullname($user), $results[0]['label']);

        $empty = $service->search_filter_users('Searchable', $now + YEARSECS, $now + YEARSECS + DAYSECS);
        $this->assertSame([], $empty);
    }

    /**
     * Period-scoped course search returns matching courses with usage in the period.
     */
    public function test_search_filter_courses_is_period_scoped(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['fullname' => 'Searchable Course']);
        $now = time();
        $repo = new credit_usage_repository();
        $repo->upsert_from_transaction(
            credit_transaction::from_array([
                'id' => 'tx-search-course-service',
                'type' => credit_transaction::TYPE_DEDUCTION,
                'amount' => -1,
                'balanceAfter' => 99,
                'createdAt' => gmdate('c', $now),
            ]),
            (object) [
                'userid' => 0,
                'courseid' => (int) $course->id,
                'operation' => 'module_generate',
                'component' => 'block_dixeo_modulegen',
            ]
        );

        $service = new credit_usage_report_service();
        $results = $service->search_filter_courses('Searchable', $now - DAYSECS, $now + DAYSECS);
        $this->assertCount(1, $results);
        $this->assertSame((int) $course->id, $results[0]['id']);

        $empty = $service->search_filter_courses('Searchable', $now + YEARSECS, $now + YEARSECS + DAYSECS);
        $this->assertSame([], $empty);
    }

    /**
     * Entity labels load applied user and course IDs in a single query per type.
     */
    public function test_get_entity_labels_returns_applied_entities(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['firstname' => 'Label', 'lastname' => 'User']);
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Label Course']);
        $repo = new credit_usage_repository();
        $repo->upsert_from_transaction(
            credit_transaction::from_array([
                'id' => 'tx-entity-labels',
                'type' => credit_transaction::TYPE_DEDUCTION,
                'amount' => -1,
                'balanceAfter' => 99,
                'createdAt' => gmdate('c'),
            ]),
            (object) [
                'userid' => (int) $user->id,
                'courseid' => (int) $course->id,
                'operation' => 'module_generate',
                'component' => 'block_dixeo_modulegen',
            ]
        );

        $service = new credit_usage_report_service();
        $labels = $service->get_entity_labels([(int) $user->id], [(int) $course->id]);

        $this->assertSame(fullname($user), $labels['users'][(int) $user->id]);
        $this->assertSame('Label Course', $labels['courses'][(int) $course->id]);
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
        $this->assertArrayNotHasKey('courses', $options);

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
        $this->assertArrayHasKey('moduletype', $columns);
    }

    /**
     * Rows with a course module show linked course and activity names.
     */
    public function test_format_row_includes_module_context_links(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['fullname' => 'Context Course']);
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Intro page',
        ]);
        $modulecontext = \context_module::instance($page->cmid);
        $now = time();

        $repo = new credit_usage_repository();
        $repo->upsert_from_transaction(
            credit_transaction::from_array([
                'id' => 'tx-context',
                'type' => credit_transaction::TYPE_DEDUCTION,
                'amount' => -2,
                'balanceAfter' => 98,
                'createdAt' => gmdate('c', $now),
                'moduleType' => 'page',
            ]),
            (object) [
                'userid' => 0,
                'courseid' => (int) $course->id,
                'operation' => 'module_edit',
                'component' => 'local_dixeo_editor',
                'contextid' => (int) $modulecontext->id,
                'cmid' => (int) $page->cmid,
            ]
        );

        $service = new credit_usage_report_service();
        $rows = $service->get_rows([
            'type' => credit_transaction::TYPE_DEDUCTION,
            'timestart' => $now - DAYSECS,
            'timeend' => $now + DAYSECS,
        ], 0, 10);

        $row = $rows['rows'][0];
        $this->assertTrue($row['hasactivitycontext']);
        $this->assertStringContainsString('Context Course', $row['courselabel']);
        $this->assertSame('Intro page', $row['activitylabel']);
        $this->assertStringContainsString('/course/view.php?id=' . $course->id, (string) $row['courseurl']);
        $this->assertStringContainsString('/mod/page/view.php?id=' . $page->cmid, (string) $row['activityurl']);
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
     * View switches use the current period start as anchor for week/month.
     */
    public function test_build_view_switch_params_week_to_month_uses_period_start(): void {
        $service = new credit_usage_report_service();
        $period = $service->resolve_period(credit_usage_report_service::VIEW_WEEK, '2026-07-27');

        $this->assertSame('2026-07-27', date('Y-m-d', $period['timestart']));
        $params = credit_usage_report_service::build_view_switch_params(
            credit_usage_report_service::VIEW_MONTH,
            $period
        );

        $this->assertSame(credit_usage_report_service::VIEW_MONTH, $params['view']);
        $this->assertSame('2026-07-27', $params['anchor']);
        $this->assertArrayNotHasKey('datefrom', $params);
    }

    /**
     * A later week in the month anchors the month view to that month.
     */
    public function test_build_view_switch_params_august_week_to_month(): void {
        $service = new credit_usage_report_service();
        $period = $service->resolve_period(credit_usage_report_service::VIEW_WEEK, '2026-08-03');

        $params = credit_usage_report_service::build_view_switch_params(
            credit_usage_report_service::VIEW_MONTH,
            $period
        );

        $this->assertSame('2026-08-03', $params['anchor']);
        $month = $service->resolve_period(credit_usage_report_service::VIEW_MONTH, $params['anchor']);
        $this->assertSame('2026-08-01', date('Y-m-d', $month['timestart']));
    }

    /**
     * Switching to custom carries the full active period range.
     */
    public function test_build_view_switch_params_week_to_custom_uses_period_range(): void {
        $service = new credit_usage_report_service();
        $period = $service->resolve_period(credit_usage_report_service::VIEW_WEEK, '2026-07-27');

        $params = credit_usage_report_service::build_view_switch_params(
            credit_usage_report_service::VIEW_CUSTOM,
            $period
        );

        $this->assertSame(credit_usage_report_service::VIEW_CUSTOM, $params['view']);
        $this->assertSame('2026-07-27', $params['datefrom']);
        $this->assertSame('2026-08-02', $params['dateto']);
        $this->assertArrayNotHasKey('anchor', $params);
    }

    /**
     * Custom range switches to week/month using the range start date.
     */
    public function test_build_view_switch_params_custom_to_week_uses_range_start(): void {
        $service = new credit_usage_report_service();
        $datefrom = credit_usage_report_service::parse_date_from_param('2026-06-08');
        $dateto = credit_usage_report_service::parse_date_to_param('2026-08-29');
        $period = $service->resolve_period(
            credit_usage_report_service::VIEW_CUSTOM,
            null,
            $datefrom,
            $dateto
        );

        $weekparams = credit_usage_report_service::build_view_switch_params(
            credit_usage_report_service::VIEW_WEEK,
            $period
        );
        $monthparams = credit_usage_report_service::build_view_switch_params(
            credit_usage_report_service::VIEW_MONTH,
            $period
        );

        $this->assertSame('2026-06-08', $weekparams['anchor']);
        $this->assertSame('2026-06-08', $monthparams['anchor']);
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

    /**
     * A leap-year custom range is accepted.
     */
    public function test_resolve_period_accepts_custom_range_of_one_year(): void {
        $service = new credit_usage_report_service();
        $from = credit_usage_report_service::parse_date_from_param('2024-01-01');
        $to = credit_usage_report_service::parse_date_to_param('2024-12-31');

        $period = $service->resolve_period(
            credit_usage_report_service::VIEW_CUSTOM,
            null,
            $from,
            $to
        );

        $this->assertSame('2024-01-01', date('Y-m-d', $period['timestart']));
        $this->assertSame('2024-12-31', date('Y-m-d', $period['timeend']));
    }

    /**
     * Custom ranges longer than 366 days are rejected.
     */
    public function test_resolve_period_rejects_custom_range_over_one_year(): void {
        $service = new credit_usage_report_service();
        $from = make_timestamp(2020, 1, 1, 0, 0, 0);
        $to = make_timestamp(2026, 1, 1, 23, 59, 59);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('credit_report_range_too_long', 'local_dixeo'));
        $service->resolve_period(
            credit_usage_report_service::VIEW_CUSTOM,
            null,
            $from,
            $to
        );
    }

    /**
     * The daily histogram refuses a span longer than 366 days before walking each day.
     */
    public function test_get_histogram_rejects_unbounded_span(): void {
        $service = new credit_usage_report_service();

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('credit_report_range_too_long', 'local_dixeo'));
        $service->get_histogram([
            'timestart' => 1,
            'timeend' => 1 + credit_usage_report_service::MAX_CUSTOM_RANGE_SECONDS + 1,
        ]);
    }

    /**
     * Histogram buckets sum every row, including rows created in the same second.
     */
    public function test_histogram_sums_rows_with_equal_time(): void {
        global $DB;

        $this->resetAfterTest();
        $time = time();
        foreach ([3, 4] as $index => $credits) {
            $DB->insert_record(credit_usage_repository::TABLE, (object) [
                'transactionid' => 'tx-same-second-' . $index,
                'type' => credit_transaction::TYPE_DEDUCTION,
                'amount' => -$credits,
                'credits' => $credits,
                'timecreated' => $time,
                'timesynced' => $time,
            ]);
        }

        $histogram = (new credit_usage_report_service())->get_histogram([
            'type' => credit_transaction::TYPE_DEDUCTION,
            'timestart' => usergetmidnight($time),
            'timeend' => usergetmidnight($time) + DAYSECS - 1,
        ]);

        $this->assertSame([7], $histogram['values']);
    }

    /**
     * The queries of a page of rows do not grow with the number of distinct users and courses.
     */
    public function test_get_rows_queries_do_not_grow_with_distinct_entities(): void {
        global $DB;

        $this->resetAfterTest();
        $time = time();
        $courses = [];
        $users = [];
        $cmids = [];
        for ($i = 0; $i < 50; $i++) {
            $courses[$i] = $this->getDataGenerator()->create_course();
            $users[$i] = $this->getDataGenerator()->create_user();
            $cmids[$i] = (int) $this->getDataGenerator()->create_module('page', ['course' => $courses[$i]->id])->cmid;
            get_fast_modinfo($courses[$i]->id);
        }

        $service = new credit_usage_report_service();
        $filters = [
            'type' => credit_transaction::TYPE_DEDUCTION,
            'timestart' => $time - DAYSECS,
            'timeend' => $time + DAYSECS,
        ];
        $counts = [];
        foreach ([10, 50] as $distinct) {
            $DB->delete_records(credit_usage_repository::TABLE);
            $rows = [];
            for ($i = 0; $i < 50; $i++) {
                $entity = $i % $distinct;
                $rows[] = [
                    'transactionid' => "tx-{$distinct}-{$i}",
                    'type' => credit_transaction::TYPE_DEDUCTION,
                    'amount' => -1,
                    'credits' => 1,
                    'userid' => (int) $users[$entity]->id,
                    'courseid' => (int) $courses[$entity]->id,
                    'cmid' => $i % 2 ? $cmids[$entity] : 0,
                    'timecreated' => $time - $i,
                    'timesynced' => $time,
                ];
            }
            $DB->insert_records(credit_usage_repository::TABLE, $rows);

            // The first read warms the caches of the courses on the page.
            $service->get_rows($filters, 0, 50);
            $before = $DB->perf_get_queries();
            $result = $service->get_rows($filters, 0, 50);
            $counts[$distinct] = $DB->perf_get_queries() - $before;
            $this->assertCount(50, $result['rows']);
            $this->assertSame(fullname($users[0]), $result['rows'][0]['userlabel']);
            $this->assertTrue($result['rows'][1]['hasactivitycontext']);
        }

        $this->assertSame($counts[10], $counts[50]);
    }

    /**
     * Export rows stream in report order with the same labels as the screen.
     */
    public function test_export_rows_follow_report_order(): void {
        global $DB;

        $this->resetAfterTest();
        $time = time();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $rows = [];
        for ($i = 0; $i < 3; $i++) {
            $rows[] = [
                'transactionid' => 'tx-export-' . $i,
                'type' => credit_transaction::TYPE_DEDUCTION,
                'amount' => -($i + 1),
                'credits' => $i + 1,
                'userid' => (int) $user->id,
                'courseid' => (int) $course->id,
                'timecreated' => $time - $i,
                'timesynced' => $time,
            ];
        }
        $DB->insert_records(credit_usage_repository::TABLE, $rows);

        $service = new credit_usage_report_service();
        $filters = [
            'type' => credit_transaction::TYPE_DEDUCTION,
            'timestart' => $time - DAYSECS,
            'timeend' => $time + DAYSECS,
        ];
        $export = iterator_to_array($service->export_rows($filters), false);
        $screen = $service->get_rows($filters, 0, 50)['rows'];

        $this->assertSame(3, $service->count_rows($filters));
        $this->assertSame(array_column($screen, 'creditsformatted'), array_column($export, 'credits'));
        $this->assertSame(['1', '2', '3'], array_column($export, 'credits'));
        $this->assertSame(fullname($user), $export[0]['user']);
        $this->assertSame(format_string($course->fullname), $export[0]['course']);
    }

    /**
     * An anchor that is not a real Y-m-d date resolves to the current period.
     */
    public function test_resolve_period_ignores_malformed_anchor(): void {
        $service = new credit_usage_report_service();
        $current = $service->resolve_period(credit_usage_report_service::VIEW_WEEK, null);

        foreach (['x', '+500 years', '2026-02-31'] as $anchor) {
            $period = $service->resolve_period(credit_usage_report_service::VIEW_WEEK, $anchor);
            $this->assertSame($current['timestart'], $period['timestart'], $anchor);
            $this->assertSame($current['timeend'], $period['timeend'], $anchor);
        }
    }

    /**
     * The request keeps only a real Y-m-d anchor.
     */
    public function test_request_drops_malformed_anchor(): void {
        $this->assertSame('', credit_report_request::from_renderable_params(['anchor' => '+500 years'])->anchor);
        $this->assertSame('2026-11-17', credit_report_request::from_renderable_params(['anchor' => '2026-11-17'])->anchor);
    }
}
