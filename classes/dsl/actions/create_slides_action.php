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
use local_dixeo\dsl\generated_html;
use local_dixeo\dsl\value_resolver;
use local_dixeo\external\service_factory;
use local_dixeo\service\image\content\shortcode_parser;

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
 *     "content": {"source": "$.content"},
 *     "layout": {"source": "$.layout"},
 *     "featuredimage": {"source": "$.featuredImage"}
 *   }
 * }
 *
 * An older API omits layout and featuredimage. The first slide is always
 * title only. A content slide with no featured image uses the background layout.
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
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/mod/slideshow/locallib.php');

        $this->validate_action($action);

        // Resolve the module reference to get slideshow info.
        $moduleref = $action['module_ref'];
        $moduledata = $resolver->resolve_source($moduleref, 'module_ref');

        if (!is_array($moduledata) || !isset($moduledata['id'], $moduledata['cmid'])) {
            throw new dsl_exception(
                "module_ref did not resolve to valid module data",
                'add_slides',
                ['module_ref' => $moduleref]
            );
        }

        $cm = $this->require_module_created_in_course(
            $resolver,
            'slideshow',
            (int) $moduledata['id'],
            (int) $moduledata['cmid']
        );
        $cmid = (int) $cm->id;
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
        $optionalfields = [];
        foreach (['layout', 'featuredimage'] as $optionalfield) {
            if (isset($fieldsspec[$optionalfield])) {
                $optionalfields[$optionalfield] = $fieldsspec[$optionalfield];
                unset($fieldsspec[$optionalfield]);
            }
        }
        $createdids = [];
        $now = time();
        $modulecontext = \context_module::instance($cmid);
        $shortcodeservice = service_factory::get_content_image_shortcode_service();
        $userid = (int) ($resolver->get_context()['userid'] ?? $USER->id);

        $createdids[] = $this->insert_opening_slide(
            $resolver,
            $slideshowinstanceid,
            $now
        );

        $sortorder = 1;
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
            $resolvedfields += $itemresolver->resolve_fields($optionalfields, true);

            // Build the slide record.
            $record = new \stdClass();
            $record->slideshow = $slideshowinstanceid;
            $record->name = $resolvedfields['title'] ?? '';
            $record->content = generated_html::strip_active_content((string) ($resolvedfields['content'] ?? ''));
            $record->contentformat = FORMAT_HTML;
            $record->layout = $this->layout_for_content_slide($resolvedfields);
            $record->hidetitle = 0;
            $record->hidden = 0;
            $record->sortorder = $sortorder;
            $record->timemodified = $now;
            $sortorder++;

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
     * Insert the title-only opening slide.
     *
     * The title is the API openingTitle when that response includes one, and
     * the slideshow activity name otherwise.
     *
     * @param value_resolver $resolver Resolver holding the full AI response.
     * @param int $slideshowinstanceid Slideshow instance id.
     * @param int $now Modification time.
     * @return int Created slide id.
     */
    private function insert_opening_slide(
        value_resolver $resolver,
        int $slideshowinstanceid,
        int $now
    ): int {
        global $DB;

        $aidata = $resolver->get_ai_data();
        $opening = trim((string) ($aidata['openingTitle'] ?? ''));
        if ($opening === '') {
            $slideshow = $DB->get_record('slideshow', ['id' => $slideshowinstanceid], 'name', MUST_EXIST);
            $opening = (string) $slideshow->name;
        }

        $record = new \stdClass();
        $record->slideshow = $slideshowinstanceid;
        $record->name = $opening;
        $record->content = '';
        $record->contentformat = FORMAT_HTML;
        $record->layout = MOD_SLIDESHOW_LAYOUT_TITLEONLY;
        $record->hidetitle = 0;
        $record->hidden = 0;
        $record->sortorder = 0;
        $record->timemodified = $now;

        return $DB->insert_record('slideshow_slide', $record);
    }

    /**
     * Choose a content-slide layout.
     *
     * A missing layout, an unknown layout, and a content layout without a
     * featured-image shortcode are stored as background.
     *
     * @param array $resolvedfields Resolved slide fields.
     * @return string One of the MOD_SLIDESHOW_LAYOUT_* values.
     */
    private function layout_for_content_slide(array $resolvedfields): string {
        $raw = isset($resolvedfields['layout']) ? trim((string) $resolvedfields['layout']) : '';
        $known = slideshow_get_layouts();
        if (
            !$this->featured_image_is_present($resolvedfields)
            || !isset($known[$raw])
            || $raw === MOD_SLIDESHOW_LAYOUT_TITLEONLY
        ) {
            return MOD_SLIDESHOW_LAYOUT_BACKGROUND;
        }

        return $raw;
    }

    /**
     * Whether the slide carries a new featured-image shortcode.
     *
     * @param array $resolvedfields Resolved slide fields.
     * @return bool
     */
    private function featured_image_is_present(array $resolvedfields): bool {
        $value = isset($resolvedfields['featuredimage']) ? trim((string) $resolvedfields['featuredimage']) : '';
        if ($value === '') {
            return false;
        }
        $parsed = shortcode_parser::find_all($value);
        if ($parsed === []) {
            return false;
        }

        return shortcode_parser::is_new_generation($parsed[0]);
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
