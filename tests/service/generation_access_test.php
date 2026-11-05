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

namespace local_dixeo\service;

use local_dixeo\external\service_factory;

/**
 * Tests for the generation gate applied on behalf of a named user.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\service\generation_access
 */
final class generation_access_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass Editing teacher of the course. */
    private \stdClass $teacher;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
    }

    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    public function test_editing_teacher_is_allowed(): void {
        $this->assertNull(generation_access::denial_reason((int) $this->teacher->id, (int) $this->course->id, 'page'));
    }

    public function test_missing_course_and_site_are_refused(): void {
        $userid = (int) $this->teacher->id;
        $this->assert_reason('error:generation_not_allowed', generation_access::denial_reason($userid, SITEID, 'page'));
        $this->assert_reason(
            'error:generation_not_allowed',
            generation_access::denial_reason($userid, (int) $this->course->id + 1000, 'page')
        );
    }

    /**
     * Dixeo off at the site or for the course closes the gate.
     *
     * @dataProvider disabled_layer_provider
     * @param string $layer Layer turned off: site or course.
     */
    public function test_dixeo_off_is_refused(string $layer): void {
        $this->setAdminUser();
        $context = $layer === 'site' ? \context_system::instance() : \context_course::instance($this->course->id);
        service_factory::get_settings_config()->set('local_dixeo', 'enabled', $context, '0');
        $this->setUser(null);

        $this->assert_reason(
            'error:dixeo_disabled',
            generation_access::denial_reason((int) $this->teacher->id, (int) $this->course->id, 'page')
        );
    }

    /**
     * Layers that can turn Dixeo off for a course.
     *
     * @return array
     */
    public static function disabled_layer_provider(): array {
        return [
            'site' => ['site'],
            'course' => ['course'],
        ];
    }

    public function test_inactive_users_are_refused(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $deleted = $generator->create_and_enrol($this->course, 'editingteacher');
        delete_user($deleted);
        $suspended = $generator->create_and_enrol($this->course, 'editingteacher', ['suspended' => 1]);
        $nologin = $generator->create_and_enrol($this->course, 'editingteacher');
        $DB->set_field('user', 'auth', 'nologin', ['id' => $nologin->id]);
        $unenrolled = $generator->create_user();

        foreach ([$deleted, $suspended, $nologin, $unenrolled] as $user) {
            $this->assert_reason(
                'error:generation_not_allowed',
                generation_access::denial_reason((int) $user->id, (int) $this->course->id, 'page')
            );
        }
        $this->assert_reason(
            'error:generation_not_allowed',
            generation_access::denial_reason(0, (int) $this->course->id, 'page')
        );
    }

    public function test_suspended_enrolment_is_refused(): void {
        $user = $this->getDataGenerator()->create_and_enrol(
            $this->course,
            'editingteacher',
            [],
            'manual',
            0,
            0,
            ENROL_USER_SUSPENDED
        );

        $this->assert_reason(
            'error:generation_not_allowed',
            generation_access::denial_reason((int) $user->id, (int) $this->course->id, 'page')
        );
    }

    /**
     * Missing course capabilities close the gate.
     *
     * @dataProvider course_capability_provider
     * @param string $capability Capability prohibited for editing teachers.
     */
    public function test_missing_course_capability_is_refused(string $capability): void {
        $this->prohibit_for_teacher($capability);

        $this->assert_reason(
            'error:generation_not_allowed',
            generation_access::denial_reason((int) $this->teacher->id, (int) $this->course->id, 'page')
        );
    }

    /**
     * Course capabilities required for generation.
     *
     * @return array
     */
    public static function course_capability_provider(): array {
        return [
            'generate' => ['local/dixeo:generate'],
            'manage activities' => ['moodle/course:manageactivities'],
        ];
    }

    public function test_unsupported_type_is_refused(): void {
        $this->assert_reason(
            'error:activity_not_available',
            generation_access::denial_reason((int) $this->teacher->id, (int) $this->course->id, 'notamodule')
        );
    }

    public function test_missing_addinstance_is_refused(): void {
        $this->prohibit_for_teacher('mod/page:addinstance');

        $this->assert_reason(
            'error:activity_not_available',
            generation_access::denial_reason((int) $this->teacher->id, (int) $this->course->id, 'page')
        );
    }

    /**
     * Prohibit a capability for editing teachers in the course.
     *
     * @param string $capability Capability name.
     * @return void
     */
    private function prohibit_for_teacher(string $capability): void {
        global $DB;

        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability($capability, CAP_PROHIBIT, $roleid, \context_course::instance($this->course->id)->id, true);
        accesslib_clear_all_caches_for_unit_testing();
    }

    /**
     * Assert a refusal names the expected local_dixeo string.
     *
     * @param string $identifier Expected string identifier.
     * @param \lang_string|null $reason Refusal returned by the gate.
     * @return void
     */
    private function assert_reason(string $identifier, ?\lang_string $reason): void {
        $this->assertInstanceOf(\lang_string::class, $reason);
        $this->assertSame($identifier, $reason->get_identifier());
        $this->assertSame('local_dixeo', $reason->get_component());
    }
}
