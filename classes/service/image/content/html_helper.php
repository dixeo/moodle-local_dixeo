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


/**
 * Updates stored HTML fields for img-gen CSS class transitions.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class html_helper {
    /**
     * Normalize legacy absolute intro pluginfile URLs for rewrite at display time.
     *
     * Older img-gen output stored make_pluginfile_url() with itemid 0, producing
     * /intro/0/filename paths that core resolves as filepath /0/ (not found).
     *
     * @param string $html
     * @return string
     */
    public static function normalize_legacy_intro_pluginfile_urls(string $html): string {
        if ($html === '' || strpos($html, 'pluginfile.php') === false) {
            return $html;
        }

        return (string) preg_replace(
            '#https?://[^"\'>\s]+/pluginfile\.php/\d+/mod_[^/]+/intro/0/([^"\'>\s]+)#',
            '@@PLUGINFILE@@/$1',
            $html
        );
    }

    /**
     * Swap pending/failed classes on img tags referencing a placeholder id.
     *
     * @param string $html
     * @param string $placeholderid
     * @param string $fromclass
     * @param string $toclass
     * @param string $contenthash Optional contenthash written to data-dixeo-contenthash for cache-busting.
     * @return string
     */
    public static function swap_img_class_for_placeholder(
        string $html,
        string $placeholderid,
        string $fromclass,
        string $toclass,
        string $contenthash = ''
    ): string {
        if ($html === '' || $placeholderid === '') {
            return $html;
        }

        $filename = preg_quote(file_service::stub_filename_for_placeholder($placeholderid), '/');
        // Prefer data-dixeo-img-gen; fall back to dixeo-gen stub filename when the
        // data attribute was stripped by the HTML purifier on save.
        $pattern = '/<img\b(?=[^>]*(?:\bdata-dixeo-img-gen="' . preg_quote($placeholderid, '/') .
            '"|\/' . $filename . '|"' . $filename . '"))[^>]*>/iu';

        return (string) preg_replace_callback($pattern, static function (array $match) use (
            $fromclass,
            $toclass,
            $contenthash
        ): string {
            $tag = $match[0];

            if (preg_match('/\bclass="([^"]*)"/iu', $tag, $classmatch)) {
                $classes = preg_split('/\s+/', trim($classmatch[1])) ?: [];
                // Success clear (empty $toclass): drop both status classes so a retry after
                // failure does not leave dixeo-img-gen-failed / stale error hash UI.
                $droplist = $toclass === ''
                    ? [$fromclass, 'dixeo-img-gen-pending', 'dixeo-img-gen-failed']
                    : [$fromclass];
                $classes = array_values(array_filter(
                    $classes,
                    static fn(string $c): bool => $c !== '' && !in_array($c, $droplist, true)
                ));
                if ($toclass !== '' && !in_array($toclass, $classes, true)) {
                    $classes[] = $toclass;
                }
                $tag = preg_replace('/\bclass="[^"]*"/iu', 'class="' . implode(' ', $classes) . '"', $tag, 1) ?? $tag;
            }

            $tag = preg_replace('/\s*\bdata-dixeo-contenthash="[^"]*"/iu', '', $tag) ?? $tag;
            if ($contenthash !== '') {
                $tag = preg_replace('/<img\b/iu', '<img data-dixeo-contenthash="' . s($contenthash) . '"', $tag, 1) ?? $tag;
            }

            return $tag;
        }, $html);
    }

    /**
     * Update target html class.
     * @param \stdClass $job Job row with targettable/targetfield/targetid.
     * @param string $placeholderid
     * @param string $fromclass
     * @param string $toclass
     * @param string $contenthash Optional file contenthash for browser cache-busting.
     * @return void
     */
    public static function update_target_html_class(
        \stdClass $job,
        string $placeholderid,
        string $fromclass,
        string $toclass,
        string $contenthash = ''
    ): void {
        global $DB;

        if (empty($job->targettable) || empty($job->targetfield) || empty($job->targetid)) {
            return;
        }

        $record = $DB->get_record($job->targettable, ['id' => (int) $job->targetid], '*', IGNORE_MISSING);
        if (!$record) {
            return;
        }

        $field = (string) $job->targetfield;
        if (!property_exists($record, $field)) {
            return;
        }

        $updated = self::swap_img_class_for_placeholder(
            (string) $record->{$field},
            $placeholderid,
            $fromclass,
            $toclass,
            $contenthash
        );
        if ($updated === $record->{$field}) {
            return;
        }

        $DB->set_field($job->targettable, $field, $updated, ['id' => (int) $job->targetid]);
    }

    /**
     * Strip display-only shimmer frames/status pills so they are never persisted.
     *
     * The Dixeo editor may wrap pending imgs in .dixeo-img-gen-frame for shimmer;
     * course pages do the same at display time. Stored HTML must stay bare imgs.
     *
     * @param string $html
     * @return string
     */
    public static function unwrap_display_frames(string $html): string {
        if (
            $html === ''
            || (stripos($html, 'dixeo-img-gen-frame') === false
                && stripos($html, 'dixeo-img-gen-status') === false)
        ) {
            return $html;
        }

        $html = (string) (preg_replace(
            '/<span\b(?=[^>]*\bclass="[^"]*\bdixeo-img-gen-status\b)[^>]*>.*?<\/span>/ius',
            '',
            $html
        ) ?? $html);

        return (string) (preg_replace_callback(
            '/<span\b(?=[^>]*\bclass="[^"]*\bdixeo-img-gen-frame\b)[^>]*>(.*?)<\/span>/ius',
            static function (array $match): string {
                if (preg_match('/<img\b[^>]*>/iu', $match[1], $img)) {
                    return $img[0];
                }
                return $match[1];
            },
            $html
        ) ?? $html);
    }
}
