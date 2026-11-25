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
use local_dixeo\util\generated_choices;
use local_dixeo\util\multichoice_fractions;
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
 *     "choices": {"source": "$.choices"},
 *     "answers": {"source": "$.answers"},
 *     "correct_answer": {"source": "$.rightanswer"},
 *     "pairs": {"source": "$.pairs"},
 *     "items": {"source": "$.items"},
 *     "gaps": {"source": "$.gaps"},
 *     "wrong_feedback": {"source": "$.wrong_feedback"},
 *     "partial_feedback": {"source": "$.partial_feedback"}
 *   }
 * }
 *
 * Choice questions read `choices` ({text, correct, feedback} per option) when the payload carries a valid list,
 * and the legacy `answers` / `correct_answer` fields otherwise. Every generated string stored in a question is
 * passed through clean_text(), because Moodle renders question parts without further cleaning.
 *
 * Supported question types:
 * - truefalse: True/False questions (rightanswer: 0=True, 1=False)
 * - singlechoice: Single correct answer (rightanswer: integer index)
 * - multichoice: Multiple correct answers (rightanswer: array of indices); each wrong option is graded negatively
 *   (see multichoice_fractions)
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

    /** @var int Most options a generated multiple choice question may store. */
    protected const MAX_CHOICES = 8;

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

        // The quiz's default category, created under the top category Moodle requires for backup and restore.
        // Moodle 4.5 has no $createifnotexists and returns false, so it makes the categories the older way.
        $modulecontext = context_module::instance($cmid);
        $category = question_get_default_category($modulecontext->id, true)
            ?: question_make_default_categories([$modulecontext]);
        $categoryid = (int) $category->id;

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
        $choices = generated_choices::normalise($fields['choices'] ?? null, self::MAX_CHOICES);

        if ($choices !== null) {
            $answers = array_column($choices, 'text');
            $feedbacks = array_column($choices, 'feedback');
            $correctindices = array_keys(array_filter(array_column($choices, 'correct')));
        } else {
            // LEGACY-QUIZ-FIELDS: Dixeo API responses without `choices` carry the question only
            // in `answers` / `rightanswer`. Remove this fallback once the API no longer
            // emits the legacy fields.
            $answers = $fields['answers'] ?? [];
            if (!is_array($answers) || count($answers) < 2) {
                throw new dsl_exception(
                    'Multiple choice question requires at least 2 answers',
                    'create_questions',
                    ['answers_count' => is_array($answers) ? count($answers) : 0]
                );
            }
            $answers = array_values($answers);
            $feedbacks = [];
            $correctindices = array_values(array_unique(
                $this->resolve_correct_answer_indices($answers, $fields['correct_answer'] ?? 0, $singleanswer)
            ));
        }

        // More than one correct option can only be stored in multiple-answer mode, whatever the label says.
        if (count($correctindices) > 1) {
            $singleanswer = false;
        }

        // Build the question form data structure.
        $formdata = $this->build_multichoice_form_data(
            $categoryid,
            $this->generated_text($questiontext),
            $answers,
            $correctindices,
            $feedbacks,
            $this->generated_text($fields['wrong_feedback'] ?? ''),
            $this->partial_feedback_text($fields),
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
                ['questiontext' => substr((string) $questiontext, 0, 100)]
            );
        }

        return $savedquestion->id;
    }

    /**
     * Build the form data structure for a multichoice question.
     *
     * @param int $categoryid The question category ID.
     * @param string $questiontext The question text, already cleaned.
     * @param array $answers The answer choices, as generated (cleaned here).
     * @param int[] $correctindices Zero-based indices of the correct answers.
     * @param array $feedbacks Generated feedback per answer index (cleaned here); missing entries are empty.
     * @param string $wrongfeedback Incorrect-answer feedback, already cleaned.
     * @param string $partialfeedback Partially-correct feedback, already cleaned.
     * @param array $context The runtime context.
     * @param bool $singleanswer True for single answer mode, false for multiple answers.
     * @return \stdClass The form data object.
     * @throws dsl_exception If the answers cannot be graded.
     */
    protected function build_multichoice_form_data(
        int $categoryid,
        string $questiontext,
        array $answers,
        array $correctindices,
        array $feedbacks,
        string $wrongfeedback,
        string $partialfeedback,
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
        $form->partiallycorrectfeedback = ['text' => $partialfeedback, 'format' => FORMAT_HTML];
        $form->incorrectfeedback = ['text' => $wrongfeedback, 'format' => FORMAT_HTML];
        $form->shownumcorrect = 0;

        $form->answer = [];
        $form->fraction = $this->multichoice_fractions(count($answers), $correctindices, $singleanswer);
        $form->feedback = [];

        foreach (array_values($answers) as $index => $answertext) {
            $form->answer[$index] = [
                'text' => $this->generated_text($answertext),
                'format' => FORMAT_HTML,
            ];
            $form->feedback[$index] = [
                'text' => $this->generated_text($feedbacks[$index] ?? ''),
                'format' => FORMAT_HTML,
            ];
        }

        // Ensure we have enough answer slots.
        $form->noanswers = count($answers);

        return $form;
    }

    /**
     * Fractions for the answers of a multichoice question.
     *
     * Questions with 2 to 8 answers use multichoice_fractions (wrong options graded negatively in multiple-answer
     * mode). Larger legacy payloads keep the plain grading: 1/C for each correct answer and 0 for the others.
     *
     * @param int $count Number of answers.
     * @param int[] $correctindices Zero-based indices of the correct answers.
     * @param bool $singleanswer True for single answer mode.
     * @return array<int, string> Fraction per answer index.
     * @throws dsl_exception If the fractions cannot be computed.
     */
    protected function multichoice_fractions(int $count, array $correctindices, bool $singleanswer): array {
        if ($count >= 2 && $count <= self::MAX_CHOICES) {
            try {
                return multichoice_fractions::for_options($count, $correctindices, $singleanswer);
            } catch (\coding_exception $e) {
                throw new dsl_exception(
                    'Multiple choice answers cannot be graded',
                    'create_questions',
                    ['answers_count' => $count, 'correct_count' => count($correctindices)]
                );
            }
        }

        $correctfraction = count($correctindices) > 0 ? (string) (1.0 / count($correctindices)) : '0.0';
        $fractions = [];
        for ($index = 0; $index < $count; $index++) {
            $iscorrect = in_array($index, $correctindices, true);
            $fractions[$index] = $iscorrect ? ($singleanswer ? '1.0' : $correctfraction) : '0.0';
        }
        return $fractions;
    }

    /**
     * Clean a generated string for storage in a question.
     *
     * Moodle renders question parts with noclean, so generated text gets the HTMLPurifier allowlist here.
     *
     * @param mixed $value Generated value.
     * @return string Cleaned HTML, or '' for a non-scalar value.
     */
    protected function generated_text(mixed $value): string {
        if (!is_scalar($value)) {
            return '';
        }
        return clean_text((string) $value, FORMAT_HTML);
    }

    /**
     * Partially-correct feedback: the generated partial_feedback, or wrong_feedback when it is empty.
     *
     * @param array $fields The resolved fields.
     * @return string Cleaned HTML.
     */
    protected function partial_feedback_text(array $fields): string {
        $partial = $fields['partial_feedback'] ?? null;
        if (is_string($partial) && trim($partial) !== '') {
            return $this->generated_text($partial);
        }
        return $this->generated_text($fields['wrong_feedback'] ?? '');
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
        $choices = generated_choices::normalise($fields['choices'] ?? null, 2);

        // Build the question form data structure.
        $formdata = $this->build_truefalse_form_data(
            $categoryid,
            $this->generated_text($questiontext),
            $correctanswer,
            $this->generated_text($fields['wrong_feedback'] ?? ''),
            $context,
            $choices === null ? '' : $this->generated_text($choices[0]['feedback']),
            $choices === null ? '' : $this->generated_text($choices[1]['feedback'])
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
                ['questiontext' => substr((string) $questiontext, 0, 100)]
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
     * @param string $questiontext The question text, already cleaned.
     * @param int|string $correctanswer The correct answer (0=True, 1=False from API).
     * @param string $wrongfeedback Feedback shown when answer is wrong, already cleaned.
     * @param array $context The runtime context.
     * @param string $truechoicefeedback Generated feedback for the True answer, already cleaned ('' when absent).
     * @param string $falsechoicefeedback Generated feedback for the False answer, already cleaned ('' when absent).
     * @return \stdClass The form data object.
     */
    protected function build_truefalse_form_data(
        int $categoryid,
        string $questiontext,
        int|string $correctanswer,
        string $wrongfeedback,
        array $context,
        string $truechoicefeedback = '',
        string $falsechoicefeedback = ''
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

        // Feedback: the generated per-answer feedback when there is one, otherwise "Correct!" for the right
        // answer and wrong_feedback for the wrong answer.
        $correctfeedbacktext = get_string('feedback_correct', 'local_dixeo');
        $defaulttrue = $trueiscorrect ? $correctfeedbacktext : $wrongfeedback;
        $defaultfalse = $trueiscorrect ? $wrongfeedback : $correctfeedbacktext;
        $form->feedbacktrue = [
            'text' => trim($truechoicefeedback) !== '' ? $truechoicefeedback : $defaulttrue,
            'format' => FORMAT_HTML,
        ];
        $form->feedbackfalse = [
            'text' => trim($falsechoicefeedback) !== '' ? $falsechoicefeedback : $defaultfalse,
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
            $this->generated_text($questiontext),
            $pairs,
            $this->generated_text($wrongfeedback),
            $context,
            $this->partial_feedback_text($fields)
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
     * @param string $partialfeedback Partially-correct feedback, already cleaned.
     * @return \stdClass The form data object.
     */
    protected function build_match_form_data(
        int $categoryid,
        string $questiontext,
        array $pairs,
        string $wrongfeedback,
        array $context,
        string $partialfeedback = ''
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
        $form->partiallycorrectfeedback = ['text' => $partialfeedback, 'format' => FORMAT_HTML];
        $form->incorrectfeedback = ['text' => $wrongfeedback, 'format' => FORMAT_HTML];
        $form->shownumcorrect = 0;

        // Build subquestions and subanswers arrays.
        $form->subquestions = [];
        $form->subanswers = [];

        foreach ($pairs as $index => $pair) {
            $item = is_array($pair) ? ($pair['item'] ?? '') : '';
            $match = is_array($pair) ? ($pair['match'] ?? '') : '';

            $form->subquestions[$index] = [
                'text' => $this->generated_text($item),
                'format' => FORMAT_HTML,
            ];
            $form->subanswers[$index] = $this->generated_text($match);
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
            $this->generated_text($questiontext),
            $items,
            $this->generated_text($wrongfeedback),
            $context,
            $this->partial_feedback_text($fields)
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
     * @param string $partialfeedback Partially-correct feedback, already cleaned.
     * @return \stdClass The form data object.
     */
    protected function build_ordering_form_data(
        int $categoryid,
        string $questiontext,
        array $items,
        string $wrongfeedback,
        array $context,
        string $partialfeedback = ''
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
        $form->partiallycorrectfeedback = ['text' => $partialfeedback, 'format' => FORMAT_HTML];
        $form->incorrectfeedback = ['text' => $wrongfeedback, 'format' => FORMAT_HTML];
        $form->shownumcorrect = 0;

        // Build answer array - items in correct order.
        $form->answer = [];
        foreach ($items as $index => $item) {
            $form->answer[$index] = [
                'text' => $this->generated_text($item),
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
            (string) $questiontext,
            $gaps,
            $this->generated_text($wrongfeedback),
            $context,
            $this->partial_feedback_text($fields)
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
     * @param string $partialfeedback Partially-correct feedback, already cleaned.
     * @return \stdClass The form data object.
     */
    protected function build_gapselect_form_data(
        int $categoryid,
        string $questiontext,
        array $gaps,
        string $wrongfeedback,
        array $context,
        string $partialfeedback = ''
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
                    'answer' => $this->generated_text($option),
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
        $processedtext = $this->generated_text($processedtext);
        $form->name = $this->generate_question_name($processedtext);
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
        $form->partiallycorrectfeedback = ['text' => $partialfeedback, 'format' => FORMAT_HTML];
        $form->incorrectfeedback = ['text' => $wrongfeedback, 'format' => FORMAT_HTML];
        $form->shownumcorrect = 0;

        // Set choices array.
        $form->choices = $choices;

        return $form;
    }
}
