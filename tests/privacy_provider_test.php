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
 * Privacy provider tests for local_dixeo.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_dixeo\privacy\provider;
use local_dixeo\repository\job_repository;

/**
 * Privacy provider tests.
 *
 * @covers \local_dixeo\privacy\provider
 */
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Insert a course AI sync row linked to a user.
     *
     * @param int $courseid Course ID.
     * @param int $userid User ID for enabledby.
     * @param int|null $disabledby Optional disabledby user ID.
     * @return int Inserted record ID.
     */
    private function insert_course_ai_record(int $courseid, int $userid, ?int $disabledby = null): int {
        global $DB;

        $now = time();
        return (int) $DB->insert_record('local_dixeo_course_ai', (object) [
            'courseid' => $courseid,
            'enabled' => 1,
            'syncstatus' => 'synchronized',
            'errorcount' => 0,
            'errormessage' => null,
            'enabledby' => $userid,
            'enabledat' => $now,
            'disabledby' => $disabledby,
            'disabledat' => $disabledby ? $now : null,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Register a local job binding for privacy tests.
     *
     * @param string $jobid Remote job id.
     * @param int $courseid Course id (0 for pre-course jobs).
     * @param int $userid Initiating user id.
     * @return void
     */
    private function insert_job_record(string $jobid, int $courseid, int $userid): void {
        (new job_repository())->register($jobid, $courseid, $userid, 'default', 'course_structure');
    }

    public function test_get_metadata(): void {
        $collection = new collection('local_dixeo');
        $items = provider::get_metadata($collection)->get_collection();

        $names = array_map(static fn($item) => $item->get_name(), $items);
        $this->assertContains('local_dixeo_course_ai', $names);
        $this->assertContains('local_dixeo_jobs', $names);
        $this->assertContains('local_dixeo_image_job', $names);
        $this->assertContains('dixeo_api', $names);

        $external = null;
        foreach ($items as $item) {
            if ($item->get_name() === 'dixeo_api') {
                $external = $item;
                break;
            }
        }
        $this->assertNotNull($external);
        $fields = array_keys($external->get_privacy_fields());
        $expectedfields = [
            'courseId',
            'userId',
            'message',
            'instructions',
            'context',
            'pageContext',
            'moduleType',
            'templateId',
            'name',
            'description',
            'templateDefinition',
            'title',
            'summary',
            'images',
            'files',
            'namespace',
        ];
        foreach ($expectedfields as $field) {
            $this->assertContains($field, $fields, "External metadata must declare {$field}");
        }
    }

    public function test_get_contexts_for_userid_empty_without_data(): void {
        $user = $this->getDataGenerator()->create_user();
        $contextlist = provider::get_contexts_for_userid((int) $user->id);
        $this->assertCount(0, $contextlist);
    }

    public function test_get_contexts_for_userid_with_enabledby(): void {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->insert_course_ai_record((int) $course->id, (int) $user->id);

        $contextlist = provider::get_contexts_for_userid((int) $user->id);
        $coursecontext = \context_course::instance((int) $course->id);
        $this->assertEquals([$coursecontext->id], $contextlist->get_contextids());
    }

    public function test_export_and_delete_user_data(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();

        $this->insert_course_ai_record((int) $course->id, (int) $user->id);
        $this->insert_course_ai_record((int) $othercourse->id, (int) $other->id);

        $coursecontext = \context_course::instance((int) $course->id);
        writer::reset();

        $approved = new approved_contextlist($user, 'local_dixeo', [$coursecontext->id]);
        provider::export_user_data($approved);

        $writer = writer::with_context($coursecontext);
        $this->assertTrue($writer->has_any_data());

        provider::delete_data_for_user($approved);

        $record = $DB->get_record('local_dixeo_course_ai', ['courseid' => $course->id], '*', MUST_EXIST);
        $this->assertNull($record->enabledby);
        $this->assertNull($record->enabledat);
        $this->assertEquals(1, (int) $record->enabled);

        $this->assertTrue($DB->record_exists('local_dixeo_course_ai', [
            'courseid' => $othercourse->id,
            'enabledby' => $other->id,
        ]));
    }

    public function test_get_users_in_context_and_delete_users(): void {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->insert_course_ai_record((int) $course->id, (int) $user1->id, (int) $user2->id);

        $coursecontext = \context_course::instance((int) $course->id);
        $userlist = new userlist($coursecontext, 'local_dixeo');
        provider::get_users_in_context($userlist);
        $userids = $userlist->get_userids();

        $this->assertContains((int) $user1->id, $userids);
        $this->assertContains((int) $user2->id, $userids);

        $approved = new approved_userlist($coursecontext, 'local_dixeo', [$user1->id]);
        provider::delete_data_for_users($approved);

        $record = $DB->get_record('local_dixeo_course_ai', ['courseid' => $course->id], '*', MUST_EXIST);
        $this->assertNull($record->enabledby);
        $this->assertEquals((int) $user2->id, (int) $record->disabledby);
    }

    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->insert_course_ai_record((int) $course->id, (int) $user->id);

        $coursecontext = \context_course::instance((int) $course->id);
        provider::delete_data_for_all_users_in_context($coursecontext);

        $this->assertFalse($DB->record_exists('local_dixeo_course_ai', ['courseid' => $course->id]));
    }

    public function test_get_contexts_for_userid_includes_system_for_pre_course_jobs(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->insert_job_record('pre-course-job', 0, (int) $user->id);

        $contextlist = provider::get_contexts_for_userid((int) $user->id);
        $systemcontext = \context_system::instance();

        $this->assertEquals([$systemcontext->id], $contextlist->get_contextids());
    }

    public function test_export_and_delete_pre_course_job_under_system_context(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->insert_job_record('pre-course-job', 0, (int) $user->id);
        $this->insert_job_record('other-pre-course-job', 0, (int) $other->id);

        $systemcontext = \context_system::instance();
        writer::reset();

        $approved = new approved_contextlist($user, 'local_dixeo', [$systemcontext->id]);
        provider::export_user_data($approved);

        $writer = writer::with_context($systemcontext);
        $this->assertTrue($writer->has_any_data());

        provider::delete_data_for_user($approved);

        $this->assertFalse($DB->record_exists('local_dixeo_jobs', [
            'jobid' => 'pre-course-job',
            'userid' => $user->id,
            'courseid' => 0,
        ]));
        $this->assertTrue($DB->record_exists('local_dixeo_jobs', [
            'jobid' => 'other-pre-course-job',
            'userid' => $other->id,
            'courseid' => 0,
        ]));
    }

    public function test_get_users_in_context_and_delete_users_for_pre_course_jobs(): void {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->insert_job_record('pre-course-1', 0, (int) $user1->id);
        $this->insert_job_record('pre-course-2', 0, (int) $user2->id);

        $systemcontext = \context_system::instance();
        $userlist = new userlist($systemcontext, 'local_dixeo');
        provider::get_users_in_context($userlist);
        $userids = $userlist->get_userids();

        $this->assertContains((int) $user1->id, $userids);
        $this->assertContains((int) $user2->id, $userids);

        $approved = new approved_userlist($systemcontext, 'local_dixeo', [$user1->id]);
        provider::delete_data_for_users($approved);

        $this->assertFalse($DB->record_exists('local_dixeo_jobs', [
            'jobid' => 'pre-course-1',
            'userid' => $user1->id,
        ]));
        $this->assertTrue($DB->record_exists('local_dixeo_jobs', [
            'jobid' => 'pre-course-2',
            'userid' => $user2->id,
        ]));
    }

    public function test_delete_data_for_all_users_in_system_context_removes_pre_course_jobs(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->insert_job_record('pre-course-job', 0, (int) $user->id);
        $this->insert_job_record('course-job', (int) $course->id, (int) $user->id);

        provider::delete_data_for_all_users_in_context(\context_system::instance());

        $this->assertFalse($DB->record_exists('local_dixeo_jobs', ['jobid' => 'pre-course-job']));
        $this->assertTrue($DB->record_exists('local_dixeo_jobs', ['jobid' => 'course-job']));
    }
}
