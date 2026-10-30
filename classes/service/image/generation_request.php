<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Image generate job waiting to be submitted to the Dixeo API.
 *
 * Bulk content creation queues its images instead of submitting them inline, so
 * a refused or lost submission is retried by the poll task rather than dropped.
 * The request travels in the task custom data and keeps one idempotency key, so
 * a resubmission after a lost response never creates or bills a second job.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service\image;

use local_dixeo\dto\job_binding_metadata;
use local_dixeo\external\service_factory;
use local_dixeo\service\image_generation_service;

/**
 * Everything needed to submit one image generate job.
 */
final class generation_request {
    /**
     * Constructor.
     *
     * @param string $kind image_target::KIND_CONTENT or image_target::KIND_FORMAT_SECTION.
     * @param string $component Originating frankenstyle component.
     * @param int $courseid Course id.
     * @param int $sectionid course_sections.id, section kind only.
     * @param string $title Image title, content kind only.
     * @param string $prompt Visual description, content kind only.
     * @param string $size Image dimensions.
     * @param string $quality Quality level.
     * @param array|null $binding job_binding_metadata::to_array() output.
     * @param string $idempotencykey Key sent with every submission attempt.
     */
    private function __construct(
        /** @var string image_target::KIND_CONTENT or image_target::KIND_FORMAT_SECTION. */
        public readonly string $kind,
        /** @var string Originating frankenstyle component. */
        public readonly string $component,
        /** @var int Course id. */
        public readonly int $courseid,
        /** @var int course_sections.id, section kind only. */
        public readonly int $sectionid,
        /** @var string Image title, content kind only. */
        public readonly string $title,
        /** @var string Visual description, content kind only. */
        public readonly string $prompt,
        /** @var string Image dimensions. */
        public readonly string $size,
        /** @var string Quality level. */
        public readonly string $quality,
        /** @var array|null job_binding_metadata::to_array() output. */
        public readonly ?array $binding,
        /** @var string Key sent with every submission attempt. */
        public readonly string $idempotencykey
    ) {
        if (!in_array($kind, [image_target::KIND_CONTENT, image_target::KIND_FORMAT_SECTION], true)) {
            throw new \coding_exception('Unsupported image generation kind: ' . $kind);
        }
    }

    /**
     * Request for an image embedded in content.
     *
     * @param string $component Originating frankenstyle component.
     * @param int $courseid Course id.
     * @param string $title Image title.
     * @param string $prompt Visual description.
     * @param string $size Image dimensions.
     * @param string $quality Quality level.
     * @param job_binding_metadata|null $binding Host activity/context metadata.
     * @return self
     */
    public static function for_content(
        string $component,
        int $courseid,
        string $title,
        string $prompt,
        string $size,
        string $quality,
        ?job_binding_metadata $binding = null
    ): self {
        return new self(
            image_target::KIND_CONTENT,
            $component,
            $courseid,
            0,
            $title,
            $prompt,
            $size,
            $quality,
            $binding?->to_array(),
            \core\uuid::generate()
        );
    }

    /**
     * Request for a course section image.
     *
     * @param string $component Originating frankenstyle component.
     * @param int $courseid Course id.
     * @param int $sectionid course_sections.id.
     * @return self
     */
    public static function for_section(string $component, int $courseid, int $sectionid): self {
        return new self(
            image_target::KIND_FORMAT_SECTION,
            $component,
            $courseid,
            $sectionid,
            '',
            '',
            image_generation_service::DEFAULT_SIZE,
            image_generation_service::DEFAULT_QUALITY,
            null,
            \core\uuid::generate()
        );
    }

    /**
     * Plain array form, for adhoc task custom data.
     *
     * @return array<string, mixed>
     */
    public function to_array(): array {
        return [
            'kind' => $this->kind,
            'component' => $this->component,
            'courseid' => $this->courseid,
            'sectionid' => $this->sectionid,
            'title' => $this->title,
            'prompt' => $this->prompt,
            'size' => $this->size,
            'quality' => $this->quality,
            'binding' => $this->binding,
            'idempotencykey' => $this->idempotencykey,
        ];
    }

    /**
     * Rebuild from {@see self::to_array()} output, also as decoded task custom data.
     *
     * @param array|\stdClass $data
     * @return self
     */
    public static function from_array(array|\stdClass $data): self {
        $data = json_decode(json_encode($data), true);
        $binding = $data['binding'] ?? null;

        return new self(
            (string) ($data['kind'] ?? ''),
            (string) ($data['component'] ?? ''),
            (int) ($data['courseid'] ?? 0),
            (int) ($data['sectionid'] ?? 0),
            (string) ($data['title'] ?? ''),
            (string) ($data['prompt'] ?? ''),
            (string) ($data['size'] ?? image_generation_service::DEFAULT_SIZE),
            (string) ($data['quality'] ?? image_generation_service::DEFAULT_QUALITY),
            is_array($binding) ? $binding : null,
            (string) ($data['idempotencykey'] ?? '')
        );
    }

    /**
     * Submit the generate job.
     *
     * @return string Remote job id.
     * @throws \local_dixeo\api\exception\api_exception If the API refuses the request.
     * @throws \moodle_exception If image generation is disabled or the API returns no job id.
     */
    public function submit(): string {
        $service = service_factory::get_image_generation_service($this->component);
        $key = $this->idempotencykey !== '' ? $this->idempotencykey : null;

        $result = match ($this->kind) {
            image_target::KIND_FORMAT_SECTION => $service->submit_section_image_job(
                $this->sectionid,
                $this->size,
                $this->quality,
                $key
            ),
            image_target::KIND_CONTENT => $service->submit_content_image_generate_job(
                $this->courseid,
                $this->title,
                $this->prompt,
                $this->size,
                $this->quality,
                $this->binding !== null ? job_binding_metadata::from_array($this->binding) : null,
                $key
            ),
        };

        $jobid = trim((string) $result->jobid);
        if ($jobid === '') {
            throw new \moodle_exception('dixeo_image_job_empty_result', 'local_dixeo');
        }

        return $jobid;
    }
}
