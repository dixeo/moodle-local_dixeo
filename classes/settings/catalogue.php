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

namespace local_dixeo\settings;

/**
 * Loads Dixeo setting definitions and resolves any core Moodle target.
 *
 * A definition with no core target is Dixeo-only. A definition whose core target is not
 * an admin setting is rejected and is not returned as a Dixeo-only setting.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class catalogue {
    /**
     * Definitions declared by local_dixeo_dixeo_settings(), keyed by setting key.
     *
     * @return definition[]
     */
    public static function load(): array {
        $rows = component_callback('local_dixeo', 'dixeo_settings', [], null);
        if (!is_array($rows)) {
            return [];
        }
        return self::from_definitions($rows);
    }

    /**
     * Validate raw catalogue rows.
     *
     * @param array[] $rows Rows shaped like the return value of local_dixeo_dixeo_settings().
     * @return definition[] Keyed by setting key.
     */
    public static function from_definitions(array $rows): array {
        $definitions = [];
        foreach ($rows as $raw) {
            if (!is_array($raw)) {
                throw new \coding_exception('Dixeo setting catalogue rows must be arrays.');
            }
            $definition = definition::from_array($raw);
            $core = $definition->get_core();
            if ($core !== null) {
                core_lookup::assert_resolvable($definition->get_key(), $core);
            }
            $key = $definition->get_key();
            if (isset($definitions[$key])) {
                throw new \coding_exception("Dixeo setting \"{$key}\" is declared more than once.");
            }
            $definitions[$key] = $definition;
        }
        return $definitions;
    }
}
