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
 * DSL action for creating child records (e.g., glossary entries).
 *
 * Iterates over a collection in the AI data and creates database records
 * for each item. Primarily used for glossary entries but extensible
 * to other entity types.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\dsl\actions;

use local_dixeo\dsl\dsl_exception;
use local_dixeo\dsl\value_resolver;
use local_dixeo\external\service_factory;

/**
 * Action handler for creating child entity records.
 *
 * Expected action format:
 * {
 *   "action": "create_entries",
 *   "entity": "glossary_entry",
 *   "foreach": "$.entries",
 *   "fields": {
 *     "glossaryid": {"source": "$module.id"},
 *     "concept": {"source": "$.concept"},
 *     "definition": {"source": "$.definition"},
 *     "definitionformat": {"value": 1},
 *     "userid": {"source": "$context.userid"}
 *   }
 * }
 */
class create_entries_action {
    use action_validation;

    /**
     * Entity type to database table mapping.
     *
     * Maps logical entity names to actual Moodle database tables.
     */
    protected const ENTITY_TABLE_MAP = [
        'glossary_entry' => 'glossary_entries',
    ];

    /**
     * Default field values for each entity type.
     *
     * Applied to all records of that entity type.
     */
    protected const ENTITY_DEFAULTS = [
        'glossary_entry' => [
            'timecreated' => null, // Will be set to current time.
            'timemodified' => null, // Will be set to current time.
            'approved' => 1,
            'usedynalink' => 0,
            'casesensitive' => 0,
            'fullmatch' => 1,
        ],
    ];

    /**
     * Execute the create_entries action.
     *
     * @param array $action The action specification.
     * @param value_resolver $resolver The value resolver.
     * @return array Array of created record IDs.
     * @throws dsl_exception If creation fails.
     */
    public function execute(array $action, value_resolver $resolver): array {
        global $DB, $USER;

        $this->validate_action($action);

        $entity = $action['entity'];
        $foreachpath = $action['foreach'];
        $fieldsspec = $action['fields'] ?? [];

        // Get the database table for this entity.
        $table = $this->get_table_for_entity($entity);

        // Resolve the collection to iterate over.
        $collection = $resolver->resolve_source($foreachpath, 'foreach');

        if (!is_array($collection)) {
            throw new dsl_exception(
                "foreach path '$foreachpath' did not resolve to an array",
                'create_entries',
                ['path' => $foreachpath, 'type' => gettype($collection)]
            );
        }

        $createdids = [];
        $shortcodeservice = service_factory::get_content_image_shortcode_service();
        $userid = (int) ($resolver->get_context()['userid'] ?? $USER->id);

        foreach ($collection as $index => $item) {
            // Convert item to array if it's an object.
            $itemdata = is_object($item) ? (array) $item : $item;

            if (!is_array($itemdata)) {
                throw new dsl_exception(
                    "Item at index $index is not an array or object",
                    'create_entries',
                    ['index' => $index, 'type' => gettype($item)]
                );
            }

            // Create a resolver with this item as the AI data context.
            // This allows $.field to reference fields within the current item.
            $itemresolver = $resolver->with_ai_data($itemdata);

            // Resolve fields for this item.
            $record = $this->build_record($entity, $fieldsspec, $itemresolver);

            // Insert the record.
            $recordid = $DB->insert_record($table, $record);

            if ($entity === 'glossary_entry' && property_exists($record, 'definition')) {
                $glossaryid = (int) ($record->glossaryid ?? 0);
                if ($glossaryid > 0) {
                    $cm = get_coursemodule_from_instance('glossary', $glossaryid, 0, false, MUST_EXIST);
                    $modulecontext = \context_module::instance($cm->id);
                    $record->id = $recordid;
                    $shortcodeservice->process_and_persist(
                        'glossary_entry',
                        $recordid,
                        $modulecontext->id,
                        (int) $cm->course,
                        (int) $cm->id,
                        $record,
                        $userid
                    );
                }
            }

            $createdids[] = $recordid;
        }

        return $createdids;
    }

    /**
     * Validate the action specification.
     *
     * @param array $action The action to validate.
     * @throws dsl_exception If validation fails.
     */
    protected function validate_action(array $action): void {
        $this->require_action_fields($action, ['entity', 'foreach'], 'create_entries');
    }

    /**
     * Get the database table name for an entity type.
     *
     * @param string $entity The entity type.
     * @return string The database table name (without prefix).
     * @throws dsl_exception If the entity type is unknown.
     */
    protected function get_table_for_entity(string $entity): string {
        if (!isset(self::ENTITY_TABLE_MAP[$entity])) {
            throw new dsl_exception(
                "Unknown entity type '$entity'",
                'create_entries',
                ['entity' => $entity, 'valid_entities' => array_keys(self::ENTITY_TABLE_MAP)]
            );
        }

        return self::ENTITY_TABLE_MAP[$entity];
    }

    /**
     * Build a record object for insertion.
     *
     * @param string $entity The entity type.
     * @param array $fieldsspec The field specifications.
     * @param value_resolver $resolver The value resolver for this item.
     * @return \stdClass The record object ready for insertion.
     * @throws dsl_exception If field resolution fails.
     */
    protected function build_record(string $entity, array $fieldsspec, value_resolver $resolver): \stdClass {
        // Start with entity defaults.
        $record = $this->get_entity_defaults($entity);

        // Resolve and apply field values.
        $resolvedfields = $resolver->resolve_fields($fieldsspec);

        foreach ($resolvedfields as $field => $value) {
            $record->$field = $value;
        }

        return $record;
    }

    /**
     * Get default field values for an entity type.
     *
     * @param string $entity The entity type.
     * @return \stdClass Object with default values.
     */
    protected function get_entity_defaults(string $entity): \stdClass {
        $defaults = self::ENTITY_DEFAULTS[$entity] ?? [];
        $record = new \stdClass();
        $now = time();

        foreach ($defaults as $field => $value) {
            // Handle null values that should be set to current time.
            if ($value === null && in_array($field, ['timecreated', 'timemodified'])) {
                $record->$field = $now;
            } else {
                $record->$field = $value;
            }
        }

        return $record;
    }
}
