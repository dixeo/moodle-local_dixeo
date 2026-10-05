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

/**
 * Tests for activity addinstance checks on Dixeo creation paths.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service;

use local_dixeo\dsl\actions\create_module_action;
use local_dixeo\dsl\value_resolver;

/**
 * Activity creation must require mod/<type>:addinstance.
 *
 * @covers \local_dixeo\service\module_addinstance_service
 * @covers \local_dixeo\dsl\actions\create_module_action
 * @covers \local_dixeo\service\h5p_packaging_service
 * @covers \local_dixeo\service\scorm_creation_service
 * @covers \local_dixeo\service\resource_upload_service
 */
final class module_addinstance_service_test extends \advanced_testcase {
    public function test_unknown_module_is_rejected(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $this->expectException(\moodle_exception::class);
        module_addinstance_service::require_for_course((int) $course->id, 'notamodule');
    }

    public function test_disabled_module_is_rejected(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $DB->set_field('modules', 'visible', 0, ['name' => 'quiz']);

        $this->expectException(\moodle_exception::class);
        module_addinstance_service::require_for_course((int) $course->id, 'quiz');
    }

    public function test_user_without_quiz_addinstance_cannot_create_quiz(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 0], '*', MUST_EXIST);
        $user = $this->user_who_can_manage_activities_but_not_add('quiz', $course);

        $this->setUser($user);
        try {
            $this->execute_create_module($course, $section, 'quiz', 'Blocked quiz');
            $this->fail('Quiz creation should require mod/quiz:addinstance');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }

        $this->assertFalse($DB->record_exists('course_modules', ['course' => $course->id, 'module' => $DB->get_field(
            'modules',
            'id',
            ['name' => 'quiz']
        )]));
    }

    public function test_same_user_can_create_a_page_when_addinstance_is_allowed(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 0], '*', MUST_EXIST);
        $context = \context_course::instance($course->id);
        $user = $this->user_who_can_manage_activities_but_not_add('quiz', $course);
        $roleid = $DB->get_field('role_assignments', 'roleid', [
            'userid' => $user->id,
            'contextid' => $context->id,
        ], MUST_EXIST);
        assign_capability('mod/page:addinstance', CAP_ALLOW, $roleid, $context->id, true);

        $this->setUser($user);
        $created = $this->execute_create_module($course, $section, 'page', 'Allowed page');

        $this->assertGreaterThan(0, $created['cmid']);
        $this->assertTrue($DB->record_exists('page', ['id' => $created['id'], 'course' => $course->id]));
    }

    public function test_manual_upload_paths_require_addinstance_before_files(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->user_who_can_manage_activities_but_not_add('resource', $course);
        $context = \context_course::instance($course->id);
        assign_capability('mod/scorm:addinstance', CAP_PROHIBIT, $this->roleid_for($user, $context), $context->id, true);
        if (plugin_installation_service::is_component_installed('mod_h5pactivity')) {
            assign_capability(
                'mod/h5pactivity:addinstance',
                CAP_PROHIBIT,
                $this->roleid_for($user, $context),
                $context->id,
                true
            );
        }
        $this->setUser($user);

        try {
            (new resource_upload_service())->create_from_draft((int) $course->id, 1, 0, null, 'File', 0);
            $this->fail('Resource creation should require mod/resource:addinstance');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }

        try {
            (new scorm_creation_service())->create_from_draft((int) $course->id, 1, 0, null, 'Package', 0);
            $this->fail('SCORM creation should require mod/scorm:addinstance');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }

        if (!plugin_installation_service::is_component_installed('mod_h5pactivity')) {
            return;
        }
        try {
            (new h5p_packaging_service())->create_activity(
                (int) $course->id,
                1,
                0,
                'H5P',
                '',
                'H5P.QuestionSet 1.20',
                [],
            );
            $this->fail('H5P creation should require mod/h5pactivity:addinstance');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }
    }

    /**
     * User with generation and activity management, without addinstance for one type.
     *
     * @param string $blockedmodule Module whose addinstance capability is not granted.
     * @param \stdClass $course Course.
     * @return \stdClass
     */
    private function user_who_can_manage_activities_but_not_add(string $blockedmodule, \stdClass $course): \stdClass {
        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $roleid = $generator->create_role();
        $context = \context_course::instance($course->id);
        assign_capability('local/dixeo:generate', CAP_ALLOW, $roleid, $context->id, true);
        assign_capability('moodle/course:manageactivities', CAP_ALLOW, $roleid, $context->id, true);
        role_assign($roleid, $user->id, $context->id);
        $this->assertFalse(has_capability('mod/' . $blockedmodule . ':addinstance', $context, $user));
        return $user;
    }

    /**
     * Role assigned to a user in a course context.
     *
     * @param \stdClass $user
     * @param \context_course $context
     * @return int
     */
    private function roleid_for(\stdClass $user, \context_course $context): int {
        global $DB;
        return (int) $DB->get_field('role_assignments', 'roleid', [
            'userid' => $user->id,
            'contextid' => $context->id,
        ], MUST_EXIST);
    }

    /**
     * Run create_module_action for one activity in a section.
     *
     * @param \stdClass $course
     * @param \stdClass $section
     * @param string $modulename
     * @param string $name
     * @return array
     */
    private function execute_create_module(\stdClass $course, \stdClass $section, string $modulename, string $name): array {
        $context = [
            'courseid' => (int) $course->id,
            'sectionid' => (int) $section->id,
            'sectionnum' => (int) $section->section,
            'modulename' => $modulename,
            'userid' => (int) $course->id,
        ];
        $resolver = new value_resolver([
            'name' => $name,
            'intro' => 'Intro',
            'introformat' => FORMAT_HTML,
            'content' => 'Body',
            'contentformat' => FORMAT_HTML,
        ], [], $context);

        return (new create_module_action())->execute([
            'fields' => [
                'name' => ['source' => '$.name'],
                'intro' => ['source' => '$.intro'],
                'content' => ['source' => '$.content'],
            ],
        ], $resolver);
    }
}
