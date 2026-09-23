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
 * injected when authorship records must be written (implementation lives in UX).
 */
class assign_ux_service {
    /** Component for credit / job binding attribution. */
    public const COMPONENT = 'local_dixeo_ux';

    /** @var string */
    public const ENDPOINT_REVIEW = '/v1/assign/review';

    /** @var string */
    public const ENDPOINT_GRADE = '/v1/assign/grade';

    /** @var string */
    public const ENDPOINT_AUTHORSHIP = '/v1/assign/authorship';

    /** Authorship API modes. */
    public const MODE_CONFIDENCE = 'confidence';
    public const MODE_QUIZ = 'quiz';
    public const MODE_FINAL = 'final';

    /** API accepts at most this many submission files. */
    private const MAX_FILES = 5;

    /** @var job_service */
    private job_service $jobservice;

    /** @var assign_submission_reader */
    private assign_submission_reader $reader;

    /** @var assign_ux_persistence_interface|null */
    private ?assign_ux_persistence_interface $persistence;

    /** @var string|null */
    private ?string $namespace;

    /**
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
        $payload = $this->build_review_or_grade_payload($courseid, $cmid, $studentuserid, null);

        return $this->jobservice->submit_job(
            self::ENDPOINT_REVIEW,
            $payload,
            self::COMPONENT,
            job_binding_metadata::for_module('assign', $cmid)
        );
    }

    /**
     * Submit a teacher Assist grading job.
     *
     * @param int $courseid Course id.
     * @param int $cmid Assign course module id.
     * @param int $studentuserid Student being graded.
     * @param \stdClass|null $authorship Optional authorship record (confidence fields).
     * @return operation_result Pending result with jobid.
     * @throws \moodle_exception When submission cannot be analyzed.
     * @throws api_exception When the API request fails.
     */
    public function submit_grade(
        int $courseid,
        int $cmid,
        int $studentuserid,
        ?\stdClass $authorship = null
    ): operation_result {
        $payload = $this->build_review_or_grade_payload($courseid, $cmid, $studentuserid, $authorship);

        return $this->jobservice->submit_job(
            self::ENDPOINT_GRADE,
            $payload,
            self::COMPONENT,
            job_binding_metadata::for_module('assign', $cmid)
        );
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
        if (!in_array($mode, [self::MODE_CONFIDENCE, self::MODE_QUIZ, self::MODE_FINAL], true)) {
            throw new \moodle_exception('assign_ux_authorship_mode_invalid', 'local_dixeo');
        }

        $payload = $this->build_authorship_payload($courseid, $cmid, $studentuserid, $mode, $options);

        return $this->jobservice->submit_job(
            self::ENDPOINT_AUTHORSHIP,
            $payload,
            self::COMPONENT,
            job_binding_metadata::for_module('assign', $cmid)
        );
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
     * Validate and map a completed grade job result against assignment criteria.
     *
     * @param array $assignmentcontext From {@see assign_submission_reader::get_assignment_context()}.
     * @param array|null $result Job result array.
     * @return array{gradingmethod: string, analysis: string, feedback: string, grade?: float, criteria?: array}|array{error: string}
     */
    public function map_grade_result(array $assignmentcontext, ?array $result): array {
        if ($result === null || !is_array($result)) {
            return ['error' => get_string('assign_ux_result_missing', 'local_dixeo')];
        }

        $gradingmethod = $assignmentcontext['grading_method'] ?? 'simple';
        $mapped = [
            'gradingmethod' => $gradingmethod,
            'analysis' => trim((string) ($result['analysis'] ?? '')),
            'feedback' => trim((string) ($result['feedback'] ?? '')),
        ];
        $criteriactx = $assignmentcontext['criteria'] ?? [];
        $expectedcount = count($criteriactx);

        if ($gradingmethod === 'simple') {
            $grade = isset($result['grade']) ? (float) $result['grade'] : 0.0;
            $mapped['grade'] = max(0.0, min(100.0, $grade));
            return $mapped;
        }

        $rawcriteria = $result['criteria'] ?? [];
        if (!is_array($rawcriteria) || count($rawcriteria) !== $expectedcount) {
            return ['error' => get_string('assign_ux_grading_criteria_count', 'local_dixeo',
                (object) ['got' => is_array($rawcriteria) ? count($rawcriteria) : 0, 'expected' => $expectedcount])];
        }

        $idtoctx = [];
        foreach ($criteriactx as $c) {
            $idtoctx[(int) $c['id']] = $c;
        }

        $out = [];
        foreach ($rawcriteria as $item) {
            $id = isset($item['id']) ? (int) $item['id'] : 0;
            if (!isset($idtoctx[$id])) {
                return ['error' => get_string('assign_ux_grading_invalid_criterion', 'local_dixeo', $id)];
            }
            $cctx = $idtoctx[$id];
            $remark = trim((string) ($item['remark'] ?? $item['comment'] ?? $item['feedback'] ?? $item['comments'] ?? ''));
            if ($gradingmethod === 'guide') {
                $maxscore = isset($cctx['maxscore']) ? (float) $cctx['maxscore'] : 0.0;
                $score = isset($item['score']) ? (float) $item['score'] : 0.0;
                $out[] = [
                    'id' => $id,
                    'remark' => $remark,
                    'score' => max(0.0, min($maxscore, $score)),
                ];
            } else {
                $levelids = [];
                foreach ($cctx['levels'] ?? [] as $l) {
                    $levelids[(int) $l['id']] = true;
                }
                $levelid = isset($item['levelid']) ? (int) $item['levelid'] : 0;
                if (!isset($levelids[$levelid])) {
                    return ['error' => get_string('assign_ux_grading_invalid_level', 'local_dixeo',
                        (object) ['levelid' => $levelid, 'criterionid' => $id])];
                }
                $out[] = [
                    'id' => $id,
                    'remark' => $remark,
                    'levelid' => $levelid,
                ];
            }
        }
        $mapped['criteria'] = $out;
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
            return ['confidence' => max(0.0, min(100.0, (float) $result['confidence']))];
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
            return [
                'final_confidence' => max(0.0, min(100.0, (float) $result['final_confidence'])),
                'grade' => $grade === null ? null : max(0.0, min(100.0, (float) $grade)),
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
     * @return \stdClass|null Created record, or null if no persistence / failure.
     */
    public function apply_authorship_confidence(
        int $userid,
        int $submissionid,
        float $initialconfidence,
        ?array $quizdata = null
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
            $quizdata
        );
    }

    /**
     * Persist final quiz evaluation when a persistence adapter is set.
     *
     * @param int $recordid Authorship record id.
     * @param array $responses Question id => answer.
     * @param float $finalconfidence Final confidence.
     * @param float|null $quizgrade Objective grade or null.
     * @return \stdClass|null Updated record, or null if no persistence / failure.
     */
    public function apply_authorship_final(
        int $recordid,
        array $responses,
        float $finalconfidence,
        ?float $quizgrade
    ): ?\stdClass {
        if ($this->persistence === null) {
            return null;
        }
        return $this->persistence->save_quiz_result($recordid, $responses, $finalconfidence, $quizgrade);
    }

    /**
     * Build review/grade API payload from assignment + submission.
     *
     * @param int $courseid Course id.
     * @param int $cmid Cm id.
     * @param int $studentuserid Student id.
     * @param \stdClass|null $authorship Optional authorship for grade.
     * @return array
     * @throws \moodle_exception
     */
    private function build_review_or_grade_payload(
        int $courseid,
        int $cmid,
        int $studentuserid,
        ?\stdClass $authorship
    ): array {
        $ctx = $this->reader->get_assignment_context($cmid, $courseid);
        $submission = $this->reader->get_submission_data($cmid, $studentuserid, $courseid);
        $prepared = $this->prepare_submission_content_and_files((int) $ctx['assignmentid'], $submission);

        $payload = [
            'name' => (string) $ctx['name'],
            'intro' => (string) ($ctx['intro'] ?? ''),
            'activity' => (string) ($ctx['activity'] ?? ''),
            'gradingMethod' => (string) ($ctx['grading_method'] ?? 'simple'),
            'criteria' => $ctx['criteria'] ?? [],
            'submissionText' => $prepared['submissionText'],
            'files' => $prepared['files'],
            'courseId' => (string) $courseid,
        ];
        if ($this->namespace !== null && $this->namespace !== '') {
            $payload['namespace'] = $this->namespace;
        }
        if ($authorship !== null) {
            $hint = [
                'initial_confidence' => (float) ($authorship->initial_confidence ?? 0),
            ];
            if (isset($authorship->final_confidence) && $authorship->final_confidence !== null) {
                $hint['final_confidence'] = (float) $authorship->final_confidence;
            }
            if (isset($authorship->quiz_grade) && $authorship->quiz_grade !== null) {
                $hint['grade'] = (float) $authorship->quiz_grade;
            }
            $payload['authorship'] = $hint;
        }
        return $payload;
    }

    /**
     * Build authorship API payload.
     *
     * @param int $courseid Course id.
     * @param int $cmid Cm id.
     * @param int $studentuserid Student id.
     * @param string $mode Mode.
     * @param array $options Options.
     * @return array
     * @throws \moodle_exception
     */
    private function build_authorship_payload(
        int $courseid,
        int $cmid,
        int $studentuserid,
        string $mode,
        array $options
    ): array {
        $payload = [
            'mode' => $mode,
            'courseId' => (string) $courseid,
        ];
        if ($this->namespace !== null && $this->namespace !== '') {
            $payload['namespace'] = $this->namespace;
        }

        if ($mode === self::MODE_FINAL) {
            if (!isset($options['initialConfidence']) && !isset($options['initial_confidence'])) {
                throw new \moodle_exception('assign_ux_authorship_final_incomplete', 'local_dixeo');
            }
            $payload['initialConfidence'] = (float) ($options['initialConfidence']
                ?? $options['initial_confidence']);
            $payload['questions'] = $options['questions'] ?? [];
            $payload['answers'] = $options['answers'] ?? [];
            if (!is_array($payload['questions']) || $payload['questions'] === []
                    || !is_array($payload['answers'])) {
                throw new \moodle_exception('assign_ux_authorship_final_incomplete', 'local_dixeo');
            }
            return $payload;
        }

        $ctx = $this->reader->get_assignment_context($cmid, $courseid);
        $submission = $this->reader->get_submission_data($cmid, $studentuserid, $courseid);
        $prepared = $this->prepare_submission_content_and_files((int) $ctx['assignmentid'], $submission);
        $payload['submissionText'] = $prepared['submissionText'];
        $payload['files'] = $prepared['files'];

        if ($mode === self::MODE_QUIZ) {
            $payload['trueFalseCount'] = (int) ($options['trueFalseCount'] ?? $options['true_false_count'] ?? 1);
            $payload['multipleChoiceCount'] = (int) ($options['multipleChoiceCount']
                ?? $options['multiple_choice_count'] ?? 1);
            $payload['openCount'] = (int) ($options['openCount'] ?? $options['open_count'] ?? 1);
            if (isset($options['initialConfidence']) || isset($options['initial_confidence'])) {
                $payload['initialConfidence'] = (float) ($options['initialConfidence']
                    ?? $options['initial_confidence']);
            }
        }

        return $payload;
    }

    /**
     * Validate plugins, build submission text, and encode up to MAX_FILES for the API.
     *
     * @param int $assignmentid Assign instance id.
     * @param array $submissiondata From reader.
     * @return array{submissionText: string, files: list<array{filename: string, mimeType: string, contentBase64: string}>}
     * @throws \moodle_exception
     */
    private function prepare_submission_content_and_files(int $assignmentid, array $submissiondata): array {
        $block = $this->reader->build_submission_block_for_ai($assignmentid, $submissiondata);
        if (isset($block['error'])) {
            throw new \moodle_exception('assign_ux_submission_invalid', 'local_dixeo', '', $block['error']);
        }

        $text = (string) ($block['content'] ?? '');
        if (!empty($block['warning'])) {
            $text = $block['warning'] . "\n\n" . $text;
        }

        $validated = $this->reader->validate_submission_files_for_upload(
            $submissiondata['submission_files'] ?? []
        );
        $files = [];
        foreach (array_slice($validated['valid'], 0, self::MAX_FILES) as $stored) {
            if (!($stored instanceof \stored_file)) {
                continue;
            }
            $files[] = [
                'filename' => $stored->get_filename(),
                'mimeType' => $stored->get_mimetype() ?: 'application/octet-stream',
                'contentBase64' => base64_encode($stored->get_content()),
            ];
        }

        return [
            'submissionText' => $text,
            'files' => $files,
        ];
    }
}
