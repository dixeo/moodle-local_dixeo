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

namespace local_dixeo\service\image\content;


use local_dixeo\dto\job_binding_metadata;
use local_dixeo\repository\image\job_repository;
use local_dixeo\service\image\content_target;
use local_dixeo\service\image\generation_request;
use local_dixeo\service\image\job_orchestrator;
use local_dixeo\service\image\policy;

/**
 * Processes [img-gen] shortcodes in DSL HTML fields.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class shortcode_service {
    /** @var string[] HTML fields that may contain img-gen shortcodes. */
    private const PROCESSABLE_FIELDS = ['intro', 'content', 'definition'];

    /**
     * Process HTML for one html_field_target field; returns updated HTML.
     *
     * @param string $html
     * @param html_field_target $htmlfieldtarget
     * @param int $userid
     * @return string
     */
    public function process_html(string $html, html_field_target $htmlfieldtarget, int $userid): string {
        if ($html === '' || !shortcode_parser::contains_shortcode($html)) {
            return $html;
        }

        $courseid = (int) $htmlfieldtarget->courseid;
        if (
            !policy::is_enabled(
                policy::ENTITY_CONTENT,
                policy::ACTION_GENERATE,
                $courseid > 0 ? \context_course::instance($courseid) : null
            )
        ) {
            return shortcode_parser::strip_all($html);
        }

        $title = $this->resolve_title($htmlfieldtarget);

        return (string) preg_replace_callback(
            shortcode_parser::SHORTCODE_PATTERN,
            function (array $match) use ($htmlfieldtarget, $userid, $title): string {
                return $this->replace_shortcode_match($match[0], $htmlfieldtarget, $userid, $title);
            },
            $html
        );
    }

    /**
     * Process all HTML fields on a module/entity record after insert.
     *
     * @param string $modname
     * @param int $instanceid
     * @param int $contextid
     * @param int $courseid
     * @param int|null $cmid
     * @param \stdClass $record
     * @param int $userid
     * @return \stdClass Updated record (same object, fields mutated).
     */
    public function process_record_fields(
        string $modname,
        int $instanceid,
        int $contextid,
        int $courseid,
        ?int $cmid,
        \stdClass $record,
        int $userid
    ): \stdClass {
        foreach (self::PROCESSABLE_FIELDS as $field) {
            if (!property_exists($record, $field)) {
                continue;
            }
            $html = (string) $record->{$field};
            if (!shortcode_parser::contains_shortcode($html)) {
                continue;
            }
            if (!target_registry::is_html_field($record, $field)) {
                // Non-HTML fields cannot host generated images; drop the placeholders.
                $record->{$field} = shortcode_parser::strip_all($html);
                continue;
            }

            $htmlfieldtarget = target_registry::resolve(
                $modname,
                $field,
                $instanceid,
                $contextid,
                $courseid,
                $cmid
            );
            if ($htmlfieldtarget === null) {
                $record->{$field} = shortcode_parser::strip_all($html);
                continue;
            }

            $record->{$field} = $this->process_html($html, $htmlfieldtarget, $userid);
        }

        return $record;
    }

    /**
     * Process all HTML fields on a freshly inserted record and persist any changes.
     *
     * Single entry point for DSL actions: runs {@see process_record_fields()} and
     * writes changed fields back to the entity table.
     *
     * @param string $modname Module plugin name or logical entity (slideshow_slide, glossary_entry).
     * @param int $instanceid Instance or row id.
     * @param int $contextid Module context id.
     * @param int $courseid
     * @param int|null $cmid
     * @param \stdClass $record
     * @param int $userid
     * @return \stdClass Updated record (same object, fields mutated).
     */
    public function process_and_persist(
        string $modname,
        int $instanceid,
        int $contextid,
        int $courseid,
        ?int $cmid,
        \stdClass $record,
        int $userid
    ): \stdClass {
        global $DB;

        $before = [];
        foreach (self::PROCESSABLE_FIELDS as $field) {
            if (property_exists($record, $field)) {
                $before[$field] = (string) $record->{$field};
            }
        }

        $record = $this->process_record_fields($modname, $instanceid, $contextid, $courseid, $cmid, $record, $userid);

        $updates = new \stdClass();
        $updates->id = $instanceid;
        $changed = false;
        foreach ($before as $field => $original) {
            if ((string) $record->{$field} === $original) {
                continue;
            }
            $updates->{$field} = $record->{$field};
            $changed = true;
        }

        if ($changed) {
            $DB->update_record(target_registry::get_table_for_entity($modname), $updates);
            // Creation paths that rebuild the course cache before calling us (h5p) would
            // otherwise keep serving the pre-shortcode intro.
            modinfo_helper::purge_module($courseid, $cmid);
        }

        return $record;
    }

    /**
     * Resolve title.
     * @param html_field_target $htmlfieldtarget
     * @return string
     */
    private function resolve_title(html_field_target $htmlfieldtarget): string {
        if ($htmlfieldtarget->cmid) {
            $cm = get_coursemodule_from_id(null, $htmlfieldtarget->cmid, 0, false, IGNORE_MISSING);
            if ($cm && !empty($cm->name)) {
                return (string) $cm->name;
            }
        }
        return get_string('contentimagetitlefallback', 'local_dixeo');
    }

    /**
     * Replace shortcode match.
     * @param string $shortcode Full shortcode token including brackets.
     * @param html_field_target $htmlfieldtarget
     * @param int $userid
     * @param string $title
     * @return string
     */
    private function replace_shortcode_match(
        string $shortcode,
        html_field_target $htmlfieldtarget,
        int $userid,
        string $title
    ): string {
        $parsed = shortcode_parser::find_all($shortcode);
        if ($parsed === []) {
            return '';
        }
        $spec = $parsed[0];

        if (!shortcode_parser::is_new_generation($spec)) {
            return '';
        }

        $placeholderid = \core\uuid::generate();
        $filename = file_service::stub_filename_for_placeholder($placeholderid);
        $location = new location(
            $htmlfieldtarget->contextid,
            $htmlfieldtarget->component,
            $htmlfieldtarget->filearea,
            $htmlfieldtarget->itemid,
            '/',
            $filename,
            $htmlfieldtarget->courseid
        );

        file_service::create_stub($location, $userid);

        $request = generation_request::for_content(
            'local_dixeo',
            $htmlfieldtarget->courseid,
            $title,
            $spec['prompt'],
            $spec['size'],
            $spec['quality'],
            $this->metadata_for_field_target($htmlfieldtarget)
        );
        job_orchestrator::queue_generation(content_target::from_location($location), $userid, $request, [
            'placeholderid' => $placeholderid,
            'targettable' => $htmlfieldtarget->targettable,
            'targetfield' => $htmlfieldtarget->targetfield,
            'targetid' => $htmlfieldtarget->targetid,
            'cmid' => $htmlfieldtarget->cmid,
            'origin' => job_repository::ORIGIN_SHORTCODE,
            'prompt' => $spec['prompt'],
            'quality' => $spec['quality'],
            'mode' => $spec['mode'],
        ]);

        $url = s($location->get_pluginfile_token_src());
        return '<img src="' . $url . '" class="' . html_helper::IMG_CLASS . ' dixeo-img-gen-pending" data-dixeo-img-gen="' .
            s($placeholderid) . '" alt="" />';
    }

    /**
     * Resolve job binding metadata for a shortcode HTML field target.
     *
     * @param html_field_target $htmlfieldtarget Field target with module context.
     * @return job_binding_metadata|null
     */
    private function metadata_for_field_target(html_field_target $htmlfieldtarget): ?job_binding_metadata {
        if ($htmlfieldtarget->cmid !== null && $htmlfieldtarget->cmid > 0) {
            return job_binding_metadata::for_module($htmlfieldtarget->modname, $htmlfieldtarget->cmid);
        }
        if ($htmlfieldtarget->contextid > 0) {
            return new job_binding_metadata(
                moduletype: $htmlfieldtarget->modname,
                contextid: $htmlfieldtarget->contextid
            );
        }
        if ($htmlfieldtarget->courseid > 0) {
            return job_binding_metadata::for_course($htmlfieldtarget->courseid, $htmlfieldtarget->modname);
        }

        return null;
    }
}
