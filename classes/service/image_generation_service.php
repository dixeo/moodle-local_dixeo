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
 * Service for AI-powered image generation and editing.
 *
 * Exposes library-level methods to submit async image generation/edit jobs
 * for a course or a section. Consumers (blocks, format overrides, etc.)
 * call these methods, poll the returned job via job_service, and decide
 * themselves what to do with the resulting base64 WebP payload.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service;

use local_dixeo\api\exception\api_exception;
use local_dixeo\dto\operation_result;

/**
 * Service for course/section image generation and edit operations.
 */
class image_generation_service {
    /** @var string API endpoint for image generation. */
    private const GENERATE_ENDPOINT = '/v1/images/generate';

    /** @var string API endpoint for image editing. */
    private const EDIT_ENDPOINT = '/v1/images/edit';

    /** @var string Default image size (landscape, suitable for banners). */
    public const DEFAULT_SIZE = '1536x1024';

    /** @var array Supported image dimensions, validated client-side before submission. */
    public const SUPPORTED_SIZES = ['1024x1024', '1024x1536', '1536x1024'];

    /** @var string Default quality level. */
    public const DEFAULT_QUALITY = 'medium';

    /** @var int Maximum summary length sent to the API. */
    private const SUMMARY_MAX_LENGTH = 2000;

    /** @var job_service Job management service. */
    private job_service $jobservice;

    /** @var html_helper HTML cleaning helper. */
    private html_helper $htmlhelper;

    /** @var string|null Namespace for API requests. */
    private ?string $namespace;

    /**
     * Constructor.
     *
     * @param job_service|null $jobservice Optional job service.
     * @param html_helper|null $htmlhelper Optional HTML helper.
     * @param string|null $namespace Optional namespace override.
     */
    public function __construct(
        ?job_service $jobservice = null,
        ?html_helper $htmlhelper = null,
        ?string $namespace = null
    ) {
        $this->jobservice = $jobservice ?? new job_service();
        $this->htmlhelper = $htmlhelper ?? new html_helper();
        $this->namespace = $namespace ?? $this->get_configured_namespace();
    }

    /**
     * Submit an image generation job for a course (async, non-blocking).
     *
     * Loads the course from DB, derives title/summary, and submits the job.
     * Returns immediately with a jobid — caller polls via job_service.
     *
     * @param int $courseid The course ID.
     * @param string $size Image dimensions (default 1536x1024 landscape).
     * @param string $quality Quality level (low/medium/high, default medium).
     * @param string|null $title When non-null, sent as payload title instead of course.fullname (e.g. draft before DB update).
     * @param string|null $summary When non-null, sent instead of course.summary (same use-case).
     * @return operation_result Pending operation result with jobid.
     * @throws \moodle_exception If image generation is disabled by {@see image_generation_policy}.
     * @throws api_exception If the API request fails.
     * @throws \dml_exception If the course is not found.
     */
    public function submit_course_image_job(
        int $courseid,
        string $size = self::DEFAULT_SIZE,
        string $quality = self::DEFAULT_QUALITY,
        ?string $title = null,
        ?string $summary = null
    ): operation_result {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname, summary', MUST_EXIST);

        image_generation_policy::assert_enabled(
            image_generation_policy::ENTITY_COURSE,
            image_generation_policy::ACTION_GENERATE
        );

        $resolvedtitle = $title !== null ? $title : $course->fullname;
        $resolvedsummary = $summary !== null ? $summary : ($course->summary ?? null);

        $payload = $this->build_payload(
            scope: 'course',
            title: $resolvedtitle,
            summary: $resolvedsummary,
            size: $size,
            quality: $quality,
            courseid: (string) $courseid,
        );

        return $this->jobservice->submit_job(self::GENERATE_ENDPOINT, $payload);
    }

    /**
     * Submit an image generation job for a section (async, non-blocking).
     *
     * Loads the section from DB, derives title (section name or "Section N")
     * and summary, and submits the job. Returns immediately with a jobid.
     *
     * @param int $sectionid The course_sections.id.
     * @param string $size Image dimensions (default 1536x1024 landscape).
     * @param string $quality Quality level (low/medium/high, default medium).
     * @return operation_result Pending operation result with jobid.
     * @throws \moodle_exception If section image generation is disabled by {@see image_generation_policy}.
     * @throws api_exception If the API request fails.
     * @throws \dml_exception If the section is not found.
     */
    public function submit_section_image_job(
        int $sectionid,
        string $size = self::DEFAULT_SIZE,
        string $quality = self::DEFAULT_QUALITY
    ): operation_result {
        global $DB;

        $section = $DB->get_record('course_sections', ['id' => $sectionid], 'id, course, section, name, summary', MUST_EXIST);

        image_generation_policy::assert_enabled(
            image_generation_policy::ENTITY_SECTION,
            image_generation_policy::ACTION_GENERATE
        );

        $title = $this->resolve_section_title($section);

        $payload = $this->build_payload(
            scope: 'section',
            title: $title,
            summary: $section->summary ?? null,
            size: $size,
            quality: $quality,
            courseid: (string) $section->course,
        );

        return $this->jobservice->submit_job(self::GENERATE_ENDPOINT, $payload);
    }

    /**
     * Submit an image edit job for a course (async, non-blocking).
     *
     * @param int $courseid The course ID.
     * @param array $imagesbase64 List of raw base64-encoded source images (1-16).
     * @param string $instructions Description of the change to apply.
     * @param string $size Image dimensions (default 1536x1024 landscape).
     * @param string $quality Quality level (default medium).
     * @return operation_result Pending operation result with jobid.
     * @throws \moodle_exception If course image editing is disabled by {@see image_generation_policy}.
     * @throws api_exception If the API request fails.
     * @throws \dml_exception If the course is not found.
     */
    public function submit_course_image_edit_job(
        int $courseid,
        array $imagesbase64,
        string $instructions,
        string $size = self::DEFAULT_SIZE,
        string $quality = self::DEFAULT_QUALITY
    ): operation_result {
        global $DB;

        if ($imagesbase64 === []) {
            throw new \invalid_parameter_exception('At least one source image is required for editing');
        }

        $DB->get_record('course', ['id' => $courseid], 'id', MUST_EXIST);

        image_generation_policy::assert_enabled(
            image_generation_policy::ENTITY_COURSE,
            image_generation_policy::ACTION_EDIT
        );

        $payload = $this->build_edit_payload(
            $imagesbase64,
            $instructions,
            $size,
            $quality,
            (string) $courseid,
        );

        return $this->jobservice->submit_job(self::EDIT_ENDPOINT, $payload);
    }

    /**
     * Submit an image edit job for a section (async, non-blocking).
     *
     * @param int $sectionid The course_sections.id.
     * @param array $imagesbase64 List of raw base64-encoded source images (1-16).
     * @param string $instructions Description of the change to apply.
     * @param string $size Image dimensions (default 1536x1024 landscape).
     * @param string $quality Quality level (default medium).
     * @return operation_result Pending operation result with jobid.
     * @throws \moodle_exception If section image editing is disabled by {@see image_generation_policy}.
     * @throws api_exception If the API request fails.
     * @throws \dml_exception If the section is not found.
     */
    public function submit_section_image_edit_job(
        int $sectionid,
        array $imagesbase64,
        string $instructions,
        string $size = self::DEFAULT_SIZE,
        string $quality = self::DEFAULT_QUALITY
    ): operation_result {
        global $DB;

        if ($imagesbase64 === []) {
            throw new \invalid_parameter_exception('At least one source image is required for editing');
        }

        $section = $DB->get_record('course_sections', ['id' => $sectionid], 'id, course', MUST_EXIST);

        image_generation_policy::assert_enabled(
            image_generation_policy::ENTITY_SECTION,
            image_generation_policy::ACTION_EDIT
        );

        $payload = $this->build_edit_payload(
            $imagesbase64,
            $instructions,
            $size,
            $quality,
            (string) $section->course,
        );

        return $this->jobservice->submit_job(self::EDIT_ENDPOINT, $payload);
    }

    /**
     * Submit an image generation job for embedded content (async, non-blocking).
     *
     * v1 uses scope "course" on the remote API with explicit title/summary; PHP gates on content policy.
     *
     * @param int $courseid The course ID.
     * @param string $title Human-readable title (e.g. activity name).
     * @param string $summary User prompt mapped to API summary.
     * @param string $size Image dimensions.
     * @param string $quality Quality level.
     * @return operation_result
     */
    public function submit_content_image_generate_job(
        int $courseid,
        string $title,
        string $summary,
        string $size = self::DEFAULT_SIZE,
        string $quality = self::DEFAULT_QUALITY
    ): operation_result {
        global $DB;

        $DB->get_record('course', ['id' => $courseid], 'id', MUST_EXIST);

        image_generation_policy::assert_enabled(
            image_generation_policy::ENTITY_CONTENT,
            image_generation_policy::ACTION_GENERATE
        );

        $payload = $this->build_payload(
            scope: 'course',
            title: $title,
            summary: $summary,
            size: $size,
            quality: $quality,
            courseid: (string) $courseid,
        );

        return $this->jobservice->submit_job(self::GENERATE_ENDPOINT, $payload);
    }

    /**
     * Submit an image edit job for embedded content (async, non-blocking).
     *
     * @param int $courseid The course ID.
     * @param array $imagesbase64 Raw base64-encoded source images.
     * @param string $instructions Change to apply (required).
     * @param string $size Image dimensions.
     * @param string $quality Quality level.
     * @return operation_result
     */
    public function submit_content_image_edit_job(
        int $courseid,
        array $imagesbase64,
        string $instructions,
        string $size = self::DEFAULT_SIZE,
        string $quality = self::DEFAULT_QUALITY
    ): operation_result {
        if ($imagesbase64 === []) {
            throw new \invalid_parameter_exception('At least one source image is required for editing');
        }
        if (trim($instructions) === '') {
            throw new \invalid_parameter_exception('Edit instructions are required');
        }

        image_generation_policy::assert_enabled(
            image_generation_policy::ENTITY_CONTENT,
            image_generation_policy::ACTION_EDIT
        );

        global $DB;
        $DB->get_record('course', ['id' => $courseid], 'id', MUST_EXIST);

        $payload = $this->build_edit_payload(
            $imagesbase64,
            $instructions,
            $size,
            $quality,
            (string) $courseid,
        );

        return $this->jobservice->submit_job(self::EDIT_ENDPOINT, $payload);
    }

    /**
     * Derive a human-readable title for an embedded content image from its file context.
     *
     * @param \stored_file $file
     * @return string
     */
    public static function resolve_title_for_stored_file(\stored_file $file): string {
        global $DB;

        $context = \context::instance_by_id($file->get_contextid(), IGNORE_MISSING);
        if (!$context) {
            return get_string('contentimagetitlefallback', 'local_dixeo');
        }

        if ($context->contextlevel === CONTEXT_MODULE) {
            $cm = get_coursemodule_from_id(null, $context->instanceid, 0, false, IGNORE_MISSING);
            if ($cm && !empty($cm->name)) {
                return (string) $cm->name;
            }
        }

        if ($context->contextlevel === CONTEXT_BLOCK) {
            $block = $DB->get_record('block_instances', ['id' => $context->instanceid], 'id, blockname, configdata', IGNORE_MISSING);
            if ($block) {
                $blocktitle = '';
                if (!empty($block->configdata)) {
                    $config = @unserialize($block->configdata);
                    if (is_object($config) && !empty($config->title)) {
                        $blocktitle = (string) $config->title;
                    }
                }
                if ($blocktitle !== '') {
                    return $blocktitle;
                }
            }
        }

        $coursecontext = $context->get_course_context(false);
        if ($coursecontext) {
            $course = $DB->get_record('course', ['id' => $coursecontext->instanceid], 'fullname', IGNORE_MISSING);
            if ($course && !empty($course->fullname)) {
                return (string) $course->fullname;
            }
        }

        return get_string('contentimagetitlefallback', 'local_dixeo');
    }

    /**
     * Build the API request payload for image editing.
     *
     * @param array $imagesbase64 Raw base64-encoded source images.
     * @param string $instructions Change to apply.
     * @param string $size Image dimensions.
     * @param string $quality Quality level.
     * @param string $courseid Course identifier for tracking.
     * @return array The request payload.
     */
    private function build_edit_payload(
        array $imagesbase64,
        string $instructions,
        string $size,
        string $quality,
        string $courseid
    ): array {
        if (!in_array($size, self::SUPPORTED_SIZES, true)) {
            throw new \invalid_parameter_exception(
                'Unsupported image size "' . $size . '". Supported: ' . implode(', ', self::SUPPORTED_SIZES)
            );
        }

        $payload = [
            'images' => array_values($imagesbase64),
            'instructions' => trim($instructions),
            'size' => $size,
            'quality' => $quality,
            'courseId' => $courseid,
        ];

        if ($this->namespace !== null) {
            $payload['namespace'] = $this->namespace;
        }

        return $payload;
    }

    /**
     * Build the API request payload for image generation.
     *
     * @param string $scope 'course' or 'section'.
     * @param string $title Human-readable title.
     * @param string|null $summary Optional HTML or plain text summary.
     * @param string $size Image dimensions.
     * @param string $quality Quality level.
     * @param string|null $courseid Optional course identifier for tracking.
     * @return array The request payload.
     */
    private function build_payload(
        string $scope,
        string $title,
        ?string $summary,
        string $size,
        string $quality,
        ?string $courseid
    ): array {
        if (!in_array($size, self::SUPPORTED_SIZES, true)) {
            throw new \invalid_parameter_exception(
                'Unsupported image size "' . $size . '". Supported: ' . implode(', ', self::SUPPORTED_SIZES)
            );
        }

        $payload = [
            'scope' => $scope,
            'title' => trim($title),
            'size' => $size,
            'quality' => $quality,
        ];

        $cleansummary = $this->clean_summary($summary);
        if ($cleansummary !== null) {
            $payload['summary'] = $cleansummary;
        }

        if ($courseid !== null) {
            $payload['courseId'] = $courseid;
        }

        if ($this->namespace !== null) {
            $payload['namespace'] = $this->namespace;
        }

        return $payload;
    }

    /**
     * Clean and truncate a raw (possibly HTML) summary for API consumption.
     *
     * @param string|null $raw Raw summary content.
     * @return string|null Cleaned summary or null if empty.
     */
    private function clean_summary(?string $raw): ?string {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $cleaned = trim($this->htmlhelper->clean_html($raw));
        if ($cleaned === '') {
            return null;
        }

        if (mb_strlen($cleaned) > self::SUMMARY_MAX_LENGTH) {
            $cleaned = mb_substr($cleaned, 0, self::SUMMARY_MAX_LENGTH);
        }

        return $cleaned;
    }

    /**
     * Resolve a user-friendly title for a section record.
     *
     * @param object $section The course_sections record.
     * @return string The section title.
     */
    private function resolve_section_title(object $section): string {
        if (!empty($section->name)) {
            return (string) $section->name;
        }

        return get_string('sectionname', 'moodle') . ' ' . (int) $section->section;
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
     * Get the underlying job service (for status polling).
     *
     * @return job_service The job service.
     */
    public function get_job_service(): job_service {
        return $this->jobservice;
    }
}
