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


use local_dixeo\context\context_builder_factory;
use local_dixeo\repository\image\job_repository;
use local_dixeo\service\image\job_orchestrator;
use local_dixeo\service\image\policy;
use local_dixeo\service\module_generation_service;

/**
 * Builds encoded edit payloads and decodes AI responses for the content editor.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class editor_regenerate_service {
    /**
     * Build editor image context from adapter-like data.
     *
     * @param \context_module $context
     * @param int $courseid
     * @param int $cmid
     * @param string $modname
     * @param string $component mod_* component
     * @param string $filearea
     * @param int $fileitemid
     * @param string $contentfield
     * @param string $shortcodeentity
     * @param int $recordid
     * @param int $sessionid
     * @return editor_image_context
     */
    public static function build_image_context(
        \context_module $context,
        int $courseid,
        int $cmid,
        string $modname,
        string $component,
        string $filearea,
        int $fileitemid,
        string $contentfield,
        string $shortcodeentity,
        int $recordid,
        int $sessionid
    ): editor_image_context {
        return new editor_image_context(
            $context->id,
            $courseid,
            $cmid,
            $modname,
            $component,
            $filearea,
            $fileitemid,
            $contentfield,
            $shortcodeentity,
            $recordid,
            $sessionid
        );
    }

    /**
     * Build markdown context with img shortcodes for the remote edit API.
     *
     * @param int $cmid
     * @param int|null $subid
     * @param string|null $drafthtml
     * @param editor_image_context $imagecontext
     * @param int $userid Acting user, needed to resolve their form draft files.
     * @return string
     */
    public static function build_api_context(
        int $cmid,
        ?int $subid,
        ?string $drafthtml,
        editor_image_context $imagecontext,
        int $userid = 0
    ): string {
        $raw = context_builder_factory::build_edit_context($cmid, $subid, $drafthtml);
        $codec = new editor_image_codec();
        $encoded = $codec->encode_context_block($raw, $imagecontext, $userid);

        return $encoded;
    }

    /**
     * Build full edit API payload with editor image instructions.
     *
     * @param int $cmid
     * @param int|null $subid
     * @param string $instructions
     * @param editor_image_context $imagecontext
     * @param string|null $drafthtml
     * @param int $userid Acting user, needed to resolve their form draft files.
     * @return array{payload: array, imagecontext: editor_image_context}
     */
    public static function build_edit_payload(
        int $cmid,
        ?int $subid,
        string $instructions,
        editor_image_context $imagecontext,
        ?string $drafthtml = null,
        int $userid = 0
    ): array {
        $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);

        $contextmarkdown = self::build_api_context($cmid, $subid, $drafthtml, $imagecontext, $userid);

        $fullinstructions = $instructions;
        if (policy::is_enabled(policy::ENTITY_CONTENT, policy::ACTION_GENERATE)) {
            $fullinstructions = rtrim($fullinstructions) . "\n\n" . shortcode_service::get_editor_keep_image_prompt();
        }

        $service = new module_generation_service();
        $payload = $service->build_edit_payload(
            $cm->modname,
            $fullinstructions,
            $contextmarkdown,
            (int) $cm->course
        );

        return [
            'payload' => $payload,
            'imagecontext' => $imagecontext,
        ];
    }

    /**
     * Decode a content field returned by the edit API.
     *
     * @param string $html
     * @param editor_image_context $imagecontext
     * @param int $userid
     * @return decode_result
     */
    public static function decode_api_content(
        string $html,
        editor_image_context $imagecontext,
        int $userid
    ): decode_result {
        $codec = new editor_image_codec();
        $result = $codec->decode_html_from_api($html, $imagecontext, $userid);

        // Session jobs whose placeholder no longer appears in the latest content
        // were dropped by the AI (or an earlier regenerate): stop them.
        $orphans = job_repository::fail_orphan_session_jobs(
            $imagecontext->sessionid,
            array_merge($result->newplaceholderids, $result->restoredplaceholderids),
            get_string('editorimageorphaned', 'local_dixeo')
        );
        foreach ($orphans as $orphan) {
            job_orchestrator::cancel_remote((string) ($orphan->jobid ?? ''));
        }

        return $result;
    }
}
