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
 * config. A course value is a row in local_dixeo_setting, written only when it differs from
 * the parent. A course-only setting has no Dixeo parent, so that parent is the shipped default.
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
        $effective = resolver::effective($level, $this->stored_values($definition, $context, $level));
        return new resolved_value($effective['value'], $effective['source']);
    }

    /**
     * Store $value at $context, or delete the override when it matches the parent.
     *
     * @param string $component Frankenstyle component.
     * @param string $name Catalogue key.
     * @param \context $context System context for the Dixeo layer, or a course context.
     * @param string $value New text.
     */
    public function set(string $component, string $name, \context $context, string $value): void {
        $definition = $this->definition($component, $name);
        $level = $this->level_for($definition, $context);
        $this->require_edit($level, $context);
        $value = clean_param($value, PARAM_TEXT);
        $stored = $this->stored_values($definition, $context, $level);

        if (!resolver::stores_override($level, $value, $stored)) {
            $this->delete_stored($definition, $context, $level);
            return;
        }
        $this->write_stored($definition, $context, $level, $value);
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
        $stored = $this->stored_values($definition, $context, $level);
        $effective = resolver::effective($level, $stored);
        $layers = [];
        foreach ($this->chain_for($definition, $level) as $layer) {
            $own = $layer === resolver::LAYER_SITE ? null : ($stored[$layer] ?? null);
            $layers[] = [
                'level' => $layer,
                'value' => $this->value_at($layer, $stored),
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
            'coremissing' => false,
            'value' => $effective['value'],
            'source' => $effective['source'],
            'layers' => $layers,
        ];
    }

    /**
     * Site, then Dixeo when this setting declares it, then course when that is the opened level.
     *
     * @param definition $definition Catalogue entry.
     * @param string $level Dixeo or course.
     * @return string[]
     */
    private function chain_for(definition $definition, string $level): array {
        $chain = [resolver::LAYER_SITE];
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
     * @return string
     */
    private function value_at(string $layer, array $stored): string {
        if ($layer === resolver::LAYER_SITE) {
            return (string) ($stored[resolver::LAYER_SITE] ?? '');
        }
        return resolver::effective($layer, $stored)['value'];
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
     * Own values for the layers this setting declares. The site layer is the shipped default.
     *
     * @param definition $definition Catalogue entry.
     * @param \context $context Context being read. Course rows are read only for a course context.
     * @param string $level Level being read or written.
     * @return array<string, string|null>
     */
    private function stored_values(definition $definition, \context $context, string $level): array {
        $stored = [
            resolver::LAYER_SITE => $definition->get_default(),
        ];
        if (in_array(resolver::LAYER_DIXEO, $definition->get_levels(), true)) {
            $stored[resolver::LAYER_DIXEO] = $this->read_dixeo($definition);
        }
        if ($level === resolver::LAYER_COURSE) {
            $stored[resolver::LAYER_COURSE] = $this->read_course($definition, $context);
        }
        return $stored;
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
