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
     * @return string
     */
    public static function swap_img_class_for_placeholder(
        string $html,
        string $placeholderid,
        string $fromclass,
        string $toclass
    ): string {
        if ($html === '' || $placeholderid === '') {
            return $html;
        }

        $pattern = '/(<img\b(?=[^>]*\bdata-dixeo-img-gen="' . preg_quote($placeholderid, '/') .
            '")[^>]*\bclass=")([^"]*)(")/iu';

        return (string) preg_replace_callback($pattern, static function (array $match) use ($fromclass, $toclass): string {
            $classes = preg_split('/\s+/', trim($match[2])) ?: [];
            $classes = array_values(array_filter($classes, static fn(string $c): bool => $c !== '' && $c !== $fromclass));
            if ($toclass !== '' && !in_array($toclass, $classes, true)) {
                $classes[] = $toclass;
            }
            return $match[1] . implode(' ', $classes) . $match[3];
        }, $html);
    }

    /**
     * Update target html class.
     * @param \stdClass $job Job row with targettable/targetfield/targetid.
     * @param string $placeholderid
     * @param string $fromclass
     * @param string $toclass
     * @return void
     */
    public static function update_target_html_class(
        \stdClass $job,
        string $placeholderid,
        string $fromclass,
        string $toclass
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

        $updated = self::swap_img_class_for_placeholder((string) $record->{$field}, $placeholderid, $fromclass, $toclass);
        if ($updated === $record->{$field}) {
            return;
        }

        $DB->set_field($job->targettable, $field, $updated, ['id' => (int) $job->targetid]);
    }
}
