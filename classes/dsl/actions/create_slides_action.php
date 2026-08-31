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
 * DSL action for creating slideshow slides.
 *
 * Iterates over a collection of slides in the AI data and creates
 * database records in the slideshow_slide table.
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
 * Action handler for creating slideshow slides.
 *
 * Expected action format:
 * {
 *   "action": "add_slides",
 *   "module_ref": "$module",
 *   "foreach": "$.slides",
 *   "fields": {
 *     "title": {"source": "$.title"},
 *     "content": {"source": "$.content"}
 *   }
 * }
 */
class create_slides_action {
    use action_validation;

    /**
     * Execute the add_slides action.
     *
     * @param array $action The action specification.
     * @param value_resolver $resolver The value resolver.
     * @return array Array of created slide IDs.
     * @throws dsl_exception If creation fails.
     */
    public function execute(array $action, value_resolver $resolver): array {
        global $DB, $USER;

        $this->validate_action($action);

        // Resolve the module reference to get slideshow info.
        $moduleref = $action['module_ref'];
        $moduledata = $resolver->resolve_source($moduleref, 'module_ref');

        if (!is_array($moduledata) || !isset($moduledata['cmid'])) {
            throw new dsl_exception(
                "module_ref did not resolve to valid module data",
                'add_slides',
                ['module_ref' => $moduleref]
            );
        }

        $cmid = (int) $moduledata['cmid'];
        $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
        if ($cm->modname !== 'slideshow') {
            throw new dsl_exception(
                'module_ref must resolve to a slideshow activity',
                'add_slides',
                ['cmid' => $cmid]
            );
        }
        $slideshowinstanceid = (int) $cm->instance;

        // Resolve the slides collection.
        $foreachpath = $action['foreach'];
        $slides = $resolver->resolve_source($foreachpath, 'foreach');

        if (!is_array($slides)) {
            throw new dsl_exception(
                "foreach path '$foreachpath' did not resolve to an array",
                'add_slides',
                ['path' => $foreachpath]
            );
        }

        $fieldsspec = $action['fields'] ?? [];
        $createdids = [];
        $now = time();
        $modulecontext = \context_module::instance($cmid);
        $shortcodeservice = service_factory::get_content_image_shortcode_service();
        $userid = (int) ($resolver->get_context()['userid'] ?? $USER->id);

        foreach ($slides as $index => $slideitem) {
            $itemdata = is_object($slideitem) ? (array) $slideitem : $slideitem;

            if (!is_array($itemdata)) {
                throw new dsl_exception(
                    "Slide at index $index is not an array or object",
                    'add_slides',
                    ['index' => $index]
                );
            }

            // Create resolver with this slide's data for per-item field resolution.
            $itemresolver = $resolver->with_ai_data($itemdata);
            $resolvedfields = $itemresolver->resolve_fields($fieldsspec);

            // Build the slide record.
            $record = new \stdClass();
            $record->slideshow = $slideshowinstanceid;
            $record->name = $resolvedfields['title'] ?? '';
            $record->content = $resolvedfields['content'] ?? '';
            $record->contentformat = FORMAT_HTML;
            $record->hidden = 0;
            $record->sortorder = $index;
            $record->timemodified = $now;

            // Insert the slide.
            $slideid = $DB->insert_record('slideshow_slide', $record);

            $record->id = $slideid;
            $shortcodeservice->process_and_persist(
                'slideshow_slide',
                $slideid,
                $modulecontext->id,
                (int) $cm->course,
                $cmid,
                $record,
                $userid
            );

            $createdids[] = $slideid;
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
        $this->require_action_fields($action, ['module_ref', 'foreach'], 'add_slides');
    }
}
