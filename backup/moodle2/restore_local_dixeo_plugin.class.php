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
 * Restore of the Dixeo setting values stored on a course or an activity.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_dixeo\external\service_factory;
use local_dixeo\settings\catalogue;
use local_dixeo\settings\resolver;

/**
 * Stores the backed-up setting values on the restored course or module.
 *
 * A value whose setting is not in this site's catalogue at that level, or that the catalogue
 * refuses, is skipped. Each write is marked as restored in its setting_updated event. Restore
 * does not fire course_created, so a restored course also receives the new-course default of
 * every retained setting the backup did not carry.
 */
class restore_local_dixeo_plugin extends restore_local_plugin {
    /**
     * Paths of the course setting rows.
     *
     * @return restore_path_element[]
     */
    protected function define_course_plugin_structure() {
        return [new restore_path_element('local_dixeo_coursesetting', $this->get_pathfor('/settings/setting'))];
    }

    /**
     * Paths of the module setting rows.
     *
     * @return restore_path_element[]
     */
    protected function define_module_plugin_structure() {
        return [new restore_path_element('local_dixeo_modulesetting', $this->get_pathfor('/settings/setting'))];
    }

    /**
     * Store one course setting row.
     *
     * @param array|stdClass $data Backed-up row.
     */
    public function process_local_dixeo_coursesetting($data): void {
        $this->process_setting((object) $data, resolver::LAYER_COURSE);
    }

    /**
     * Store one module setting row.
     *
     * @param array|stdClass $data Backed-up row.
     */
    public function process_local_dixeo_modulesetting($data): void {
        $this->process_setting((object) $data, resolver::LAYER_ACTIVITY);
    }

    /**
     * Give the restored course the new-course default of each retained setting it has no value for.
     */
    public function after_restore_course(): void {
        global $DB;

        $context = context_course::instance((int) $this->task->get_courseid());
        $config = service_factory::get_settings_config();
        foreach (catalogue::load() as $definition) {
            if (!$definition->retains_value() || !in_array(resolver::LAYER_COURSE, $definition->get_levels(), true)) {
                continue;
            }
            $stored = $DB->record_exists(\local_dixeo\settings\config::TABLE, [
                'contextid' => $context->id,
                'component' => $definition->get_component(),
                'name' => $definition->get_key(),
            ]);
            if ($stored) {
                continue;
            }
            $config->apply_new_course_default($definition->get_component(), $definition->get_key(), $context);
        }
    }

    /**
     * Store a backed-up value when the catalogue declares its setting at this level.
     *
     * @param stdClass $data Backed-up row with component, name and value.
     * @param string $level Catalogue level of the restored context.
     */
    private function process_setting(stdClass $data, string $level): void {
        $component = (string) ($data->component ?? '');
        $name = (string) ($data->name ?? '');
        $definition = catalogue::load()[$name] ?? null;
        if (
            $definition === null
            || $definition->get_component() !== $component
            || !in_array($level, $definition->get_levels(), true)
        ) {
            return;
        }

        $context = context::instance_by_id((int) $this->task->get_contextid());
        try {
            service_factory::get_settings_config()->restore_value($component, $name, $context, (string) ($data->value ?? ''));
        } catch (\coding_exception | \moodle_exception $e) {
            // A value this site's catalogue refuses is not restored.
            return;
        }
    }
}
