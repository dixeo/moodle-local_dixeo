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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_dixeo;

use local_dixeo\dto\tutor_message;
use local_dixeo\service\tutor_usage_performance_service;
use local_dixeo\service\tutor_usage_recorder;
use local_dixeo\service\tutor_usage_report_service;

/**
 * Tests for tutor usage Performance section.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_dixeo\service\tutor_usage_performance_service
 */
final class tutor_usage_performance_test extends \advanced_testcase {
    /**
     * Create a course with an assign activity and enrolled students/teacher.
     *
     * @param int $studentcount Number of students.
     * @return array{course: \stdClass, assign: \stdClass, students: \stdClass[], teacher: \stdClass}
     */
    protected function create_course_with_grades(int $studentcount = 2): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $students = [];
        for ($i = 0; $i < $studentcount; $i++) {
            $students[] = $generator->create_and_enrol($course, 'student');
        }
        $assign = $generator->create_module('assign', [
            'course' => $course->id,
            'grade' => 100,
        ]);

        return [
            'course' => $course,
            'assign' => $assign,
            'students' => $students,
            'teacher' => $teacher,
        ];
    }

    /**
     * Set a numeric grade on an assign grade item.
     *
     * @param \stdClass $course Course.
     * @param \stdClass $assign Assign module record.
     * @param int $userid User id.
     * @param float $grade Grade value.
     */
    protected function set_assign_grade(\stdClass $course, \stdClass $assign, int $userid, float $grade): void {
        $item = \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'iteminstance' => $assign->id,
            'courseid' => $course->id,
        ]);
        $this->assertNotFalse($item);
        $item->update_final_grade($userid, $grade, 'test');
    }

    /**
     * Set course total grade directly.
     *
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param float $grade Grade value.
     */
    protected function set_course_total_grade(int $courseid, int $userid, float $grade): void {
        $courseitem = \grade_item::fetch_course_item($courseid);
        $courseitem->update_final_grade($userid, $grade, 'test');
    }

    /**
     * Teachers rolescope and missing grade:viewall hide the section.
     */
    public function test_section_eligibility_gates(): void {
        $this->resetAfterTest();
        $fixture = $this->create_course_with_grades(1);
        $course = $fixture['course'];
        $service = new tutor_usage_performance_service();

        $this->setAdminUser();
        $this->assertTrue($service->is_section_eligible(
            tutor_usage_report_service::LEVEL_COURSE,
            tutor_usage_report_service::ROLE_SCOPE_STUDENTS,
            (int) $course->id
        ));
        $this->assertTrue($service->is_section_eligible(
            tutor_usage_report_service::LEVEL_COURSE,
            tutor_usage_report_service::ROLE_SCOPE_ALL,
            (int) $course->id
        ));
        $this->assertFalse($service->is_section_eligible(
            tutor_usage_report_service::LEVEL_COURSE,
            tutor_usage_report_service::ROLE_SCOPE_TEACHERS,
            (int) $course->id
        ));
        $this->assertFalse($service->is_section_eligible(
            tutor_usage_report_service::LEVEL_SITE,
            tutor_usage_report_service::ROLE_SCOPE_STUDENTS,
            (int) $course->id
        ));

        $student = $fixture['students'][0];
        $this->setUser($student);
        $this->assertFalse($service->is_section_eligible(
            tutor_usage_report_service::LEVEL_COURSE,
            tutor_usage_report_service::ROLE_SCOPE_STUDENTS,
            (int) $course->id
        ));
    }

    /**
     * Scatter omits users without a course total grade; includes zero-message graded users.
     */
    public function test_scatter_omit_rules_and_zero_usage(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->create_course_with_grades(2);
        $course = $fixture['course'];
        $graded = $fixture['students'][0];
        $ungraded = $fixture['students'][1];

        $this->set_course_total_grade((int) $course->id, (int) $graded->id, 80);

        $recorder = new tutor_usage_recorder();
        $recorder->record_message((int) $ungraded->id, (int) $course->id, tutor_message::MODE_NORMAL, 0);

        $service = new tutor_usage_performance_service();
        $payload = $service->build_course_payload((int) $course->id, true);

        $this->assertNotNull($payload);
        $this->assertCount(1, $payload['scatterpoints']);
        $point = $payload['scatterpoints'][0];
        $this->assertSame((int) $graded->id, (int) $point['userid']);
        $this->assertSame(0, (int) $point['messages']);
        $this->assertEqualsWithDelta(80.0, (float) $point['grade'], 0.01);
    }

    /**
     * Activity averages only include students who have a grade for that item.
     */
    public function test_activity_average_only_over_graded_students(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->create_course_with_grades(2);
        $course = $fixture['course'];
        $assign = $fixture['assign'];
        $s1 = $fixture['students'][0];
        $s2 = $fixture['students'][1];

        $this->set_assign_grade($course, $assign, (int) $s1->id, 100);
        $this->set_assign_grade($course, $assign, (int) $s2->id, 50);

        $service = new tutor_usage_performance_service();
        $payload = $service->build_course_payload((int) $course->id, true);
        $this->assertNotNull($payload);

        $assignrow = null;
        foreach ($payload['activities'] as $activity) {
            if (empty($activity['iscoursetotal'])) {
                $assignrow = $activity;
                break;
            }
        }
        $this->assertNotNull($assignrow);
        $this->assertEqualsWithDelta(75.0, (float) $assignrow['average'], 0.01);
    }

    /**
     * Random sample never exceeds the configured cap.
     */
    public function test_random_sample_cap(): void {
        $service = new tutor_usage_performance_service();
        $points = [];
        for ($i = 0; $i < 600; $i++) {
            $points[] = ['userid' => $i + 1, 'messages' => $i, 'grade' => 50.0];
        }
        $sampled = $service->random_sample($points, tutor_usage_performance_service::SCATTER_POINT_CAP);
        $this->assertCount(tutor_usage_performance_service::SCATTER_POINT_CAP, $sampled);
    }

    /**
     * User-level context highlights the current user and shows user vs average.
     */
    public function test_user_level_highlight_and_uservsaverage(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->create_course_with_grades(2);
        $course = $fixture['course'];
        $assign = $fixture['assign'];
        $s1 = $fixture['students'][0];
        $s2 = $fixture['students'][1];

        $this->set_course_total_grade((int) $course->id, (int) $s1->id, 90);
        $this->set_course_total_grade((int) $course->id, (int) $s2->id, 70);
        $this->set_assign_grade($course, $assign, (int) $s1->id, 90);
        $this->set_assign_grade($course, $assign, (int) $s2->id, 70);

        $recorder = new tutor_usage_recorder();
        $recorder->record_message((int) $s1->id, (int) $course->id, tutor_message::MODE_NORMAL, 0);
        $recorder->record_message((int) $s1->id, (int) $course->id, tutor_message::MODE_GUIDE, 0);

        $service = new tutor_usage_performance_service();
        $context = $service->get_section_context(
            tutor_usage_report_service::LEVEL_USER,
            (int) $course->id,
            (int) $s1->id,
            tutor_usage_report_service::ROLE_SCOPE_STUDENTS
        );

        $this->assertNotNull($context);
        $this->assertTrue($context['isuserlevel']);
        $this->assertTrue($context['hasscatter']);

        $scatter = json_decode($context['scatterdata'], true);
        $this->assertIsArray($scatter);
        $highlighted = array_filter($scatter['points'], static fn(array $p): bool => !empty($p['iscurrent']));
        $this->assertCount(1, $highlighted);
        $current = reset($highlighted);
        $this->assertSame(2, (int) $current['x']);
        $this->assertSame(fullname($s1), $current['name']);

        $this->assertNotEmpty($context['activities']);
        foreach ($context['activities'] as $activity) {
            $this->assertArrayHasKey('uservsaverage', $activity);
            $this->assertStringContainsString('/', $activity['uservsaverage']);
        }
    }

    /**
     * Teachers rolescope hides section context even for admins.
     */
    public function test_teachers_scope_hides_section_context(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->create_course_with_grades(1);
        $service = new tutor_usage_performance_service();

        $this->assertNull($service->get_section_context(
            tutor_usage_report_service::LEVEL_COURSE,
            (int) $fixture['course']->id,
            0,
            tutor_usage_report_service::ROLE_SCOPE_TEACHERS
        ));
    }

    /**
     * Letter/text-style absence of percentage returns null from grade_to_percentage.
     */
    public function test_null_finalgrade_is_not_percentage(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->create_course_with_grades(1);
        $courseitem = \grade_item::fetch_course_item((int) $fixture['course']->id);
        $grade = new \grade_grade();
        $grade->userid = (int) $fixture['students'][0]->id;
        $grade->itemid = $courseitem->id;
        $grade->finalgrade = null;

        $service = new tutor_usage_performance_service();
        $this->assertNull($service->grade_to_percentage($courseitem, $grade, true));
    }
}
