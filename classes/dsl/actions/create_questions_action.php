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
 * DSL action for creating quiz questions.
 *
 * Creates quiz questions using Moodle's question bank API and links them
 * to the quiz via quiz slots. Supports multiple question types: truefalse,
 * singlechoice, multichoice, match, ordering, and gapselect.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\dsl\actions;

use local_dixeo\dsl\dsl_exception;
use local_dixeo\dsl\value_resolver;
use context_module;
use core_question\local\bank\question_version_status;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/lib/questionlib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * Action handler for creating quiz questions.
 *
 * Expected action format:
 * {
 *   "action": "create_questions",
 *   "module_ref": "$module",
 *   "foreach": "$.questions",
 *   "question_type": {"source": "$.question_type"},
 *   "fields": {
 *     "questiontext": {"source": "$.question"},
 *     "answers": {"source": "$.answers"},
 *     "correct_answer": {"source": "$.rightanswer"},
 *     "pairs": {"source": "$.pairs"},
 *     "items": {"source": "$.items"},
 *     "gaps": {"source": "$.gaps"},
 *     "wrong_feedback": {"source": "$.wrong_feedback"}
 *   }
 * }
 *
 * Supported question types:
 * - truefalse: True/False questions (rightanswer: 0=True, 1=False)
 * - singlechoice: Single correct answer (rightanswer: integer index)
 * - multichoice: Multiple correct answers (rightanswer: array of indices)
 * - match: Matching pairs (pairs: array of {item, match} objects)
 * - ordering: Order items correctly (items: array of strings in correct order)
 * - gapselect: Fill in blanks (questiontext with {{placeholders}}, gaps: array of {answer, distractors})
 */
class create_questions_action {
    use action_validation;

    /** @var float Fallback mark when no questions are created. */
    protected const DEFAULT_MARK = 1.0;

    /** @var float Penalty for incorrect answers. */
    protected const DEFAULT_PENALTY = 0.3333333;

    /** @var float Per-question mark for the current quiz (100 / question count). */
    protected float $questiondefaultmark = self::DEFAULT_MARK;

    /**
     * Execute the create_questions action.
     *
     * @param array $action The action specification.
     * @param value_resolver $resolver The value resolver.
     * @return array Array of created question IDs.
     * @throws dsl_exception If creation fails.
     */
    public function execute(array $action, value_resolver $resolver): array {
        global $DB, $CFG;

        $this->validate_action($action);

        // Resolve the module reference to get quiz info.
        $moduleref = $action['module_ref'];
        $moduledata = $resolver->resolve_source($moduleref, 'module_ref');

        if (!is_array($moduledata) || !isset($moduledata['id'])) {
            throw new dsl_exception(
                "module_ref did not resolve to valid module data",
                'create_questions',
                ['module_ref' => $moduleref]
            );
        }

        $quizid = (int) $moduledata['id'];
        $cm = $this->require_module_created_in_course(
            $resolver,
            'quiz',
            $quizid,
            isset($moduledata['cmid']) ? (int) $moduledata['cmid'] : null
        );
        $cmid = (int) $cm->id;
        $context = $resolver->get_context();
        $courseid = (int) $context['courseid'];

        // Get or create the question category for this quiz.
        $categoryid = $this->ensure_question_category($cmid, $courseid);

        // Resolve the questions collection.
        $foreachpath = $action['foreach'];
        $questions = $resolver->resolve_source($foreachpath, 'foreach');

        if (!is_array($questions)) {
            throw new dsl_exception(
                "foreach path '$foreachpath' did not resolve to an array",
                'create_questions',
                ['path' => $foreachpath]
            );
        }

        $questiontypespec = $action['question_type'] ?? 'multichoice';
        $fieldsspec = $action['fields'] ?? [];
        $createdids = [];

        // Preload all supported question type libraries.
        $this->load_question_type('multichoice');
        $this->load_question_type('truefalse');
        $this->load_question_type('match');
        $this->load_question_type('ordering');
        $this->load_question_type('gapselect');

        // Get quiz record for adding questions.
        $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
        $quiz->cmid = $cmid;

        $questioncount = count($questions);
        $this->questiondefaultmark = $questioncount > 0 ? (100.0 / $questioncount) : 100.0;

        foreach ($questions as $index => $questionitem) {
            $itemdata = is_object($questionitem) ? (array) $questionitem : $questionitem;

            if (!is_array($itemdata)) {
                throw new dsl_exception(
                    "Question at index $index is not an array or object",
                    'create_questions',
                    ['index' => $index]
                );
            }

            // Create resolver with this question's data for per-item field resolution.
            $itemresolver = $resolver->with_ai_data($itemdata);
            $resolvedfields = $itemresolver->resolve_fields($fieldsspec, true);

            // Resolve question_type per item (supports both static string and dynamic source).
            $questiontype = $this->resolve_question_type($questiontypespec, $itemresolver);

            // Create the question.
            $questionid = $this->create_question(
                $questiontype,
                $categoryid,
                $resolvedfields,
                $context
            );

            // Add question to quiz (slot maxmark: 100 / question count).
            \quiz_add_quiz_question($questionid, $quiz, 0, $this->questiondefaultmark);

            $createdids[] = $questionid;
        }

        return $createdids;
    }

    /**
     * Validate the action specification.
     *
     * @param array $action The action to validate.
     * @throws dsl_exception If validation fails.
     */
    protected function validate_action(array $action): void {
        $this->require_action_fields($action, ['module_ref', 'foreach'], 'create_questions');
    }

    /**
     * Ensure a question category exists for the quiz.
     *
     * Creates a new category if one doesn't exist.
     *
     * @param int $cmid The course module ID.
     * @param int $courseid The course ID.
     * @return int The question category ID.
     */
    protected function ensure_question_category(int $cmid, int $courseid): int {
        global $DB;

        $modulecontext = context_module::instance($cmid);

        // Check if a category already exists for this context.
        $existingcategory = $DB->get_record('question_categories', [
            'contextid' => $modulecontext->id,
        ]);

        if ($existingcategory) {
            return $existingcategory->id;
        }

        // Create a new category.
        $category = new \stdClass();
        $category->name = get_string('defaultcategory', 'question');
        $category->info = '';
        $category->infoformat = FORMAT_HTML;
        $category->contextid = $modulecontext->id;
        $category->parent = 0;
        $category->sortorder = 999;
        $category->stamp = make_unique_id_code();
        $category->idnumber = null;

        return $DB->insert_record('question_categories', $category);
    }

    /**
     * Load the question type library.
     *
     * @param string $qtype The question type.
     * @throws dsl_exception If the question type is not found.
     */
    protected function load_question_type(string $qtype): void {
        global $CFG;

        $qtypepath = $CFG->dirroot . '/question/type/' . $qtype . '/questiontype.php';
        if (!file_exists($qtypepath)) {
            throw new dsl_exception(
                "Question type '$qtype' not found",
                'create_questions',
                ['qtype' => $qtype, 'path' => $qtypepath]
            );
        }

        require_once($qtypepath);
    }

    /**
     * Create a question using the question bank API.
     *
     * Routes to type-specific handlers based on the question type.
     * singlechoice and multichoice both use Moodle's multichoice qtype
     * with different configuration (single=1 vs single=0).
     *
     * @param string $qtype The question type (truefalse, singlechoice, multichoice).
     * @param int $categoryid The question category ID.
     * @param array $fields The resolved field values.
     * @param array $context The runtime context.
     * @return int The created question ID.
     * @throws dsl_exception If creation fails.
     */
    protected function create_question(
        string $qtype,
        int $categoryid,
        array $fields,
        array $context
    ): int {
        // Route to type-specific handler.
        return match ($qtype) {
            'truefalse' => $this->create_truefalse_question($categoryid, $fields, $context),
            'singlechoice' => $this->create_multichoice_question($categoryid, $fields, $context, true),
            'multichoice' => $this->create_multichoice_question($categoryid, $fields, $context, false),
            'match' => $this->create_match_question($categoryid, $fields, $context),
            'ordering' => $this->create_ordering_question($categoryid, $fields, $context),
            'gapselect' => $this->create_gapselect_question($categoryid, $fields, $context),
            default => throw new dsl_exception(
                "Unsupported question type '$qtype'",
                'create_questions',
                ['qtype' => $qtype]
            ),
        };
    }

    /**
     * Create a multiple choice question.
     *
     * Handles both single-answer (singlechoice) and multiple-answer (multichoice) modes.
     * Both use Moodle's multichoice qtype with different 'single' setting.
     *
     * @param int $categoryid The question category ID.
     * @param array $fields The resolved fields (questiontext, answers, correct_answer).
     * @param array $context The runtime context.
     * @param bool $singleanswer True for single correct answer, false for multiple.
     * @return int The created question ID.
     * @throws dsl_exception If creation fails.
     */
    protected function create_multichoice_question(
        int $categoryid,
        array $fields,
        array $context,
        bool $singleanswer = true
    ): int {
        global $USER;

        $questiontext = $fields['questiontext'] ?? '';
        $answers = $fields['answers'] ?? [];
        $correctanswer = $fields['correct_answer'] ?? 0;
        $wrongfeedback = $fields['wrong_feedback'] ?? '';

        if (!is_array($answers) || count($answers) < 2) {
            throw new dsl_exception(
                'Multiple choice question requires at least 2 answers',
                'create_questions',
                ['answers_count' => is_array($answers) ? count($answers) : 0]
            );
        }

        // Build the question form data structure.
        $formdata = $this->build_multichoice_form_data(
            $categoryid,
            $questiontext,
            $answers,
            $correctanswer,
            $wrongfeedback,
            $context,
            $singleanswer
        );

        // Get the question type handler.
        $qtypeobj = \question_bank::get_qtype('multichoice');

        // Create a blank question to save into.
        $question = new \stdClass();
        $question->category = $categoryid;
        $question->qtype = 'multichoice';
        $question->createdby = $context['userid'] ?? $USER->id;
        $question->modifiedby = $context['userid'] ?? $USER->id;

        // Use the qtype's save_question method.
        $savedquestion = $qtypeobj->save_question($question, $formdata);

        if (!$savedquestion || !isset($savedquestion->id)) {
            throw new dsl_exception(
                'Failed to save multichoice question',
                'create_questions',
                ['questiontext' => substr($questiontext, 0, 100)]
            );
        }

        return $savedquestion->id;
    }

    /**
     * Build the form data structure for a multichoice question.
     *
     * @param int $categoryid The question category ID.
     * @param string $questiontext The question text.
     * @param array $answers The answer choices.
     * @param int|string|array $correctanswer The correct answer(s) - index, text, or array of indices.
     * @param string $wrongfeedback Feedback shown when answer is wrong.
     * @param array $context The runtime context.
     * @param bool $singleanswer True for single answer mode, false for multiple answers.
     * @return \stdClass The form data object.
     */
    protected function build_multichoice_form_data(
        int $categoryid,
        string $questiontext,
        array $answers,
        int|string|array $correctanswer,
        string $wrongfeedback,
        array $context,
        bool $singleanswer = true
    ): \stdClass {
        $form = new \stdClass();

        // Basic question properties.
        $form->category = $categoryid . ',' . $context['contextid'];
        $form->name = $this->generate_question_name($questiontext);
        $form->questiontext = [
            'text' => $questiontext,
            'format' => FORMAT_HTML,
        ];
        $form->generalfeedback = [
            'text' => '',
            'format' => FORMAT_HTML,
        ];
        $form->defaultmark = $this->questiondefaultmark;
        $form->penalty = self::DEFAULT_PENALTY;
        $form->status = question_version_status::QUESTION_STATUS_READY;

        // Multichoice-specific options: single=1 for one answer, single=0 for multiple.
        $form->single = $singleanswer ? 1 : 0;
        $form->shuffleanswers = 1;
        $form->answernumbering = 'abc';
        $form->showstandardinstruction = 0;

        // Feedback for correct/incorrect/partial answers.
        $correctfeedbacktext = get_string('feedback_correct', 'local_dixeo');
        $form->correctfeedback = ['text' => $correctfeedbacktext, 'format' => FORMAT_HTML];
        $form->partiallycorrectfeedback = ['text' => $wrongfeedback, 'format' => FORMAT_HTML];
        $form->incorrectfeedback = ['text' => $wrongfeedback, 'format' => FORMAT_HTML];
        $form->shownumcorrect = 0;

        // Build answers array.
        $form->answer = [];
        $form->fraction = [];
        $form->feedback = [];

        // Determine correct answer indices and fractions.
        $correctindices = $this->resolve_correct_answer_indices($answers, $correctanswer, $singleanswer);
        $correctcount = count($correctindices);

        // For multiple answers, distribute fractions equally among correct answers.
        $correctfraction = $correctcount > 0 ? (1.0 / $correctcount) : 0.0;

        foreach ($answers as $index => $answertext) {
            $form->answer[$index] = [
                'text' => is_string($answertext) ? $answertext : (string) $answertext,
                'format' => FORMAT_HTML,
            ];

            // Set fraction based on whether this answer is correct.
            $iscorrect = in_array($index, $correctindices, true);
            if ($singleanswer) {
                // Single answer: 1.0 for correct, 0.0 for incorrect.
                $form->fraction[$index] = $iscorrect ? '1.0' : '0.0';
            } else {
                // Multiple answers: distribute fraction among correct, 0.0 for incorrect.
                $form->fraction[$index] = $iscorrect ? (string) $correctfraction : '0.0';
            }

            $form->feedback[$index] = [
                'text' => '',
                'format' => FORMAT_HTML,
            ];
        }

        // Ensure we have enough answer slots.
        $form->noanswers = count($answers);

        return $form;
    }

    /**
     * Generate a question name from the question text.
     *
     * @param string $questiontext The full question text.
     * @return string A shortened name suitable for display.
     */
    protected function generate_question_name(string $questiontext): string {
        $stripped = strip_tags($questiontext);
        $name = shorten_text($stripped, 50);

        if (empty($name)) {
            $name = get_string('question', 'question');
        }

        return $name;
    }

    /**
     * Resolve correct answer(s) to an array of indices.
     *
     * Handles integer index, string text matching, and array of indices.
     * For multichoice, expects an array of correct indices.
     * For singlechoice, expects a single index (wrapped in array for uniform handling).
     *
     * @param array $answers The answer choices.
     * @param int|string|array $correctanswer The correct answer specification.
     * @param bool $singleanswer Whether this is single answer mode.
     * @return array Array of correct answer indices.
     */
    protected function resolve_correct_answer_indices(
        array $answers,
        int|string|array $correctanswer,
        bool $singleanswer
    ): array {
        // Handle array of correct answers (multichoice mode).
        if (is_array($correctanswer)) {
            $indices = [];
            foreach ($correctanswer as $item) {
                $resolved = $this->resolve_single_answer_index($answers, $item);
                if ($resolved !== null) {
                    $indices[] = $resolved;
                }
            }
            return !empty($indices) ? $indices : [0];
        }

        // Single answer: resolve and wrap in array.
        $index = $this->resolve_single_answer_index($answers, $correctanswer);
        return [$index ?? 0];
    }

    /**
     * Resolve a single correct answer specification to an index.
     *
     * @param array $answers The answer choices.
     * @param int|string $correctanswer The correct answer (index or text).
     * @return int|null The resolved index, or null if not found.
     */
    protected function resolve_single_answer_index(array $answers, int|string $correctanswer): ?int {
        // If it's already a valid index, use it.
        if (is_int($correctanswer) && isset($answers[$correctanswer])) {
            return $correctanswer;
        }

        // If it's a string, try to find a matching answer.
        if (is_string($correctanswer)) {
            // First check if it's a numeric string.
            if (is_numeric($correctanswer)) {
                $index = (int) $correctanswer;
                if (isset($answers[$index])) {
                    return $index;
                }
            }

            // Try to match by answer text.
            foreach ($answers as $index => $answertext) {
                if (strcasecmp($answertext, $correctanswer) === 0) {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * Resolve question type from spec (supports static string or dynamic source).
     *
     * @param string|array $spec The question_type specification.
     * @param value_resolver $itemresolver The resolver for the current item.
     * @return string The resolved question type.
     */
    protected function resolve_question_type(string|array $spec, value_resolver $itemresolver): string {
        // Static string: return as-is.
        if (is_string($spec)) {
            return $spec;
        }

        // Dynamic source: resolve using item resolver.
        if (is_array($spec) && isset($spec['source'])) {
            $resolved = $itemresolver->resolve_source($spec['source'], 'question_type');
            return is_string($resolved) ? $resolved : 'multichoice';
        }

        // Default fallback.
        return 'multichoice';
    }

    /**
     * Create a true/false question.
     *
     * @param int $categoryid The question category ID.
     * @param array $fields The resolved fields (questiontext, correct_answer, wrong_feedback).
     * @param array $context The runtime context.
     * @return int The created question ID.
     * @throws dsl_exception If creation fails.
     */
    protected function create_truefalse_question(
        int $categoryid,
        array $fields,
        array $context
    ): int {
        global $USER;

        $questiontext = $fields['questiontext'] ?? '';
        $correctanswer = $fields['correct_answer'] ?? 0;
        $wrongfeedback = $fields['wrong_feedback'] ?? '';

        // Build the question form data structure.
        $formdata = $this->build_truefalse_form_data(
            $categoryid,
            $questiontext,
            $correctanswer,
            $wrongfeedback,
            $context
        );

        // Get the question type handler.
        $qtypeobj = \question_bank::get_qtype('truefalse');

        // Create a blank question to save into.
        $question = new \stdClass();
        $question->category = $categoryid;
        $question->qtype = 'truefalse';
        $question->createdby = $context['userid'] ?? $USER->id;
        $question->modifiedby = $context['userid'] ?? $USER->id;

        // Use the qtype's save_question method.
        $savedquestion = $qtypeobj->save_question($question, $formdata);

        if (!$savedquestion || !isset($savedquestion->id)) {
            throw new dsl_exception(
                'Failed to save truefalse question',
                'create_questions',
                ['questiontext' => substr($questiontext, 0, 100)]
            );
        }

        return $savedquestion->id;
    }

    /**
     * Build the form data structure for a truefalse question.
     *
     * Moodle truefalse uses 'correctanswer' field: 1 = True, 0 = False.
     * API sends rightanswer: 0 = True is correct, 1 = False is correct.
     *
     * @param int $categoryid The question category ID.
     * @param string $questiontext The question text.
     * @param int|string $correctanswer The correct answer (0=True, 1=False from API).
     * @param string $wrongfeedback Feedback shown when answer is wrong.
     * @param array $context The runtime context.
     * @return \stdClass The form data object.
     */
    protected function build_truefalse_form_data(
        int $categoryid,
        string $questiontext,
        int|string $correctanswer,
        string $wrongfeedback,
        array $context
    ): \stdClass {
        $form = new \stdClass();

        // Basic question properties.
        $form->category = $categoryid . ',' . $context['contextid'];
        $form->name = $this->generate_question_name($questiontext);
        $form->questiontext = [
            'text' => $questiontext,
            'format' => FORMAT_HTML,
        ];
        $form->generalfeedback = [
            'text' => '',
            'format' => FORMAT_HTML,
        ];
        $form->defaultmark = $this->questiondefaultmark;
        $form->penalty = self::DEFAULT_PENALTY;
        $form->status = question_version_status::QUESTION_STATUS_READY;

        // Convert API format (0=True, 1=False) to Moodle format (1=True, 0=False).
        // API: rightanswer 0 means "True" is correct, rightanswer 1 means "False" is correct.
        // Moodle: correctanswer 1 means "True" is correct, correctanswer 0 means "False" is correct.
        $answerindex = is_numeric($correctanswer) ? (int) $correctanswer : 0;
        $trueiscorrect = ($answerindex === 0);
        $form->correctanswer = $trueiscorrect ? 1 : 0;

        // Feedback: "Correct!" for the right answer, wrong_feedback for the wrong answer.
        $correctfeedbacktext = get_string('feedback_correct', 'local_dixeo');
        $form->feedbacktrue = [
            'text' => $trueiscorrect ? $correctfeedbacktext : $wrongfeedback,
            'format' => FORMAT_HTML,
        ];
        $form->feedbackfalse = [
            'text' => $trueiscorrect ? $wrongfeedback : $correctfeedbacktext,
            'format' => FORMAT_HTML,
        ];

        return $form;
    }

    /**
     * Create a matching question.
     *
     * @param int $categoryid The question category ID.
     * @param array $fields The resolved fields (questiontext, pairs, wrong_feedback).
     * @param array $context The runtime context.
     * @return int The created question ID.
     * @throws dsl_exception If creation fails.
     */
    protected function create_match_question(
        int $categoryid,
        array $fields,
        array $context
    ): int {
        global $USER;

        $questiontext = $fields['questiontext'] ?? '';
        $pairs = $fields['pairs'] ?? [];
        $wrongfeedback = $fields['wrong_feedback'] ?? '';

        if (!is_array($pairs) || count($pairs) < 2) {
            throw new dsl_exception(
                'Match question requires at least 2 pairs',
                'create_questions',
                ['pairs_count' => is_array($pairs) ? count($pairs) : 0]
            );
        }

        // Build the question form data structure.
        $formdata = $this->build_match_form_data(
            $categoryid,
            $questiontext,
            $pairs,
            $wrongfeedback,
            $context
        );

        // Get the question type handler.
        $qtypeobj = \question_bank::get_qtype('match');

        // Create a blank question to save into.
        $question = new \stdClass();
        $question->category = $categoryid;
        $question->qtype = 'match';
        $question->createdby = $context['userid'] ?? $USER->id;
        $question->modifiedby = $context['userid'] ?? $USER->id;

        // Use the qtype's save_question method.
        $savedquestion = $qtypeobj->save_question($question, $formdata);

        if (!$savedquestion || !isset($savedquestion->id)) {
            throw new dsl_exception(
                'Failed to save match question',
                'create_questions',
                ['questiontext' => substr($questiontext, 0, 100)]
            );
        }

        return $savedquestion->id;
    }

    /**
     * Build the form data structure for a match question.
     *
     * @param int $categoryid The question category ID.
     * @param string $questiontext The question text (instructions).
     * @param array $pairs Array of pairs with 'item' and 'match' keys.
     * @param string $wrongfeedback Feedback shown when answer is wrong.
     * @param array $context The runtime context.
     * @return \stdClass The form data object.
     */
    protected function build_match_form_data(
        int $categoryid,
        string $questiontext,
        array $pairs,
        string $wrongfeedback,
        array $context
    ): \stdClass {
        $form = new \stdClass();

        // Basic question properties.
        $form->category = $categoryid . ',' . $context['contextid'];
        $form->name = $this->generate_question_name($questiontext);
        $form->questiontext = [
            'text' => $questiontext,
            'format' => FORMAT_HTML,
        ];
        $form->generalfeedback = [
            'text' => '',
            'format' => FORMAT_HTML,
        ];
        $form->defaultmark = $this->questiondefaultmark;
        $form->penalty = self::DEFAULT_PENALTY;
        $form->status = question_version_status::QUESTION_STATUS_READY;

        // Match-specific options.
        $form->shuffleanswers = 1;

        // Combined feedback.
        $correctfeedbacktext = get_string('feedback_correct', 'local_dixeo');
        $form->correctfeedback = ['text' => $correctfeedbacktext, 'format' => FORMAT_HTML];
        $form->partiallycorrectfeedback = ['text' => $wrongfeedback, 'format' => FORMAT_HTML];
        $form->incorrectfeedback = ['text' => $wrongfeedback, 'format' => FORMAT_HTML];
        $form->shownumcorrect = 0;

        // Build subquestions and subanswers arrays.
        $form->subquestions = [];
        $form->subanswers = [];

        foreach ($pairs as $index => $pair) {
            $item = is_array($pair) ? ($pair['item'] ?? '') : '';
            $match = is_array($pair) ? ($pair['match'] ?? '') : '';

            $form->subquestions[$index] = [
                'text' => $item,
                'format' => FORMAT_HTML,
            ];
            $form->subanswers[$index] = $match;
        }

        return $form;
    }

    /**
     * Create an ordering question.
     *
     * @param int $categoryid The question category ID.
     * @param array $fields The resolved fields (questiontext, items, wrong_feedback).
     * @param array $context The runtime context.
     * @return int The created question ID.
     * @throws dsl_exception If creation fails.
     */
    protected function create_ordering_question(
        int $categoryid,
        array $fields,
        array $context
    ): int {
        global $USER;

        $questiontext = $fields['questiontext'] ?? '';
        $items = $fields['items'] ?? [];
        $wrongfeedback = $fields['wrong_feedback'] ?? '';

        if (!is_array($items) || count($items) < 2) {
            throw new dsl_exception(
                'Ordering question requires at least 2 items',
                'create_questions',
                ['items_count' => is_array($items) ? count($items) : 0]
            );
        }

        // Build the question form data structure.
        $formdata = $this->build_ordering_form_data(
            $categoryid,
            $questiontext,
            $items,
            $wrongfeedback,
            $context
        );

        // Get the question type handler.
        $qtypeobj = \question_bank::get_qtype('ordering');

        // Create a blank question to save into.
        $question = new \stdClass();
        $question->category = $categoryid;
        $question->qtype = 'ordering';
        $question->createdby = $context['userid'] ?? $USER->id;
        $question->modifiedby = $context['userid'] ?? $USER->id;

        // Use the qtype's save_question method.
        $savedquestion = $qtypeobj->save_question($question, $formdata);

        if (!$savedquestion || !isset($savedquestion->id)) {
            throw new dsl_exception(
                'Failed to save ordering question',
                'create_questions',
                ['questiontext' => substr($questiontext, 0, 100)]
            );
        }

        return $savedquestion->id;
    }

    /**
     * Build the form data structure for an ordering question.
     *
     * @param int $categoryid The question category ID.
     * @param string $questiontext The question text (instructions).
     * @param array $items Array of items in correct order.
     * @param string $wrongfeedback Feedback shown when answer is wrong.
     * @param array $context The runtime context.
     * @return \stdClass The form data object.
     */
    protected function build_ordering_form_data(
        int $categoryid,
        string $questiontext,
        array $items,
        string $wrongfeedback,
        array $context
    ): \stdClass {
        $form = new \stdClass();

        // Basic question properties.
        $form->category = $categoryid . ',' . $context['contextid'];
        $form->name = $this->generate_question_name($questiontext);
        $form->questiontext = [
            'text' => $questiontext,
            'format' => FORMAT_HTML,
        ];
        $form->generalfeedback = [
            'text' => '',
            'format' => FORMAT_HTML,
        ];
        $form->defaultmark = $this->questiondefaultmark;
        $form->penalty = self::DEFAULT_PENALTY;
        $form->status = question_version_status::QUESTION_STATUS_READY;

        // Ordering-specific options.
        $form->layouttype = 0;          // VERTICAL.
        $form->selecttype = 0;          // SELECT_ALL.
        $form->selectcount = count($items);
        $form->gradingtype = 0;         // RELATIVE_NEXT_EXCLUDE_LAST.
        $form->showgrading = 1;
        $form->numberingstyle = 'none';

        // Combined feedback.
        $correctfeedbacktext = get_string('feedback_correct', 'local_dixeo');
        $form->correctfeedback = ['text' => $correctfeedbacktext, 'format' => FORMAT_HTML];
        $form->partiallycorrectfeedback = ['text' => $wrongfeedback, 'format' => FORMAT_HTML];
        $form->incorrectfeedback = ['text' => $wrongfeedback, 'format' => FORMAT_HTML];
        $form->shownumcorrect = 0;

        // Build answer array - items in correct order.
        $form->answer = [];
        foreach ($items as $index => $item) {
            $form->answer[$index] = [
                'text' => is_string($item) ? $item : (string) $item,
                'format' => FORMAT_HTML,
            ];
        }

        return $form;
    }

    /**
     * Create a gapselect (select missing words) question.
     *
     * @param int $categoryid The question category ID.
     * @param array $fields The resolved fields (questiontext, gaps, wrong_feedback).
     * @param array $context The runtime context.
     * @return int The created question ID.
     * @throws dsl_exception If creation fails.
     */
    protected function create_gapselect_question(
        int $categoryid,
        array $fields,
        array $context
    ): int {
        global $USER;

        $questiontext = $fields['questiontext'] ?? '';
        $gaps = $fields['gaps'] ?? [];
        $wrongfeedback = $fields['wrong_feedback'] ?? '';

        if (!is_array($gaps) || count($gaps) < 1) {
            throw new dsl_exception(
                'Gapselect question requires at least 1 gap',
                'create_questions',
                ['gaps_count' => is_array($gaps) ? count($gaps) : 0]
            );
        }

        // Build the question form data structure.
        $formdata = $this->build_gapselect_form_data(
            $categoryid,
            $questiontext,
            $gaps,
            $wrongfeedback,
            $context
        );

        // Get the question type handler.
        $qtypeobj = \question_bank::get_qtype('gapselect');

        // Create a blank question to save into.
        $question = new \stdClass();
        $question->category = $categoryid;
        $question->qtype = 'gapselect';
        $question->createdby = $context['userid'] ?? $USER->id;
        $question->modifiedby = $context['userid'] ?? $USER->id;

        // Use the qtype's save_question method.
        $savedquestion = $qtypeobj->save_question($question, $formdata);

        if (!$savedquestion || !isset($savedquestion->id)) {
            throw new dsl_exception(
                'Failed to save gapselect question',
                'create_questions',
                ['questiontext' => substr($questiontext, 0, 100)]
            );
        }

        return $savedquestion->id;
    }

    /**
     * Build the form data structure for a gapselect question.
     *
     * Converts {{placeholder}} format to [[n]] format and builds choices array.
     * Each gap gets its own choice group to ensure correct matching.
     *
     * @param int $categoryid The question category ID.
     * @param string $questiontext The question text with {{placeholders}}.
     * @param array $gaps Array of gaps with 'answer' and 'distractors' keys.
     * @param string $wrongfeedback Feedback shown when answer is wrong.
     * @param array $context The runtime context.
     * @return \stdClass The form data object.
     */
    protected function build_gapselect_form_data(
        int $categoryid,
        string $questiontext,
        array $gaps,
        string $wrongfeedback,
        array $context
    ): \stdClass {
        $form = new \stdClass();

        // Process question text and build choices.
        $processedtext = $questiontext;
        $choices = [];
        $choiceindex = 0;

        foreach ($gaps as $gapindex => $gap) {
            $answer = is_array($gap) ? ($gap['answer'] ?? '') : '';
            $distractors = is_array($gap) ? ($gap['distractors'] ?? []) : [];

            // Ensure distractors is an array.
            if (is_string($distractors)) {
                $distractors = array_map('trim', explode(',', $distractors));
            }

            // Build all options for this gap (answer + distractors).
            $alloptions = array_merge([$answer], $distractors);
            shuffle($alloptions);

            // Each gap gets its own choice group (1-indexed).
            $choicegroup = $gapindex + 1;

            // Find the position of the correct answer in shuffled options.
            $correctposition = null;
            foreach ($alloptions as $optindex => $option) {
                $choices[$choiceindex] = [
                    'answer' => $option,
                    'choicegroup' => $choicegroup,
                ];

                if ($option === $answer) {
                    // Moodle gapselect uses 1-based placeholder indexes in the question text.
                    $correctposition = $choiceindex + 1;
                }

                $choiceindex++;
            }

            // Replace the next curly-brace placeholder with the correct choice index token.
            // Uses positional replacement (first gap = first placeholder) instead of string
            // matching, which is more robust when answers contain special characters.
            if ($correctposition !== null) {
                $processedtext = preg_replace('/\{\{[^}]+\}\}/', '[[' . $correctposition . ']]', $processedtext, 1);
            }
        }

        // Basic question properties.
        $form->category = $categoryid . ',' . $context['contextid'];
        $form->name = $this->generate_question_name($questiontext);
        $form->questiontext = [
            'text' => $processedtext,
            'format' => FORMAT_HTML,
        ];
        $form->generalfeedback = [
            'text' => '',
            'format' => FORMAT_HTML,
        ];
        $form->defaultmark = $this->questiondefaultmark;
        $form->penalty = self::DEFAULT_PENALTY;
        $form->status = question_version_status::QUESTION_STATUS_READY;

        // Gapselect-specific options.
        $form->shuffleanswers = 1;

        // Combined feedback.
        $correctfeedbacktext = get_string('feedback_correct', 'local_dixeo');
        $form->correctfeedback = ['text' => $correctfeedbacktext, 'format' => FORMAT_HTML];
        $form->partiallycorrectfeedback = ['text' => $wrongfeedback, 'format' => FORMAT_HTML];
        $form->incorrectfeedback = ['text' => $wrongfeedback, 'format' => FORMAT_HTML];
        $form->shownumcorrect = 0;

        // Set choices array.
        $form->choices = $choices;

        return $form;
    }
}
