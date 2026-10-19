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
     * Definitions declared by every installed `*_dixeo_settings()` callback, keyed by setting key.
     *
     * Keys are unique across components.
     *
     * @return definition[]
     */
    public static function load(): array {
        $rows = [];
        // Plugin callbacks are unavailable through get_plugins_with_function while an upgrade is running.
        // The catalogue is read during upgrade, so the lib.php files are included directly.
        foreach (\core_component::get_plugin_types() as $plugintype => $unused) {
            foreach (\core_component::get_plugin_list($plugintype) as $plugin => $dir) {
                $file = $dir . '/lib.php';
                if (!is_readable($file)) {
                    continue;
                }
                include_once($file);
                $function = $plugintype . '_' . $plugin . '_dixeo_settings';
                if (!function_exists($function)) {
                    continue;
                }
                $pluginrows = $function();
                if (is_array($pluginrows)) {
                    array_push($rows, ...$pluginrows);
                }
            }
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
        self::assert_requires($definitions);
        return $definitions;
    }

    /**
     * A requires key names another setting in the same component, and the chain does not loop.
     *
     * @param definition[] $definitions Catalogue keyed by setting key.
     */
    private static function assert_requires(array $definitions): void {
        foreach ($definitions as $definition) {
            $requires = $definition->get_requires();
            if ($requires === null) {
                continue;
            }
            $parent = $definitions[$requires] ?? null;
            if ($parent === null || $parent->get_component() !== $definition->get_component()) {
                throw new \coding_exception(
                    "Dixeo setting \"{$definition->get_key()}\" requires \"{$requires}\", which is not in this component."
                );
            }
            $seen = [$definition->get_key() => true];
            $cursor = $parent;
            while ($cursor->get_requires() !== null) {
                $next = $cursor->get_requires();
                if (isset($seen[$next])) {
                    throw new \coding_exception("Dixeo setting \"{$definition->get_key()}\" has a requires cycle.");
                }
                $seen[$next] = true;
                $cursor = $definitions[$next];
            }
        }
    }
}
