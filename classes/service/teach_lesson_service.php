<?php
/**
 * Ephemeral teach-lesson generation (no Moodle module creation).
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service;

use local_dixeo\api\exception\api_exception;
use local_dixeo\context\context_builder_factory;
use local_dixeo\context\course_context_builder;
use local_dixeo\dto\operation_result;

defined('MOODLE_INTERNAL') || die();

/**
 * Service for tutor teach-lesson generation jobs.
 */
class teach_lesson_service {

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
     * Submit a teach lesson generation job from tutor setup panel values.
     *
     * @param int $courseid
     * @param string $scope course|section|activity
     * @param int $sectionnum Section number when scope is section.
     * @param int $cmid Course module id when scope is activity.
     * @param string $topictitle Human-readable topic label.
     * @param string $learnerrequest Learner's free-text learning request (required).
     * @param string $language Moodle language code for generated content.
     * @return operation_result
     * @throws api_exception
     */
    public function submit_from_setup(
        int $courseid,
        string $scope,
        int $sectionnum = 0,
        int $cmid = 0,
        string $topictitle = '',
        string $learnerrequest = '',
        string $language = ''
    ): operation_result {
        $scope = $this->normalize_scope($scope);
        $learnerrequest = trim($learnerrequest);
        if ($learnerrequest === '') {
            throw new \invalid_parameter_exception('Learner request is required');
        }

        $contextmarkdown = $this->build_context(
            $courseid,
            $scope,
            $sectionnum > 0 ? $sectionnum : null,
            $cmid > 0 ? $cmid : null
        );
        $instructions = $this->build_instructions($scope, $topictitle, $learnerrequest, $language);

        return $this->submit_job($courseid, $instructions, $contextmarkdown);
    }

    /**
     * Submit a page generation job for ephemeral teach-lesson use.
     *
     * @param int $courseid
     * @param string $instructions
     * @param string $contextmarkdown
     * @return operation_result
     * @throws api_exception
     */
    public function submit_job(int $courseid, string $instructions, string $contextmarkdown): operation_result {
        return $this->modulegeneration->submit_generate_job(
            'page',
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
                    course_context_builder::MODE_TEACHING
                );
        }
    }

    /**
     * Compose generation instructions from setup panel values.
     *
     * @param string $scope course|section|activity
     * @param string $scopename Human-readable scope name (course, section, or activity title).
     * @param string $learnerrequest Learner's free-text learning request.
     * @param string $language Moodle language code for generated content.
     * @return string
     */
    public function build_instructions(
        string $scope,
        string $scopename,
        string $learnerrequest,
        string $language = ''
    ): string {
        global $USER;

        $scope = $this->normalize_scope($scope);
        $language = generation_language_helper::resolve($language, (int) $USER->id);

        $instructions = generation_language_helper::get_string('teach_lesson_instructions', (object) [
            'scopedescription' => $this->build_scope_description($scope, $scopename, $language),
            'learnerrequest' => trim($learnerrequest),
        ], $language);

        return generation_language_helper::append_output_language_instruction($instructions, $language);
    }

    /**
     * Human-readable scope line for generation instructions.
     *
     * @param string $scope course|section|activity
     * @param string $scopename Scope display name.
     * @param string $language Moodle language code for localized scope text.
     * @return string
     */
    private function build_scope_description(string $scope, string $scopename, string $language): string {
        $name = trim($scopename);

        return match ($scope) {
            self::SCOPE_SECTION => generation_language_helper::get_string(
                'practice_quiz_scope_section_description',
                (object) ['name' => $name],
                $language
            ),
            self::SCOPE_ACTIVITY => generation_language_helper::get_string(
                'practice_quiz_scope_activity_description',
                (object) ['name' => $name],
                $language
            ),
            default => generation_language_helper::get_string(
                'practice_quiz_scope_course_description',
                (object) ['name' => $name],
                $language
            ),
        };
    }

    /**
     * Transform a completed generation job into formatted lesson HTML.
     *
     * @param string $jobid
     * @param string $fallbacktitle Title if job data has no name.
     * @param int $courseid Course id for format_text context.
     * @return array{success: bool, error: string, title: string, introhtml: string, contenthtml: string}
     * @throws api_exception
     */
    public function finalize_from_job(string $jobid, string $fallbacktitle = '', int $courseid = 0): array {
        $status = $this->jobservice->get_job_status($jobid, $courseid > 0 ? $courseid : null);

        if (!$status->is_completed()) {
            return $this->teach_lesson_result(
                false,
                '',
                '',
                '',
                get_string('teach_lesson_error_job_not_completed', 'local_dixeo', (object) [
                    'status' => $status->status,
                ])
            );
        }

        $result = $status->result;
        if (is_string($result)) {
            $result = json_decode($result, true);
        }
        if (!is_array($result)) {
            return $this->teach_lesson_result(
                false,
                '',
                '',
                '',
                get_string('teach_lesson_error_invalid_result', 'local_dixeo')
            );
        }

        $moduletype = $result['moduleType'] ?? '';
        if ($moduletype !== 'page') {
            return $this->teach_lesson_result(
                false,
                '',
                '',
                '',
                get_string('teach_lesson_error_wrong_module_type', 'local_dixeo')
            );
        }

        $data = $result['data'] ?? [];
        $content = trim((string) ($data['content'] ?? ''));
        if ($content === '') {
            return $this->teach_lesson_result(
                false,
                '',
                '',
                '',
                get_string('teach_lesson_error_no_content', 'local_dixeo')
            );
        }

        $title = trim((string) ($data['name'] ?? ''));
        if ($title === '') {
            $title = trim($fallbacktitle);
        }
        if ($title === '') {
            $title = get_string('teach_lesson_default_title', 'local_dixeo');
        }

        $intro = trim((string) ($data['intro'] ?? ''));
        $formatoptions = ['noclean' => true, 'para' => true, 'filter' => true];

        $introhtml = '';
        if ($intro !== '') {
            $introhtml = trim(format_text($intro, FORMAT_HTML, $formatoptions, $courseid));
        }

        $contenthtml = trim(format_text($content, FORMAT_HTML, $formatoptions, $courseid));

        return $this->teach_lesson_result(true, $title, $introhtml, $contenthtml);
    }

    /**
     * Build a teach lesson finalize response array.
     *
     * @param bool $success
     * @param string $title
     * @param string $introhtml
     * @param string $contenthtml
     * @param string $error
     * @return array{success: bool, error: string, title: string, introhtml: string, contenthtml: string}
     */
    private function teach_lesson_result(
        bool $success,
        string $title = '',
        string $introhtml = '',
        string $contenthtml = '',
        string $error = ''
    ): array {
        return [
            'success' => $success,
            'error' => $error,
            'title' => $title,
            'introhtml' => $introhtml,
            'contenthtml' => $contenthtml,
        ];
    }

    /**
     * Normalize and validate a teach lesson scope value.
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
