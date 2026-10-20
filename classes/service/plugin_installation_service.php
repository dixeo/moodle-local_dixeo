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
use core_component;
use core_plugin_manager;

/**
 * Cached lookups for which Moodle plugins are installed (per plugintype).
 *
 * Application-cached for 24 hours. Purge all caches after installing or removing
 * plugins if you need immediate accuracy.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugin_installation_service {
    /** @var string Application cache area (see db/caches.php). */
    public const CACHE_AREA = 'installedplugintypes';

    /**
     * Whether a frankenstyle component is installed (present in the plugin manager).
     *
     * @param string $component e.g. local_edai, block_dixeo_modulegen, mod_coursecertificate.
     * @return bool False for invalid components; true only if the plugin is listed for its type.
     */
    public static function is_component_installed(string $component): bool {
        [$type, $name] = core_component::normalize_component($component);
        if ($type === 'core' || $name === null || $name === '') {
            return false;
        }
        $installed = self::get_installed_plugin_map($type);
        return isset($installed[$name]);
    }

    /**
     * Whether a plugin is installed and its code is available.
     *
     * A plugin that has not been installed, or whose code is gone, does not contribute
     * Dixeo settings. The version row is read directly so this stays accurate during install.
     *
     * @param string $component Frankenstyle component, such as block_dixeo_tutor.
     * @return bool
     */
    public static function is_plugin_installed(string $component): bool {
        [$type, $name] = core_component::normalize_component($component);
        if ($type === 'core' || $name === null || $name === '') {
            return false;
        }
        $directory = core_component::get_plugin_directory($type, $name);
        if ($directory === null || !is_dir($directory)) {
            return false;
        }
        return get_config($type . '_' . $name, 'version') !== false;
    }

    /**
     * Plugin names installed for a Moodle plugintype (mod, block, local, theme, tool, …).
     *
     * @param string $plugintype Moodle plugintype.
     * @return array<string, true> Map of plugin name => true.
     */
    public static function get_installed_plugin_map(string $plugintype): array {
        $cache = cache::make('local_dixeo', self::CACHE_AREA);
        $cached = $cache->get($plugintype);
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        $pluginmanager = core_plugin_manager::instance();
        $plugins = $pluginmanager->get_plugins_of_type($plugintype);
        $map = [];
        foreach (array_keys($plugins) as $pluginname) {
            $map[$pluginname] = true;
        }
        $cache->set($plugintype, $map);

        return $map;
    }

    /**
     * Ordered list of installed plugin names for a plugintype.
     *
     * @param string $plugintype Moodle plugintype.
     * @return string[]
     */
    public static function get_installed_plugin_names(string $plugintype): array {
        return array_keys(self::get_installed_plugin_map($plugintype));
    }

    /**
     * Whether everything needed to use the Dixeo module type is installed.
     *
     * Two layers of checks:
     * - The activity plugin behind the row (e.g. mod_h5pactivity for every H5P
     *   variant). Uses the row's `component`, falling back to `mod_<type>` when
     *   absent — matches the legacy 1:1 convention of classic types.
     * - Every entry in `requirements` (currently H5P library identifiers like
     *   "H5P.QuestionSet 1.20"). Empty/missing requirements means no extra check.
     *
     * @param array $typerow Row as returned by /v1/modules/types.
     */
    public static function is_module_type_installed(array $typerow): bool {
        $modname = self::resolve_module_type_plugin_name($typerow);
        if ($modname === '') {
            return false;
        }
        if (!isset(self::get_installed_plugin_map('mod')[$modname])) {
            return false;
        }

        $requirements = $typerow['requirements'] ?? [];
        if (!is_array($requirements)) {
            return false;
        }
        foreach ($requirements as $library) {
            if (!is_string($library) || !h5p_library_service::is_installed($library)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Underlying Moodle activity plugin name for a Dixeo module-type row.
     *
     * @param array $typerow Row as returned by /v1/modules/types.
     * @return string Plugin name (e.g. 'page', 'h5pactivity'), or '' when unresolvable.
     */
    public static function resolve_module_type_plugin_name(array $typerow): string {
        $component = isset($typerow['component']) && is_string($typerow['component']) ? $typerow['component'] : '';
        if ($component !== '' && strpos($component, 'mod_') === 0) {
            return substr($component, strlen('mod_'));
        }
        return isset($typerow['type']) && is_string($typerow['type']) ? $typerow['type'] : '';
    }
}
