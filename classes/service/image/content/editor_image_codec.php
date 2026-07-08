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
 * Encode/decode img-gen shortcodes for Dixeo editor API round-trip.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class editor_image_codec {
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
     * Replace pluginfile images in HTML with [img-gen ref/file] shortcodes for the remote API.
     *
     * @param string $html
     * @param editor_image_context $ctx
     * @param int $userid Acting user, needed to resolve their form draft files.
     * @return string
     */
    public function encode_html_for_api(string $html, editor_image_context $ctx, int $userid = 0): string {
        if ($html === '' || stripos($html, '<img') === false) {
            return $html;
        }

        return (string) preg_replace_callback(
            '/<img\b[^>]*>/iu',
            function (array $match) use ($ctx, $userid): string {
                return $this->encode_img_tag($match[0], $ctx, $userid);
            },
            $html
        );
    }

    /**
     * Encode HTML inside a markdown edit-context block.
     *
     * @param string $contextmarkdown
     * @param editor_image_context $ctx
     * @param int $userid Acting user, needed to resolve their form draft files.
     * @return string
     */
    public function encode_context_block(string $contextmarkdown, editor_image_context $ctx, int $userid = 0): string {
        $start = '>>> MODULE TO EDIT <<<';
        $end = '>>> END MODULE TO EDIT <<<';
        $startpos = strpos($contextmarkdown, $start);
        if ($startpos === false) {
            return $contextmarkdown;
        }
        $contentstart = $startpos + strlen($start);
        $endpos = strpos($contextmarkdown, $end, $contentstart);
        if ($endpos === false) {
            return $contextmarkdown;
        }

        $before = substr($contextmarkdown, 0, $contentstart);
        $block = substr($contextmarkdown, $contentstart, $endpos - $contentstart);
        $after = substr($contextmarkdown, $endpos);

        return $before . $this->encode_html_for_api($block, $ctx, $userid) . $after;
    }

    /**
     * Decode API HTML: restore ref/file images and queue jobs for new shortcodes.
     *
     * @param string $html
     * @param editor_image_context $ctx
     * @param int $userid
     * @return decode_result
     */
    public function decode_html_from_api(string $html, editor_image_context $ctx, int $userid): decode_result {
        if ($html === '' || !shortcode_parser::contains_shortcode($html)) {
            return new decode_result($html, [], []);
        }

        if (!policy::is_enabled(policy::ENTITY_CONTENT, policy::ACTION_GENERATE)) {
            return new decode_result(shortcode_parser::strip_all($html), [], []);
        }

        $newids = [];
        $restoredids = [];

        $decoded = (string) preg_replace_callback(
            shortcode_parser::SHORTCODE_PATTERN,
            function (array $match) use ($ctx, $userid, &$newids, &$restoredids): string {
                $parsed = shortcode_parser::find_all($match[0]);
                if ($parsed === []) {
                    return '';
                }
                $spec = $parsed[0];

                if (!empty($spec['ref'])) {
                    $restoredids[] = (string) $spec['ref'];
                    return $this->restore_ref_shortcode($spec, $ctx);
                }
                if (!empty($spec['file'])) {
                    return $this->restore_file_shortcode($spec, $ctx, $userid);
                }

                $img = $this->create_new_shortcode_image($spec, $ctx, $userid);
                if ($img !== '') {
                    if (preg_match('/\bdata-dixeo-img-gen="([^"]+)"/', $img, $idmatch)) {
                        $newids[] = $idmatch[1];
                    }
                }
                return $img;
            },
            $html
        );

        return new decode_result($decoded, $newids, $restoredids);
    }

    /**
     * Encode img tag.
     * @param string $imghtml
     * @param editor_image_context $ctx
     * @param int $userid Acting user, needed to resolve their form draft files.
     * @return string
     */
    private function encode_img_tag(string $imghtml, editor_image_context $ctx, int $userid = 0): string {
        $ref = $this->extract_placeholder_id($imghtml);
        if ($ref !== null) {
            $job = job_repository::get_by_placeholderid($ref);
            $prompt = $job && !empty($job->prompt) ? (string) $job->prompt : shortcode_parser::PRESERVE_PROMPT;
            $quality = $job && !empty($job->quality) ? (string) $job->quality : '';
            $mode = $job && !empty($job->mode) ? (string) $job->mode : '';
            return shortcode_parser::build_ref_shortcode($ref, $prompt, $quality, $mode);
        }

        $filename = $this->extract_module_pluginfile_filename($imghtml, $ctx);
        if ($filename !== null && !file_service::is_generated_stub_filename($filename)) {
            // Only encode files that can be restored from this context. Images from
            // other fileareas (e.g. the module intro rendered into the same context
            // block) pass through unchanged so they are not silently dropped later.
            if (
                $ctx->module_location($filename)->get_stored_file()
                    || $ctx->draft_location($filename)->get_stored_file()
            ) {
                return shortcode_parser::build_file_shortcode($filename);
            }
        }

        // Images in the user's form draft area (e.g. uploaded via TinyMCE but not
        // saved yet) carry draftfile.php URLs. Encode them with the draftitemid so
        // decode can restore the original draft URL.
        $draftinfo = $this->extract_user_draftfile_info($imghtml, $userid);
        if ($draftinfo !== null && !file_service::is_generated_stub_filename($draftinfo['filename'])) {
            return shortcode_parser::build_file_shortcode(
                $draftinfo['filename'],
                shortcode_parser::PRESERVE_PROMPT,
                $draftinfo['draftitemid']
            );
        }

        return $imghtml;
    }

    /**
     * Restore a ref shortcode to an img tag.
     *
     * @param array $spec Parsed shortcode attributes.
     * @param editor_image_context $ctx Editor image context.
     * @return string
     */
    private function restore_ref_shortcode(array $spec, editor_image_context $ctx): string {
        $ref = (string) $spec['ref'];
        $job = job_repository::get_by_placeholderid($ref);
        $filename = file_service::stub_filename_for_placeholder($ref);

        $location = $this->resolve_ref_location($ref, $filename, $ctx, $job);
        if ($location === null) {
            return '';
        }

        return $this->build_img_for_location($location, $ref, $job);
    }

    /**
     * Restore a file shortcode to an img tag.
     *
     * @param array $spec Parsed shortcode attributes.
     * @param editor_image_context $ctx Editor image context.
     * @param int $userid User id for draft file lookup.
     * @return string
     */
    private function restore_file_shortcode(array $spec, editor_image_context $ctx, int $userid = 0): string {
        $filename = (string) $spec['file'];
        if ($filename === '' || $filename === '.') {
            return '';
        }

        // Files living in the user's form draft area (uploaded but not saved yet)
        // are restored to their original draftfile URL: the form save then handles
        // them like any other draft image.
        $draftitemid = (int) ($spec['draftitemid'] ?? 0);
        if ($draftitemid > 0 && $userid > 0) {
            $usercontext = \context_user::instance($userid);
            $draftfile = get_file_storage()->get_file(
                $usercontext->id,
                'user',
                'draft',
                $draftitemid,
                '/',
                $filename
            );
            if ($draftfile && !$draftfile->is_directory()) {
                $url = \moodle_url::make_draftfile_url($draftitemid, '/', $filename)->out(false);
                return '<img src="' . s($url) . '" class="img-fluid" alt="" />';
            }
        }

        $location = $ctx->module_location($filename);
        if (!$location->get_stored_file()) {
            $draftloc = $ctx->draft_location($filename);
            if ($draftloc->get_stored_file()) {
                $location = $draftloc;
            } else {
                return '';
            }
        }

        // Emit a real URL so the image renders inside TinyMCE; the marker lets
        // editor_session_promoter rewrite it back to @@PLUGINFILE@@ on save.
        $url = s($location->get_pluginfile_url());
        return '<img src="' . $url . '" class="img-fluid" data-dixeo-draft-file="' .
            s($location->filename) . '" alt="" />';
    }

    /**
     * Create a new shortcode image stub and queue generation.
     *
     * @param array $spec Parsed shortcode attributes.
     * @param editor_image_context $ctx Editor image context.
     * @param int $userid User id for file ownership.
     * @return string
     */
    private function create_new_shortcode_image(array $spec, editor_image_context $ctx, int $userid): string {
        $placeholderid = \core\uuid::generate();
        $filename = file_service::stub_filename_for_placeholder($placeholderid);
        $location = $ctx->draft_location($filename);

        file_service::create_stub($location, $userid);

        $imageservice = $this->imageservice ?? service_factory::get_image_generation_service();
        $title = $this->resolve_title($ctx);

        try {
            $result = $imageservice->submit_content_image_generate_job(
                $ctx->courseid,
                $title,
                (string) $spec['prompt'],
                (string) $spec['size'],
                (string) $spec['quality']
            );

            $jobid = trim((string) $result->jobid);
            if ($jobid === '') {
                throw new \moodle_exception('dixeo_image_job_empty_result', 'local_dixeo');
            }

            $contenttarget = content_target::from_location($location);
            job_orchestrator::submit_and_queue($contenttarget, $jobid, $userid, [
                'placeholderid' => $placeholderid,
                'targettable' => 'local_dixeo_editor_session',
                'targetfield' => 'draft',
                'targetid' => $ctx->sessionid,
                'cmid' => $ctx->cmid,
                'origin' => job_repository::ORIGIN_EDITOR_DRAFT,
                'prompt' => (string) $spec['prompt'],
                'quality' => (string) $spec['quality'],
                'mode' => (string) $spec['mode'],
            ]);
        } catch (\Throwable $e) {
            debugging('editor img-gen job submission failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $stub = $location->get_stored_file();
            if ($stub) {
                $stub->delete();
            }
            return '';
        }

        return $this->build_img_for_location($location, $placeholderid, job_repository::get_by_placeholderid($placeholderid));
    }

    /**
     * Build img for location.
     * @param location $location
     * @param string $placeholderid
     * @param \stdClass|null $job
     * @return string
     */
    private function build_img_for_location(location $location, string $placeholderid, ?\stdClass $job): string {
        $class = 'img-fluid';
        if ($job && in_array($job->status, [job_repository::STATUS_PENDING, job_repository::STATUS_PROCESSING], true)) {
            $class .= ' dixeo-img-gen-pending';
        } else if ($job && $job->status === job_repository::STATUS_FAILED) {
            $class .= ' dixeo-img-gen-failed';
        }

        $url = s($location->get_pluginfile_url());
        return '<img src="' . $url . '" class="' . $class . '" data-dixeo-img-gen="' .
            s($placeholderid) . '" alt="" />';
    }

    /**
     * Resolve ref location.
     * @param string $ref
     * @param string $filename
     * @param editor_image_context $ctx
     * @param \stdClass|null $job
     * @return location|null
     */
    private function resolve_ref_location(
        string $ref,
        string $filename,
        editor_image_context $ctx,
        ?\stdClass $job
    ): ?location {
        if ($job) {
            $fromjob = location::from_job_record($job);
            if ($fromjob->get_stored_file()) {
                return $fromjob;
            }
        }

        $candidates = [
            $ctx->draft_location($filename),
            $ctx->module_location($filename),
        ];

        foreach ($candidates as $location) {
            if ($location->get_stored_file()) {
                return $location;
            }
        }

        return null;
    }

    /**
     * Extract placeholder id.
     * @param string $imghtml
     * @return string|null
     */
    private function extract_placeholder_id(string $imghtml): ?string {
        if (preg_match('/\bdata-dixeo-img-gen="([^"]+)"/iu', $imghtml, $match)) {
            return trim($match[1]);
        }
        if (preg_match('/dixeo-gen-([a-f0-9-]{36})\.png/iu', $imghtml, $match)) {
            return trim($match[1]);
        }
        return null;
    }

    /**
     * Extract module pluginfile filename.
     * @param string $imghtml
     * @param editor_image_context $ctx
     * @return string|null
     */
    private function extract_module_pluginfile_filename(string $imghtml, editor_image_context $ctx): ?string {
        if (!preg_match('/\bsrc="([^"]+)"/iu', $imghtml, $match)) {
            return null;
        }
        $src = html_entity_decode($match[1], ENT_QUOTES);

        if (preg_match('/@@PLUGINFILE@@\/([^"?#]+)/iu', $src, $filematch)) {
            return rawurldecode($filematch[1]);
        }

        $draftpattern = '#pluginfile\.php/' . $ctx->contextid . '/' .
            preg_quote(editor_draft_fileareas::COMPONENT, '#') . '/#iu';
        if (preg_match($draftpattern, $src)) {
            return null;
        }

        $pattern = '#pluginfile\.php/' . $ctx->contextid . '/' .
            preg_quote($ctx->component, '#') . '/' .
            preg_quote($ctx->filearea, '#') . '/#iu';

        if (!preg_match($pattern, $src)) {
            return null;
        }

        if (preg_match('#/([^/?#]+)(?:\?|"|$)#u', $src, $filematch)) {
            return rawurldecode($filematch[1]);
        }

        return null;
    }

    /**
     * Extract filename and draftitemid from a draftfile.php img src for this user.
     *
     * @param string $imghtml
     * @param int $userid
     * @return array{filename: string, draftitemid: int}|null
     */
    private function extract_user_draftfile_info(string $imghtml, int $userid): ?array {
        if ($userid <= 0 || !preg_match('/\bsrc="([^"]+)"/iu', $imghtml, $match)) {
            return null;
        }
        $src = html_entity_decode($match[1], ENT_QUOTES);

        if (!preg_match('#draftfile\.php/(\d+)/user/draft/(\d+)/([^"?\#]+)#iu', $src, $draftmatch)) {
            return null;
        }

        $usercontext = \context_user::instance($userid);
        if ((int) $draftmatch[1] !== (int) $usercontext->id) {
            return null;
        }

        $draftitemid = (int) $draftmatch[2];
        $filename = rawurldecode($draftmatch[3]);

        $file = get_file_storage()->get_file($usercontext->id, 'user', 'draft', $draftitemid, '/', $filename);
        if (!$file || $file->is_directory()) {
            return null;
        }

        return ['filename' => $filename, 'draftitemid' => $draftitemid];
    }

    /**
     * Resolve title.
     * @param editor_image_context $ctx
     * @return string
     */
    private function resolve_title(editor_image_context $ctx): string {
        $cm = get_coursemodule_from_id(null, $ctx->cmid, 0, false, IGNORE_MISSING);
        if ($cm && !empty($cm->name)) {
            return (string) $cm->name;
        }
        return get_string('contentimagetitlefallback', 'local_dixeo');
    }
}
