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
 * Submit and map Dixeo_API assign UX jobs (review, grade, authorship).
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service;

use local_dixeo\api\exception\api_exception;
use local_dixeo\dto\job_binding_metadata;
use local_dixeo\dto\job_status;
use local_dixeo\dto\operation_result;

/**
 * Orchestrates assign UX AI jobs via job_service (initiator_scoped by default).
 *
 * Builds structured camelCase payloads only — no prompt text. Persistence is
 * injected when authorship records must be written.
 */
class assign_ux_service {
    /** Component for credit / job binding attribution. */
    public const COMPONENT = 'plagiarism_dixeo';

    /** @var string */
    public const ENDPOINT_REVIEW = '/v1/assign/review';

    /** @var string */
    public const ENDPOINT_GRADE = '/v1/assign/grade';

    /** @var string */
    public const ENDPOINT_AUTHORSHIP_CONFIDENCE = '/v1/assign/authorship/confidence';

    /** @var string */
    public const ENDPOINT_AUTHORSHIP_QUIZ = '/v1/assign/authorship/quiz';

    /** @var string */
    public const ENDPOINT_AUTHORSHIP_EVALUATION = '/v1/assign/authorship/evaluation';

    /** Authorship API mode: initial confidence. */
    public const MODE_CONFIDENCE = 'confidence';

    /** Authorship API mode: quiz generation. */
    public const MODE_QUIZ = 'quiz';

    /** Authorship API mode: final evaluation. */
    public const MODE_FINAL = 'final';

    /** API accepts at most this many submission files. */
    private const MAX_FILES = 5;

    /** Authorship mode => [endpoint, job type]. */
    private const AUTHORSHIP_STEPS = [
        self::MODE_CONFIDENCE => [self::ENDPOINT_AUTHORSHIP_CONFIDENCE, 'assign_authorship_confidence'],
        self::MODE_QUIZ => [self::ENDPOINT_AUTHORSHIP_QUIZ, 'assign_authorship_quiz'],
        self::MODE_FINAL => [self::ENDPOINT_AUTHORSHIP_EVALUATION, 'assign_authorship_evaluation'],
    ];

    /** @var job_service */
    private job_service $jobservice;

    /** @var assign_submission_reader */
    private assign_submission_reader $reader;

    /** @var assign_ux_persistence_interface|null */
    private ?assign_ux_persistence_interface $persistence;

    /** @var string|null */
    private ?string $namespace;

    /**
     * Build the service with optional collaborators for tests.
     *
     * @param job_service|null $jobservice Optional job service.
     * @param assign_submission_reader|null $reader Optional submission reader.
     * @param assign_ux_persistence_interface|null $persistence Optional authorship store.
     * @param string|null $namespace Optional namespace override.
     */
    public function __construct(
        ?job_service $jobservice = null,
        ?assign_submission_reader $reader = null,
        ?assign_ux_persistence_interface $persistence = null,
        ?string $namespace = null
    ) {
        $this->jobservice = $jobservice ?? new job_service();
        $this->reader = $reader ?? new assign_submission_reader();
        $this->persistence = $persistence;
        if ($namespace !== null) {
            $this->namespace = $namespace;
        } else {
            global $CFG;
            require_once($CFG->dirroot . '/local/dixeo/lib.php');
            $this->namespace = \local_dixeo_get_configured_namespace();
        }
    }

    /**
     * Submit a student pre-submission review job.
     *
     * @param int $courseid Course id.
     * @param int $cmid Assign course module id.
     * @param int $studentuserid Student whose submission is reviewed.
     * @return operation_result Pending result with jobid.
     * @throws \moodle_exception When submission cannot be analyzed.
     * @throws api_exception When the API request fails.
     */
    public function submit_review(int $courseid, int $cmid, int $studentuserid): operation_result {
        $request = $this->build_assignment_request($courseid, $cmid, $studentuserid);

        return $this->submit(self::ENDPOINT_REVIEW, $request, null, $cmid);
    }

    /**
     * Submit a review job and block until it completes or times out.
     *
     * @param int $courseid Course id.
     * @param int $cmid Assign course module id.
     * @param int $studentuserid Student whose submission is reviewed.
     * @return operation_result
     * @throws \moodle_exception
     * @throws api_exception
     */
    public function submit_review_and_wait(int $courseid, int $cmid, int $studentuserid): operation_result {
        $request = $this->build_assignment_request($courseid, $cmid, $studentuserid);

        return $this->submit(self::ENDPOINT_REVIEW, $request, 'assign_review', $cmid);
    }

    /**
     * Submit a teacher Assist grading job.
     *
     * Authorship results are never sent: they must not affect the grade.
     *
     * @param int $courseid Course id.
     * @param int $cmid Assign course module id.
     * @param int $studentuserid Student being graded.
     * @return operation_result Pending result with jobid.
     * @throws \moodle_exception When submission cannot be analyzed.
     * @throws api_exception When the API request fails.
     */
    public function submit_grade(int $courseid, int $cmid, int $studentuserid): operation_result {
        $request = $this->build_assignment_request($courseid, $cmid, $studentuserid);

        return $this->submit(self::ENDPOINT_GRADE, $request, null, $cmid);
    }

    /**
     * Submit a grade job and block until it completes or times out.
     *
     * @param int $courseid Course id.
     * @param int $cmid Assign course module id.
     * @param int $studentuserid Student being graded.
     * @return operation_result
     * @throws \moodle_exception
     * @throws api_exception
     */
    public function submit_grade_and_wait(int $courseid, int $cmid, int $studentuserid): operation_result {
        $request = $this->build_assignment_request($courseid, $cmid, $studentuserid);

        return $this->submit(self::ENDPOINT_GRADE, $request, 'assign_grade', $cmid);
    }

    /**
     * Submit an authorship step (confidence, quiz, or final).
     *
     * @param int $courseid Course id.
     * @param int $cmid Assign course module id.
     * @param int $studentuserid Student userid.
     * @param string $mode One of confidence|quiz|final.
     * @param array $options Mode-specific fields (trueFalseCount, questions, answers, …).
     * @return operation_result Pending result with jobid.
     * @throws \moodle_exception When mode/submission is invalid.
     * @throws api_exception When the API request fails.
     */
    public function submit_authorship(
        int $courseid,
        int $cmid,
        int $studentuserid,
        string $mode,
        array $options = []
    ): operation_result {
        $mode = strtolower(trim($mode));
        if (!isset(self::AUTHORSHIP_STEPS[$mode])) {
            throw new \moodle_exception('assign_ux_authorship_mode_invalid', 'local_dixeo');
        }

        $request = $this->build_authorship_request($courseid, $cmid, $studentuserid, $mode, $options);

        return $this->submit(self::AUTHORSHIP_STEPS[$mode][0], $request, null, $cmid);
    }

    /**
     * Submit an authorship job and block until it completes or times out.
     *
     * @param int $courseid Course id.
     * @param int $cmid Assign course module id.
     * @param int $studentuserid Student userid.
     * @param string $mode One of confidence|quiz|final.
     * @param array $options Mode-specific fields.
     * @return operation_result
     * @throws \moodle_exception
     * @throws api_exception
     */
    public function submit_authorship_and_wait(
        int $courseid,
        int $cmid,
        int $studentuserid,
        string $mode,
        array $options = []
    ): operation_result {
        $mode = strtolower(trim($mode));
        if (!isset(self::AUTHORSHIP_STEPS[$mode])) {
            throw new \moodle_exception('assign_ux_authorship_mode_invalid', 'local_dixeo');
        }

        $request = $this->build_authorship_request($courseid, $cmid, $studentuserid, $mode, $options);

        [$endpoint, $jobtype] = self::AUTHORSHIP_STEPS[$mode];

        return $this->submit($endpoint, $request, $jobtype, $cmid);
    }

    /**
     * Poll job status with initiator/course access checks.
     *
     * @param string $jobid Remote job id.
     * @param int $courseid Course id.
     * @param int $userid Acting user id (initiator for initiator_scoped jobs).
     * @return job_status
     */
    public function get_status(string $jobid, int $courseid, int $userid): job_status {
        return $this->jobservice->get_job_status($jobid, $courseid, $userid);
    }

    /**
     * Map a completed review job result for UX.
     *
     * @param array|null $result Job result array.
     * @return array{feedback_html: string}|array{error: string}
     */
    public function map_review_result(?array $result): array {
        if ($result === null) {
            return ['error' => get_string('assign_ux_result_missing', 'local_dixeo')];
        }
        $html = trim((string) ($result['feedback_html'] ?? ''));
        if ($html === '') {
            return ['error' => get_string('assign_ux_result_missing', 'local_dixeo')];
        }
        return ['feedback_html' => $html];
    }

    /**
     * Map a completed grade job result.
     *
     * The API returns exactly one entry per criterion of the assignment, within its bounds.
     *
     * @param array $assignmentcontext From {@see assign_submission_reader::get_assignment_context()}.
     * @param array|null $result Job result array.
     * @return array Mapped grade fields, or an error key.
     */
    public function map_grade_result(array $assignmentcontext, ?array $result): array {
        if ($result === null) {
            return ['error' => get_string('assign_ux_result_missing', 'local_dixeo')];
        }

        $gradingmethod = $assignmentcontext['grading_method'] ?? 'simple';
        $mapped = [
            'gradingmethod' => $gradingmethod,
            'analysis' => trim((string) ($result['analysis'] ?? '')),
            'feedback' => trim((string) ($result['feedback'] ?? '')),
        ];

        if ($gradingmethod === 'simple') {
            if (!isset($result['grade'])) {
                return ['error' => get_string('assign_ux_result_missing', 'local_dixeo')];
            }
            $mapped['grade'] = (float) $result['grade'];
            return $mapped;
        }

        if (!is_array($result['criteria'] ?? null)) {
            return ['error' => get_string('assign_ux_result_missing', 'local_dixeo')];
        }
        $mapped['criteria'] = $result['criteria'];
        return $mapped;
    }

    /**
     * Map a completed authorship job result by mode.
     *
     * @param string $mode confidence|quiz|final.
     * @param array|null $result Job result.
     * @return array Mapped fields or error key.
     */
    public function map_authorship_result(string $mode, ?array $result): array {
        if ($result === null) {
            return ['error' => get_string('assign_ux_result_missing', 'local_dixeo')];
        }
        $mode = strtolower(trim($mode));
        if ($mode === self::MODE_CONFIDENCE) {
            if (!isset($result['confidence'])) {
                return ['error' => get_string('assign_ux_result_missing', 'local_dixeo')];
            }
            return [
                'confidence' => max(0.0, min(100.0, (float) $result['confidence'])),
                'explanation' => trim((string) ($result['explanation'] ?? '')),
            ];
        }
        if ($mode === self::MODE_QUIZ) {
            $questions = $result['questions'] ?? null;
            if (!is_array($questions) || $questions === []) {
                return ['error' => get_string('assign_ux_result_missing', 'local_dixeo')];
            }
            return ['questions' => $questions];
        }
        if ($mode === self::MODE_FINAL) {
            if (!isset($result['final_confidence'])) {
                return ['error' => get_string('assign_ux_result_missing', 'local_dixeo')];
            }
            $grade = $result['grade'] ?? null;
            $feedbacks = $result['question_feedbacks'] ?? $result['quiz_question_feedbacks'] ?? [];
            return [
                'final_confidence' => max(0.0, min(100.0, (float) $result['final_confidence'])),
                'grade' => $grade === null ? null : max(0.0, min(100.0, (float) $grade)),
                'explanation' => trim((string) ($result['explanation'] ?? $result['final_explanation'] ?? '')),
                'quiz_explanation' => trim((string) ($result['quiz_explanation'] ?? '')),
                'question_feedbacks' => self::string_list($feedbacks),
            ];
        }
        return ['error' => get_string('assign_ux_authorship_mode_invalid', 'local_dixeo')];
    }

    /**
     * Persist initial confidence / quiz data when a persistence adapter is set.
     *
     * @param int $userid Student id.
     * @param int $submissionid Assign submission id.
     * @param float $initialconfidence Confidence 0–100.
     * @param array|null $quizdata Quiz structure or null.
     * @param string $initialexplanation Reasons for the initial score.
     * @return \stdClass|null Created record, or null if no persistence / failure.
     */
    public function apply_authorship_confidence(
        int $userid,
        int $submissionid,
        float $initialconfidence,
        ?array $quizdata = null,
        string $initialexplanation = ''
    ): ?\stdClass {
        if ($this->persistence === null) {
            return null;
        }
        return $this->persistence->create_authorship_record(
            $userid,
            'mod_assign',
            'submission',
            $submissionid,
            $initialconfidence,
            $quizdata,
            $initialexplanation
        );
    }

    /**
     * Persist final quiz evaluation when a persistence adapter is set.
     *
     * @param int $recordid Authorship record id.
     * @param array $responses Question id => answer.
     * @param float $finalconfidence Final confidence.
     * @param float|null $quizgrade Objective grade or null.
     * @param string $finalexplanation Reasons for the final score.
     * @param string $quizexplanation How the verification test was judged overall.
     * @param array $questionfeedbacks Per-question notes, in question order.
     * @return \stdClass|null Updated record, or null if no persistence / failure.
     */
    public function apply_authorship_final(
        int $recordid,
        array $responses,
        float $finalconfidence,
        ?float $quizgrade,
        string $finalexplanation = '',
        string $quizexplanation = '',
        array $questionfeedbacks = []
    ): ?\stdClass {
        if ($this->persistence === null) {
            return null;
        }
        return $this->persistence->save_quiz_result(
            $recordid,
            $responses,
            $finalconfidence,
            $quizgrade,
            $finalexplanation,
            $quizexplanation,
            $questionfeedbacks
        );
    }

    /**
     * Submit a request, waiting for the result when a job type is given.
     *
     * @param string $endpoint API endpoint.
     * @param array $request Built request with payload and files keys.
     * @param string|null $jobtype Job type to poll for, or null to return at once.
     * @param int $cmid Assign course module id.
     * @return operation_result
     * @throws api_exception
     */
    private function submit(string $endpoint, array $request, ?string $jobtype, int $cmid): operation_result {
        $metadata = job_binding_metadata::for_module('assign', $cmid);

        if ($jobtype === null) {
            return $this->jobservice->submit_job(
                $endpoint,
                $request['payload'],
                self::COMPONENT,
                $metadata,
                files: $request['files']
            );
        }

        return $this->jobservice->submit_and_wait(
            $endpoint,
            $request['payload'],
            $jobtype,
            self::COMPONENT,
            $metadata,
            files: $request['files']
        );
    }

    /**
     * Build a review/grade request from the assignment and the submission.
     *
     * @param int $courseid Course id.
     * @param int $cmid Cm id.
     * @param int $studentuserid Student id.
     * @return array{payload: array, files: list<\stored_file>}
     * @throws \moodle_exception
     */
    private function build_assignment_request(int $courseid, int $cmid, int $studentuserid): array {
        $ctx = $this->reader->get_assignment_context($cmid, $courseid);
        $request = $this->build_submission_request($courseid, $cmid, $studentuserid, (int) $ctx['assignmentid']);

        $request['payload'] += [
            'name' => (string) $ctx['name'],
            'intro' => (string) ($ctx['intro'] ?? ''),
            'activity' => (string) ($ctx['activity'] ?? ''),
            'gradingMethod' => (string) ($ctx['grading_method'] ?? 'simple'),
            'criteria' => $ctx['criteria'] ?? [],
        ];
        return $request;
    }

    /**
     * Build an authorship request for one step.
     *
     * @param int $courseid Course id.
     * @param int $cmid Cm id.
     * @param int $studentuserid Student id.
     * @param string $mode Mode.
     * @param array $options Options.
     * @return array{payload: array, files: list<\stored_file>}
     * @throws \moodle_exception
     */
    private function build_authorship_request(
        int $courseid,
        int $cmid,
        int $studentuserid,
        string $mode,
        array $options
    ): array {
        if ($mode === self::MODE_FINAL) {
            $initialconfidence = $options['initialConfidence'] ?? $options['initial_confidence'] ?? null;
            $questions = $options['questions'] ?? [];
            $answers = $options['answers'] ?? [];
            if ($initialconfidence === null || !is_array($questions) || $questions === [] || !is_array($answers)) {
                throw new \moodle_exception('assign_ux_authorship_final_incomplete', 'local_dixeo');
            }
            return [
                'payload' => $this->with_scope($courseid, [
                    'initialConfidence' => (float) $initialconfidence,
                    'questions' => $questions,
                    'answers' => (object) array_map('strval', $answers),
                ]),
                'files' => [],
            ];
        }

        $ctx = $this->reader->get_assignment_context($cmid, $courseid);
        $request = $this->build_submission_request($courseid, $cmid, $studentuserid, (int) $ctx['assignmentid']);

        if ($mode === self::MODE_QUIZ) {
            $request['payload'] += [
                'trueFalseCount' => (int) ($options['trueFalseCount'] ?? $options['true_false_count'] ?? 1),
                'multipleChoiceCount' => (int) ($options['multipleChoiceCount'] ?? $options['multiple_choice_count'] ?? 1),
                'openCount' => (int) ($options['openCount'] ?? $options['open_count'] ?? 1),
            ];
        }
        return $request;
    }

    /**
     * Read the submission: its text in the payload, its readable files alongside.
     *
     * @param int $courseid Course id.
     * @param int $cmid Cm id.
     * @param int $studentuserid Student id.
     * @param int $assignmentid Assign instance id.
     * @return array{payload: array, files: list<\stored_file>}
     * @throws \moodle_exception
     */
    private function build_submission_request(int $courseid, int $cmid, int $studentuserid, int $assignmentid): array {
        $submission = $this->reader->get_submission_data($cmid, $studentuserid, $courseid);
        $block = $this->reader->build_submission_block_for_ai($assignmentid, $submission);
        if (isset($block['error'])) {
            throw new \moodle_exception('assign_ux_submission_invalid', 'local_dixeo', '', $block['error']);
        }

        $text = (string) ($block['content'] ?? '');
        if (!empty($block['warning'])) {
            $text = $block['warning'] . "\n\n" . $text;
        }

        return [
            'payload' => $this->with_scope($courseid, ['submissionText' => $text]),
            'files' => array_slice($block['files'], 0, self::MAX_FILES),
        ];
    }

    /**
     * Add the course and namespace every request carries.
     *
     * @param int $courseid Course id.
     * @param array $payload Payload.
     * @return array
     */
    private function with_scope(int $courseid, array $payload): array {
        $payload['courseId'] = (string) $courseid;
        if ($this->namespace !== null && $this->namespace !== '') {
            $payload['namespace'] = $this->namespace;
        }
        return $payload;
    }

    /**
     * Keep only non-empty scalar strings from a list.
     *
     * @param mixed $value Raw list from the API.
     * @return list<string>
     */
    private static function string_list($value): array {
        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (!is_scalar($item)) {
                continue;
            }
            $line = trim((string) $item);
            if ($line !== '') {
                $out[] = $line;
            }
        }

        return $out;
    }
}
