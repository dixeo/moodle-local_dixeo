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


use local_dixeo\external\service_factory;
use local_dixeo\repository\image\job_repository;
use local_dixeo\service\image\content_target;
use local_dixeo\service\image\job_orchestrator;
use local_dixeo\service\image\policy;
use local_dixeo\service\image_generation_service;

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

    /** @var image_generation_service|null */
    private ?image_generation_service $imageservice;

    /**
     *   construct.
     * @param image_generation_service|null $imageservice
     */
    public function __construct(?image_generation_service $imageservice = null) {
        $this->imageservice = $imageservice;
    }

    /**
     * AI prompt fragment instructing the model to use img-gen shortcodes.
     *
     * @return string
     */
    public static function get_image_prompt(): string {
        return <<<'PROMPT'
When the content would benefit from an original illustration, insert an image shortcode on its own line using this exact format:
[img-gen prompt="Describe the image in detail" quality="medium" mode="landscape"]

Rules:
- prompt (required): detailed visual description in English.
- quality (optional): low, medium, or high (default medium).
- mode (optional): landscape, portrait, or square (default landscape).
- Do not use <img> tags for AI-generated images; use [img-gen ...] only.
- Do not substitute illustrations with inline <svg>, icon fonts, emoji art, or decorative HTML/CSS shapes.
- You may include multiple shortcodes in one field when appropriate.
PROMPT;
    }

    /**
     * Image prompt with optional module-type-specific rules.
     *
     * @param string $moduletype API module type (page, label, slideshow, …).
     * @return string
     */
    public static function get_image_prompt_for_module(string $moduletype): string {
        $prompt = self::get_image_prompt();

        if ($moduletype === 'slideshow') {
            $prompt .= <<<'PROMPT'


Slideshow-specific rules:
- When a slide should show an illustration, put a [img-gen ...] shortcode on its
  own line inside that slide's `content` field (the add_slides payload).
- Do not leave an empty image column or use <svg> / icons as a stand-in for a photo
  or illustration.
- Use at least one [img-gen ...] per slide when the user asks for images on slides.
PROMPT;
        }

        if ($moduletype === 'glossary') {
            $prompt .= <<<'PROMPT'


Glossary-specific rules:
- Glossary illustrations belong in each entry's `definition` field inside the
  `create_entries` action payload (not the module intro).
- When the user asks for images, include at least one [img-gen ...] shortcode on its
  own line in each entry `definition` that should have an illustration.
- Do not use <img>, inline <svg>, icons, or emoji art as substitutes for
  [img-gen ...] in entry definitions.
PROMPT;
        }

        return $prompt;
    }

    /**
     * Editor-specific prompt: preserve existing ref/file shortcodes unless user asks to remove images.
     *
     * @return string
     */
    public static function get_editor_keep_image_prompt(): string {
        return <<<'PROMPT'
When editing existing content, preserve existing image shortcodes unless the user explicitly asks to remove or replace images:
- Keep `[img-gen ref="…"]` tokens unchanged (AI-generated images, including pending ones).
- Keep `[img-gen file="…"]` tokens unchanged (other embedded pluginfile images).
- Do not drop `ref` or `file` attributes.
- Use new `[img-gen prompt="…"]` (without ref or file) only for newly requested illustrations.
PROMPT;
    }

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

        if (
            !policy::is_enabled(
                policy::ENTITY_CONTENT,
                policy::ACTION_GENERATE
            )
        ) {
            return shortcode_parser::strip_all($html);
        }

        $imageservice = $this->imageservice ?? service_factory::get_image_generation_service();
        $title = $this->resolve_title($htmlfieldtarget);

        return (string) preg_replace_callback(
            shortcode_parser::SHORTCODE_PATTERN,
            function (array $match) use ($htmlfieldtarget, $userid, $imageservice, $title): string {
                return $this->replace_shortcode_match($match[0], $htmlfieldtarget, $userid, $imageservice, $title);
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
     * @param image_generation_service $imageservice
     * @param string $title
     * @return string
     */
    private function replace_shortcode_match(
        string $shortcode,
        html_field_target $htmlfieldtarget,
        int $userid,
        image_generation_service $imageservice,
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

        try {
            $result = $imageservice->submit_content_image_generate_job(
                $htmlfieldtarget->courseid,
                $title,
                $spec['prompt'],
                $spec['size'],
                $spec['quality']
            );

            $jobid = trim((string) $result->jobid);
            if ($jobid === '') {
                throw new \moodle_exception('dixeo_image_job_empty_result', 'local_dixeo');
            }

            $contenttarget = content_target::from_location($location);
            job_orchestrator::submit_and_queue($contenttarget, $jobid, $userid, [
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
        } catch (\Throwable $e) {
            // Degrade gracefully: content creation must not fail because an image
            // could not be generated. Drop the shortcode and the orphaned stub file.
            debugging('img-gen shortcode job submission failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $stub = $location->get_stored_file();
            if ($stub) {
                $stub->delete();
            }
            return '';
        }

        $url = s($location->get_pluginfile_token_src());
        return '<img src="' . $url . '" class="img-fluid dixeo-img-gen-pending" data-dixeo-img-gen="' .
            s($placeholderid) . '" alt="" />';
    }
}
