<?php
/**
 * Ephemeral practice quiz generation (no Moodle module creation).
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service;

use local_dixeo\api\exception\api_exception;
use local_dixeo\context\context_builder_factory;
use local_dixeo\context\course_context_builder;
use local_dixeo\dsl\dsl_exception;
use local_dixeo\dto\operation_result;

defined('MOODLE_INTERNAL') || die();

/**
 * Service for tutor practice quiz generation jobs.
 */
class practice_quiz_service {

    public const SCOPE_COURSE = 'course';
    public const SCOPE_SECTION = 'section';
    public const SCOPE_ACTIVITY = 'activity';

    /** @var module_generation_service */
    private module_generation_service $modulegeneration;

    /** @var job_service */
    private job_service $jobservice;

    /**
     * @param module_generation_service|null $modulegeneration
     * @param job_service|null $jobservice
     */
    public function __construct(
        ?module_generation_service $modulegeneration = null,
        ?job_service $jobservice = null
    ) {
        $this->modulegeneration = $modulegeneration ?? new module_generation_service();
        $this->jobservice = $jobservice ?? new job_service();
    }

    /**
     * Submit a practice quiz generation job from tutor setup panel values.
     *
     * @param int $courseid
     * @param string $scope course|section|activity
     * @param int $sectionnum Section number when scope is section.
     * @param int $cmid Course module id when scope is activity.
     * @param int $count Number of questions (3-10).
     * @param string $difficulty easy|medium|hard
     * @param string $topictitle Human-readable topic label.
     * @return operation_result
     * @throws api_exception
     */
    public function submit_from_setup(
        int $courseid,
        string $scope,
        int $sectionnum = 0,
        int $cmid = 0,
        int $count = 5,
        string $difficulty = 'medium',
        string $topictitle = ''
    ): operation_result {
        $scope = $this->normalize_scope($scope);
        $contextmarkdown = $this->build_context(
            $courseid,
            $scope,
            $sectionnum > 0 ? $sectionnum : null,
            $cmid > 0 ? $cmid : null
        );
        $instructions = $this->build_instructions($count, $difficulty, $scope, $topictitle);

        return $this->submit_job($courseid, $instructions, $contextmarkdown);
    }

    /**
     * Submit a simplequiz2 generation job for ephemeral practice use.
     *
     * @param int $courseid
     * @param string $instructions
     * @param string $contextmarkdown
     * @return operation_result
     * @throws api_exception
     */
    public function submit_job(int $courseid, string $instructions, string $contextmarkdown): operation_result {
        return $this->modulegeneration->submit_generate_job(
            'simplequiz2',
            $instructions,
            $contextmarkdown,
            $courseid
        );
    }

    /**
     * Build markdown context for the selected scope.
     *
     * @param int $courseid
     * @param string $scope course|section|activity
     * @param int|null $sectionnum Section number when scope is section.
     * @param int|null $cmid Course module id when scope is activity.
     * @return string
     */
    public function build_context(int $courseid, string $scope, ?int $sectionnum, ?int $cmid): string {
        switch ($scope) {
            case self::SCOPE_SECTION:
                if ($sectionnum !== null && $sectionnum > 0) {
                    return context_builder_factory::buildSectionContextForNumber($courseid, $sectionnum);
                }
                // Fall through to course if section number missing.
                // no break
            case self::SCOPE_ACTIVITY:
                if ($cmid !== null && $cmid > 0) {
                    // Reject cmids from other courses (section scope is enforced by its DB lookup).
                    get_coursemodule_from_id('', $cmid, $courseid, false, MUST_EXIST);
                    return context_builder_factory::buildModulePracticeContext($cmid);
                }
                // Fall through to course if cmid missing.
                // no break
            case self::SCOPE_COURSE:
            default:
                return context_builder_factory::buildCourseContext(
                    $courseid,
                    null,
                    course_context_builder::MODE_ASSESSMENT
                );
        }
    }

    /**
     * Compose generation instructions from setup panel values.
     *
     * @param int $count Number of questions (3-10).
     * @param string $difficulty easy|medium|hard
     * @param string $scope course|section|activity
     * @param string $scopename Human-readable scope name (course, section, or activity title).
     * @return string
     */
    public function build_instructions(int $count, string $difficulty, string $scope, string $scopename): string {
        $count = max(3, min(10, $count));
        $difficulty = in_array($difficulty, ['easy', 'medium', 'hard'], true) ? $difficulty : 'medium';
        $scope = $this->normalize_scope($scope);

        $difficultylabel = match ($difficulty) {
            'easy' => get_string('practice_quiz_difficulty_easy', 'local_dixeo'),
            'hard' => get_string('practice_quiz_difficulty_hard', 'local_dixeo'),
            default => get_string('practice_quiz_difficulty_medium', 'local_dixeo'),
        };

        return get_string('practice_quiz_instructions', 'local_dixeo', (object) [
            'scopedescription' => $this->build_scope_description($scope, $scopename),
            'count' => $count,
            'difficulty' => $difficulty,
            'difficultylabel' => $difficultylabel,
        ]);
    }

    /**
     * Human-readable scope line for generation instructions.
     *
     * @param string $scope course|section|activity
     * @param string $scopename Scope display name.
     * @return string
     */
    private function build_scope_description(string $scope, string $scopename): string {
        $name = trim($scopename);

        return match ($scope) {
            self::SCOPE_SECTION => get_string('practice_quiz_scope_section_description', 'local_dixeo', (object) [
                'name' => $name,
            ]),
            self::SCOPE_ACTIVITY => get_string('practice_quiz_scope_activity_description', 'local_dixeo', (object) [
                'name' => $name,
            ]),
            default => get_string('practice_quiz_scope_course_description', 'local_dixeo', (object) [
                'name' => $name,
            ]),
        };
    }

    /**
     * Transform a completed generation job into simplequiz2 question JSON.
     *
     * @param string $jobid
     * @param string $fallbacktitle Title if job data has no name.
     * @param int|null $expectedcount Trim excess questions when the API returns too many.
     * @param int|null $courseid Course ID to enforce job ownership (required for AJAX paths).
     * @return array{success: bool, error: string, title: string, questions: string}
     * @throws api_exception
     */
    public function finalize_from_job(
        string $jobid,
        string $fallbacktitle = '',
        ?int $expectedcount = null,
        ?int $courseid = null
    ): array {
        $status = $this->jobservice->get_job_status($jobid, $courseid);

        if (!$status->is_completed()) {
            return $this->practice_quiz_result(
                false,
                '',
                '[]',
                get_string('practice_quiz_error_job_not_completed', 'local_dixeo', (object) [
                    'status' => $status->status,
                ])
            );
        }

        $result = $status->result;
        if (is_string($result)) {
            $result = json_decode($result, true);
        }
        if (!is_array($result)) {
            return $this->practice_quiz_result(
                false,
                '',
                '[]',
                get_string('practice_quiz_error_invalid_result', 'local_dixeo')
            );
        }

        $moduletype = $result['moduleType'] ?? '';
        if ($moduletype !== 'simplequiz2') {
            return $this->practice_quiz_result(
                false,
                '',
                '[]',
                get_string('practice_quiz_error_wrong_module_type', 'local_dixeo')
            );
        }

        $data = $result['data'] ?? [];
        $rawquestions = $data['questions'] ?? [];
        if (!is_array($rawquestions) || empty($rawquestions)) {
            return $this->practice_quiz_result(
                false,
                '',
                '[]',
                get_string('practice_quiz_error_no_questions', 'local_dixeo')
            );
        }

        try {
            $questions = simplequiz2_question_transformer::transform_job_questions($rawquestions);
        } catch (dsl_exception $e) {
            return $this->practice_quiz_result(false, '', '[]', $e->getMessage());
        }

        $title = trim((string) ($data['name'] ?? ''));
        if ($title === '') {
            $title = trim($fallbacktitle);
        }
        if ($title === '') {
            $title = get_string('practice_quiz_default_title', 'local_dixeo');
        }

        // Re-index as JSON array for embed player.
        $questionlist = array_values($questions);

        if ($expectedcount !== null && $expectedcount > 0) {
            $expectedcount = max(3, min(10, $expectedcount));
            if (count($questionlist) > $expectedcount) {
                $questionlist = array_slice($questionlist, 0, $expectedcount);
            }
        }

        return $this->practice_quiz_result(
            true,
            $title,
            json_encode($questionlist)
        );
    }

    /**
     * Build a practice quiz finalize response array.
     *
     * @param bool $success
     * @param string $title
     * @param string $questions
     * @param string $error
     * @return array{success: bool, error: string, title: string, questions: string}
     */
    private function practice_quiz_result(
        bool $success,
        string $title = '',
        string $questions = '[]',
        string $error = ''
    ): array {
        return [
            'success' => $success,
            'error' => $error,
            'title' => $title,
            'questions' => $questions,
        ];
    }

    /**
     * Normalize and validate a practice quiz scope value.
     *
     * @param string $scope
     * @return string
     */
    private function normalize_scope(string $scope): string {
        $scope = strtolower(trim($scope));
        if (!in_array($scope, [
            self::SCOPE_COURSE,
            self::SCOPE_SECTION,
            self::SCOPE_ACTIVITY,
        ], true)) {
            throw new \invalid_parameter_exception('Invalid scope');
        }

        return $scope;
    }
}
