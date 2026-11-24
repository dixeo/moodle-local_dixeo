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
 * Value resolver for DSL field references.
 *
 * Resolves field values from different sources:
 * - $.field - AI data path (e.g., $.name, $.entries[0].concept)
 * - $var.field - Saved variable reference (e.g., $module.id)
 * - $context.key - Runtime context (e.g., $context.userid)
 * - {"value": X} - Constant values
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\dsl;

/**
 * Resolves DSL field values from various sources.
 *
 * This class handles the resolution of field specifications in DSL actions,
 * supporting AI data paths, variable references, context values, and constants.
 */
class value_resolver {
    /** @var array The AI-generated data from the API response. */
    protected array $aidata;

    /** @var array Saved variables from previous actions (e.g., $module). */
    protected array $variables;

    /** @var array Runtime context (courseid, userid, etc.). */
    protected array $context;

    /**
     * Constructor.
     *
     * @param array $aidata The AI-generated data from the API.
     * @param array $variables Saved variables from previous actions.
     * @param array $context Runtime context values.
     */
    public function __construct(array $aidata, array $variables = [], array $context = []) {
        $this->aidata = $aidata;
        $this->variables = $variables;
        $this->context = $context;
    }

    /**
     * Resolve a field specification to its actual value.
     *
     * @param array $fieldspec The field specification (e.g., {"source": "$.name"} or {"value": 1}).
     * @param string $fieldname The field name for error messages.
     * @return mixed The resolved value.
     * @throws dsl_exception If the field cannot be resolved.
     */
    public function resolve(array $fieldspec, string $fieldname = ''): mixed {
        // Constant value - return directly.
        if (array_key_exists('value', $fieldspec)) {
            return $fieldspec['value'];
        }

        // Source reference - parse and resolve.
        if (!isset($fieldspec['source'])) {
            throw dsl_exception::invalid_source('missing source', $fieldname);
        }

        return $this->resolve_source($fieldspec['source'], $fieldname);
    }

    /**
     * Resolve a source reference string.
     *
     * @param string $source The source reference (e.g., "$.name", "$module.id", "$context.userid").
     * @param string $fieldname The field name for error messages.
     * @return mixed The resolved value.
     * @throws dsl_exception If resolution fails.
     */
    public function resolve_source(string $source, string $fieldname = ''): mixed {
        $source = trim($source);

        if ($source === '') {
            throw dsl_exception::invalid_source('empty source', $fieldname);
        }

        // Context reference: $context.key.
        if (str_starts_with($source, '$context.')) {
            return $this->resolve_context_reference($source);
        }

        // AI data path: $.field or $.field[0].subfield.
        if (str_starts_with($source, '$.')) {
            return $this->resolve_ai_data_path($source);
        }

        // Variable reference: $varname.field.
        if (str_starts_with($source, '$')) {
            return $this->resolve_variable_reference($source);
        }

        throw dsl_exception::invalid_source($source, $fieldname);
    }

    /**
     * Resolve a context reference.
     *
     * @param string $source The source (e.g., "$context.userid").
     * @return mixed The context value.
     * @throws dsl_exception If the context key is missing.
     */
    protected function resolve_context_reference(string $source): mixed {
        // Remove the "$context." prefix.
        $key = substr($source, strlen('$context.'));

        if (!array_key_exists($key, $this->context)) {
            throw dsl_exception::missing_context($key);
        }

        return $this->context[$key];
    }

    /**
     * Resolve a variable reference.
     *
     * @param string $source The source (e.g., "$module.id").
     * @return mixed The variable field value.
     * @throws dsl_exception If the variable or field is missing.
     */
    protected function resolve_variable_reference(string $source): mixed {
        // Parse $varname.field format.
        $withoutdollar = substr($source, 1);
        $parts = explode('.', $withoutdollar, 2);

        $varname = $parts[0];

        if (!array_key_exists($varname, $this->variables)) {
            throw dsl_exception::missing_variable($varname);
        }

        $varvalue = $this->variables[$varname];

        // If there's no field path, return the whole variable.
        if (count($parts) === 1) {
            return $varvalue;
        }

        // Navigate to the field.
        $path = $parts[1];
        return $this->navigate_path($varvalue, $path, $source);
    }

    /**
     * Resolve an AI data path.
     *
     * @param string $source The source (e.g., "$.name" or "$.entries[0].concept").
     * @return mixed The resolved value.
     * @throws dsl_exception If the path is invalid.
     */
    protected function resolve_ai_data_path(string $source): mixed {
        // Remove the "$." prefix.
        $path = substr($source, 2);

        if ($path === '') {
            return $this->aidata;
        }

        return $this->navigate_path($this->aidata, $path, $source);
    }

    /**
     * Navigate a dot-notation path through nested data.
     *
     * Supports both object property access (field.subfield) and
     * array index access (field[0].subfield).
     *
     * @param mixed $data The data to navigate.
     * @param string $path The dot-notation path.
     * @param string $fullpath The full path for error messages.
     * @return mixed The value at the path.
     * @throws dsl_exception If the path is invalid.
     */
    protected function navigate_path(mixed $data, string $path, string $fullpath): mixed {
        $current = $data;
        $segments = $this->parse_path_segments($path);

        foreach ($segments as $segment) {
            $current = $this->access_segment($current, $segment, $fullpath);
        }

        return $current;
    }

    /**
     * Parse a path into segments.
     *
     * Handles both dot notation and array indexing.
     * Example: "entries[0].concept" => ["entries", "[0]", "concept"]
     *
     * @param string $path The path to parse.
     * @return array The path segments.
     */
    protected function parse_path_segments(string $path): array {
        $segments = [];
        $current = '';

        $length = strlen($path);
        for ($i = 0; $i < $length; $i++) {
            $char = $path[$i];

            if ($char === '.') {
                // Dot separator - save current segment if not empty.
                if ($current !== '') {
                    $segments[] = $current;
                    $current = '';
                }
            } else if ($char === '[') {
                // Array index start - save current segment first.
                if ($current !== '') {
                    $segments[] = $current;
                    $current = '';
                }
                // Collect the index including brackets.
                $indexpart = '[';
                $i++;
                while ($i < $length && $path[$i] !== ']') {
                    $indexpart .= $path[$i];
                    $i++;
                }
                $indexpart .= ']';
                $segments[] = $indexpart;
            } else {
                $current .= $char;
            }
        }

        // Don't forget the last segment.
        if ($current !== '') {
            $segments[] = $current;
        }

        return $segments;
    }

    /**
     * Access a single segment in the data.
     *
     * @param mixed $data The current data context.
     * @param string $segment The segment to access.
     * @param string $fullpath The full path for error messages.
     * @return mixed The value at the segment.
     * @throws dsl_exception If access fails.
     */
    protected function access_segment(mixed $data, string $segment, string $fullpath): mixed {
        // A segment wrapped in square brackets is a numeric array index.
        if (str_starts_with($segment, '[') && str_ends_with($segment, ']')) {
            $index = (int) substr($segment, 1, -1);

            if (!is_array($data)) {
                throw new dsl_exception(
                    "Cannot access index $segment on non-array data in path '$fullpath'",
                    'value_resolution',
                    ['path' => $fullpath, 'segment' => $segment]
                );
            }

            if (!array_key_exists($index, $data)) {
                throw new dsl_exception(
                    "Index $index does not exist in path '$fullpath'",
                    'value_resolution',
                    ['path' => $fullpath, 'segment' => $segment]
                );
            }

            return $data[$index];
        }

        // Handle object/array field access.
        if (is_array($data)) {
            if (!array_key_exists($segment, $data)) {
                throw dsl_exception::invalid_path($fullpath, $data);
            }
            return $data[$segment];
        }

        if (is_object($data)) {
            if (!property_exists($data, $segment)) {
                throw new dsl_exception(
                    "Property '$segment' does not exist on object in path '$fullpath'",
                    'value_resolution',
                    ['path' => $fullpath, 'segment' => $segment]
                );
            }
            return $data->$segment;
        }

        throw new dsl_exception(
            "Cannot navigate path '$fullpath' - expected array or object",
            'value_resolution',
            ['path' => $fullpath, 'type' => gettype($data)]
        );
    }

    /**
     * Resolve all fields in a fields specification array.
     *
     * @param array $fieldsspec The fields specification (field => {"source": ...} or {"value": ...}).
     * @param bool $islenient When true, missing data paths resolve to null instead of throwing.
     *                        Use for union field specs where items have different shapes (e.g., quiz question types).
     * @return array The resolved field values.
     * @throws dsl_exception If any field cannot be resolved (when not lenient).
     */
    public function resolve_fields(array $fieldsspec, bool $islenient = false): array {
        $resolved = [];

        foreach ($fieldsspec as $fieldname => $fieldspec) {
            try {
                $resolved[$fieldname] = $this->resolve($fieldspec, $fieldname);
            } catch (dsl_exception $e) {
                if (!$islenient) {
                    throw $e;
                }
                $resolved[$fieldname] = null;
            }
        }

        return $resolved;
    }

    /**
     * Create a new resolver with updated AI data.
     *
     * Used when iterating over a collection (foreach).
     *
     * @param array $aidata The new AI data context.
     * @return self A new resolver with updated data.
     */
    public function with_ai_data(array $aidata): self {
        return new self($aidata, $this->variables, $this->context);
    }

    /**
     * Create a new resolver with an additional variable.
     *
     * @param string $name The variable name.
     * @param mixed $value The variable value.
     * @return self A new resolver with the added variable.
     */
    public function with_variable(string $name, mixed $value): self {
        $variables = $this->variables;
        $variables[$name] = $value;
        return new self($this->aidata, $variables, $this->context);
    }

    /**
     * Get the current variables.
     *
     * @return array The variables array.
     */
    public function get_variables(): array {
        return $this->variables;
    }

    /**
     * Get the current AI data.
     *
     * @return array The AI data.
     */
    public function get_ai_data(): array {
        return $this->aidata;
    }

    /**
     * Get the current context.
     *
     * @return array The context.
     */
    public function get_context(): array {
        return $this->context;
    }
}
