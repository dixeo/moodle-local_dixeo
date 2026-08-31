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
use local_dixeo\output\tutor_usage_report_page;
use local_dixeo\output\tutor_usage_report_request;
use local_dixeo\service\tutor_usage_aggregator;
use local_dixeo\service\tutor_usage_recorder;
use local_dixeo\service\tutor_usage_report_service;

/**
 * Tests for tutor usage recorder, aggregator, and report access.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_dixeo\service\tutor_usage_recorder
 * @covers \local_dixeo\service\tutor_usage_aggregator
 * @covers \local_dixeo\service\tutor_usage_report_service
 * @covers \local_dixeo\output\tutor_usage_report_request
 * @covers \local_dixeo\output\tutor_usage_report_page
 */
final class tutor_usage_report_test extends \advanced_testcase {
    /**
     * Recorder persists message events with sanitized mode and cmid.
     */
    public function test_recorder_writes_message_event(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $recorder = new tutor_usage_recorder();
        $id = $recorder->record_message(
            (int) $user->id,
            (int) $course->id,
            tutor_message::MODE_GUIDE,
            0
        );

        $this->assertGreaterThan(0, $id);
        $row = $DB->get_record(tutor_usage_recorder::TABLE_EVENT, ['id' => $id], '*', MUST_EXIST);
        $this->assertSame(tutor_usage_recorder::EVENT_MESSAGE, $row->eventtype);
        $this->assertSame(tutor_message::MODE_GUIDE, $row->mode);
        $this->assertEquals((int) $user->id, (int) $row->userid);
        $this->assertEquals((int) $course->id, (int) $row->courseid);
    }

    /**
     * Invalid cmid for the course is coerced to 0.
     */
    public function test_sanitize_cmid_rejects_foreign_module(): void {
        $this->resetAfterTest();
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('page', ['course' => $course1->id]);

        $this->assertSame(0, tutor_usage_recorder::sanitize_cmid((int) $course2->id, (int) $cm->cmid));
        $this->assertSame((int) $cm->cmid, tutor_usage_recorder::sanitize_cmid((int) $course1->id, (int) $cm->cmid));
    }

    /**
     * The base duration is credited on top of the message span of every session.
     */
    public function test_session_duration_adds_base_duration(): void {
        $this->assertSame(
            tutor_usage_aggregator::SESSION_BASE_DURATION,
            tutor_usage_aggregator::calculate_duration(1000, 1000)
        );
        $this->assertSame(
            600 + tutor_usage_aggregator::SESSION_BASE_DURATION,
            tutor_usage_aggregator::calculate_duration(1000, 1600)
        );
    }

    /**
     * Durations are rendered in seconds, minutes, hours, or days without unit overflow.
     */
    public function test_format_duration_units(): void {
        $this->assertSame('45s', tutor_usage_report_service::format_duration(45));
        $this->assertSame('15 min', tutor_usage_report_service::format_duration(15 * MINSECS));
        $this->assertSame('2h 15m', tutor_usage_report_service::format_duration(2 * HOURSECS + 15 * MINSECS));
        $this->assertSame('1h 59m', tutor_usage_report_service::format_duration(2 * HOURSECS - 30));
        $this->assertSame('1d 0h 0m', tutor_usage_report_service::format_duration(DAYSECS));
        $this->assertSame(
            '3d 4h 5m',
            tutor_usage_report_service::format_duration(3 * DAYSECS + 4 * HOURSECS + 5 * MINSECS)
        );
    }

    /**
     * Idle gap above the session timeout closes a session; aggregation is idempotent.
     */
    public function test_aggregator_closes_timed_out_sessions_idempotently(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $recorder = new tutor_usage_recorder();
        $aggregator = new tutor_usage_aggregator();

        $daystart = $aggregator->get_day_start(time() - DAYSECS);
        $t1 = $daystart + HOURSECS;
        $t2 = $t1 + 600;
        $t3 = $t2 + tutor_usage_aggregator::SESSION_TIMEOUT + 10;

        $recorder->record_message((int) $user->id, (int) $course->id, tutor_message::MODE_NORMAL, 0, $t1);
        $recorder->record_message((int) $user->id, (int) $course->id, tutor_message::MODE_NORMAL, 0, $t2);
        $recorder->record_message((int) $user->id, (int) $course->id, tutor_message::MODE_QUIZ, 0, $t3);

        $first = $aggregator->aggregate_day($daystart, $t3 + tutor_usage_aggregator::SESSION_TIMEOUT + 5);
        $this->assertGreaterThan(0, $first['dailyrows']);
        $sessionsafterfirst = $DB->count_records(tutor_usage_aggregator::TABLE_SESSION);

        $second = $aggregator->aggregate_day($daystart, $t3 + tutor_usage_aggregator::SESSION_TIMEOUT + 5);
        $this->assertSame($first['dailyrows'], $second['dailyrows']);
        $this->assertSame(
            $sessionsafterfirst,
            $DB->count_records(tutor_usage_aggregator::TABLE_SESSION)
        );

        $sessions = $DB->get_records(tutor_usage_aggregator::TABLE_SESSION, [
            'userid' => $user->id,
            'courseid' => $course->id,
        ], 'timestart ASC');
        $this->assertCount(2, $sessions);
        $firstsession = reset($sessions);
        $this->assertSame(2, (int) $firstsession->messagecount);
        $this->assertSame(600 + tutor_usage_aggregator::SESSION_BASE_DURATION, (int) $firstsession->duration);
    }

    /**
     * Pending aggregation catches up missed days and advances the watermark.
     */
    public function test_aggregate_pending_days_catches_up_from_watermark(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $recorder = new tutor_usage_recorder();
        $aggregator = new tutor_usage_aggregator();

        $now = time();
        $yesterday = $aggregator->get_previous_day_start($now);
        $twodaysago = $aggregator->get_previous_day_start($yesterday);
        $threedaysago = $aggregator->get_previous_day_start($twodaysago);

        $recorder->record_message(
            (int) $user->id,
            (int) $course->id,
            tutor_message::MODE_NORMAL,
            0,
            $threedaysago + HOURSECS
        );
        $recorder->record_message(
            (int) $user->id,
            (int) $course->id,
            tutor_message::MODE_NORMAL,
            0,
            $twodaysago + HOURSECS
        );
        $recorder->record_message(
            (int) $user->id,
            (int) $course->id,
            tutor_message::MODE_NORMAL,
            0,
            $yesterday + HOURSECS
        );

        // Pretend we last completed three days ago, then missed two nights.
        $aggregator->set_last_aggregated_day($threedaysago);
        $DB->delete_records(tutor_usage_aggregator::TABLE_DAILY);

        $result = $aggregator->aggregate_pending_days($now);
        $this->assertSame($twodaysago, $result['from']);
        $this->assertSame($yesterday, $result['to']);
        $this->assertCount(2, $result['days']);
        $this->assertSame($yesterday, $aggregator->get_last_aggregated_day());

        $daystarts = array_column($result['days'], 'daystart');
        $this->assertSame([$twodaysago, $yesterday], $daystarts);
        $this->assertSame(1, $DB->count_records(tutor_usage_aggregator::TABLE_DAILY, [
            'daystart' => $twodaysago,
        ]));
        $this->assertSame(1, $DB->count_records(tutor_usage_aggregator::TABLE_DAILY, [
            'daystart' => $yesterday,
        ]));

        // Second run finds nothing pending.
        $again = $aggregator->aggregate_pending_days($now);
        $this->assertSame([], $again['days']);
        $this->assertSame($yesterday, $aggregator->get_last_aggregated_day());
    }

    /**
     * Custom ranges longer than six months are rejected.
     */
    public function test_resolve_period_rejects_long_custom_range(): void {
        $this->resetAfterTest();
        $service = new tutor_usage_report_service();
        $from = make_timestamp(2026, 1, 1, 0, 0, 0);
        $to = make_timestamp(2026, 8, 1, 23, 59, 59);

        $this->expectException(\moodle_exception::class);
        $service->resolve_period(
            tutor_usage_report_service::VIEW_CUSTOM,
            null,
            $from,
            $to
        );
    }

    /**
     * Role scopes resolve to student/teacher archetype role ids or no filter.
     */
    public function test_role_scope_resolves_archetype_roles(): void {
        $this->resetAfterTest();

        $studentids = tutor_usage_report_service::get_roleids_for_scope(
            tutor_usage_report_service::ROLE_SCOPE_STUDENTS
        );
        $this->assertNotEmpty($studentids);
        $this->assertSame(
            tutor_usage_report_service::get_default_student_roleids(),
            $studentids
        );

        $teacherids = tutor_usage_report_service::get_roleids_for_scope(
            tutor_usage_report_service::ROLE_SCOPE_TEACHERS
        );
        $this->assertNotEmpty($teacherids);
        $this->assertSame(
            tutor_usage_report_service::get_teacher_roleids(),
            $teacherids
        );

        $this->assertSame(
            [],
            tutor_usage_report_service::get_roleids_for_scope(tutor_usage_report_service::ROLE_SCOPE_ALL)
        );

        global $DB;
        foreach ($studentids as $roleid) {
            $role = $DB->get_record('role', ['id' => $roleid], '*', MUST_EXIST);
            $this->assertSame('student', $role->archetype);
        }
        foreach ($teacherids as $roleid) {
            $role = $DB->get_record('role', ['id' => $roleid], '*', MUST_EXIST);
            $this->assertContains($role->archetype, ['teacher', 'editingteacher']);
        }

        $request = tutor_usage_report_request::from_renderable_params([
            'rolescope' => tutor_usage_report_service::ROLE_SCOPE_STUDENTS,
        ]);
        $this->assertSame($studentids, $request->resolved_roleids());

        // Requests without an explicit scope, and invalid values, fall back to students.
        $this->assertSame(
            tutor_usage_report_service::ROLE_SCOPE_STUDENTS,
            tutor_usage_report_service::ROLE_SCOPE_DEFAULT
        );
        $this->assertSame(
            tutor_usage_report_service::ROLE_SCOPE_STUDENTS,
            tutor_usage_report_request::from_renderable_params([])->rolescope
        );
        $this->assertSame(
            tutor_usage_report_service::ROLE_SCOPE_STUDENTS,
            tutor_usage_report_service::normalize_role_scope('bogus')
        );
    }

    /**
     * The role scope selector links switch scope while keeping the active period.
     */
    public function test_role_scope_selector_links(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/local/dixeo/tutor_usage_report.php');

        $page = new tutor_usage_report_page([
            'level' => tutor_usage_report_service::LEVEL_SITE,
            'view' => tutor_usage_report_service::VIEW_WEEK,
            'anchor' => '2026-07-14',
            'rolescope' => tutor_usage_report_service::ROLE_SCOPE_TEACHERS,
        ]);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $scopes = [];
        foreach ($data['rolescopes'] as $scope) {
            $scopes[$scope['id']] = $scope;
            $this->assertStringContainsString('anchor=2026-07-14', $scope['url']);
        }

        $this->assertTrue($scopes[tutor_usage_report_service::ROLE_SCOPE_TEACHERS]['active']);
        $this->assertFalse($scopes[tutor_usage_report_service::ROLE_SCOPE_STUDENTS]['active']);

        // Students is the default scope, so only the other scopes carry the param.
        $this->assertStringNotContainsString(
            'rolescope',
            $scopes[tutor_usage_report_service::ROLE_SCOPE_STUDENTS]['url']
        );
        $this->assertStringContainsString(
            'rolescope=all',
            $scopes[tutor_usage_report_service::ROLE_SCOPE_ALL]['url']
        );
    }

    /**
     * Course-level access is denied without the course capability (IDOR guard).
     */
    public function test_course_access_requires_capability(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        // Strip the default editingteacher allow if present by assigning a role without the cap.
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->assertFalse(tutor_usage_report_service::can_view_course((int) $course->id));

        $request = tutor_usage_report_request::from_renderable_params([
            'level' => tutor_usage_report_service::LEVEL_COURSE,
            'courseid' => (int) $course->id,
        ]);
        $this->expectException(\required_capability_exception::class);
        $request->require_access();
    }

    /**
     * Site capability grants access to all courses; course capability is scoped.
     */
    public function test_site_cap_vs_course_cap_access(): void {
        $this->resetAfterTest();

        $coursea = $this->getDataGenerator()->create_course();
        $courseb = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $coursea->id, 'editingteacher');
        $this->setUser($teacher);

        $this->assertTrue(tutor_usage_report_service::can_view_course((int) $coursea->id));
        $accessible = tutor_usage_report_service::get_accessible_courseids();
        $this->assertContains((int) $coursea->id, $accessible);
        $this->assertNotContains((int) $courseb->id, $accessible);

        $admin = get_admin();
        $this->setUser($admin);
        $this->assertTrue(tutor_usage_report_service::can_view_site());
        $this->assertTrue(tutor_usage_report_service::can_view_course((int) $courseb->id));
    }

    /**
     * KPI message totals respect the selected period and role filter cohort.
     */
    public function test_kpis_count_messages_in_period(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setAdminUser();

        $recorder = new tutor_usage_recorder();
        $now = time();
        $recorder->record_message((int) $student->id, (int) $course->id, tutor_message::MODE_NORMAL, 0, $now - 60);
        $recorder->record_message((int) $student->id, (int) $course->id, tutor_message::MODE_GUIDE, 0, $now - 30);
        $recorder->record_message((int) $teacher->id, (int) $course->id, tutor_message::MODE_NORMAL, 0, $now - 10);

        $service = new tutor_usage_report_service();
        $roleids = tutor_usage_report_service::get_default_student_roleids();
        $kpis = $service->get_kpis(
            tutor_usage_report_service::LEVEL_COURSE,
            (int) $course->id,
            0,
            $now - DAYSECS,
            $now + HOURSECS,
            $roleids
        );

        $this->assertSame(2, (int) $kpis['messages']['raw']);
        $guide = null;
        foreach ($kpis['modes'] as $mode) {
            if ($mode['mode'] === tutor_message::MODE_GUIDE) {
                $guide = $mode;
                break;
            }
        }
        $this->assertNotNull($guide);
        $this->assertSame(1, (int) $guide['raw']);
        $this->assertTrue(!empty($guide['hastooltip']));
        $this->assertStringContainsString('1.0', $guide['tooltip']);
        $this->assertNotEmpty($kpis['messages']['tooltip']);
        $this->assertSame(1, (int) $kpis['active']['raw']);
        $this->assertGreaterThanOrEqual(1, (int) $kpis['total']['raw']);

        $userkpis = $service->get_kpis(
            tutor_usage_report_service::LEVEL_USER,
            (int) $course->id,
            (int) $student->id,
            $now - DAYSECS,
            $now + HOURSECS,
            $roleids
        );
        $this->assertSame(1, (int) $userkpis['engagement']['raw']);
        $this->assertStringContainsString('1', $userkpis['engagement']['value']);
        $this->assertStringContainsString('Last active:', $userkpis['engagement']['tooltip']);
        $this->assertTrue($userkpis['engagement']['hastooltip']);

        // Median/average repeat the value for a single user, so those tooltips are hidden.
        foreach (['messages', 'sessions', 'duration'] as $kpi) {
            $this->assertTrue($kpis[$kpi]['hastooltip'], "{$kpi} tooltip expected at course level");
            $this->assertFalse($userkpis[$kpi]['hastooltip'], "{$kpi} tooltip expected to be hidden");
            $this->assertArrayNotHasKey('tooltip', $userkpis[$kpi]);
        }
        // Quiz is the only mode whose tooltip reports something other than the card value.
        foreach ($userkpis['modes'] as $mode) {
            $this->assertSame($mode['mode'] === tutor_message::MODE_QUIZ, $mode['hastooltip']);
            $this->assertNotEmpty($mode['info']);
        }
        foreach ($kpis['modes'] as $mode) {
            $this->assertSame($mode['mode'] !== tutor_message::MODE_TEACH, $mode['hastooltip']);
        }

        // Every KPI carries the description shown by its info icon.
        foreach (['adoption', 'engagement', 'messages', 'sessions', 'duration'] as $kpi) {
            $this->assertNotEmpty($kpis[$kpi]['info'], "{$kpi} description missing");
            $this->assertStringNotContainsString('[[', $kpis[$kpi]['info']);
        }
        $this->assertNotSame($kpis['engagement']['info'], $userkpis['engagement']['info']);

        // Above user level, engagement is active days averaged over active users.
        $this->assertSame(1.0, (float) $kpis['engagement']['raw']);
        $this->assertStringContainsString('1.0', $kpis['engagement']['value']);
        $this->assertStringContainsString('Median 1.0 / Average 1.0', $kpis['engagement']['tooltip']);
    }

    /**
     * Summary rows can be sorted by any column, in either direction.
     */
    public function test_summary_rows_sorting_by_column(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $first = $this->getDataGenerator()->create_and_enrol($course, 'student', [
            'firstname' => 'Anna',
            'lastname' => 'Alpha',
        ]);
        $second = $this->getDataGenerator()->create_and_enrol($course, 'student', [
            'firstname' => 'Bob',
            'lastname' => 'Beta',
        ]);
        $third = $this->getDataGenerator()->create_and_enrol($course, 'student', [
            'firstname' => 'Cara',
            'lastname' => 'Gamma',
        ]);

        $recorder = new tutor_usage_recorder();
        $now = time();
        $messagecounts = [
            (int) $first->id => 1,
            (int) $second->id => 5,
            (int) $third->id => 3,
        ];
        foreach ($messagecounts as $userid => $count) {
            for ($i = 0; $i < $count; $i++) {
                $recorder->record_message(
                    $userid,
                    (int) $course->id,
                    tutor_message::MODE_NORMAL,
                    0,
                    $now - HOURSECS + $i
                );
            }
        }

        $roleids = tutor_usage_report_service::get_default_student_roleids();
        $service = new tutor_usage_report_service();
        $sortedids = function (
            string $sort = tutor_usage_report_service::SORT_DEFAULT,
            string $dir = ''
        ) use (
            $service,
            $course,
            $roleids,
            $now
        ): array {
            $result = $service->get_rows(
                tutor_usage_report_service::LEVEL_COURSE,
                (int) $course->id,
                0,
                $now - DAYSECS,
                $now + HOURSECS,
                $roleids,
                0,
                100,
                [],
                $sort,
                $dir
            );
            return array_map('intval', array_column($result['rows'], 'key'));
        };

        // Messages descending is the default, and metric columns start descending.
        $bymessagesdesc = [(int) $second->id, (int) $third->id, (int) $first->id];
        $this->assertSame($bymessagesdesc, $sortedids());
        $this->assertSame($bymessagesdesc, $sortedids('messages'));
        $this->assertSame(array_reverse($bymessagesdesc), $sortedids('messages', 'asc'));

        // Text columns start ascending.
        $byname = [(int) $first->id, (int) $second->id, (int) $third->id];
        $this->assertSame($byname, $sortedids('name'));
        $this->assertSame(array_reverse($byname), $sortedids('name', 'desc'));

        // Unknown columns, unknown directions, and columns absent at this level fall back to the default.
        $this->assertSame($bymessagesdesc, $sortedids('bogus'));
        $this->assertSame($bymessagesdesc, $sortedids('messages', 'sideways'));
        $this->assertSame($bymessagesdesc, $sortedids('moduletype'));

        $this->assertSame(
            tutor_usage_report_service::SORT_DEFAULT,
            tutor_usage_report_service::normalize_sort('sessions', tutor_usage_report_service::LEVEL_USER)
        );
        $this->assertSame(
            'sessions',
            tutor_usage_report_service::normalize_sort('sessions', tutor_usage_report_service::LEVEL_COURSE)
        );
    }

    /**
     * Table headers expose sort links that flip the active column and keep the active period.
     */
    public function test_summary_table_column_sort_links(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/local/dixeo/tutor_usage_report.php');

        $params = [
            'level' => tutor_usage_report_service::LEVEL_SITE,
            'view' => tutor_usage_report_service::VIEW_WEEK,
            'anchor' => '2026-07-14',
        ];
        $page = new tutor_usage_report_page($params);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $columns = [];
        foreach ($data['columns'] as $column) {
            $columns[$column['key']] = $column;
            $this->assertStringContainsString('anchor=2026-07-14', $column['url']);
        }
        $this->assertSame(
            tutor_usage_report_service::table_column_keys(tutor_usage_report_service::LEVEL_SITE),
            array_keys($columns)
        );

        // Messages is sorted descending by default, so its link reverses the direction.
        $this->assertTrue($columns['messages']['active']);
        $this->assertTrue($columns['messages']['descending']);
        $this->assertSame('descending', $columns['messages']['ariasort']);
        $this->assertStringContainsString('sortdir=asc', $columns['messages']['url']);

        // Other columns link to their own natural direction.
        $this->assertFalse($columns['name']['active']);
        $this->assertSame('none', $columns['name']['ariasort']);
        $this->assertStringContainsString('sort=name', $columns['name']['url']);
        $this->assertStringContainsString('sortdir=asc', $columns['name']['url']);
        $this->assertStringContainsString('sortdir=desc', $columns['sessions']['url']);

        // A non-default sort rides along with the other report links.
        $sorted = new tutor_usage_report_page($params + [
            'sort' => 'sessions',
            'sortdir' => tutor_usage_report_service::SORT_ASC,
        ]);
        $sorteddata = $sorted->export_for_template($PAGE->get_renderer('core'));
        $this->assertStringContainsString('sort=sessions', $sorteddata['period']['prevurl']);
        $this->assertStringContainsString('sortdir=asc', $sorteddata['period']['prevurl']);
        $this->assertStringContainsString('sort=sessions', $sorteddata['rolescopes'][0]['url']);
        foreach ($sorteddata['columns'] as $column) {
            if ($column['key'] === 'sessions') {
                $this->assertTrue($column['ascending']);
                $this->assertSame('ascending', $column['ariasort']);
                $this->assertStringContainsString('sortdir=desc', $column['url']);
            }
        }
    }

    /**
     * Summary rows expose engagement per level and total session duration in the sessions tooltip.
     */
    public function test_summary_rows_report_engagement_and_total_duration(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $frequent = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $occasional = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $recorder = new tutor_usage_recorder();
        $aggregator = new tutor_usage_aggregator();

        $dayonestart = $aggregator->get_day_start(time() - (2 * DAYSECS));
        $daytwostart = $aggregator->get_day_start(time() - DAYSECS);
        $dayone = $dayonestart + HOURSECS;
        $daytwo = $daytwostart + HOURSECS;

        // The frequent learner is active on two days, the occasional one on a single day.
        $recorder->record_message((int) $frequent->id, (int) $course->id, tutor_message::MODE_NORMAL, 0, $dayone);
        $recorder->record_message((int) $frequent->id, (int) $course->id, tutor_message::MODE_NORMAL, 0, $dayone + 600);
        $recorder->record_message((int) $frequent->id, (int) $course->id, tutor_message::MODE_NORMAL, 0, $daytwo);
        $recorder->record_message((int) $occasional->id, (int) $course->id, tutor_message::MODE_NORMAL, 0, $daytwo);

        $timeend = $daytwostart + DAYSECS;
        $aggregator->close_timed_out_open_sessions($timeend);

        $roleids = tutor_usage_report_service::get_default_student_roleids();
        $service = new tutor_usage_report_service();

        $siterows = $service->get_rows(
            tutor_usage_report_service::LEVEL_SITE,
            0,
            0,
            $dayonestart,
            $timeend,
            $roleids,
            0,
            100
        );
        $siterow = null;
        foreach ($siterows['rows'] as $row) {
            if ((int) $row['key'] === (int) $course->id) {
                $siterow = $row;
            }
        }
        $this->assertNotNull($siterow);

        // Three active days over two active users.
        $this->assertSame(1.5, (float) $siterow['engagement']);
        $this->assertStringContainsString('1.5', $siterow['engagementformatted']);
        // Three sessions of 600 + 300, 300, and 300 seconds.
        $this->assertSame(3, (int) $siterow['sessions']);
        $this->assertStringContainsString('25 min', $siterow['sessionstooltip']);

        $courserows = $service->get_rows(
            tutor_usage_report_service::LEVEL_COURSE,
            (int) $course->id,
            0,
            $dayonestart,
            $timeend,
            $roleids,
            0,
            100
        );
        $byuser = [];
        foreach ($courserows['rows'] as $row) {
            $byuser[(int) $row['key']] = $row;
        }

        // A user row counts that user's own active days, with no decimals.
        $this->assertSame(2.0, (float) $byuser[(int) $frequent->id]['engagement']);
        $this->assertStringContainsString('2', $byuser[(int) $frequent->id]['engagementformatted']);
        $this->assertStringNotContainsString('.', $byuser[(int) $frequent->id]['engagementformatted']);
        $this->assertSame(1.0, (float) $byuser[(int) $occasional->id]['engagement']);
        // Two sessions of 900 and 300 seconds: 10 minutes on average, 20 in total.
        $this->assertStringContainsString('10 min', $byuser[(int) $frequent->id]['sessionstooltip']);
        $this->assertStringContainsString('20 min', $byuser[(int) $frequent->id]['sessionstooltip']);
    }

    /**
     * Summary tables include zero-usage courses, participants, and activities with navigation.
     */
    public function test_summary_rows_include_zero_usage_entities(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $inactive = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $roleids = tutor_usage_report_service::get_default_student_roleids();
        $service = new tutor_usage_report_service();
        $now = time();

        // Site: both courses appear with zeros and course drill-down URLs.
        $siterows = $service->get_rows(
            tutor_usage_report_service::LEVEL_SITE,
            0,
            0,
            $now - DAYSECS,
            $now + HOURSECS,
            $roleids,
            0,
            100
        );
        $sitecourseids = array_column($siterows['rows'], 'key');
        $this->assertContains((int) $course->id, $sitecourseids);
        $this->assertContains((int) $othercourse->id, $sitecourseids);
        foreach ($siterows['rows'] as $row) {
            if ((int) $row['key'] === (int) $othercourse->id) {
                $this->assertSame(0, (int) $row['messages']);
                $this->assertSame('0.0%', $row['adoptionformatted']);
                $this->assertNotEmpty($row['url']);
                $this->assertStringContainsString('level=course', $row['url']);
            }
        }

        // Course: inactive participant listed with zeros and user drill-down URL.
        $courserows = $service->get_rows(
            tutor_usage_report_service::LEVEL_COURSE,
            (int) $course->id,
            0,
            $now - DAYSECS,
            $now + HOURSECS,
            $roleids,
            0,
            100
        );
        $userids = array_column($courserows['rows'], 'key');
        $this->assertContains((int) $student->id, $userids);
        $this->assertContains((int) $inactive->id, $userids);
        foreach ($courserows['rows'] as $row) {
            if ((int) $row['key'] === (int) $inactive->id) {
                $this->assertSame(0, (int) $row['messages']);
                $this->assertNotEmpty($row['url']);
                $this->assertStringContainsString('level=user', $row['url']);
            }
        }

        // User: course page + non-excluded activity appear with zeros; excluded zero-usage hidden.
        $userrows = $service->get_rows(
            tutor_usage_report_service::LEVEL_USER,
            (int) $course->id,
            (int) $student->id,
            $now - DAYSECS,
            $now + HOURSECS,
            $roleids,
            0,
            100
        );
        $cmids = array_column($userrows['rows'], 'key');
        $this->assertContains(0, $cmids);
        $this->assertContains((int) $page->cmid, $cmids);
        $this->assertNotContains((int) $quiz->cmid, $cmids);
        foreach ($userrows['rows'] as $row) {
            $this->assertSame(0, (int) $row['messages']);
            $this->assertNotEmpty($row['url']);
        }

        // Excluded module with messages still appears.
        $recorder = new tutor_usage_recorder();
        $recorder->record_message(
            (int) $student->id,
            (int) $course->id,
            tutor_message::MODE_NORMAL,
            (int) $quiz->cmid,
            $now - 60
        );
        $userrowswithquiz = $service->get_rows(
            tutor_usage_report_service::LEVEL_USER,
            (int) $course->id,
            (int) $student->id,
            $now - DAYSECS,
            $now + HOURSECS,
            $roleids,
            0,
            100
        );
        $this->assertContains((int) $quiz->cmid, array_column($userrowswithquiz['rows'], 'key'));

        $recorder->record_message((int) $student->id, (int) $course->id, tutor_message::MODE_NORMAL, 0, $now - 30);
        $siterows = $service->get_rows(
            tutor_usage_report_service::LEVEL_SITE,
            0,
            0,
            $now - DAYSECS,
            $now + HOURSECS,
            $roleids,
            0,
            100
        );
        foreach ($siterows['rows'] as $row) {
            if ((int) $row['key'] === (int) $course->id) {
                // One active of two enrolled students => 50%.
                $this->assertSame('50.0%', $row['adoptionformatted']);
                break;
            }
        }
    }
}
