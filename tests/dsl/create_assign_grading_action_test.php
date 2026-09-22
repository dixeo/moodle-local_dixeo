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

namespace local_dixeo\dsl;

use local_dixeo\dsl\actions\create_assign_grading_action;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/grade/grading/lib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

/**
 * Tests for assign creation DSL (quirks + grading).
 *
 * Smoke checklist (manual / after deploy):
 * 1. Deploy Dixeo_API so AssignSchema is registered before Moodle submits moduleType=assign.
 * 2. Deploy local_dixeo (quirks + create_assign_grading).
 * 3. In modulegen, generate an assignment → confirm one-PDF file submission settings.
 * 4. Generate with grading_method rubric/guide/simple → confirm advanced grading method + criteria.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\dsl\actions\create_assign_grading_action
 * @covers     \local_dixeo\dsl\actions\create_module_action
 * @covers     \local_dixeo\dsl\interpreter
 */
final class create_assign_grading_action_test extends \advanced_testcase {
    /**
     * Assign creation DSL matching AssignSchema::getCreationDsl().
     *
     * @return array
     */
    private function assign_creation_dsl(): array {
        return [
            [
                'action' => 'create_module',
                'save_as' => 'module',
                'fields' => [
                    'name' => ['source' => '$.name'],
                    'intro' => ['source' => '$.intro'],
                    'introformat' => ['value' => FORMAT_HTML],
                    'activity' => ['source' => '$.activity'],
                ],
            ],
            [
                'action' => 'create_assign_grading',
                'module_ref' => '$module',
                'fields' => [
                    'grading_method' => ['source' => '$.grading_method'],
                    'marking_guide' => ['source' => '$.marking_guide'],
                    'marking_rubric' => ['source' => '$.marking_rubric'],
                ],
            ],
        ];
    }

    /**
     * @param \stdClass $course Course.
     * @return array Interpreter context.
     */
    private function build_context(\stdClass $course): array {
        $context = interpreter::build_context((int) $course->id, 1, 'assign');
        $context['userid'] = 2;
        return $context;
    }

    public function test_interpreter_creates_assign_with_pdf_submission_and_simple_grading(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $data = [
            'name' => 'Essay PDF',
            'intro' => '<p>Write an essay</p>',
            'activity' => '<h3>Task</h3><p>Submit your work as a single PDF file.</p>',
            'grading_method' => 'simple',
            'marking_guide' => null,
            'marking_rubric' => null,
        ];

        $cmid = (new interpreter())->execute($this->assign_creation_dsl(), $data, $this->build_context($course));

        $cm = get_coursemodule_from_id('assign', $cmid, (int) $course->id, false, MUST_EXIST);
        $assign = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);
        $this->assertSame('Essay PDF', $assign->name);
        $this->assertStringContainsString('Submit your work as a single PDF file', $assign->activity);
        $this->assertEquals(1, $assign->submissiondrafts);
        $this->assertEquals(100, $assign->grade);

        $pluginconfig = $DB->get_record('assign_plugin_config', [
            'assignment' => $assign->id,
            'plugin' => 'file',
            'subtype' => 'assignsubmission',
            'name' => 'enabled',
        ]);
        $this->assertNotFalse($pluginconfig);
        $this->assertEquals('1', $pluginconfig->value);

        $filetypes = $DB->get_record('assign_plugin_config', [
            'assignment' => $assign->id,
            'plugin' => 'file',
            'subtype' => 'assignsubmission',
            'name' => 'filetypeslist',
        ]);
        $this->assertNotFalse($filetypes);
        $this->assertStringContainsString('pdf', strtolower((string) $filetypes->value));

        $maxfiles = $DB->get_record('assign_plugin_config', [
            'assignment' => $assign->id,
            'plugin' => 'file',
            'subtype' => 'assignsubmission',
            'name' => 'maxfilesubmissions',
        ]);
        $this->assertNotFalse($maxfiles);
        $this->assertEquals('1', $maxfiles->value);

        $onlinetext = $DB->get_record('assign_plugin_config', [
            'assignment' => $assign->id,
            'plugin' => 'onlinetext',
            'subtype' => 'assignsubmission',
            'name' => 'enabled',
        ]);
        if ($onlinetext) {
            $this->assertEquals('0', $onlinetext->value);
        }

        $context = \context_module::instance($cmid);
        $gradingman = get_grading_manager($context, 'mod_assign', 'submissions');
        $this->assertSame('', (string) $gradingman->get_active_method());
    }

    public function test_interpreter_creates_assign_with_rubric(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $data = [
            'name' => 'Rubric assignment',
            'intro' => '<p>Intro</p>',
            'activity' => '<p>Upload one PDF document.</p>',
            'grading_method' => 'rubric',
            'marking_guide' => null,
            'marking_rubric' => [
                'name' => 'Essay rubric',
                'description' => '<p>Criteria</p>',
                'criteria' => [
                    [
                        'description' => 'Clarity',
                        'levels' => [
                            ['score' => 0, 'definition' => 'Poor'],
                            ['score' => 50, 'definition' => 'Fair'],
                            ['score' => 100, 'definition' => 'Excellent'],
                        ],
                    ],
                ],
            ],
        ];

        $cmid = (new interpreter())->execute($this->assign_creation_dsl(), $data, $this->build_context($course));

        $context = \context_module::instance($cmid);
        $gradingman = get_grading_manager($context, 'mod_assign', 'submissions');
        $this->assertSame('rubric', $gradingman->get_active_method());

        $controller = $gradingman->get_controller('rubric');
        $this->assertTrue($controller->is_form_defined());
        $definition = $controller->get_definition();
        $this->assertSame('Essay rubric', $definition->name);
        $this->assertNotEmpty($definition->rubric_criteria);
    }

    public function test_interpreter_creates_assign_with_guide_defaults_when_omitted(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $data = [
            'name' => 'Guide assignment',
            'intro' => '<p>Intro</p>',
            'activity' => '<p>Submit your work as a single PDF file.</p>',
            'grading_method' => 'guide',
            'marking_guide' => null,
            'marking_rubric' => null,
        ];

        $cmid = (new interpreter())->execute($this->assign_creation_dsl(), $data, $this->build_context($course));

        $context = \context_module::instance($cmid);
        $gradingman = get_grading_manager($context, 'mod_assign', 'submissions');
        $this->assertSame('guide', $gradingman->get_active_method());

        $controller = $gradingman->get_controller('guide');
        $this->assertTrue($controller->is_form_defined());
    }

    public function test_create_assign_grading_rejects_module_not_created_in_run(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $resolver = new value_resolver([
            'parent' => ['id' => 626262, 'cmid' => 636363],
            'grading_method' => 'simple',
            'marking_guide' => null,
            'marking_rubric' => null,
        ], [], [
            'courseid' => (int) $course->id,
            'sectionid' => 1,
            'sectionnum' => 1,
            'modulename' => 'assign',
            'userid' => 2,
        ]);

        try {
            (new create_assign_grading_action())->execute([
                'module_ref' => '$.parent',
                'fields' => [
                    'grading_method' => ['source' => '$.grading_method'],
                    'marking_guide' => ['source' => '$.marking_guide'],
                    'marking_rubric' => ['source' => '$.marking_rubric'],
                ],
            ], $resolver);
            $this->fail('dsl_exception expected');
        } catch (dsl_exception $e) {
            $this->assertStringContainsString('was not created by this execution', $e->getMessage());
        }
    }
}
