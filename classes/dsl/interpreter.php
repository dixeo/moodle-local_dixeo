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
 * DSL interpreter for executing API-returned creation instructions.
 *
 * The interpreter processes an array of DSL actions returned by the Dixeo API
 * and executes them in order to create Moodle modules and their associated content.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\dsl;

use local_dixeo\dsl\actions\create_module_action;
use local_dixeo\dsl\actions\create_entries_action;
use local_dixeo\dsl\actions\create_h5p_module_action;
use local_dixeo\dsl\actions\create_questions_action;
use local_dixeo\dsl\actions\create_slides_action;
use local_dixeo\dsl\actions\create_questions_simplequiz2_action;
use local_dixeo\dsl\actions\create_assign_grading_action;

/**
 * Main DSL interpreter for module creation.
 *
 * Orchestrates the execution of DSL actions, maintaining variable state
 * between actions and delegating to specialized action handlers.
 *
 * Usage:
 * ```php
 * $interpreter = new interpreter();
 * $cmid = $interpreter->execute($actions, $aidata, $context);
 * ```
 */
class interpreter {
    /** @var array Saved variables from action execution (e.g., $module). */
    protected array $variables = [];

    /** @var create_module_action Action handler for create_module. */
    protected create_module_action $createmoduleaction;

    /** @var create_entries_action Action handler for create_entries. */
    protected create_entries_action $createentriesaction;

    /** @var create_questions_action Action handler for create_questions. */
    protected create_questions_action $createquestionsaction;

    /** @var create_slides_action Action handler for add_slides. */
    protected create_slides_action $createslidesaction;

    /** @var create_questions_simplequiz2_action Action handler for simplequiz2 questions. */
    protected create_questions_simplequiz2_action $createquestionssimplequiz2action;

    /** @var create_h5p_module_action Action handler for create_h5p_module. */
    protected create_h5p_module_action $createh5pmoduleaction;

    /** @var create_assign_grading_action Action handler for create_assign_grading. */
    protected create_assign_grading_action $createassigngradingaction;

    /**
     * Constructor.
     *
     * Initializes action handlers. Custom handlers can be injected for testing.
     *
     * @param create_module_action|null $createmoduleaction Custom module action handler.
     * @param create_entries_action|null $createentriesaction Custom entries action handler.
     * @param create_questions_action|null $createquestionsaction Custom questions action handler.
     * @param create_slides_action|null $createslidesaction Custom slides action handler.
     * @param create_questions_simplequiz2_action|null $createquestionssimplequiz2action Custom simplequiz2 questions action.
     * @param create_h5p_module_action|null $createh5pmoduleaction Custom H5P module action handler.
     * @param create_assign_grading_action|null $createassigngradingaction Custom assign grading action handler.
     */
    public function __construct(
        ?create_module_action $createmoduleaction = null,
        ?create_entries_action $createentriesaction = null,
        ?create_questions_action $createquestionsaction = null,
        ?create_slides_action $createslidesaction = null,
        ?create_questions_simplequiz2_action $createquestionssimplequiz2action = null,
        ?create_h5p_module_action $createh5pmoduleaction = null,
        ?create_assign_grading_action $createassigngradingaction = null
    ) {
        $this->createmoduleaction = $createmoduleaction ?? new create_module_action();
        $this->createentriesaction = $createentriesaction ?? new create_entries_action();
        $this->createquestionsaction = $createquestionsaction ?? new create_questions_action();
        $this->createslidesaction = $createslidesaction ?? new create_slides_action();
        $this->createquestionssimplequiz2action = $createquestionssimplequiz2action ?? new create_questions_simplequiz2_action();
        $this->createh5pmoduleaction = $createh5pmoduleaction ?? new create_h5p_module_action();
        $this->createassigngradingaction = $createassigngradingaction ?? new create_assign_grading_action();
    }

    /**
     * Execute a sequence of DSL actions.
     *
     * Processes each action in order, maintaining variable state between
     * actions. Returns the cmid of the created module.
     *
     * @param array $actions The array of DSL action specifications.
     * @param array $data The AI-generated data from the API response.
     * @param array $context Runtime context (courseid, sectionid, sectionnum, modulename, userid, etc.).
     * @return int The course module ID (cmid) of the created module.
     * @throws dsl_exception If execution fails.
     */
    public function execute(array $actions, array $data, array $context): int {
        // Reset variables for this execution.
        $this->variables = [];

        // Ensure we have the required module context.
        $modulecontext = $this->prepare_context($context);

        $cmid = 0;

        foreach ($actions as $index => $action) {
            if (!is_array($action)) {
                throw new dsl_exception(
                    "Action at index $index is not an array",
                    'interpreter',
                    ['index' => $index, 'type' => gettype($action)]
                );
            }

            if (!isset($action['action'])) {
                throw new dsl_exception(
                    "Action at index $index is missing 'action' field",
                    'interpreter',
                    ['index' => $index, 'action' => $action]
                );
            }

            // Execute the action and capture any result.
            $result = $this->execute_action($action, $data, $modulecontext);

            // If the action specifies save_as, store the result as a variable.
            if (isset($action['save_as']) && $result !== null) {
                $varname = $action['save_as'];
                $this->variables[$varname] = $result;

                // Track the cmid from the first module creation.
                if (
                    in_array($action['action'], ['create_module', 'create_h5p_module'], true)
                    && isset($result['cmid'])
                ) {
                    $cmid = (int) $result['cmid'];
                }
            }
        }

        // Return the cmid of the created module.
        if ($cmid === 0) {
            throw new dsl_exception(
                'No module was created by the DSL actions',
                'interpreter',
                ['actions_count' => count($actions)]
            );
        }

        return $cmid;
    }

    /**
     * Execute a single DSL action.
     *
     * @param array $action The action specification.
     * @param array $data The AI-generated data.
     * @param array $context The runtime context.
     * @return mixed The action result (varies by action type).
     * @throws dsl_exception If the action fails or is unknown.
     */
    protected function execute_action(array $action, array $data, array $context): mixed {
        $actiontype = $action['action'];

        // Create resolver with current state.
        $resolver = new value_resolver($data, $this->variables, $context);

        return match ($actiontype) {
            'create_module' => $this->createmoduleaction->execute($action, $resolver),
            'create_h5p_module' => $this->createh5pmoduleaction->execute($action, $resolver),
            'create_entries' => $this->createentriesaction->execute($action, $resolver),
            'create_questions' => $this->dispatch_create_questions($action, $resolver, $context),
            'add_slides' => $this->createslidesaction->execute($action, $resolver),
            'create_assign_grading' => $this->createassigngradingaction->execute($action, $resolver),
            default => throw dsl_exception::unknown_action($actiontype),
        };
    }

    /**
     * Dispatch create_questions to the appropriate handler based on module type.
     *
     * SimpleQuiz stores questions as JSON in a single field, while standard quiz
     * uses Moodle's question bank. This method routes to the correct handler.
     *
     * @param array $action The action specification.
     * @param value_resolver $resolver The value resolver.
     * @param array $context The runtime context.
     * @return mixed The action result.
     */
    protected function dispatch_create_questions(array $action, value_resolver $resolver, array $context): mixed {
        $modulename = $context['modulename'] ?? '';

        if ($modulename === 'simplequiz2') {
            return $this->createquestionssimplequiz2action->execute($action, $resolver);
        }

        // Default to standard quiz question handling.
        return $this->createquestionsaction->execute($action, $resolver);
    }

    /**
     * Prepare the execution context.
     *
     * Ensures all required context values are present and adds derived values.
     *
     * @param array $context The input context.
     * @return array The prepared context.
     * @throws dsl_exception If required values are missing.
     */
    protected function prepare_context(array $context): array {
        global $USER;

        // Validate required fields.
        $required = ['courseid', 'sectionid', 'sectionnum', 'modulename'];
        foreach ($required as $field) {
            if (!isset($context[$field])) {
                throw dsl_exception::missing_context($field);
            }
        }

        // Add derived/default values.
        if (!isset($context['userid'])) {
            $context['userid'] = $USER->id;
        }

        // Add the module context ID for question creation.
        if (!isset($context['contextid'])) {
            $coursecontext = \context_course::instance($context['courseid']);
            $context['contextid'] = $coursecontext->id;
        }

        return $context;
    }

    /**
     * Get the current variables.
     *
     * Useful for debugging or post-execution inspection.
     *
     * @return array The variables array.
     */
    public function get_variables(): array {
        return $this->variables;
    }

    /**
     * Get a specific variable by name.
     *
     * @param string $name The variable name.
     * @return mixed|null The variable value or null if not set.
     */
    public function get_variable(string $name): mixed {
        return $this->variables[$name] ?? null;
    }

    /**
     * Reset the interpreter state.
     *
     * Clears all stored variables. Useful for reusing the interpreter.
     *
     * @return self For method chaining.
     */
    public function reset(): self {
        $this->variables = [];
        return $this;
    }

    /**
     * Create a context array from common input parameters.
     *
     * Helper method to build the context array from typical input sources.
     *
     * @param int $courseid The course ID.
     * @param int $sectionnum The section number.
     * @param string $modulename The module type (page, quiz, glossary, etc.).
     * @param int|null $beforemod Optional course module ID to insert before.
     * @return array The context array ready for execute().
     * @throws dsl_exception If the section is not found.
     */
    public static function build_context(
        int $courseid,
        int $sectionnum,
        string $modulename,
        ?int $beforemod = null
    ): array {
        global $DB;

        // Get the section record.
        $section = $DB->get_record('course_sections', [
            'course' => $courseid,
            'section' => $sectionnum,
        ]);

        if (!$section) {
            // Fall back to the last section.
            $section = $DB->get_record_sql(
                'SELECT * FROM {course_sections} WHERE course = ? ORDER BY section DESC LIMIT 1',
                [$courseid]
            );
        }

        if (!$section) {
            throw new dsl_exception(
                "No section found for course $courseid",
                'interpreter',
                ['courseid' => $courseid, 'sectionnum' => $sectionnum]
            );
        }

        return [
            'courseid' => $courseid,
            'sectionid' => $section->id,
            'sectionnum' => $section->section,
            'modulename' => $modulename,
            'beforemod' => $beforemod,
        ];
    }
}
