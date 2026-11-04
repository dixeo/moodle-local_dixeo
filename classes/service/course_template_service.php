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

namespace local_dixeo\service;

use cache;
use local_dixeo\api\client;
use local_dixeo\api\exception\api_exception;

/**
 * Service for course template CRUD operations.
 *
 * Wraps the /v1/courses/templates API endpoints with synchronous direct calls.
 * Templates define the exact course structure (sections × slots with per-slot
 * module type constraints) that the course structure generator applies when
 * building a course outline.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_template_service {
    /** @var string Base API endpoint for course templates. */
    private const ENDPOINT = '/v1/courses/templates';

    /** @var string Cache key for normalised template records (id, name, description). */
    private const CACHE_KEY_TEMPLATES = 'templates';

    /** @var client The API client. */
    protected client $client;

    /**
     * Constructor.
     *
     * @param client|null $client Optional API client (creates new if not provided).
     */
    public function __construct(?client $client = null) {
        $this->client = $client ?? new client();
    }

    /**
     * List all available course templates (system-wide and tenant-owned).
     *
     * @return array List of template objects from the API.
     * @throws api_exception If an API error occurs.
     */
    public function list_templates(): array {
        return $this->client->get(self::ENDPOINT);
    }

    /**
     * Returns normalised course templates for UI (id, name, description), cached.
     *
     * @return array List of template records with keys id, name, description.
     */
    public function get_cached_templates(): array {
        if (!$this->is_configured()) {
            return [];
        }

        $cache = cache::make('local_dixeo', 'coursetemplates');
        $cached = $cache->get(self::CACHE_KEY_TEMPLATES);
        if ($cached !== false) {
            return $cached;
        }

        try {
            $templates = $this->list_templates();
            $normalised = $this->normalise_templates($templates);
            $cache->set(self::CACHE_KEY_TEMPLATES, $normalised);
            return $normalised;
        } catch (api_exception $e) {
            debugging('Unable to load course templates from Dixeo API: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }

    /**
     * Returns course template choices for UI (id => label), cached.
     *
     * @return array Map of template id => display label.
     */
    public function get_cached_choices(): array {
        $choices = [];
        foreach ($this->get_cached_templates() as $template) {
            $choices[$template['id']] = $template['name'];
        }
        return $choices;
    }

    /**
     * Course template choices (id => plain-text name) from the cache only.
     *
     * Never calls the API, so it is safe where a remote call is too slow, such as while the
     * settings catalogue loads.
     *
     * @return array Map of template id => name, empty when the cache is cold.
     */
    public function peek_cached_choices(): array {
        $cached = cache::make('local_dixeo', 'coursetemplates')->get(self::CACHE_KEY_TEMPLATES);
        if (!is_array($cached)) {
            return [];
        }

        $choices = [];
        foreach ($cached as $template) {
            if (is_array($template) && isset($template['id'], $template['name'])) {
                $choices[(string) $template['id']] = (string) $template['name'];
            }
        }
        return $choices;
    }

    /**
     * Normalises template arrays into UI records.
     *
     * Names come from the API and are stored as plain text, so every consumer can print them
     * as such. A template whose name is empty once cleaned is skipped.
     *
     * @param array $templates List of template arrays from the API.
     * @return array List of records with id, name, and description.
     */
    private function normalise_templates(array $templates): array {
        $result = [];
        foreach ($templates as $template) {
            if (!is_array($template)) {
                continue;
            }
            $id = (string) ($template['id'] ?? '');
            $name = trim(clean_param((string) ($template['name'] ?? ''), PARAM_TEXT));
            if ($id === '' || $name === '') {
                continue;
            }
            $description = trim((string) ($template['description'] ?? ''));
            $result[] = [
                'id' => $id,
                'name' => $name,
                'description' => $description !== '' ? s($description) : '',
            ];
        }
        return $result;
    }

    /**
     * Get a single course template by its UUID.
     *
     * @param string $templateid Template UUID.
     * @return array Template data from the API.
     * @throws api_exception If the template is not found or an API error occurs.
     */
    public function get_template(string $templateid): array {
        return $this->client->get(self::ENDPOINT . '/' . $templateid);
    }

    /**
     * Create a new course template.
     *
     * The templateDefinition defines the exact course structure: an ordered list
     * of sections each containing an ordered list of slots. Each slot may restrict
     * which Moodle module types the AI may choose, or leave them unrestricted (null).
     *
     * @param string $name Template display name.
     * @param string|null $description Optional human-readable description.
     * @param array|null $templatedefinition Optional per-section, per-slot structure definition.
     * @return array Created template data returned by the API.
     * @throws \invalid_parameter_exception If the name is empty.
     * @throws api_exception If an API error occurs.
     */
    public function create_template(
        string $name,
        ?string $description = null,
        ?array $templatedefinition = null
    ): array {
        if (empty(trim($name))) {
            throw new \invalid_parameter_exception('Template name is required');
        }

        $payload = ['name' => $name];

        if ($description !== null) {
            $payload['description'] = $description;
        }
        if ($templatedefinition !== null) {
            $payload['templateDefinition'] = $templatedefinition;
        }

        return $this->client->post(self::ENDPOINT, $payload);
    }

    /**
     * Update an existing course template by UUID.
     *
     * Performs a full replacement (PUT semantics). Fields omitted from this call
     * will be cleared on the server, so always pass all desired field values.
     *
     * @param string $templateid Template UUID to update.
     * @param string $name Updated template display name.
     * @param string|null $description Updated description (null clears it).
     * @param array|null $templatedefinition Updated structure definition (null clears it).
     * @return array Updated template data returned by the API.
     * @throws \invalid_parameter_exception If the name is empty.
     * @throws api_exception If the template is not found or an API error occurs.
     */
    public function update_template(
        string $templateid,
        string $name,
        ?string $description = null,
        ?array $templatedefinition = null
    ): array {
        if (empty(trim($name))) {
            throw new \invalid_parameter_exception('Template name is required');
        }

        // Always send all fields: PUT is a full replacement, so omitting a field
        // would silently preserve the old value on some servers. Sending null
        // is the correct way to signal "clear this field".
        $payload = [
            'name' => $name,
            'description' => $description,
            'templateDefinition' => $templatedefinition,
        ];

        return $this->client->put(self::ENDPOINT . '/' . $templateid, $payload);
    }

    /**
     * Delete a course template by UUID.
     *
     * Only tenant-owned templates can be deleted. System templates are read-only.
     *
     * @param string $templateid Template UUID to delete.
     * @return array Deletion result from the API.
     * @throws api_exception If the template is not found, is read-only, or an API error occurs.
     */
    public function delete_template(string $templateid): array {
        return $this->client->delete(self::ENDPOINT . '/' . $templateid);
    }

    /**
     * Check if the API is configured.
     *
     * @return bool True if the API client is configured with a valid key.
     */
    public function is_configured(): bool {
        return $this->client->is_configured();
    }
}
