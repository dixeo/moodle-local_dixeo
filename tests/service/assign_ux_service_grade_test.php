<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Tests for grade proposal validation in assign_ux_service.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\service\assign_submission_reader;
use local_dixeo\service\assign_ux_service;
use local_dixeo\service\job_service;

/**
 * A model grade reaches Moodle only when it fits the assignment's grading setup.
 *
 * @covers \local_dixeo\service\assign_ux_service::map_grade_result
 * @covers \local_dixeo\service\assign_submission_reader::get_assignment_context
 */
final class assign_ux_service_grade_test extends \advanced_testcase {
    /** @var array Simple grading out of 100. */
    private const SIMPLE = ['grading_method' => 'simple', 'maxgrade' => 100.0, 'criteria' => []];

    /** @var array Marking guide with two criteria. */
    private const GUIDE = [
        'grading_method' => 'guide',
        'maxgrade' => 100.0,
        'criteria' => [
            ['id' => 11, 'maxscore' => 10.0],
            ['id' => 12, 'maxscore' => 5.0],
        ],
    ];

    /** @var array Rubric with two criteria and their levels. */
    private const RUBRIC = [
        'grading_method' => 'rubric',
        'maxgrade' => 100.0,
        'criteria' => [
            ['id' => 21, 'levels' => [['id' => 211, 'score' => 0.0], ['id' => 212, 'score' => 2.0]]],
            ['id' => 22, 'levels' => [['id' => 221, 'score' => 0.0], ['id' => 222, 'score' => 2.0]]],
        ],
    ];

    /**
     * Service with collaborators that are never used by the mapping.
     *
     * @return assign_ux_service
     */
    private function service(): assign_ux_service {
        return new assign_ux_service(
            $this->createStub(job_service::class),
            $this->createStub(assign_submission_reader::class),
            null,
            'test-ns'
        );
    }

    /**
     * Assert that a mapping was refused as not fitting the assignment.
     *
     * @param array $mapped Mapping result.
     */
    private function assert_invalid(array $mapped): void {
        $this->assertSame(['error' => get_string('assign_ux_result_invalid', 'local_dixeo')], $mapped);
    }

    public function test_simple_grade_within_range_is_kept(): void {
        $mapped = $this->service()->map_grade_result(self::SIMPLE, [
            'analysis' => ' A ',
            'feedback' => 'B',
            'grade' => '72.5',
            'extra' => 'dropped',
        ]);

        $this->assertSame(['gradingmethod' => 'simple', 'analysis' => 'A', 'feedback' => 'B', 'grade' => 72.5], $mapped);
    }

    public function test_simple_grade_at_both_bounds_is_kept(): void {
        $this->assertSame(0.0, $this->service()->map_grade_result(self::SIMPLE, ['grade' => 0])['grade']);
        $this->assertSame(100.0, $this->service()->map_grade_result(self::SIMPLE, ['grade' => 100])['grade']);
    }

    public function test_simple_grade_above_maximum_is_refused(): void {
        $this->assert_invalid($this->service()->map_grade_result(self::SIMPLE, ['grade' => 150]));
    }

    public function test_simple_negative_grade_is_refused(): void {
        $this->assert_invalid($this->service()->map_grade_result(self::SIMPLE, ['grade' => -1]));
    }

    public function test_simple_non_finite_or_non_numeric_grade_is_refused(): void {
        $service = $this->service();
        $this->assert_invalid($service->map_grade_result(self::SIMPLE, ['grade' => INF]));
        $this->assert_invalid($service->map_grade_result(self::SIMPLE, ['grade' => NAN]));
        $this->assert_invalid($service->map_grade_result(self::SIMPLE, ['grade' => 'eighty']));
        $this->assert_invalid($service->map_grade_result(self::SIMPLE, ['grade' => true]));
        $this->assert_invalid($service->map_grade_result(self::SIMPLE, ['grade' => [50]]));
    }

    public function test_simple_grade_on_a_scale_assignment_is_refused(): void {
        $scale = ['grading_method' => 'simple', 'maxgrade' => -3.0, 'criteria' => []];
        $this->assert_invalid($this->service()->map_grade_result($scale, ['grade' => 2]));

        $nograde = ['grading_method' => 'simple', 'maxgrade' => 0.0, 'criteria' => []];
        $this->assert_invalid($this->service()->map_grade_result($nograde, ['grade' => 0]));
    }

    public function test_unknown_grading_method_is_refused(): void {
        $other = ['grading_method' => 'checklist', 'maxgrade' => 100.0, 'criteria' => []];
        $this->assert_invalid($this->service()->map_grade_result($other, ['criteria' => []]));
    }

    public function test_valid_guide_is_normalised_in_definition_order(): void {
        $mapped = $this->service()->map_grade_result(self::GUIDE, [
            'analysis' => 'A',
            'feedback' => 'B',
            'criteria' => [
                ['id' => '12', 'score' => 5, 'remark' => ' Fine ', 'levelid' => 99],
                ['id' => 11, 'score' => '7.5', 'remark' => 'Good', 'note' => 'dropped'],
            ],
        ]);

        $this->assertSame('guide', $mapped['gradingmethod']);
        $this->assertSame([
            ['id' => 11, 'score' => 7.5, 'remark' => 'Good'],
            ['id' => 12, 'score' => 5.0, 'remark' => 'Fine'],
        ], $mapped['criteria']);
    }

    public function test_guide_score_above_maxscore_is_refused(): void {
        $this->assert_invalid($this->service()->map_grade_result(self::GUIDE, ['criteria' => [
            ['id' => 11, 'score' => 11, 'remark' => ''],
            ['id' => 12, 'score' => 1, 'remark' => ''],
        ]]));
    }

    public function test_guide_negative_or_non_finite_score_is_refused(): void {
        $this->assert_invalid($this->service()->map_grade_result(self::GUIDE, ['criteria' => [
            ['id' => 11, 'score' => -1, 'remark' => ''],
            ['id' => 12, 'score' => 1, 'remark' => ''],
        ]]));
        $this->assert_invalid($this->service()->map_grade_result(self::GUIDE, ['criteria' => [
            ['id' => 11, 'score' => NAN, 'remark' => ''],
            ['id' => 12, 'score' => 1, 'remark' => ''],
        ]]));
    }

    public function test_unknown_criterion_is_refused(): void {
        $this->assert_invalid($this->service()->map_grade_result(self::GUIDE, ['criteria' => [
            ['id' => 11, 'score' => 1, 'remark' => ''],
            ['id' => 99, 'score' => 1, 'remark' => ''],
        ]]));
    }

    public function test_duplicate_criterion_is_refused(): void {
        $this->assert_invalid($this->service()->map_grade_result(self::GUIDE, ['criteria' => [
            ['id' => 11, 'score' => 1, 'remark' => ''],
            ['id' => 11, 'score' => 2, 'remark' => ''],
            ['id' => 12, 'score' => 1, 'remark' => ''],
        ]]));
    }

    public function test_missing_criterion_is_refused(): void {
        $this->assert_invalid($this->service()->map_grade_result(self::GUIDE, ['criteria' => [
            ['id' => 11, 'score' => 1, 'remark' => ''],
        ]]));
        $this->assert_invalid($this->service()->map_grade_result(self::RUBRIC, ['criteria' => [
            ['id' => 21, 'levelid' => 211, 'remark' => ''],
        ]]));
    }

    public function test_definition_without_criteria_is_refused(): void {
        $empty = ['grading_method' => 'guide', 'maxgrade' => 100.0, 'criteria' => []];
        $this->assert_invalid($this->service()->map_grade_result($empty, ['criteria' => []]));
    }

    public function test_missing_criteria_list_reports_a_missing_result(): void {
        $this->assertSame(
            ['error' => get_string('assign_ux_result_missing', 'local_dixeo')],
            $this->service()->map_grade_result(self::GUIDE, ['analysis' => 'A'])
        );
        $this->assertSame(
            ['error' => get_string('assign_ux_result_missing', 'local_dixeo')],
            $this->service()->map_grade_result(self::GUIDE, null)
        );
    }

    public function test_valid_rubric_is_normalised_in_definition_order(): void {
        $mapped = $this->service()->map_grade_result(self::RUBRIC, ['criteria' => [
            ['id' => 22, 'levelid' => '222', 'remark' => 'Two', 'score' => 5],
            ['id' => 21, 'levelid' => 211, 'remark' => ['not text']],
        ]]);

        $this->assertSame([
            ['id' => 21, 'levelid' => 211, 'remark' => ''],
            ['id' => 22, 'levelid' => 222, 'remark' => 'Two'],
        ], $mapped['criteria']);
    }

    public function test_rubric_level_of_another_criterion_is_refused(): void {
        $this->assert_invalid($this->service()->map_grade_result(self::RUBRIC, ['criteria' => [
            ['id' => 21, 'levelid' => 221, 'remark' => ''],
            ['id' => 22, 'levelid' => 222, 'remark' => ''],
        ]]));
    }

    public function test_rubric_missing_or_malformed_level_is_refused(): void {
        $this->assert_invalid($this->service()->map_grade_result(self::RUBRIC, ['criteria' => [
            ['id' => 21, 'remark' => ''],
            ['id' => 22, 'levelid' => 222, 'remark' => ''],
        ]]));
        $this->assert_invalid($this->service()->map_grade_result(self::RUBRIC, ['criteria' => [
            ['id' => 21, 'levelid' => '211.5', 'remark' => ''],
            ['id' => 22, 'levelid' => 222, 'remark' => ''],
        ]]));
    }

    /**
     * The reader exposes the maximum grade and loads every rubric criterion's levels.
     */
    public function test_assignment_context_carries_maxgrade_and_rubric_levels(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id, 'grade' => 40]);
        $context = \context_module::instance($assign->cmid);
        $this->getDataGenerator()->get_plugin_generator('gradingform_rubric')
            ->get_test_rubric($context, 'mod_assign', 'submissions');

        $ctx = (new assign_submission_reader())->get_assignment_context((int) $assign->cmid, (int) $course->id);

        $this->assertSame(40.0, $ctx['maxgrade']);
        $this->assertSame('rubric', $ctx['grading_method']);
        $this->assertCount(2, $ctx['criteria']);
        foreach ($ctx['criteria'] as $criterion) {
            $this->assertSame([0.0, 1.0, 2.0], array_column($criterion['levels'], 'score'));
        }

        $proposal = ['criteria' => array_map(static fn(array $criterion): array => [
            'id' => $criterion['id'],
            'levelid' => $criterion['levels'][2]['id'],
            'remark' => '',
        ], $ctx['criteria'])];
        $this->assertCount(2, $this->service()->map_grade_result($ctx, $proposal)['criteria']);
    }
}
