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

use local_dixeo\dsl\actions\create_entries_action;
use local_dixeo\dsl\actions\create_questions_simplequiz2_action;
use local_dixeo\dsl\actions\create_slides_action;

/**
 * Tests that child DSL actions only write into the module they created in the course.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\dsl\actions\action_validation
 */
final class action_course_boundary_test extends \advanced_testcase {
    /**
     * Build the interpreter context for a course.
     *
     * @param \stdClass $course The course.
     * @param string $modulename The module type being created.
     * @return array The context array.
     */
    private function build_context(\stdClass $course, string $modulename): array {
        global $DB;

        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1], '*', MUST_EXIST);

        return [
            'courseid' => (int) $course->id,
            'sectionid' => (int) $section->id,
            'sectionnum' => 1,
            'modulename' => $modulename,
            'userid' => 2,
            'contextid' => \context_course::instance($course->id)->id,
        ];
    }

    /**
     * The glossary field specification of a create_entries action.
     *
     * @param array $glossaryidspec The field specification resolving the glossary id.
     * @return array The action specification.
     */
    private function entries_action(array $glossaryidspec): array {
        return [
            'entity' => 'glossary_entry',
            'foreach' => '$.entries',
            'fields' => [
                'glossaryid' => $glossaryidspec,
                'concept' => ['source' => '$.concept'],
                'definition' => ['source' => '$.definition'],
                'definitionformat' => ['value' => FORMAT_HTML],
                'userid' => ['source' => '$context.userid'],
            ],
        ];
    }

    public function test_create_entries_rejects_a_glossary_of_another_course(): void {
        global $DB;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $othercourse = $generator->create_course();
        $otherglossary = $generator->create_module('glossary', ['course' => $othercourse->id]);

        // A variable claiming a module of another course must not open a write path to it.
        $variables = ['module' => [
            'id' => (int) $otherglossary->id,
            'cmid' => (int) $otherglossary->cmid,
            'modulename' => 'glossary',
        ]];
        $resolver = new value_resolver(
            ['entries' => [['concept' => 'Term', 'definition' => 'Definition']]],
            $variables,
            $this->build_context($course, 'glossary')
        );

        try {
            (new create_entries_action())->execute($this->entries_action(['source' => '$module.id']), $resolver);
            $this->fail('dsl_exception expected');
        } catch (dsl_exception $e) {
            $this->assertStringContainsString('does not belong to course', $e->getMessage());
        }

        $this->assertSame(0, $DB->count_records('glossary_entries', ['glossaryid' => $otherglossary->id]));
    }

    public function test_create_entries_rejects_a_glossary_not_created_in_the_run(): void {
        global $DB;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $created = $generator->create_module('glossary', ['course' => $course->id]);
        $untouched = $generator->create_module('glossary', ['course' => $course->id]);

        $variables = ['module' => [
            'id' => (int) $created->id,
            'cmid' => (int) $created->cmid,
            'modulename' => 'glossary',
        ]];
        $resolver = new value_resolver(
            ['entries' => [['concept' => 'Term', 'definition' => 'Definition']]],
            $variables,
            $this->build_context($course, 'glossary')
        );

        // A constant glossary id in the specification bypasses $module.id entirely.
        try {
            (new create_entries_action())->execute(
                $this->entries_action(['value' => (int) $untouched->id]),
                $resolver
            );
            $this->fail('dsl_exception expected');
        } catch (dsl_exception $e) {
            $this->assertStringContainsString('was not created by this execution', $e->getMessage());
        }

        $this->assertSame(0, $DB->count_records('glossary_entries', ['glossaryid' => $untouched->id]));
    }

    public function test_create_entries_writes_into_the_glossary_created_in_the_run(): void {
        global $DB;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $glossary = $generator->create_module('glossary', ['course' => $course->id]);

        $variables = ['module' => [
            'id' => (int) $glossary->id,
            'cmid' => (int) $glossary->cmid,
            'modulename' => 'glossary',
        ]];
        $resolver = new value_resolver(
            ['entries' => [['concept' => 'Term', 'definition' => '<p>Definition</p>']]],
            $variables,
            $this->build_context($course, 'glossary')
        );

        $createdids = (new create_entries_action())->execute(
            $this->entries_action(['source' => '$module.id']),
            $resolver
        );

        $this->assertCount(1, $createdids);
        $this->assertSame(1, $DB->count_records('glossary_entries', ['glossaryid' => $glossary->id]));
    }

    /**
     * The production glossary DSL still creates its entries, with the definition cleaned.
     */
    public function test_interpreter_runs_the_glossary_creation_dsl(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $context = interpreter::build_context((int) $course->id, 1, 'glossary');
        $context['userid'] = 2;

        $actions = [
            [
                'action' => 'create_module',
                'save_as' => 'module',
                'fields' => [
                    'name' => ['source' => '$.name'],
                    'intro' => ['source' => '$.intro'],
                    'introformat' => ['value' => FORMAT_HTML],
                ],
            ],
            [
                'action' => 'create_entries',
                'entity' => 'glossary_entry',
                'foreach' => '$.entries',
                'fields' => [
                    'glossaryid' => ['source' => '$module.id'],
                    'concept' => ['source' => '$.concept'],
                    'definition' => ['source' => '$.definition'],
                    'definitionformat' => ['value' => FORMAT_HTML],
                    'userid' => ['source' => '$context.userid'],
                ],
            ],
        ];
        $data = [
            'name' => 'Biology glossary',
            'intro' => '<p>Key terms</p>',
            'entries' => [
                ['concept' => 'Cell', 'definition' => '<p>The basic unit of life.</p>'],
            ],
        ];

        $cmid = (new interpreter())->execute($actions, $data, $context);

        $cm = get_coursemodule_from_id('glossary', $cmid, (int) $course->id, false, MUST_EXIST);
        $entries = $DB->get_records('glossary_entries', ['glossaryid' => $cm->instance]);
        $this->assertCount(1, $entries);

        $entry = reset($entries);
        $this->assertStringContainsString('The basic unit of life.', $entry->definition);
    }

    public function test_add_slides_rejects_a_module_reference_built_from_api_data(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        // The module_ref may point at any source, including API data the interpreter never vetted.
        // Use synthetic ids so this runs without mod_slideshow installed (plugin CI).
        $resolver = new value_resolver([
            'parent' => ['id' => 424242, 'cmid' => 434343],
            'slides' => [['title' => 'Slide', 'content' => '<p>Body</p>']],
        ], [], $this->build_context($course, 'slideshow'));

        try {
            (new create_slides_action())->execute([
                'module_ref' => '$.parent',
                'foreach' => '$.slides',
                'fields' => [
                    'title' => ['source' => '$.title'],
                    'content' => ['source' => '$.content'],
                ],
            ], $resolver);
            $this->fail('dsl_exception expected');
        } catch (dsl_exception $e) {
            $this->assertStringContainsString('was not created by this execution', $e->getMessage());
        }
    }

    public function test_create_questions_simplequiz2_rejects_an_instance_not_created_in_the_run(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        // Synthetic ids: validation rejects before any mod_simplequiz2 lookup (plugin CI).
        $resolver = new value_resolver([
            'parent' => ['id' => 525252, 'cmid' => 535353],
            'questions' => [['text' => 'Question?', 'options' => ['A', 'B'], 'answer' => 0]],
        ], [], $this->build_context($course, 'simplequiz2'));

        try {
            (new create_questions_simplequiz2_action())->execute([
                'module_ref' => '$.parent',
                'foreach' => '$.questions',
                'fields' => [
                    'questiontext' => ['source' => '$.text'],
                    'options' => ['source' => '$.options'],
                    'correct_answer' => ['source' => '$.answer'],
                ],
            ], $resolver);
            $this->fail('dsl_exception expected');
        } catch (dsl_exception $e) {
            $this->assertStringContainsString('was not created by this execution', $e->getMessage());
        }
    }
}
