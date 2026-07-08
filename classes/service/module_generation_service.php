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
 * Service for AI-powered module generation and fill operations.
 *
 * Handles module generation (create) and fill (content-only) operations,
 * building appropriate context and submitting jobs to the Dixeo API.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service;

use local_dixeo\api\exception\api_exception;
use local_dixeo\context\context_builder_factory;
use local_dixeo\context\course_context_builder;
use local_dixeo\dto\operation_result;

/**
 * Service for module generation and fill operations.
 */
class module_generation_service {
    /** @var string API endpoint for module generation (create mode). */
    private const GENERATE_ENDPOINT = '/v1/modules/generate';

    /** @var string API endpoint for module fill (content only, no name/intro). */
    private const FILL_ENDPOINT = '/v1/modules/fill';

    /** @var string API endpoint for module editing (surgical edits to existing content). */
    private const EDIT_ENDPOINT = '/v1/modules/edit';

    /** @var string Job type for generation operations. */
    private const JOB_TYPE_GENERATE = 'generate_module';

    /** @var string Job type for fill operations. */
    private const JOB_TYPE_FILL = 'fill_module';

    /** @var string Job type for edit operations. */
    private const JOB_TYPE_EDIT = 'edit_module';

    /** @var array Module types that require assessment context (full content). */
    private const ASSESSMENT_MODULES = ['quiz', 'glossary'];

    /** @var job_service Job management service. */
    private job_service $jobservice;

    /** @var string|null The namespace for API requests. */
    private ?string $namespace;

    /**
     * Constructor.
     *
     * @param job_service|null $jobservice Optional job service.
     * @param string|null $namespace Optional namespace override.
     */
    public function __construct(
        ?job_service $jobservice = null,
        ?string $namespace = null
    ) {
        $this->jobservice = $jobservice ?? new job_service();
        $this->namespace = $namespace ?? $this->get_configured_namespace();
    }

    /**
     * Submit a module generation job for a course (recommended).
     *
     * Builds appropriate context internally based on module type:
     * - Teaching modules (page, label): tiered context by section proximity
     * - Assessment modules (quiz, glossary): full content everywhere
     *
     * @param string $moduletype The module type (page, label, quiz, glossary).
     * @param string $instructions Instructions for the AI.
     * @param int $courseid The course ID.
     * @param int|null $sectionnumber Target section number.
     * @return operation_result Pending operation result with jobid.
     * @throws api_exception If the API request fails.
     */
    public function submit_generate_job_for_course(
        string $moduletype,
        string $instructions,
        int $courseid,
        ?int $sectionnumber = null
    ): operation_result {
        $mode = $this->get_context_mode($moduletype);
        $context = context_builder_factory::buildcoursecontext($courseid, $sectionnumber, $mode);

        return $this->submit_generate_job($moduletype, $instructions, $context, $courseid);
    }

    /**
     * Submit a module generation job without polling.
     *
     * Returns immediately with jobid. Use job_service::get_job_status() to poll.
     * Prefer submit_generate_job_for_course() which builds context automatically.
     *
     * @param string $moduletype The module type (page, label, quiz, glossary).
     * @param string $instructions Instructions for the AI.
     * @param string $context Course/section context in markdown format.
     * @param int|null $courseid Optional course ID for RAG file search.
     * @return operation_result Pending operation result with jobid.
     * @throws api_exception If the API request fails.
     */
    public function submit_generate_job(
        string $moduletype,
        string $instructions,
        string $context,
        ?int $courseid = null
    ): operation_result {
        $payload = $this->build_payload($moduletype, $instructions, $context, $courseid);

        return $this->jobservice->submit_job(self::GENERATE_ENDPOINT, $payload);
    }

    /**
     * Submit a module fill job for a course (content only, no name/intro).
     *
     * Used for structure-based generation where the module title and summary
     * are already defined. Enriches the course context with module metadata
     * so the AI generates coherent content.
     *
     * @param string $moduletype The module type (page, label, quiz, glossary).
     * @param string $instructions Instructions for the AI.
     * @param int $courseid The course ID.
     * @param int|null $sectionnumber Target section number.
     * @param string $title The module title from the course structure.
     * @param string $summary The module summary from the course structure.
     * @return operation_result Pending operation result with jobid.
     * @throws api_exception If the API request fails.
     */
    public function submit_fill_job_for_course(
        string $moduletype,
        string $instructions,
        int $courseid,
        ?int $sectionnumber,
        string $title,
        string $summary
    ): operation_result {
        $mode = $this->get_context_mode($moduletype);
        $context = context_builder_factory::buildmodulefillcontext(
            $courseid,
            $sectionnumber,
            $mode,
            $title,
            $summary
        );

        return $this->submit_fill_job($moduletype, $instructions, $context, $courseid);
    }

    /**
     * Submit a module fill job without polling (content only, no name/intro).
     *
     * Returns immediately with jobid. Use job_service::get_job_status() to poll.
     *
     * @param string $moduletype The module type (page, label, quiz, glossary).
     * @param string $instructions Instructions for the AI.
     * @param string $context Course/section context in markdown format.
     * @param int|null $courseid Optional course ID for RAG file search.
     * @return operation_result Pending operation result with jobid.
     * @throws api_exception If the API request fails.
     */
    public function submit_fill_job(
        string $moduletype,
        string $instructions,
        string $context,
        ?int $courseid = null
    ): operation_result {
        $payload = $this->build_payload($moduletype, $instructions, $context, $courseid);

        return $this->jobservice->submit_job(self::FILL_ENDPOINT, $payload);
    }

    /**
     * Generate a new module using AI (blocking).
     *
     * Submits a generation request and polls for completion.
     *
     * @param string $moduletype The module type (page, label, quiz, glossary).
     * @param string $instructions Instructions for the AI.
     * @param string $context Course/section context in markdown format.
     * @param int|null $courseid Optional course ID for RAG file search.
     * @return operation_result The operation result (completed or pending).
     * @throws api_exception If an API error occurs.
     */
    public function generate_module(
        string $moduletype,
        string $instructions,
        string $context,
        ?int $courseid = null
    ): operation_result {
        $payload = $this->build_payload($moduletype, $instructions, $context, $courseid);

        return $this->jobservice->submit_and_wait(
            self::GENERATE_ENDPOINT,
            $payload,
            self::JOB_TYPE_GENERATE
        );
    }

    /**
     * Fill a module using AI (content only, no name/intro generated).
     *
     * Submits a fill request and polls for completion.
     * Used for editing existing modules and generating content from a course structure.
     *
     * @param string $moduletype The module type (page, label, quiz, etc.).
     * @param string $instructions Instructions for the AI.
     * @param string $context Course/section context (includes existing content for edits).
     * @param int|null $courseid Optional course ID for RAG file search.
     * @return operation_result The operation result (completed or pending).
     * @throws api_exception If an API error occurs.
     */
    public function fill_module(
        string $moduletype,
        string $instructions,
        string $context,
        ?int $courseid = null
    ): operation_result {
        $payload = $this->build_payload($moduletype, $instructions, $context, $courseid);

        return $this->jobservice->submit_and_wait(
            self::FILL_ENDPOINT,
            $payload,
            self::JOB_TYPE_FILL
        );
    }

    /**
     * Fill a module by course module ID (for editing existing content).
     *
     * Simplified interface that resolves module type and context internally.
     * Catches exceptions and returns failed operation_result instead of throwing.
     *
     * @param int $cmid The course module ID.
     * @param string $instructions Instructions for the AI.
     * @return operation_result The operation result (completed, pending, or failed).
     */
    public function fill_module_by_cmid(int $cmid, string $instructions): operation_result {
        try {
            $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
            $moduletype = $cm->modname;
            $courseid = (int) $cm->course;
            $context = context_builder_factory::buildmoduleeditcontext($cmid);

            return $this->fill_module($moduletype, $instructions, $context, $courseid);
        } catch (api_exception $e) {
            return operation_result::failed($e->getMessage(), 'api_error');
        } catch (\dml_exception $e) {
            return operation_result::failed(
                'Module not found or database error: ' . $e->getMessage(),
                'module_not_found'
            );
        } catch (\Throwable $e) {
            return operation_result::failed(
                'Unexpected error: ' . $e->getMessage(),
                'unexpected_error'
            );
        }
    }

    /**
     * Edit existing module content using AI (blocking).
     *
     * Submits an edit request to the dedicated edit endpoint and polls for completion.
     * Unlike fill, this uses a specialized prompt for surgical, minimal edits.
     *
     * @param string $moduletype The module type (page, label, slideshow).
     * @param string $instructions Instructions for the AI.
     * @param string $context Course/section context including current content.
     * @param int|null $courseid Optional course ID for RAG file search.
     * @return operation_result The operation result (completed or pending).
     * @throws api_exception If an API error occurs.
     */
    public function edit_module_content(
        string $moduletype,
        string $instructions,
        string $context,
        ?int $courseid = null
    ): operation_result {
        $payload = $this->build_payload($moduletype, $instructions, $context, $courseid);

        return $this->jobservice->submit_and_wait(
            self::EDIT_ENDPOINT,
            $payload,
            self::JOB_TYPE_EDIT
        );
    }

    /**
     * Edit a module (or one sub-record of a composite module) using AI (blocking).
     *
     * Unified entry point: page/label pass $subid = null, slideshow passes the
     * slide id. Context construction and module type resolution are handled
     * internally via context_builder_factory — callers do not branch on
     * modname.
     *
     * Catches exceptions and returns a failed operation_result instead of
     * throwing.
     *
     * @param int $cmid The course module ID.
     * @param int|null $subid Optional sub-record ID for composite modules (slideshow slide id).
     * @param string $instructions Instructions for the AI.
     * @return operation_result The operation result (completed, pending, or failed).
     */
    public function edit(int $cmid, ?int $subid, string $instructions): operation_result {
        try {
            $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
            $context = context_builder_factory::build_edit_context($cmid, $subid);

            return $this->edit_module_content(
                $cm->modname,
                $instructions,
                $context,
                (int) $cm->course
            );
        } catch (api_exception $e) {
            return operation_result::failed($e->getMessage(), 'api_error');
        } catch (\dml_exception $e) {
            return operation_result::failed(
                'Module not found or database error: ' . $e->getMessage(),
                'module_not_found'
            );
        } catch (\Throwable $e) {
            return operation_result::failed(
                'Unexpected error: ' . $e->getMessage(),
                'unexpected_error'
            );
        }
    }

    /**
     * Edit module content from a pre-built payload (blocking).
     *
     * Companion to {@see build_edit_payload()} for callers that post-process the
     * payload (e.g. the content editor encodes images into it) but must still go
     * through the canonical edit endpoint and job type.
     *
     * @param array $payload Payload from build_edit_payload().
     * @return operation_result The operation result (completed or pending).
     * @throws api_exception If an API error occurs.
     */
    public function edit_module_content_with_payload(array $payload): operation_result {
        return $this->jobService->submit_and_wait(
            self::EDIT_ENDPOINT,
            $payload,
            self::JOB_TYPE_EDIT
        );
    }

    /**
     * Submit an edit job from a pre-built payload without polling.
     *
     * Returns immediately with jobid. Use job_service::get_job_status() to poll.
     *
     * @param array $payload Payload from build_edit_payload().
     * @return operation_result Pending operation result with jobid.
     * @throws api_exception If the API request fails.
     */
    public function submit_edit_job(array $payload): operation_result {
        return $this->jobService->submit_job(self::EDIT_ENDPOINT, $payload);
    }

    /**
     * Set a custom namespace for subsequent requests.
     *
     * @param string|null $namespace The namespace to use.
     * @return self For method chaining.
     */
    public function set_namespace(?string $namespace): self {
        $this->namespace = $namespace;

        return $this;
    }

    /**
     * Get the current namespace.
     *
     * @return string|null The namespace.
     */
    public function get_namespace(): ?string {
        return $this->namespace;
    }

    /**
     * Get the underlying job service.
     *
     * @return job_service The job service.
     */
    public function get_job_service(): job_service {
        return $this->jobservice;
    }

    /**
     * Build the API request payload.
     *
     * @param string $moduletype The module type.
     * @param string $instructions The AI instructions.
     * @param string $context The context markdown.
     * @param int|null $courseid Optional course ID for RAG file search.
     * @return array The request payload.
     * @throws \invalid_parameter_exception If required parameters are empty.
     */
    public function build_edit_payload(
        string $moduletype,
        string $instructions,
        string $context,
        ?int $courseid = null
    ): array {
        return $this->build_payload($moduletype, $instructions, $context, $courseid);
    }

    /**
     * Build the API request payload.
     *
     * @param string $moduletype The module type.
     * @param string $instructions The AI instructions.
     * @param string $context The context markdown.
     * @param int|null $courseid Optional course ID for RAG file search.
     * @return array The request payload.
     * @throws \invalid_parameter_exception If required parameters are empty.
     */
    private function build_payload(string $moduletype, string $instructions, string $context, ?int $courseid = null): array {
        if (empty(trim($moduletype))) {
            throw new \invalid_parameter_exception('Module type is required');
        }
        if (empty(trim($instructions))) {
            throw new \invalid_parameter_exception('Instructions are required');
        }

        $payload = [
            'moduleType' => $moduletype,
            'instructions' => $instructions,
            'context' => $context,
        ];

        if ($courseid !== null) {
            $payload['courseId'] = (string) $courseid;
        }

        if ($this->namespace !== null) {
            $payload['namespace'] = $this->namespace;
        }

        if (
            \local_dixeo\service\image\policy::is_enabled(
                \local_dixeo\service\image\policy::ENTITY_CONTENT,
                \local_dixeo\service\image\policy::ACTION_GENERATE
            )
        ) {
            $payload['instructions'] = rtrim($payload['instructions']) . "\n\n" .
                \local_dixeo\service\image\content\shortcode_service::get_image_prompt_for_module($moduletype);
        }

        return $payload;
    }

    /**
     * Get the configured namespace.
     *
     * @return string The namespace.
     */
    private function get_configured_namespace(): string {
        global $CFG;
        require_once($CFG->dirroot . '/local/dixeo/lib.php');
        return \local_dixeo_get_configured_namespace();
    }

    /**
     * Determine the appropriate context mode for a module type.
     *
     * @param string $moduletype The module type.
     * @return string The context mode (MODE_TEACHING or MODE_ASSESSMENT).
     */
    private function get_context_mode(string $moduletype): string {
        if (in_array($moduletype, self::ASSESSMENT_MODULES, true)) {
            return course_context_builder::MODE_ASSESSMENT;
        }

        return course_context_builder::MODE_TEACHING;
    }
}
