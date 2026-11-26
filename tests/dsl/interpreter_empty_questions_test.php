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

/**
 * Tests that an empty generated question list creates no activity.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\dsl\interpreter
 * @covers     \local_dixeo\dsl\empty_generation_exception
 */
final class interpreter_empty_questions_test extends \advanced_testcase {
    /**
     * Quiz creation DSL as the Dixeo API sends it.
     *
     * @return array
     */
    private function quiz_dsl(): array {
        return [
            ['action' => 'create_module', 'save_as' => 'module', 'fields' => [
                'name' => ['source' => '$.name'], 'intro' => ['source' => '$.intro'], 'introformat' => ['value' => 1],
            ]],
            ['action' => 'create_questions', 'module_ref' => '$module', 'foreach' => '$.questions',
                'question_type' => ['source' => '$.question_type'], 'fields' => [
                    'questiontext' => ['source' => '$.question'],
                    'choices' => ['source' => '$.choices'],
                    'answers' => ['source' => '$.answers'],
                    'correct_answer' => ['source' => '$.rightanswer'],
                    'wrong_feedback' => ['source' => '$.wrong_feedback'],
                ]],
        ];
    }

    /**
     * SimpleQuiz creation DSL as the Dixeo API sends it.
     *
     * @return array
     */
    private function simplequiz_dsl(): array {
        return [
            ['action' => 'create_module', 'save_as' => 'module', 'fields' => [
                'name' => ['source' => '$.name'], 'intro' => ['source' => '$.intro'], 'introformat' => ['value' => 1],
            ]],
            ['action' => 'create_questions', 'module_ref' => '$module', 'foreach' => '$.questions', 'fields' => [
                'questiontext' => ['source' => '$.text'],
                'choices' => ['source' => '$.choices'],
                'options' => ['source' => '$.options'],
                'correct_answer' => ['source' => '$.answer'],
            ]],
        ];
    }

    /**
     * Data provider: module type, DSL and data with an empty question list.
     *
     * @return array
     */
    public static function empty_cases_provider(): array {
        return [
            'quiz' => ['quiz', ['name' => 'Quiz', 'intro' => 'Intro', 'questions' => []]],
            'simplequiz2' => ['simplequiz2', ['name' => 'Simple quiz', 'intro' => 'Intro', 'questions' => []]],
            'quiz fill' => ['quiz', ['name' => '', 'intro' => '', 'questions' => []]],
        ];
    }

    /**
     * An empty question list is refused before the module is created.
     *
     * @dataProvider empty_cases_provider
     * @param string $modulename Module type.
     * @param array $data Generated data.
     */
    public function test_empty_question_list_creates_no_module(string $modulename, array $data): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        if ($modulename === 'simplequiz2' && \core_component::get_component_directory('mod_simplequiz2') === null) {
            $this->markTestSkipped('mod_simplequiz2 is not installed.');
        }
        $course = $this->getDataGenerator()->create_course();
        $dsl = $modulename === 'simplequiz2' ? $this->simplequiz_dsl() : $this->quiz_dsl();
        $before = $DB->count_records('course_modules', ['course' => $course->id]);

        try {
            (new interpreter())->execute($dsl, $data, interpreter::build_context((int) $course->id, 1, $modulename));
            $this->fail('empty_generation_exception expected');
        } catch (empty_generation_exception $e) {
            $this->assertSame('create_questions', $e->get_action_type());
        }

        $this->assertSame($before, $DB->count_records('course_modules', ['course' => $course->id]));
    }

    /**
     * A non-empty question list creates the quiz and its questions.
     */
    public function test_non_empty_question_list_proceeds(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $data = ['name' => 'Quiz', 'intro' => 'Intro', 'questions' => [[
            'question_type' => 'singlechoice',
            'question' => 'Q?',
            'answers' => ['A', 'B'],
            'rightanswer' => 0,
            'wrong_feedback' => 'W',
        ]]];
        set_debugging(DEBUG_NONE);
        $cmid = (new interpreter())->execute($this->quiz_dsl(), $data, interpreter::build_context((int) $course->id, 1, 'quiz'));
        set_debugging(DEBUG_DEVELOPER);

        $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
        $this->assertSame(1, $DB->count_records('quiz_slots', ['quizid' => $cm->instance]));
    }

    /**
     * A module type without a create_questions action is unaffected.
     */
    public function test_page_is_unaffected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $dsl = [['action' => 'create_module', 'save_as' => 'module', 'fields' => [
            'name' => ['source' => '$.name'], 'intro' => ['source' => '$.intro'], 'content' => ['source' => '$.content'],
        ]]];
        $data = ['name' => 'Page', 'intro' => 'Intro', 'content' => '<p>Body</p>', 'questions' => []];

        $cmid = (new interpreter())->execute($dsl, $data, interpreter::build_context((int) $course->id, 1, 'page'));

        $this->assertGreaterThan(0, $cmid);
    }
}
