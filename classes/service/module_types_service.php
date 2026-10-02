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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_dixeo\service;

use cache;
use local_dixeo\api\client;
use local_dixeo\api\exception\api_exception;

/**
 * Service for Dixeo module type catalogue (activity chooser / designer).
 *
 * Caches the raw /v1/modules/types API payload in application cache. Consumers should
 * call {@see get_module_types_resolved()} to obtain rows enriched with installation
 * status and Moodle-localized labels — single source of truth for both the web service
 * and the block queue presenter.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class module_types_service {
    /** @var string Dixeo API endpoint for module type catalogue. */
    private const ENDPOINT = '/v1/modules/types';

    /** @var string Application cache key for the raw type list. */
    private const CACHE_KEY = 'moduletypes';

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
     * Raw module type rows from the Dixeo API, application-cached when configured.
     *
     * Does not set installed or localized labels (done in the web service).
     *
     * @return array List of associative arrays (type, label, description, category, component, …).
     * @throws api_exception If the API is not configured or the request fails.
     */
    public function get_module_types_cached(): array {
        // When not configured, skip cache so we never serve stale data after key removal.
        if ($this->client->is_configured()) {
            $cache = cache::make('local_dixeo', 'moduletypes');
            $cached = $cache->get(self::CACHE_KEY);
            if ($cached !== false) {
                return $this->copy_type_rows($cached);
            }
        }

        $types = $this->client->get(self::ENDPOINT);
        if (!is_array($types)) {
            $types = [];
        }
        $types = array_values(array_filter($types, 'is_array'));

        if ($this->client->is_configured()) {
            $cache = cache::make('local_dixeo', 'moduletypes');
            $cache->set(self::CACHE_KEY, $types);
        }

        return $this->copy_type_rows($types);
    }

    /**
     * Module type rows enriched with installation status and a Moodle-localized label.
     *
     * For each row:
     * - `installed` is set via {@see plugin_installation_service::is_module_type_installed()},
     *   which validates both the activity plugin and any H5P library requirements.
     * - `label` is replaced by Moodle's `modulename` / `pluginname` string when the row
     *   maps 1:1 to a Moodle activity plugin (so the language pack wins over the API label).
     *   Rows whose plugin is shared by several types (all H5P variants → mod_h5pactivity)
     *   keep their distinct API labels so variants don't collapse onto a single string.
     *
     * @return array Resolved rows.
     * @throws api_exception
     */
    public function get_module_types_resolved(): array {
        $rows = $this->get_module_types_cached();

        $componentcounts = [];
        foreach ($rows as $row) {
            $component = self::string_component_for($row);
            $componentcounts[$component] = ($componentcounts[$component] ?? 0) + 1;
        }

        $sm = get_string_manager();
        foreach ($rows as &$row) {
            $row['installed'] = plugin_installation_service::is_module_type_installed($row);

            if (!empty($row['installed'])) {
                $component = self::string_component_for($row);
                if (($componentcounts[$component] ?? 0) <= 1) {
                    if ($sm->string_exists('modulename', $component)) {
                        $row['label'] = get_string('modulename', $component);
                    } else if ($sm->string_exists('pluginname', $component)) {
                        $row['label'] = get_string('pluginname', $component);
                    }
                }
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Moodle activity plugin a Dixeo generation type is allowed to create.
     *
     * The type must appear in the catalogue as installed and not unsupported.
     * The row's component selects the activity (for example h5p_quiz creates
     * h5pactivity). When the catalogue cannot be loaded, only an installed
     * activity name is accepted.
     *
     * @param string $type Dixeo module type, or a Moodle activity name when the catalogue is unavailable.
     * @return string|null Moodle module name, or null when the type must not be created.
     */
    public function resolve_generation_module(string $type): ?string {
        $type = clean_param($type, PARAM_ALPHANUMEXT);
        if ($type === '') {
            return null;
        }

        try {
            $rows = $this->get_module_types_resolved();
        } catch (api_exception $e) {
            if ($this->client->is_configured()) {
                debugging('Dixeo module catalogue is unavailable: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
            if (!plugin_installation_service::is_component_installed('mod_' . $type)) {
                return null;
            }
            return $type;
        }

        foreach ($rows as $row) {
            if (!is_array($row) || ($row['type'] ?? '') !== $type) {
                continue;
            }
            if (array_key_exists('supported', $row) && $row['supported'] === false) {
                return null;
            }
            if (empty($row['installed'])) {
                return null;
            }
            $pluginname = plugin_installation_service::resolve_module_type_plugin_name($row);
            return $pluginname !== '' ? $pluginname : null;
        }

        return null;
    }

    /**
     * Whether a completed job's module type is the one that was authorised.
     *
     * The job may report either the requested Dixeo type or the Moodle activity
     * that type creates.
     *
     * @param string $requested Type stored on the queue row.
     * @param string $resulttype moduleType from the completed job.
     * @param string $moodlemodule Activity plugin resolved for the request.
     * @return bool
     */
    public static function result_matches_requested_type(
        string $requested,
        string $resulttype,
        string $moodlemodule
    ): bool {
        $resulttype = clean_param($resulttype, PARAM_ALPHANUMEXT);
        if ($resulttype === '') {
            return false;
        }
        return $resulttype === $requested || $resulttype === $moodlemodule;
    }

    /**
     * Component identifier used for `modulename` / `pluginname` string lookups.
     *
     * Falls back to `mod_<type>` when the row carries no usable `component`.
     *
     * @param array $row Row from {@see get_module_types_cached()}.
     */
    public static function string_component_for(array $row): string {
        $component = $row['component'] ?? '';
        if (is_string($component) && $component !== '' && strpos($component, 'mod_') === 0) {
            return $component;
        }
        return 'mod_' . ($row['type'] ?? '');
    }

    /**
     * Shallow-copy each row so callers can add keys without mutating cached structures.
     *
     * @param array $types Raw rows.
     * @return array Copy of rows.
     */
    private function copy_type_rows(array $types): array {
        return array_map(static function (array $row): array {
            return array_merge([], $row);
        }, $types);
    }
}
