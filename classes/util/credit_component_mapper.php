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

namespace local_dixeo\util;

/**
 * Maps job types and operations to originating Dixeo components.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credit_component_mapper {
    /** @var string Default component when unknown. */
    public const COMPONENT_UNKNOWN = 'local_dixeo';

    /** @var array<string, string> API job type to component map. */
    private const JOBTYPE_MAP = [
        'generate_module' => 'block_dixeo_modulegen',
        'fill_module' => 'block_dixeo_designer',
        'edit_module' => 'local_dixeo_editor',
        'tutor' => 'block_dixeo_tutor',
        'tutor_message' => 'block_dixeo_tutor',
        'course_structure' => 'block_dixeo_designer',
        'image_generate' => 'filter_dixeo_imageeditor',
        'image_edit' => 'filter_dixeo_imageeditor',
    ];

    /** @var array<string, string> Local operation to component map. */
    private const OPERATION_MAP = [
        'module_generate' => 'block_dixeo_modulegen',
        'module_fill' => 'block_dixeo_designer',
        'module_edit' => 'local_dixeo_editor',
        'tutor_message' => 'block_dixeo_tutor',
        'course_structure' => 'block_dixeo_designer',
        'image_generate' => 'filter_dixeo_imageeditor',
        'image_edit' => 'filter_dixeo_imageeditor',
    ];

    /**
     * Canonical Dixeo components for credit report filters.
     *
     * @return string[]
     */
    public static function get_known_components(): array {
        return [
            'block_dixeo_designer',
            'block_dixeo_modulegen',
            'block_dixeo_tutor',
            'filter_dixeo_imageeditor',
            'local_dixeo',
            'local_dixeo_editor',
        ];
    }

    /** @var array<string, string> Local operation aliases to canonical action codes. */
    private const ACTION_ALIASES = [
        'module_generate' => 'generate_module',
        'module_fill' => 'fill_module',
        'module_edit' => 'edit_module',
        'tutor' => 'tutor_message',
    ];

    /**
     * Canonical action codes for credit report filters.
     *
     * @return string[]
     */
    public static function get_known_actions(): array {
        $actions = self::normalize_action_list(array_keys(self::JOBTYPE_MAP));
        sort($actions);
        return $actions;
    }

    /**
     * Normalize a stored job type or operation to the canonical action code.
     *
     * @param string|null $code Job type or operation code.
     * @return string|null
     */
    public static function normalize_action(?string $code): ?string {
        if ($code === null || $code === '') {
            return null;
        }

        return self::ACTION_ALIASES[$code] ?? $code;
    }

    /**
     * Normalize a list of action codes, removing duplicates.
     *
     * @param string[] $codes Action codes.
     * @return string[]
     */
    public static function normalize_action_list(array $codes): array {
        $normalized = [];
        foreach ($codes as $code) {
            $canonical = self::normalize_action($code);
            if ($canonical !== null && $canonical !== '') {
                $normalized[] = $canonical;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Expand canonical action filters to include stored alias codes.
     *
     * @param string[] $codes Selected action codes.
     * @return string[]
     */
    public static function expand_action_filter(array $codes): array {
        $expanded = self::normalize_action_list($codes);

        foreach (self::ACTION_ALIASES as $alias => $canonical) {
            if (in_array($canonical, $expanded, true)) {
                $expanded[] = $alias;
            }
        }

        foreach ($codes as $code) {
            if ($code !== '' && !in_array($code, $expanded, true)) {
                $expanded[] = $code;
            }
        }

        return array_values(array_unique($expanded));
    }

    /**
     * Resolve component from stored value or API metadata.
     *
     * @param string|null $component Stored component from job binding.
     * @param string|null $jobtype API job type.
     * @param string|null $operation Local operation fallback.
     * @return string Frankenstyle component name.
     */
    public static function resolve(?string $component, ?string $jobtype = null, ?string $operation = null): string {
        if (!empty($component)) {
            return $component;
        }

        $jobtypemap = self::JOBTYPE_MAP;

        if (!empty($jobtype) && isset($jobtypemap[$jobtype])) {
            return $jobtypemap[$jobtype];
        }

        $operationmap = self::OPERATION_MAP;

        if (!empty($operation) && isset($operationmap[$operation])) {
            return $operationmap[$operation];
        }

        return self::COMPONENT_UNKNOWN;
    }

    /**
     * Human-readable component label.
     *
     * @param string|null $component Frankenstyle component.
     * @return string
     */
    public static function get_label(?string $component): string {
        $component = $component ?: self::COMPONENT_UNKNOWN;
        $key = 'credit_component_' . str_replace('/', '_', $component);
        $label = get_string($key, 'local_dixeo');
        if ($label === "[[$key]]") {
            return get_string('credit_component_unknown', 'local_dixeo');
        }
        return $label;
    }

    /**
     * Human-readable action label from job type or operation.
     *
     * @param string|null $jobtype API job type.
     * @param string|null $operation Local operation fallback.
     * @return string
     */
    public static function get_action_label(?string $jobtype, ?string $operation = null): string {
        $code = $jobtype ?: $operation;
        if (empty($code)) {
            return get_string('credit_action_unknown', 'local_dixeo');
        }

        $key = 'credit_action_' . $code;
        $label = get_string($key, 'local_dixeo');
        if ($label === "[[$key]]") {
            return ucwords(str_replace('_', ' ', $code));
        }
        return $label;
    }
}
