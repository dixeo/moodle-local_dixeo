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
use local_dixeo\repository\tutor_usage_rollup_repository;
use local_dixeo\service\tutor_usage_aggregator;
use local_dixeo\service\tutor_usage_recorder;
use local_dixeo\service\tutor_usage_report_service;
use local_dixeo\util\site_calendar;

/**
 * Query budget of the tutor usage report page.
 *
 * The number of queries that builds a report page must stay within the budget and must not
 * grow with the number of courses, enrolled users or built days.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_dixeo\service\tutor_usage_report_service
 * @covers \local_dixeo\service\tutor_usage_dataset
 * @covers \local_dixeo\service\tutor_usage_scope
 * @covers \local_dixeo\output\tutor_usage_report_page
 */
final class tutor_usage_report_perf_test extends \advanced_testcase {
    /** @var int Largest number of queries that builds one report page. */
    private const QUERY_BUDGET = 30;

    /**
     * Reset the request memo before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        tutor_usage_report_service::reset_caches();
    }

    /**
     * Reset the request memo after each test.
     */
    public function tearDown(): void {
        tutor_usage_report_service::reset_caches();
        parent::tearDown();
    }

    /**
     * Enrol new users as students with bulk inserts.
     *
     * @param int $courseid Course id.
     * @param int $count Number of users.
     * @return int[] User ids.
     */
    private function bulk_enrol(int $courseid, int $count): array {
        global $DB, $CFG;

        static $sequence = 0;
        $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual'], '*', MUST_EXIST);
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $contextid = (int) \context_course::instance($courseid)->id;
        $now = time();

        $users = [];
        for ($i = 0; $i < $count; $i++) {
            $sequence++;
            $users[] = [
                'auth' => 'manual',
                'confirmed' => 1,
                'mnethostid' => $CFG->mnet_localhost_id,
                'username' => 'bulkuser' . $sequence,
                'firstname' => 'Bulk',
                'lastname' => 'User ' . $sequence,
                'email' => 'bulkuser' . $sequence . '@example.com',
                'timecreated' => $now,
                'timemodified' => $now,
            ];
        }
        $DB->insert_records('user', $users);
        $userids = array_map('intval', $DB->get_fieldset_select(
            'user',
            'id',
            'username ' . $DB->get_in_or_equal(array_column($users, 'username'))[0],
            $DB->get_in_or_equal(array_column($users, 'username'))[1]
        ));

        $enrolments = [];
        $assignments = [];
        foreach ($userids as $userid) {
            $enrolments[] = [
                'enrolid' => (int) $instance->id,
                'userid' => $userid,
                'status' => ENROL_USER_ACTIVE,
                'timestart' => 0,
                'timeend' => 0,
                'modifierid' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $assignments[] = [
                'roleid' => $roleid,
                'contextid' => $contextid,
                'userid' => $userid,
                'timemodified' => $now,
            ];
        }
        $DB->insert_records('user_enrolments', $enrolments);
        $DB->insert_records('role_assignments', $assignments);
        return $userids;
    }

    /**
     * Insert message events for users on every day of a range.
     *
     * @param int $courseid Course id.
     * @param int[] $userids User ids.
     * @param int[] $days Site day starts.
     */
    private function insert_history(int $courseid, array $userids, array $days): void {
        global $DB;

        $events = [];
        foreach ($days as $index => $daystart) {
            foreach ($userids as $position => $userid) {
                if (($index + $position) % 3 !== 0) {
                    continue;
                }
                $events[] = [
                    'userid' => $userid,
                    'courseid' => $courseid,
                    'cmid' => 0,
                    'mode' => $position % 2 ? tutor_message::MODE_GUIDE : tutor_message::MODE_NORMAL,
                    'eventtype' => tutor_usage_recorder::EVENT_MESSAGE,
                    'timecreated' => $daystart + HOURSECS * (1 + ($position % 20)),
                ];
            }
        }
        $DB->insert_records(tutor_usage_recorder::TABLE_EVENT, $events);
    }

    /**
     * Build every past day of a range.
     *
     * @param int[] $days Site day starts.
     */
    private function build_days(array $days): void {
        $repository = new tutor_usage_rollup_repository();
        (new tutor_usage_aggregator($repository))->ensure_rollup_timezone();
        $today = site_calendar::day_start(time());
        foreach ($days as $daystart) {
            if ($daystart < $today) {
                $repository->build_day($daystart);
            }
        }
    }

    /**
     * Queries run to build the report page data, measured after a warm-up build.
     *
     * @param array $params Report page parameters.
     * @return int
     */
    private function page_queries(array $params): int {
        global $DB, $PAGE;

        $renderer = $PAGE->get_renderer('core');
        $warmup = (new tutor_usage_report_page($params))->export_for_template($renderer);
        $this->assertNull($warmup['error']);

        $before = $DB->perf_get_queries();
        $data = (new tutor_usage_report_page($params))->export_for_template($renderer);
        $count = $DB->perf_get_queries() - $before;
        $this->assertNull($data['error']);
        return $count;
    }

    /**
     * Site with history over six months, all past days built.
     *
     * @return array{course: \stdClass, users: int[], days: int[]}
     */
    private function create_site(): array {
        global $PAGE;

        $this->setAdminUser();
        $PAGE->set_url('/local/dixeo/tutor_usage_report.php');
        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $users = $this->bulk_enrol((int) $course->id, 60);
        $otherusers = $this->bulk_enrol((int) $othercourse->id, 20);
        $today = site_calendar::day_start(time());
        $days = site_calendar::days($today - 190 * DAYSECS, $today);
        $this->insert_history((int) $course->id, $users, $days);
        $this->insert_history((int) $othercourse->id, $otherusers, $days);
        $this->build_days($days);
        return ['course' => $course, 'users' => $users, 'days' => $days];
    }

    /**
     * Every level builds within the query budget, also at the largest page size.
     */
    public function test_query_budget_at_each_level(): void {
        $site = $this->create_site();
        $courseid = (int) $site['course']->id;

        foreach ([50, tutor_usage_report_request::MAX_PERPAGE] as $perpage) {
            $levels = [
                ['level' => tutor_usage_report_service::LEVEL_SITE],
                ['level' => tutor_usage_report_service::LEVEL_COURSE, 'courseid' => $courseid],
                ['level' => tutor_usage_report_service::LEVEL_USER, 'courseid' => $courseid, 'userid' => $site['users'][0]],
            ];
            foreach ($levels as $params) {
                $params['perpage'] = $perpage;
                $count = $this->page_queries($params);
                $this->assertLessThanOrEqual(self::QUERY_BUDGET, $count, "{$params['level']} at {$perpage} rows per page");
            }
        }
    }

    /**
     * Courses and users without usage do not add site-level queries.
     */
    public function test_site_queries_ignore_courses_and_users_without_usage(): void {
        $this->create_site();
        $params = ['level' => tutor_usage_report_service::LEVEL_SITE];
        $before = $this->page_queries($params);

        for ($i = 0; $i < 50; $i++) {
            $course = $this->getDataGenerator()->create_course();
            if ($i < 5) {
                $this->bulk_enrol((int) $course->id, 100);
            }
        }

        $this->assertSame($before, $this->page_queries($params));
    }

    /**
     * Course-level queries do not grow with the number of enrolled users.
     */
    public function test_course_queries_ignore_enrolled_users(): void {
        global $PAGE;

        $this->setAdminUser();
        $PAGE->set_url('/local/dixeo/tutor_usage_report.php');
        $course = $this->getDataGenerator()->create_course();
        $users = $this->bulk_enrol((int) $course->id, 100);
        $today = site_calendar::day_start(time());
        $days = site_calendar::days($today - 30 * DAYSECS, $today);
        $this->insert_history((int) $course->id, array_slice($users, 0, 40), $days);
        $this->build_days($days);
        $params = ['level' => tutor_usage_report_service::LEVEL_COURSE, 'courseid' => (int) $course->id];

        $small = $this->page_queries($params);
        $this->bulk_enrol((int) $course->id, 900);
        $this->assertSame($small, $this->page_queries($params));
        $this->assertSame($small, $this->page_queries($params + ['sort' => 'name', 'page' => 9]));
    }

    /**
     * With every past day built, the length of the period does not change the query count.
     */
    public function test_queries_do_not_grow_with_built_days(): void {
        $site = $this->create_site();
        $today = site_calendar::day_start(time());
        $todayparam = tutor_usage_report_service::format_date_param($today);

        $week = $this->page_queries([
            'level' => tutor_usage_report_service::LEVEL_SITE,
            'view' => tutor_usage_report_service::VIEW_WEEK,
        ]);
        $month = $this->page_queries([
            'level' => tutor_usage_report_service::LEVEL_SITE,
            'view' => tutor_usage_report_service::VIEW_MONTH,
        ]);
        $this->assertSame($week, $month);

        // A custom period has no previous period, so it is compared with a short custom period.
        $customweek = $this->page_queries([
            'level' => tutor_usage_report_service::LEVEL_SITE,
            'view' => tutor_usage_report_service::VIEW_CUSTOM,
            'datefrom' => tutor_usage_report_service::parse_date_from_param(
                tutor_usage_report_service::format_date_param($today - 6 * DAYSECS)
            ),
            'dateto' => tutor_usage_report_service::parse_date_to_param($todayparam),
        ]);
        $customsixmonths = $this->page_queries([
            'level' => tutor_usage_report_service::LEVEL_SITE,
            'view' => tutor_usage_report_service::VIEW_CUSTOM,
            'datefrom' => tutor_usage_report_service::parse_date_from_param(
                tutor_usage_report_service::format_date_param($site['days'][10])
            ),
            'dateto' => tutor_usage_report_service::parse_date_to_param($todayparam),
        ]);
        $this->assertSame($customweek, $customsixmonths);
    }
}
