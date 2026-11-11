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

namespace local_dixeo\task;

use local_dixeo\service\plugin_installation_service;
use local_dixeo\settings\catalogue;
use local_dixeo\settings\config;
use local_dixeo\settings\resolver;

/**
 * Daily removal of stored setting values the catalogue no longer reads.
 *
 * Course and activity rows are deleted when their context is gone, when their component and
 * key are not in the catalogue (including components that are not installed), or when the
 * catalogue does not declare the level of their context. Dixeo-level values in config_plugins
 * are unset only for keys that were declared at the Dixeo level on an earlier run and no longer
 * are, so connection settings, runtime state and other plugin config are never touched.
 * The number of queries depends on the number of distinct stored settings, not on the rows.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_settings extends \core\task\scheduled_task {
    /** @var string local_dixeo config holding the Dixeo-level catalogue keys seen on the last run. */
    public const CONFIG_CATALOGUE_KEYS = 'catalogue_keys';

    /**
     * Task name shown in the scheduled task list.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_cleanup_settings', 'local_dixeo');
    }

    /**
     * Delete orphaned course and activity rows, then unset retired Dixeo-level values.
     */
    public function execute(): void {
        $definitions = catalogue::load();
        $this->delete_rows_of_missing_contexts();
        $this->delete_undeclared_rows($definitions);
        $this->unset_retired_dixeo_values($definitions);
    }

    /**
     * Delete every row whose context no longer exists.
     */
    private function delete_rows_of_missing_contexts(): void {
        global $DB;

        $DB->execute(
            'DELETE FROM {' . config::TABLE . '}
              WHERE NOT EXISTS (SELECT 1 FROM {context} c WHERE c.id = {' . config::TABLE . '}.contextid)'
        );
    }

    /**
     * Delete rows whose setting or context level the catalogue does not declare.
     *
     * @param \local_dixeo\settings\definition[] $definitions Catalogue keyed by setting key.
     */
    private function delete_undeclared_rows(array $definitions): void {
        global $DB;

        $groups = $DB->get_recordset_sql(
            'SELECT s.component, s.name, c.contextlevel, COUNT(1) AS numrows
               FROM {' . config::TABLE . '} s
               JOIN {context} c ON c.id = s.contextid
           GROUP BY s.component, s.name, c.contextlevel'
        );
        $stale = [];
        foreach ($groups as $group) {
            $definition = $definitions[$group->name] ?? null;
            $level = $this->level_for((int) $group->contextlevel);
            if (
                $definition !== null
                && $definition->get_component() === $group->component
                && $level !== null
                && in_array($level, $definition->get_levels(), true)
            ) {
                continue;
            }
            $stale[] = $group;
        }
        $groups->close();

        foreach ($stale as $group) {
            $DB->execute(
                'DELETE FROM {' . config::TABLE . '}
                  WHERE component = :component
                    AND name = :name
                    AND contextid IN (SELECT c.id FROM {context} c WHERE c.contextlevel = :contextlevel)',
                [
                    'component' => $group->component,
                    'name' => $group->name,
                    'contextlevel' => (int) $group->contextlevel,
                ]
            );
        }
    }

    /**
     * Catalogue level stored at a context level, or null when no level is stored there.
     *
     * @param int $contextlevel Moodle context level.
     * @return string|null
     */
    private function level_for(int $contextlevel): ?string {
        if ($contextlevel === CONTEXT_COURSE) {
            return resolver::LAYER_COURSE;
        }
        if ($contextlevel === CONTEXT_MODULE) {
            return resolver::LAYER_ACTIVITY;
        }
        return null;
    }

    /**
     * Unset Dixeo-level values of keys an installed component declared before and no longer does.
     *
     * The first run only records the keys.
     *
     * @param \local_dixeo\settings\definition[] $definitions Catalogue keyed by setting key.
     */
    private function unset_retired_dixeo_values(array $definitions): void {
        $current = [];
        foreach ($definitions as $definition) {
            if (in_array(resolver::LAYER_DIXEO, $definition->get_levels(), true)) {
                $current[$definition->get_component()][] = $definition->get_key();
            }
        }

        $stored = get_config('local_dixeo', self::CONFIG_CATALOGUE_KEYS);
        $previous = is_string($stored) ? json_decode($stored, true) : null;
        if (is_array($previous)) {
            foreach ($previous as $component => $keys) {
                if (!is_string($component) || !is_array($keys)) {
                    continue;
                }
                if (!plugin_installation_service::is_plugin_installed($component)) {
                    continue;
                }
                foreach (array_diff($keys, $current[$component] ?? []) as $key) {
                    if (is_string($key) && $key !== '') {
                        unset_config($key, $component);
                    }
                }
            }
        }

        ksort($current);
        set_config(self::CONFIG_CATALOGUE_KEYS, json_encode($current), 'local_dixeo');
    }
}
