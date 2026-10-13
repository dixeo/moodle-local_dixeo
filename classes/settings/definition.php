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
 * One Dixeo catalogue setting.
 *
 * Absence of a core target means the setting is Dixeo-only.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class definition {
    /** @var string[] Levels a setting may declare. */
    public const LEVELS = ['dixeo', 'course', 'activity', 'user'];

    /** @var string[] Control types the catalogue accepts. */
    public const TYPES = ['bool', 'select', 'multiselect', 'text', 'number', 'textarea'];

    /**
     * @param string $key Storage key, unique within the component.
     * @param string $shortname Shortname shown in the settings manager.
     * @param string $component Frankenstyle component that owns the setting.
     * @param string $type One of {@see self::TYPES}.
     * @param string $default Shipped default. Empty string is a valid textarea default.
     * @param string[] $levels Levels at which the setting exists, in chain order.
     * @param string $feature Feature-group id.
     * @param string $plugingroup Plugin-group id.
     * @param string $help Language-string key for the help text, in $component.
     * @param bool $sensitive True when the value must be hidden from users who cannot edit the Dixeo layer.
     * @param string[] $options Allowed values for a select or multiselect. Empty for every other type.
     * @param core_target|null $core Moodle admin setting this entry overrides, or null when Dixeo-only.
     * @param bool $retain True when a course value is always stored, including Off and a value equal to its parent.
     * @param string|null $retainparent Catalogue key, in this component, whose Dixeo value the retained badge compares with.
     */
    public function __construct(
        private string $key,
        private string $shortname,
        private string $component,
        private string $type,
        private string $default,
        private array $levels,
        private string $feature,
        private string $plugingroup,
        private string $help,
        private bool $sensitive,
        private array $options,
        private ?core_target $core,
        private bool $retain = false,
        private ?string $retainparent = null,
    ) {
    }

    /**
     * Validate a raw catalogue row.
     *
     * @param array $raw One element of a `*_dixeo_settings()` return value.
     * @return self
     */
    public static function from_array(array $raw): self {
        $key = self::require_string($raw, 'key');
        $type = self::require_string($raw, 'type');
        if (!in_array($type, self::TYPES, true)) {
            throw new \coding_exception("Dixeo setting \"{$key}\" has unknown type \"{$type}\".");
        }

        $levels = $raw['levels'] ?? null;
        if (!is_array($levels) || $levels === []) {
            throw new \coding_exception("Dixeo setting \"{$key}\" must declare at least one level.");
        }
        foreach ($levels as $level) {
            if (!is_string($level) || !in_array($level, self::LEVELS, true)) {
                throw new \coding_exception("Dixeo setting \"{$key}\" has unknown level \"{$level}\".");
            }
        }

        $core = null;
        if (array_key_exists('core', $raw)) {
            if (!is_array($raw['core'])) {
                throw new unresolved_core_target_exception($key, '');
            }
            $core = core_target::from_array($key, $raw['core']);
        }

        if (!isset($raw['default']) || !(is_string($raw['default']) || is_int($raw['default']))) {
            throw new \coding_exception("Dixeo setting \"{$key}\" must declare a string or integer default.");
        }

        if (!isset($raw['sensitive']) || !is_bool($raw['sensitive'])) {
            throw new \coding_exception("Dixeo setting \"{$key}\" must declare sensitive as a boolean.");
        }

        $default = (string) $raw['default'];
        if ($type === 'select') {
            $options = self::require_options($key, $raw['options'] ?? null, $default);
        } else if ($type === 'multiselect') {
            $options = self::require_choice_list($key, $raw['options'] ?? null);
            $default = self::normalise_multiselect_default($key, $options, $default);
        } else {
            $options = [];
        }
        $retain = false;
        if (array_key_exists('retain', $raw)) {
            if (!is_bool($raw['retain'])) {
                throw new \coding_exception("Dixeo setting \"{$key}\" must declare retain as a boolean.");
            }
            $retain = $raw['retain'];
        }
        $retainparent = null;
        if (array_key_exists('retainparent', $raw)) {
            if (!is_string($raw['retainparent']) || $raw['retainparent'] === '') {
                throw new \coding_exception("Dixeo setting \"{$key}\" must declare retainparent as a non-empty string.");
            }
            if (!$retain) {
                throw new \coding_exception("Dixeo setting \"{$key}\" declares retainparent without retain.");
            }
            $retainparent = $raw['retainparent'];
        }

        return new self(
            $key,
            self::require_string($raw, 'shortname'),
            self::require_string($raw, 'component'),
            $type,
            $default,
            array_values($levels),
            self::require_string($raw, 'feature'),
            self::require_string($raw, 'plugingroup'),
            self::require_string($raw, 'help'),
            $raw['sensitive'],
            $options,
            $core,
            $retain,
            $retainparent,
        );
    }

    /**
     * Select options are a non-empty list of unique ids, and the default is one of them.
     *
     * @param string $key Catalogue key, used in the error.
     * @param mixed $options Raw options list.
     * @param string $default Shipped default.
     * @return string[]
     */
    private static function require_options(string $key, mixed $options, string $default): array {
        $clean = self::require_choice_list($key, $options);
        if (!in_array($default, $clean, true)) {
            throw new \coding_exception("Dixeo setting \"{$key}\" default is not one of its options.");
        }
        return $clean;
    }

    /**
     * Select and multiselect options are a non-empty list of unique ids.
     *
     * @param string $key Catalogue key, used in the error.
     * @param mixed $options Raw options list.
     * @return string[]
     */
    private static function require_choice_list(string $key, mixed $options): array {
        if (!is_array($options) || $options === [] || !array_is_list($options)) {
            throw new \coding_exception("Dixeo setting \"{$key}\" must declare options.");
        }

        $clean = [];
        foreach ($options as $option) {
            if (!is_string($option) || !preg_match('/^[a-zA-Z0-9_]+$/', $option)) {
                throw new \coding_exception("Dixeo setting \"{$key}\" has an invalid option.");
            }
            if (in_array($option, $clean, true)) {
                throw new \coding_exception("Dixeo setting \"{$key}\" repeats option \"{$option}\".");
            }
            $clean[] = $option;
        }
        return $clean;
    }

    /**
     * A multiselect default is a comma-separated subset of its options, stored in option order.
     *
     * @param string $key Catalogue key, used in the error.
     * @param string[] $options Allowed ids, in display order.
     * @param string $default Shipped default.
     * @return string
     */
    private static function normalise_multiselect_default(string $key, array $options, string $default): string {
        if ($default === '') {
            return '';
        }
        $allowed = array_flip($options);
        $picked = [];
        foreach (explode(',', $default) as $part) {
            $part = trim($part);
            if ($part === '' || !isset($allowed[$part])) {
                throw new \coding_exception("Dixeo setting \"{$key}\" default is not one of its options.");
            }
            $picked[$part] = true;
        }
        $ordered = [];
        foreach ($options as $option) {
            if (isset($picked[$option])) {
                $ordered[] = $option;
            }
        }
        return implode(',', $ordered);
    }

    /**
     * @param array $raw Catalogue row.
     * @param string $field Field name.
     * @return string
     */
    private static function require_string(array $raw, string $field): string {
        if (!isset($raw[$field]) || !is_string($raw[$field]) || $raw[$field] === '') {
            $key = isset($raw['key']) && is_string($raw['key']) ? $raw['key'] : '(missing key)';
            throw new \coding_exception("Dixeo setting \"{$key}\" must declare a non-empty {$field}.");
        }
        return $raw[$field];
    }

    /**
     * @return string
     */
    public function get_key(): string {
        return $this->key;
    }

    /**
     * @return string
     */
    public function get_shortname(): string {
        return $this->shortname;
    }

    /**
     * @return string
     */
    public function get_component(): string {
        return $this->component;
    }

    /**
     * @return string
     */
    public function get_type(): string {
        return $this->type;
    }

    /**
     * @return string
     */
    public function get_default(): string {
        return $this->default;
    }

    /**
     * @return string[]
     */
    public function get_levels(): array {
        return $this->levels;
    }

    /**
     * @return string
     */
    public function get_feature(): string {
        return $this->feature;
    }

    /**
     * @return string
     */
    public function get_plugingroup(): string {
        return $this->plugingroup;
    }

    /**
     * @return string
     */
    public function get_help(): string {
        return $this->help;
    }

    /**
     * @return bool
     */
    public function is_sensitive(): bool {
        return $this->sensitive;
    }

    /**
     * Allowed values for a select or multiselect. Empty for every other type.
     *
     * @return string[]
     */
    public function get_options(): array {
        return $this->options;
    }

    /**
     * True when this entry overrides a Moodle admin setting.
     *
     * @return bool
     */
    public function has_core(): bool {
        return $this->core !== null;
    }

    /**
     * @return core_target|null
     */
    public function get_core(): ?core_target {
        return $this->core;
    }

    /**
     * True when the stored course value is kept even if it is Off or equal to its parent.
     *
     * @return bool
     */
    public function retains_value(): bool {
        return $this->retain;
    }

    /**
     * Catalogue key whose Dixeo value a retained course setting is compared with.
     *
     * Null uses this setting's own shipped default.
     *
     * @return string|null
     */
    public function get_retain_parent(): ?string {
        return $this->retainparent;
    }
}
