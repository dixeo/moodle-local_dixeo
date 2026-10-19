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
     * Store one catalogue setting.
     *
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
     * @param array $labels Select option id to display label. Empty when the modal loads labels from language strings.
     * @param string[] $readonlylevels Levels the settings manager shows but does not save.
     * @param bool $record True when a course value is a stored snapshot. An unset course stays empty and does not inherit Dixeo.
     * @param bool $parentofflocks True when Dixeo Off forces the course value Off and the course control cannot turn it On.
     * @param string|null $requires Catalogue key, in this component, that this setting depends on. The row stays editable.
     * @param string $sub Subcategory id within the feature and plugin group. Empty when the group has none.
     * @param int|null $min Inclusive lower bound for a number, or null when the number is unbounded.
     * @param int|null $max Inclusive upper bound for a number, or null when the number is unbounded.
     */
    public function __construct(
        /** @var string Storage key, unique within the component. */
        private string $key,
        /** @var string Shortname shown in the settings manager. */
        private string $shortname,
        /** @var string Frankenstyle component that owns the setting. */
        private string $component,
        /** @var string One of {@see self::TYPES}. */
        private string $type,
        /** @var string Shipped default. */
        private string $default,
        /** @var string[] Levels at which the setting exists, in chain order. */
        private array $levels,
        /** @var string Feature-group id. */
        private string $feature,
        /** @var string Plugin-group id. */
        private string $plugingroup,
        /** @var string Language-string key for the help text. */
        private string $help,
        /** @var bool True when the value must be hidden from users who cannot edit the Dixeo layer. */
        private bool $sensitive,
        /** @var string[] Allowed values for a select or multiselect. */
        private array $options,
        /** @var core_target|null Moodle admin setting this entry overrides, or null when Dixeo-only. */
        private ?core_target $core,
        /** @var bool True when a course value is always stored, including Off and a value equal to its parent. */
        private bool $retain = false,
        /** @var string|null Catalogue key whose Dixeo value the retained badge compares with. */
        private ?string $retainparent = null,
        /** @var array Select option id to display label. */
        private array $labels = [],
        /** @var string[] Levels the settings manager shows but does not save. */
        private array $readonlylevels = [],
        /** @var bool True when a course value is a stored snapshot that does not inherit Dixeo. */
        private bool $record = false,
        /** @var bool True when Dixeo Off forces the course value Off and the course control cannot turn it On. */
        private bool $parentofflocks = false,
        /** @var string|null Catalogue key, in this component, that this setting depends on. */
        private ?string $requires = null,
        /** @var string Subcategory id within the feature and plugin group. Empty when the group has none. */
        private string $sub = '',
        /** @var int|null Inclusive lower bound for a number. */
        private ?int $min = null,
        /** @var int|null Inclusive upper bound for a number. */
        private ?int $max = null,
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

        $labels = self::require_labels($key, $raw['labels'] ?? null, $options);
        $readonlylevels = self::require_readonly_levels($key, $raw['readonly'] ?? null, $levels);
        $record = false;
        if (array_key_exists('record', $raw)) {
            if (!is_bool($raw['record'])) {
                throw new \coding_exception("Dixeo setting \"{$key}\" must declare record as a boolean.");
            }
            $record = $raw['record'];
        }
        if ($record && !in_array('course', $levels, true)) {
            throw new \coding_exception("Dixeo setting \"{$key}\" record requires the course level.");
        }
        if ($record && $retain) {
            throw new \coding_exception("Dixeo setting \"{$key}\" cannot both retain and record a course value.");
        }
        $parentofflocks = false;
        if (array_key_exists('parentofflocks', $raw)) {
            if (!is_bool($raw['parentofflocks'])) {
                throw new \coding_exception("Dixeo setting \"{$key}\" must declare parentofflocks as a boolean.");
            }
            $parentofflocks = $raw['parentofflocks'];
        }
        if ($parentofflocks && ($type !== 'bool' || !in_array('dixeo', $levels, true) || !in_array('course', $levels, true))) {
            throw new \coding_exception(
                "Dixeo setting \"{$key}\" can lock the course only for a bool at the Dixeo and course levels."
            );
        }
        $requires = null;
        if (array_key_exists('requires', $raw)) {
            if (!is_string($raw['requires']) || $raw['requires'] === '' || $raw['requires'] === $key) {
                throw new \coding_exception("Dixeo setting \"{$key}\" must declare requires as another catalogue key.");
            }
            $requires = $raw['requires'];
        }
        $sub = '';
        if (array_key_exists('sub', $raw)) {
            if (!is_string($raw['sub']) || !preg_match('/^[a-z][a-z0-9_]*$/', $raw['sub'])) {
                throw new \coding_exception("Dixeo setting \"{$key}\" must declare sub as a subcategory id.");
            }
            $sub = $raw['sub'];
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
            $labels,
            $readonlylevels,
            $record,
            $parentofflocks,
            $requires,
            $sub,
            ...self::require_number_range($key, $type, $default, $raw),
        );
    }

    /**
     * Inclusive bounds for a number. Other types leave them unset.
     *
     * @param string $key Catalogue key, used in the error.
     * @param string $type Control type.
     * @param string $default Shipped default.
     * @param array $raw Catalogue row.
     * @return array{0: int|null, 1: int|null}
     */
    private static function require_number_range(string $key, string $type, string $default, array $raw): array {
        $hasmin = array_key_exists('min', $raw);
        $hasmax = array_key_exists('max', $raw);
        if (!$hasmin && !$hasmax) {
            return [null, null];
        }
        if (
            $type !== 'number'
            || !$hasmin
            || !$hasmax
            || !is_int($raw['min'])
            || !is_int($raw['max'])
            || $raw['min'] > $raw['max']
        ) {
            throw new \coding_exception(
                "Dixeo setting \"{$key}\" must declare min and max as integers, with min no greater than max, on a number."
            );
        }
        if (!preg_match('/^(0|[1-9]\d*)$/', $default)) {
            throw new \coding_exception("Dixeo setting \"{$key}\" default is outside {$raw['min']}–{$raw['max']}.");
        }
        $number = (int) $default;
        if ($number < $raw['min'] || $number > $raw['max']) {
            throw new \coding_exception("Dixeo setting \"{$key}\" default is outside {$raw['min']}–{$raw['max']}.");
        }
        return [$raw['min'], $raw['max']];
    }

    /**
     * Optional display labels for select options. Missing labels are loaded from language strings.
     *
     * @param string $key Catalogue key, used in the error.
     * @param mixed $labels Raw id to label map, or null when omitted.
     * @param string[] $options Allowed option ids.
     * @return array<string, string>
     */
    private static function require_labels(string $key, mixed $labels, array $options): array {
        if ($labels === null) {
            return [];
        }
        if (!is_array($labels)) {
            throw new \coding_exception("Dixeo setting \"{$key}\" must declare labels as an array.");
        }
        $clean = [];
        foreach ($labels as $value => $label) {
            $value = (string) $value;
            if (!in_array($value, $options, true) || !is_string($label)) {
                throw new \coding_exception("Dixeo setting \"{$key}\" has an invalid option label.");
            }
            $clean[$value] = $label;
        }
        return $clean;
    }

    /**
     * Levels the settings manager shows without saving.
     *
     * @param string $key Catalogue key, used in the error.
     * @param mixed $readonly Raw level list, or null when omitted.
     * @param string[] $levels Levels this setting declares.
     * @return string[]
     */
    private static function require_readonly_levels(string $key, mixed $readonly, array $levels): array {
        if ($readonly === null) {
            return [];
        }
        if (!is_array($readonly) || !array_is_list($readonly)) {
            throw new \coding_exception("Dixeo setting \"{$key}\" must declare readonly as a list of levels.");
        }
        $clean = [];
        foreach ($readonly as $level) {
            if (!is_string($level) || !in_array($level, $levels, true) || in_array($level, $clean, true)) {
                throw new \coding_exception("Dixeo setting \"{$key}\" has an invalid readonly level.");
            }
            $clean[] = $level;
        }
        return $clean;
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
     * An id may be empty, so a select can offer None. Hyphens are allowed so template ids can be stored.
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
            if (!is_string($option) || !preg_match('/^[a-zA-Z0-9_-]*$/', $option)) {
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
     * Require a non-empty string field from a catalogue row.
     *
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
     * Storage key, unique within the component.
     *
     * @return string
     */
    public function get_key(): string {
        return $this->key;
    }

    /**
     * Shortname shown in the settings manager.
     *
     * @return string
     */
    public function get_shortname(): string {
        return $this->shortname;
    }

    /**
     * Frankenstyle component that owns the setting.
     *
     * @return string
     */
    public function get_component(): string {
        return $this->component;
    }

    /**
     * Control type.
     *
     * @return string
     */
    public function get_type(): string {
        return $this->type;
    }

    /**
     * Shipped default.
     *
     * @return string
     */
    public function get_default(): string {
        return $this->default;
    }

    /**
     * Levels at which the setting exists.
     *
     * @return string[]
     */
    public function get_levels(): array {
        return $this->levels;
    }

    /**
     * Feature-group id.
     *
     * @return string
     */
    public function get_feature(): string {
        return $this->feature;
    }

    /**
     * Subcategory id within the feature and plugin group.
     *
     * @return string Empty when the group has no subcategories.
     */
    public function get_sub(): string {
        return $this->sub;
    }

    /**
     * Plugin-group id.
     *
     * @return string
     */
    public function get_plugingroup(): string {
        return $this->plugingroup;
    }

    /**
     * Language-string key for the help text.
     *
     * @return string
     */
    public function get_help(): string {
        return $this->help;
    }

    /**
     * Whether the value is hidden from users who cannot edit the Dixeo layer.
     *
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
     * Moodle admin setting this entry overrides, or null when the setting is Dixeo-only.
     *
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

    /**
     * Display labels supplied with the catalogue. Options without a label use a language string.
     *
     * @return array<string, string>
     */
    public function get_labels(): array {
        return $this->labels;
    }

    /**
     * True when the settings manager must not save this level.
     *
     * A trusted caller can still store the value with the capability check turned off.
     *
     * @param string $level Dixeo, course, activity, or user.
     * @return bool
     */
    public function is_readonly_at(string $level): bool {
        return in_array($level, $this->readonlylevels, true);
    }

    /**
     * True when an unset course value stays empty instead of inheriting Dixeo.
     *
     * @return bool
     */
    public function records_course_value(): bool {
        return $this->record;
    }

    /**
     * True when Dixeo Off forces the course Off and the course control cannot store On.
     *
     * @return bool
     */
    public function locks_course_when_parent_off(): bool {
        return $this->parentofflocks;
    }

    /**
     * Catalogue key that must be On before this row can be edited, or null when it stands alone.
     *
     * @return string|null
     */
    public function get_requires(): ?string {
        return $this->requires;
    }

    /**
     * Inclusive lower bound for a number, or null when the number is unbounded.
     *
     * @return int|null
     */
    public function get_min(): ?int {
        return $this->min;
    }

    /**
     * Inclusive upper bound for a number, or null when the number is unbounded.
     *
     * @return int|null
     */
    public function get_max(): ?int {
        return $this->max;
    }
}
