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
 * Service for AI-powered course structure generation.
 *
 * Submits course structure generation jobs to the Dixeo API and optionally
 * blocks until completion. The API returns a full course outline (sections,
 * modules, metadata) based on the provided instructions and optional template.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service;

use local_dixeo\api\exception\api_exception;
use local_dixeo\dto\job_binding_metadata;
use local_dixeo\dto\operation_result;

/**
 * Service for course structure generation operations.
 */
class course_structure_service {
    /** @var string API endpoint for course structure generation. */
    private const ENDPOINT = '/v1/courses/structure';

    /** @var string Job type identifier used to select polling timing. */
    private const JOB_TYPE = 'generate_course_structure';

    /** @var job_service Job management service. */
    private job_service $jobservice;

    /** @var module_types_service Module type catalogue lookup. */
    private module_types_service $moduletypesservice;

    /** @var string|null The namespace for API requests. */
    private ?string $namespace;

    /**
     * Constructor.
     *
     * @param job_service|null $jobservice Optional job service instance.
     * @param module_types_service|null $moduletypesservice Optional module types service.
     * @param string|null $namespace Optional namespace override.
     */
    public function __construct(
        ?job_service $jobservice = null,
        ?module_types_service $moduletypesservice = null,
        ?string $namespace = null
    ) {
        $this->jobservice = $jobservice ?? new job_service();
        $this->moduletypesservice = $moduletypesservice ?? new module_types_service();
        $this->namespace = $namespace ?? $this->get_configured_namespace();
    }

    /**
     * Submit a course structure generation job without waiting for completion.
     *
     * Returns immediately with a jobid. Use job_service::get_job_status() to poll
     * for completion, or call submit_and_wait() when a blocking call is acceptable.
     *
     * @param string $instructions Course description and generation instructions.
     * @param string|null $templateid UUID of a course template to constrain the structure.
     * @param string|null $courseid External course identifier for traceability.
     * @return operation_result Pending operation result containing the jobid.
     * @throws api_exception If the API request fails.
     */
    public function submit_generate(
        string $instructions,
        ?string $templateid = null,
        ?string $courseid = null
    ): operation_result {
        $payload = $this->build_payload($instructions, $templateid, $courseid);

        return $this->jobservice->submit_job(
            self::ENDPOINT,
            $payload,
            'block_dixeo_designer',
            $this->metadata_for_payload($payload)
        );
    }

    /**
     * Submit a course structure generation job and block until completion.
     *
     * Polls the API with timing defined by polling_config::for_job_type('generate_course_structure'):
     * 5s initial delay, 3s poll interval, 300s timeout. Use submit_generate() for
     * fire-and-forget behaviour in contexts where long blocking is unacceptable.
     *
     * @param string $instructions Course description and generation instructions.
     * @param string|null $templateid UUID of a course template to constrain the structure.
     * @param string|null $courseid External course identifier for traceability.
     * @return operation_result Completed operation result containing the course structure.
     * @throws api_exception If the API request fails or polling times out.
     */
    public function submit_and_wait(
        string $instructions,
        ?string $templateid = null,
        ?string $courseid = null
    ): operation_result {
        $payload = $this->build_payload($instructions, $templateid, $courseid);

        return $this->jobservice->submit_and_wait(
            self::ENDPOINT,
            $payload,
            self::JOB_TYPE,
            'block_dixeo_designer',
            $this->metadata_for_payload($payload)
        );
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
     * Resolve course context metadata from a structure generation payload.
     *
     * @param array $payload Request payload.
     * @return job_binding_metadata|null
     */
    private function metadata_for_payload(array $payload): ?job_binding_metadata {
        $courseid = (int) ($payload['courseId'] ?? $payload['courseid'] ?? 0);
        if ($courseid <= 0) {
            return null;
        }

        return job_binding_metadata::for_course($courseid);
    }

    /**
     * Build the API request payload.
     *
     * camelCase keys are intentional — they match the API contract.
     * Optional fields are omitted entirely rather than sent as null to avoid
     * triggering validation errors on the server side.
     *
     * Automatically includes the list of installed module types so the API only
     * generates modules that this Moodle instance can actually create.
     *
     * @param string $instructions Course description and generation instructions.
     * @param string|null $templateid Optional template UUID.
     * @param string|null $courseid Optional external course identifier.
     * @return array The request payload.
     * @throws \invalid_parameter_exception If required parameters are empty.
     */
    private function build_payload(
        string $instructions,
        ?string $templateid,
        ?string $courseid
    ): array {
        if (empty(trim($instructions))) {
            throw new \invalid_parameter_exception('Instructions are required');
        }

        $payload = [
            'instructions' => $instructions,
            'availableTypes' => $this->get_installed_module_types(),
        ];

        if ($templateid !== null) {
            $payload['templateId'] = $templateid;
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
     * Module type identifiers usable on this Moodle instance.
     *
     * Walks the API type catalogue and keeps every row whose underlying activity
     * plugin is installed, so several API types can map to a single Moodle plugin
     * (e.g. all H5P variants → mod_h5pactivity). Falls back to the legacy
     * "Moodle plugin name == type" convention when the catalogue is unreachable.
     *
     * @return string[] List of API type identifiers (e.g. ['page', 'quiz', 'h5p_quiz']).
     */
    private function get_installed_module_types(): array {
        try {
            $types = $this->moduletypesservice->get_module_types_cached();
        } catch (api_exception) {
            return plugin_installation_service::get_installed_plugin_names('mod');
        }

        $available = [];
        foreach ($types as $type) {
            if (!plugin_installation_service::is_module_type_installed($type)) {
                continue;
            }
            $typeid = $type['type'] ?? '';
            if (is_string($typeid) && $typeid !== '') {
                $available[] = $typeid;
            }
        }
        return $available;
    }

    /**
     * Get the configured namespace from plugin settings.
     *
     * @return string The namespace.
     */
    private function get_configured_namespace(): string {
        global $CFG;
        require_once($CFG->dirroot . '/local/dixeo/lib.php');
        return \local_dixeo_get_configured_namespace();
    }
}
