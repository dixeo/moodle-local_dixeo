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
 * Moodle admin setting that a Dixeo catalogue entry overrides.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class core_target {
    /**
     * @param string $plugin Frankenstyle plugin that owns the admin setting. Empty for a legacy $CFG name.
     * @param string $name Setting name within the plugin, or the full legacy config name.
     * @param bool $legacycfg True when the admin setting name has no plugin slash (glossary_entbypage).
     */
    public function __construct(
        public readonly string $plugin,
        public readonly string $name,
        public readonly bool $legacycfg,
    ) {
    }

    /**
     * Build a target from the catalogue `core` array.
     *
     * @param string $settingkey Catalogue key, used in the error when the target is incomplete.
     * @param array $core Raw `core` value from the catalogue.
     * @return self
     */
    public static function from_array(string $settingkey, array $core): self {
        $legacycfg = !empty($core['legacycfg']);
        $plugin = isset($core['plugin']) ? (string) $core['plugin'] : '';
        $name = isset($core['name']) ? (string) $core['name'] : '';

        if ($name === '' || (!$legacycfg && $plugin === '')) {
            throw new unresolved_core_target_exception($settingkey, $plugin === '' ? $name : $plugin . '/' . $name);
        }

        return new self($plugin, $name, $legacycfg);
    }

    /**
     * Admin setting name passed to the admin tree (`plugin/name`, or the legacy name alone).
     *
     * @return string
     */
    public function admin_setting_name(): string {
        if ($this->legacycfg) {
            return $this->name;
        }
        return $this->plugin . '/' . $this->name;
    }
}
