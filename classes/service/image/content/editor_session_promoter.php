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


use local_dixeo\repository\image\job_repository;

/**
 * Promotes editor draft images to module fileareas on save.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class editor_session_promoter {
    /**
     * Move draft session images into the module filearea and rewrite HTML.
     *
     * @param string $html
     * @param editor_image_context $ctx
     * @param int $userid
     * @return string Updated HTML with module @@PLUGINFILE@@ tokens.
     */
    public static function promote_html(string $html, editor_image_context $ctx, int $userid): string {
        if (
            $html === ''
                || (stripos($html, 'data-dixeo-img-gen') === false
                    && stripos($html, 'data-dixeo-draft-file') === false
                    && !preg_match('/dixeo-gen-[a-f0-9-]+\.png/i', $html))
        ) {
            return $html;
        }

        return (string) preg_replace_callback(
            '/<img\b[^>]*>/iu',
            function (array $match) use ($ctx, $userid): string {
                $tag = $match[0];
                if (preg_match('/\bdata-dixeo-img-gen="([^"]+)"/iu', $tag, $attr)) {
                    return self::promote_img_tag($tag, trim($attr[1]), $ctx, $userid);
                }
                if (preg_match('/\bdata-dixeo-draft-file="([^"]+)"/iu', $tag, $attr)) {
                    $filename = html_entity_decode(trim($attr[1]), ENT_QUOTES);
                    return self::rewrite_file_tag($tag, $filename, $ctx, $userid);
                }
                $placeholderid = self::placeholderid_from_img_tag($tag);
                if ($placeholderid !== null) {
                    return self::promote_img_tag($tag, $placeholderid, $ctx, $userid);
                }
                return $tag;
            },
            $html
        );
    }

    /**
     * Resolve a generated-image placeholder id from an img tag.
     *
     * Falls back to the dixeo-gen stub filename in src when data-dixeo-img-gen
     * was stripped by the HTML purifier before save.
     *
     * @param string $imghtml
     * @return string|null
     */
    private static function placeholderid_from_img_tag(string $imghtml): ?string {
        if (preg_match('/\b(?:src|data-mce-src)="[^"]*\/(dixeo-gen-[a-f0-9-]+\.png)/iu', $imghtml, $match)) {
            return file_service::placeholderid_from_stub_filename($match[1]);
        }

        return null;
    }

    /**
     * Process any remaining new shortcodes, then promote draft images.
     *
     * @param string $html
     * @param editor_image_context $ctx
     * @param int $userid
     * @return string
     */
    public static function finalize_on_save(string $html, editor_image_context $ctx, int $userid): string {
        // Drop editor-only shimmer hosts before shortcode/promote processing.
        $html = html_helper::unwrap_display_frames($html);
        $target = $ctx->html_field_target();
        $shortcodeservice = new shortcode_service();
        $html = $shortcodeservice->process_html($html, $target, $userid);
        $html = self::promote_html($html, $ctx, $userid);
        return html_helper::unwrap_display_frames($html);
    }

    /**
     * Promote img tag.
     * @param string $imghtml
     * @param string $placeholderid
     * @param editor_image_context $ctx
     * @param int $userid
     * @return string
     */
    private static function promote_img_tag(
        string $imghtml,
        string $placeholderid,
        editor_image_context $ctx,
        int $userid
    ): string {
        $filename = file_service::stub_filename_for_placeholder($placeholderid);
        $draftloc = $ctx->draft_location($filename);
        $draftfile = $draftloc->get_stored_file();
        $moduleloc = $ctx->module_location($filename);

        if ($draftfile) {
            self::copy_file_to_location($draftfile, $moduleloc, $userid);
            self::repoint_job($placeholderid, $draftloc, $moduleloc, $ctx);
        } else if (!$moduleloc->get_stored_file()) {
            // Neither a session draft nor a module copy exists (e.g. an image
            // living in another filearea): leave the tag untouched.
            return $imghtml;
        }

        $out = self::replace_src($imghtml, $moduleloc->get_pluginfile_token_src());
        $modulefile = $moduleloc->get_stored_file();
        $placeholderhash = sha1(asset_helper::get_placeholder_binary());
        $errorhash = sha1(asset_helper::get_error_binary());
        $contenthash = $modulefile ? $modulefile->get_contenthash() : '';
        $isplaceholder = $contenthash !== '' && $contenthash === $placeholderhash;
        $iserror = $contenthash !== '' && $contenthash === $errorhash;

        // Editor-draft apply updates the file bytes but never rewrites TinyMCE HTML.
        // If the user saves while the img still carries dixeo-img-gen-pending, strip it
        // once the promoted file is the final (or error) asset.
        if (!$isplaceholder) {
            $out = self::strip_gen_status_classes($out, $iserror);
            if ($contenthash !== '') {
                $out = self::set_contenthash_attr($out, $contenthash);
            }
        }

        return $out;
    }

    /**
     * Remove generation status classes; optionally mark failed.
     *
     * @param string $imghtml
     * @param bool $markfailed
     * @return string
     */
    private static function strip_gen_status_classes(string $imghtml, bool $markfailed = false): string {
        if (!preg_match('/\bclass="([^"]*)"/iu', $imghtml, $classmatch)) {
            return $imghtml;
        }

        $classes = preg_split('/\s+/', trim($classmatch[1])) ?: [];
        $classes = array_values(array_filter(
            $classes,
            static fn(string $c): bool => $c !== ''
                && $c !== 'dixeo-img-gen-pending'
                && $c !== 'dixeo-img-gen-failed'
        ));
        if ($markfailed && !in_array('dixeo-img-gen-failed', $classes, true)) {
            $classes[] = 'dixeo-img-gen-failed';
        }

        return (string) (preg_replace(
            '/\bclass="[^"]*"/iu',
            'class="' . implode(' ', $classes) . '"',
            $imghtml,
            1
        ) ?? $imghtml);
    }

    /**
     * Set or replace data-dixeo-contenthash for display-time cache busting.
     *
     * @param string $imghtml
     * @param string $contenthash
     * @return string
     */
    private static function set_contenthash_attr(string $imghtml, string $contenthash): string {
        $imghtml = preg_replace('/\s*\bdata-dixeo-contenthash="[^"]*"/iu', '', $imghtml) ?? $imghtml;
        return (string) (preg_replace(
            '/<img\b/iu',
            '<img data-dixeo-contenthash="' . s($contenthash) . '"',
            $imghtml,
            1
        ) ?? $imghtml);
    }

    /**
     * Rewrite a restored pluginfile image back to a module @@PLUGINFILE@@ token.
     *
     * @param string $imghtml
     * @param string $filename
     * @param editor_image_context $ctx
     * @param int $userid
     * @return string
     */
    private static function rewrite_file_tag(
        string $imghtml,
        string $filename,
        editor_image_context $ctx,
        int $userid
    ): string {
        if ($filename === '') {
            return $imghtml;
        }

        $moduleloc = $ctx->module_location($filename);
        if (!$moduleloc->get_stored_file()) {
            $draftfile = $ctx->draft_location($filename)->get_stored_file();
            if (!$draftfile) {
                return $imghtml;
            }
            self::copy_file_to_location($draftfile, $moduleloc, $userid);
        }

        $tag = self::strip_attr($imghtml, 'data-dixeo-draft-file');
        return self::replace_src($tag, $moduleloc->get_pluginfile_token_src());
    }

    /**
     * Copy file to location.
     * @param \stored_file $source
     * @param location $target
     * @param int $userid
     * @return void
     */
    private static function copy_file_to_location(\stored_file $source, location $target, int $userid): void {
        $fs = get_file_storage();
        $existing = $target->get_stored_file();
        if ($existing) {
            $existing->delete();
        }

        $fs->create_file_from_storedfile([
            'contextid' => $target->contextid,
            'component' => $target->component,
            'filearea' => $target->filearea,
            'itemid' => $target->itemid,
            'filepath' => $target->filepath,
            'filename' => $target->filename,
            'userid' => $userid,
        ], $source);
    }

    /**
     * Repoint job.
     * @param string $placeholderid
     * @param location $from
     * @param location $to
     * @param editor_image_context $ctx
     * @return void
     */
    private static function repoint_job(
        string $placeholderid,
        location $from,
        location $to,
        editor_image_context $ctx
    ): void {
        global $DB;

        $job = job_repository::get_by_placeholderid($placeholderid);
        if (!$job) {
            return;
        }

        $htmltarget = $ctx->html_field_target();
        $oldtarget = \local_dixeo\service\image\target_factory::from_job_record($job);
        $newfields = array_merge($to->to_record_fields(), [
            'id' => $job->id,
            'targettable' => $htmltarget->targettable,
            'targetfield' => $htmltarget->targetfield,
            'targetid' => $htmltarget->targetid,
            'cmid' => $ctx->cmid,
            'origin' => job_repository::ORIGIN_SHORTCODE,
            'timemodified' => time(),
        ]);

        $DB->update_record(job_repository::TABLE, (object) $newfields);

        // Poll adhocs key off locationhash. After promote the draft-hash task can no
        // longer find this row. Drop it and queue against the module location.
        \local_dixeo\service\image\poll\manager::delete_queued($oldtarget);
        $updated = $DB->get_record(job_repository::TABLE, ['id' => (int) $job->id], '*', MUST_EXIST);
        \local_dixeo\service\image\poll\manager::ensure_poll_for_job($updated);
    }

    /**
     * Replace the src attribute, keeping every other attribute intact
     * (alt, width, height, style, classes set by the user in TinyMCE, …).
     *
     * @param string $imghtml
     * @param string $newsrc
     * @return string
     */
    private static function replace_src(string $imghtml, string $newsrc): string {
        $replacement = 'src="' . s($newsrc) . '"';
        if (preg_match('/\bsrc="[^"]*"/iu', $imghtml)) {
            return (string) preg_replace('/\bsrc="[^"]*"/iu', $replacement, $imghtml, 1);
        }
        return (string) preg_replace('/<img\b/iu', '<img ' . $replacement, $imghtml, 1);
    }

    /**
     * Strip attr.
     * @param string $imghtml
     * @param string $attr
     * @return string
     */
    private static function strip_attr(string $imghtml, string $attr): string {
        return (string) preg_replace('/\s*\b' . preg_quote($attr, '/') . '="[^"]*"/iu', '', $imghtml);
    }
}
