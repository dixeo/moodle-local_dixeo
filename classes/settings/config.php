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
 * Reads and writes catalogue settings.
 *
 * A setting is stored only at levels it declares. The Dixeo level, when declared, is plugin
 * config, and the catalogue default is that layer's value when nothing is stored. A site
 * layer exists only for a core Moodle config the setting overrides. A course value is a row
 * in local_dixeo_setting. A course value that matches its parent is removed, except a retained
 * setting, which stays stored including Off. A course-only setting has no stored Dixeo parent;
 * its unset value is still the Dixeo catalogue default.
 * {@see self::enabled_for()} is the master switch: Dixeo off is off in every context.
 * Callers do not query the table.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class config {
    /** @var string Course and activity override table. */
    public const TABLE = 'local_dixeo_setting';

    /** @var string Component that owns the master switch. */
    private const COMPONENT = 'local_dixeo';

    /** @var string Catalogue key for the master switch. */
    private const ENABLED = 'enabled';

    /**
     * Effective value at $context, falling back through the parent chain.
     *
     * @param string $component Frankenstyle component.
     * @param string $name Catalogue key.
     * @param \context $context System context for the Dixeo layer, or a course context.
     * @return resolved_value
     */
    public function get(string $component, string $name, \context $context): resolved_value {
        $definition = $this->definition($component, $name);
        $level = $this->level_for($definition, $context);
        if ($definition->retains_value()) {
            $own = $this->own_value($definition, $context, $level);
            $value = $own ?? $definition->get_default();
            $parent = $this->retained_parent_value($definition);
            $source = $value === $parent ? resolver::LAYER_DIXEO : resolver::LAYER_COURSE;
            return new resolved_value($value, $source);
        }
        $stored = $this->stored_values($definition, $context, $level);
        $effective = resolver::effective($level, $stored, $definition->get_default());
        return new resolved_value($effective['value'], $effective['source']);
    }

    /**
     * Store $value at $context, or delete the override when it matches the parent.
     *
     * A retained setting is always written, including Off and a value equal to its parent.
     *
     * @param string $component Frankenstyle component.
     * @param string $name Catalogue key.
     * @param \context $context System context for the Dixeo layer, or a course context.
     * @param string $value New text.
     * @param bool $checkcapability False when a trusted caller has already authorised the write.
     */
    public function set(
        string $component,
        string $name,
        \context $context,
        string $value,
        bool $checkcapability = true
    ): void {
        $definition = $this->definition($component, $name);
        $level = $this->level_for($definition, $context);
        if ($checkcapability) {
            $this->require_edit($level, $context);
        }
        $value = clean_param($value, PARAM_TEXT);
        if ($definition->get_type() === 'select' && !in_array($value, $definition->get_options(), true)) {
            throw new \coding_exception("Dixeo setting \"{$definition->get_key()}\" does not allow \"{$value}\".");
        }
        if ($definition->retains_value() && $definition->get_type() === 'bool' && $value !== '0' && $value !== '1') {
            throw new \coding_exception("Dixeo setting \"{$definition->get_key()}\" does not allow \"{$value}\".");
        }
        $stored = $this->stored_values($definition, $context, $level);
        $current = $this->own_value($definition, $context, $level);

        if ($definition->retains_value()) {
            if ($current === $value) {
                return;
            }
            $this->write_stored($definition, $context, $level, $value);
            $this->notify_updated($definition, $context);
            return;
        }

        if (!resolver::stores_override($level, $value, $stored, $definition->get_default())) {
            if ($current === null) {
                return;
            }
            $this->delete_stored($definition, $context, $level);
            $this->notify_updated($definition, $context);
            return;
        }
        if ($current === $value) {
            return;
        }
        $this->write_stored($definition, $context, $level, $value);
        $this->notify_updated($definition, $context);
    }

    /**
     * Catalogue rows for the level opened at $context.
     *
     * A system context is the Dixeo layer and requires moodle/site:config. A course
     * context requires moodle/course:update. Each row is the effective value plus
     * every layer in that setting's chain. A layer's stored value is null when that
     * layer has no own row. Customised is true only for an own stored row. A
     * sensitive setting is omitted unless the user has moodle/site:config.
     * {@see self::get()} is unchanged and does not require a capability.
     *
     * @param \context $context System context or a course context.
     * @return array[]
     */
    public function get_layers(\context $context): array {
        $level = $this->open_level($context);
        $this->require_edit($level, $context);
        $seesensitive = has_capability('moodle/site:config', \context_system::instance());

        $rows = [];
        foreach (catalogue::load() as $definition) {
            if (!in_array($level, $definition->get_levels(), true)) {
                continue;
            }
            if ($definition->is_sensitive() && !$seesensitive) {
                continue;
            }
            $rows[] = $this->layer_row($definition, $context, $level);
        }
        return $rows;
    }

    /**
     * Whether Dixeo features run in $context.
     *
     * The Dixeo layer is absolute. Off is off everywhere, including a course whose own
     * value is On. When the Dixeo layer is On, a course and contexts inside it use the
     * course value. Any other context follows the Dixeo layer. This read does not
     * require an edit capability.
     *
     * @param \context $context System, course, or a context inside a course.
     * @return bool
     */
    public function enabled_for(\context $context): bool {
        if ($this->get(self::COMPONENT, self::ENABLED, \context_system::instance())->get_value() !== '1') {
            return false;
        }
        $coursecontext = $context->get_course_context(false);
        if (!$coursecontext instanceof \context) {
            return true;
        }
        return $this->get(self::COMPONENT, self::ENABLED, $coursecontext)->get_value() === '1';
    }

    /**
     * Stop when Dixeo features must not run in $context.
     *
     * @param \context $context System, course, or a context inside a course.
     * @throws \moodle_exception When {@see self::enabled_for()} is false.
     */
    public function require_enabled(\context $context): void {
        if (!$this->enabled_for($context)) {
            throw new \moodle_exception('error:dixeo_disabled', 'local_dixeo');
        }
    }

    /**
     * Editing-mode text for a block that is hidden because Dixeo is off.
     *
     * The Dixeo layer asks for an administrator. A course that is off while the
     * Dixeo layer is on names that course.
     *
     * @param \context $context Page context used by {@see self::enabled_for()}.
     * @return string
     */
    public function disabled_block_text(\context $context): string {
        $siteoff = $this->get(self::COMPONENT, self::ENABLED, \context_system::instance())->get_value() !== '1';
        $incourse = $context->get_course_context(false) instanceof \context;
        $identifier = (!$siteoff && $incourse) ? 'disabled_block_course' : 'disabled_block_site';
        return get_string($identifier, 'local_dixeo');
    }

    /**
     * @param string $component Frankenstyle component.
     * @param string $name Catalogue key.
     * @return definition
     */
    private function definition(string $component, string $name): definition {
        foreach (catalogue::load() as $definition) {
            if ($definition->get_component() === $component && $definition->get_key() === $name) {
                return $definition;
            }
        }
        throw new \coding_exception("Dixeo setting \"{$component}/{$name}\" is not in the catalogue.");
    }

    /**
     * Dixeo or course, for a context the layer read accepts.
     *
     * @param \context $context Requested context.
     * @return string
     */
    private function open_level(\context $context): string {
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            return resolver::LAYER_DIXEO;
        }
        if ($context->contextlevel == CONTEXT_COURSE) {
            return resolver::LAYER_COURSE;
        }
        throw new \coding_exception('Dixeo settings layers are only available for the system or a course.');
    }

    /**
     * One catalogue row and the layers that lead to its effective value.
     *
     * A loaded definition has already resolved any core target, so the row is not coremissing.
     *
     * @param definition $definition Catalogue entry declared at $level.
     * @param \context $context System or course context.
     * @param string $level Dixeo or course.
     * @return array
     */
    private function layer_row(definition $definition, \context $context, string $level): array {
        if ($definition->retains_value()) {
            return $this->retained_layer_row($definition, $context);
        }
        $stored = $this->stored_values($definition, $context, $level);
        $fallback = $definition->get_default();
        $effective = resolver::effective($level, $stored, $fallback);
        $layers = [];
        foreach ($this->chain_for($definition, $level) as $layer) {
            $own = $layer === resolver::LAYER_SITE ? null : ($stored[$layer] ?? null);
            $layers[] = [
                'level' => $layer,
                'value' => $this->value_at($layer, $stored, $fallback),
                'stored' => $own,
                'customised' => $own !== null,
            ];
        }

        return [
            'component' => $definition->get_component(),
            'name' => $definition->get_key(),
            'shortname' => $definition->get_shortname(),
            'type' => $definition->get_type(),
            'feature' => $definition->get_feature(),
            'plugingroup' => $definition->get_plugingroup(),
            'help' => $definition->get_help(),
            'options' => $definition->get_options(),
            'coremissing' => false,
            'value' => $effective['value'],
            'source' => $effective['source'],
            'layers' => $layers,
        ];
    }

    /**
     * Course value that is always stored, with a read-only Dixeo cell for the comparison parent.
     *
     * The course value does not follow later changes to that parent. A missing row reads as the
     * catalogue default. The badge source is Dixeo when the course value still matches the parent.
     *
     * @param definition $definition Catalogue entry with {@see definition::retains_value()}.
     * @param \context $context Course context.
     * @return array
     */
    private function retained_layer_row(definition $definition, \context $context): array {
        $own = $this->read_course($definition, $context);
        $value = $own ?? $definition->get_default();
        $parent = $this->retained_parent_value($definition);
        $matches = $value === $parent;

        return [
            'component' => $definition->get_component(),
            'name' => $definition->get_key(),
            'shortname' => $definition->get_shortname(),
            'type' => $definition->get_type(),
            'feature' => $definition->get_feature(),
            'plugingroup' => $definition->get_plugingroup(),
            'help' => $definition->get_help(),
            'options' => $definition->get_options(),
            'coremissing' => false,
            'value' => $value,
            'source' => $matches ? resolver::LAYER_DIXEO : resolver::LAYER_COURSE,
            'layers' => [
                [
                    'level' => resolver::LAYER_DIXEO,
                    'value' => $parent,
                    'stored' => null,
                    'customised' => false,
                ],
                [
                    'level' => resolver::LAYER_COURSE,
                    'value' => $value,
                    'stored' => $own,
                    'customised' => $own !== null && !$matches,
                ],
            ],
        ];
    }

    /**
     * Value a retained course setting is compared with for the Dixeo badge.
     *
     * File sync on this course is compared with the current new-course default.
     *
     * @param definition $definition Retained catalogue entry.
     * @return string
     */
    private function retained_parent_value(definition $definition): string {
        if ($definition->get_component() === self::COMPONENT && $definition->get_key() === 'filesync_enabled') {
            return $this->get(self::COMPONENT, 'filesync_enabled_default', \context_system::instance())->get_value();
        }
        return $definition->get_default();
    }

    /**
     * Core site value when this setting overrides one, then Dixeo when declared, then course.
     *
     * @param definition $definition Catalogue entry.
     * @param string $level Dixeo or course.
     * @return string[]
     */
    private function chain_for(definition $definition, string $level): array {
        $chain = [];
        if ($definition->has_core()) {
            $chain[] = resolver::LAYER_SITE;
        }
        if (in_array(resolver::LAYER_DIXEO, $definition->get_levels(), true)) {
            $chain[] = resolver::LAYER_DIXEO;
        }
        if ($level === resolver::LAYER_COURSE) {
            $chain[] = resolver::LAYER_COURSE;
        }
        return $chain;
    }

    /**
     * Effective text at one layer, walking parents when that layer has no own row.
     *
     * @param string $layer Site, Dixeo, or course.
     * @param array<string, string|null> $stored Own values. Null means no row.
     * @param string $fallback Catalogue default for a Dixeo-only setting.
     * @return string
     */
    private function value_at(string $layer, array $stored, string $fallback): string {
        if ($layer === resolver::LAYER_SITE) {
            return (string) ($stored[resolver::LAYER_SITE] ?? '');
        }
        return resolver::effective($layer, $stored, $fallback)['value'];
    }

    /**
     * @param definition $definition Catalogue entry.
     * @param \context $context Requested context.
     * @return string
     */
    private function level_for(definition $definition, \context $context): string {
        $level = null;
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            $level = resolver::LAYER_DIXEO;
        } else if ($context->contextlevel == CONTEXT_COURSE) {
            $level = resolver::LAYER_COURSE;
        }
        if ($level === null || !in_array($level, $definition->get_levels(), true)) {
            throw new \coding_exception(
                "Dixeo setting \"{$definition->get_component()}/{$definition->get_key()}\" cannot be used at this context."
            );
        }
        return $level;
    }

    /**
     * @param string $level Dixeo or course.
     * @param \context $context Context being edited.
     */
    private function require_edit(string $level, \context $context): void {
        if ($level === resolver::LAYER_DIXEO) {
            require_capability('moodle/site:config', $context);
            return;
        }
        require_capability('moodle/course:update', $context);
    }

    /**
     * Own values for the layers this setting declares.
     *
     * The site key is present only for a core Moodle config, and holds that live value.
     * A Dixeo plugin setting has no site key; its catalogue default is applied by the resolver.
     *
     * @param definition $definition Catalogue entry.
     * @param \context $context Context being read. Course rows are read only for a course context.
     * @param string $level Level being read or written.
     * @return array<string, string|null>
     */
    private function stored_values(definition $definition, \context $context, string $level): array {
        $stored = [];
        if ($definition->has_core()) {
            $stored[resolver::LAYER_SITE] = $this->read_core($definition);
        }
        if (in_array(resolver::LAYER_DIXEO, $definition->get_levels(), true)) {
            $stored[resolver::LAYER_DIXEO] = $this->read_dixeo($definition);
        }
        if ($level === resolver::LAYER_COURSE) {
            $stored[resolver::LAYER_COURSE] = $this->read_course($definition, $context);
        }
        return $stored;
    }

    /**
     * Own stored value at the level being written, or null when that level has no row.
     *
     * @param definition $definition Catalogue entry.
     * @param \context $context Context being written.
     * @param string $level Dixeo or course.
     * @return string|null
     */
    private function own_value(definition $definition, \context $context, string $level): ?string {
        if ($level === resolver::LAYER_DIXEO) {
            return $this->read_dixeo($definition);
        }
        return $this->read_course($definition, $context);
    }

    /**
     * Record that a stored value changed. The log names the key and context, not the text.
     *
     * @param definition $definition Catalogue entry.
     * @param \context $context Context that changed.
     */
    private function notify_updated(definition $definition, \context $context): void {
        \local_dixeo\event\setting_updated::create([
            'context' => $context,
            'other' => [
                'component' => $definition->get_component(),
                'name' => $definition->get_key(),
            ],
        ])->trigger();
    }

    /**
     * Live value of the core Moodle setting this entry overrides.
     *
     * An unset core config uses that admin setting's own default, not the Dixeo catalogue default.
     *
     * @param definition $definition Catalogue entry with a core target.
     * @return string
     */
    private function read_core(definition $definition): string {
        $core = $definition->get_core();
        if ($core === null) {
            return '';
        }
        if ($core->legacycfg) {
            global $CFG;
            $name = $core->name;
            if (isset($CFG->{$name})) {
                return (string) $CFG->{$name};
            }
        } else {
            $value = get_config($core->plugin, $core->name);
            if ($value !== false) {
                return (string) $value;
            }
        }
        $setting = core_lookup::find($core->admin_setting_name());
        if ($setting === null) {
            return '';
        }
        $default = $setting->get_defaultsetting();
        if ($default === null || $default === false || is_array($default)) {
            return '';
        }
        return (string) $default;
    }

    /**
     * @param definition $definition Catalogue entry.
     * @return string|null Null when plugin config has no row.
     */
    private function read_dixeo(definition $definition): ?string {
        $value = get_config($definition->get_component(), $definition->get_key());
        if ($value === false) {
            return null;
        }
        return (string) $value;
    }

    /**
     * @param definition $definition Catalogue entry.
     * @param \context $context Course context.
     * @return string|null Null when this course has no override.
     */
    private function read_course(definition $definition, \context $context): ?string {
        global $DB;
        $value = $DB->get_field(self::TABLE, 'value', [
            'contextid' => $context->id,
            'component' => $definition->get_component(),
            'name' => $definition->get_key(),
        ]);
        if ($value === false) {
            return null;
        }
        return (string) $value;
    }

    /**
     * @param definition $definition Catalogue entry.
     * @param \context $context Context whose stored value is removed.
     * @param string $level Dixeo or course.
     */
    private function delete_stored(definition $definition, \context $context, string $level): void {
        global $DB;
        if ($level === resolver::LAYER_DIXEO) {
            unset_config($definition->get_key(), $definition->get_component());
            return;
        }
        $DB->delete_records(self::TABLE, [
            'contextid' => $context->id,
            'component' => $definition->get_component(),
            'name' => $definition->get_key(),
        ]);
    }

    /**
     * @param definition $definition Catalogue entry.
     * @param \context $context Context being written.
     * @param string $level Dixeo or course.
     * @param string $value Cleaned value.
     */
    private function write_stored(definition $definition, \context $context, string $level, string $value): void {
        global $DB;
        if ($level === resolver::LAYER_DIXEO) {
            set_config($definition->get_key(), $value, $definition->get_component());
            return;
        }

        $conditions = [
            'contextid' => $context->id,
            'component' => $definition->get_component(),
            'name' => $definition->get_key(),
        ];
        $existingid = $DB->get_field(self::TABLE, 'id', $conditions);
        if ($existingid) {
            $DB->update_record(self::TABLE, (object) [
                'id' => $existingid,
                'value' => $value,
            ]);
            return;
        }
        $DB->insert_record(self::TABLE, (object) ($conditions + ['value' => $value]));
    }
}
