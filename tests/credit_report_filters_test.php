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
use local_dixeo\output\credit_report_filters;
use local_dixeo\output\credit_report_request;
use local_dixeo\repository\credit_usage_repository;
use local_dixeo\service\credit_usage_report_service;

/**
 * Tests for credit report filter state.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_dixeo\output\credit_report_filters
 */
final class credit_report_filters_test extends \advanced_testcase {
    /**
     * Applied entity filters are rendered from validated IDs only.
     */
    public function test_get_applied_entity_options_includes_valid_selected_entities(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['firstname' => 'Filter', 'lastname' => 'User']);
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Filter Course']);
        $repo = new credit_usage_repository();
        $repo->upsert_from_transaction(
            credit_transaction::from_array([
                'id' => 'tx-filter-entity',
                'type' => credit_transaction::TYPE_DEDUCTION,
                'amount' => -2,
                'balanceAfter' => 98,
                'createdAt' => gmdate('c'),
            ]),
            (object) [
                'userid' => (int) $user->id,
                'courseid' => (int) $course->id,
                'operation' => 'module_generate',
                'component' => 'block_dixeo_modulegen',
            ]
        );

        $filters = credit_report_filters::from_raw([
            'userids' => [(int) $user->id, 424242],
            'courseids' => [(int) $course->id, 515151],
        ]);
        $options = $filters->get_applied_entity_options(new credit_usage_report_service());

        $this->assertCount(1, $options['users']);
        $this->assertSame((string) $user->id, $options['users'][0]['value']);
        $this->assertSame(fullname($user), $options['users'][0]['label']);
        $this->assertCount(1, $options['courses']);
        $this->assertSame((string) $course->id, $options['courses'][0]['value']);
    }

    /**
     * Filter state round-trips through URL query parameters.
     */
    public function test_to_query_params_round_trip(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $repo = new credit_usage_repository();
        $repo->upsert_from_transaction(
            credit_transaction::from_array([
                'id' => 'tx-filter-roundtrip',
                'type' => credit_transaction::TYPE_DEDUCTION,
                'amount' => -2,
                'balanceAfter' => 98,
                'createdAt' => gmdate('c'),
            ]),
            (object) [
                'userid' => (int) $user->id,
                'courseid' => (int) $course->id,
                'operation' => 'module_generate',
                'component' => 'block_dixeo_modulegen',
            ]
        );

        $original = credit_report_filters::from_raw([
            'components' => ['block_dixeo_tutor'],
            'jobtypes' => ['module_generate'],
            'moduletypes' => ['page'],
            'userids' => [(int) $user->id],
            'courseids' => [(int) $course->id],
        ]);

        $restored = credit_report_filters::from_raw([
            'components' => $original->to_query_params()['component'] ?? [],
            'jobtypes' => $original->to_query_params()['jobtype'] ?? [],
            'moduletypes' => $original->to_query_params()['moduletype'] ?? [],
            'userids' => $original->to_query_params()['userid'] ?? [],
            'courseids' => $original->to_query_params()['courseid'] ?? [],
        ]);

        $this->assertSame(['block_dixeo_tutor'], $restored->components);
        $this->assertSame(['generate_module'], $restored->jobtypes);
        $this->assertSame(['page'], $restored->moduletypes);
        $this->assertSame([(int) $user->id], $restored->userids);
        $this->assertSame([(int) $course->id], $restored->courseids);
    }

    /**
     * Request parsing rejects user and course IDs that are not present in usage data.
     */
    public function test_request_sanitizes_user_and_course_filters(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $repo = new credit_usage_repository();
        $repo->upsert_from_transaction(
            credit_transaction::from_array([
                'id' => 'tx-request-filter',
                'type' => credit_transaction::TYPE_DEDUCTION,
                'amount' => -2,
                'balanceAfter' => 98,
                'createdAt' => gmdate('c'),
            ]),
            (object) [
                'userid' => (int) $user->id,
                'courseid' => (int) $course->id,
                'operation' => 'module_generate',
                'component' => 'block_dixeo_modulegen',
            ]
        );

        $request = credit_report_request::from_renderable_params([
            'userids' => [(int) $user->id, 424242],
            'courseids' => [(int) $course->id, 515151],
        ]);

        $this->assertSame([(int) $user->id], $request->filters->userids);
        $this->assertSame([(int) $course->id], $request->filters->courseids);
    }
}
