<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

namespace local_dixeo\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Resolve pluginfile URLs to stored files and encode image bytes for API payloads.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class pluginfile_image_helper {

    /**
     * Resolve a wwwroot-relative or absolute pluginfile URL to a stored file.
     *
     * @param string $imageurl Full or relative URL.
     * @return \stored_file|null
     */
    public static function get_stored_file_from_pluginfile_url(string $imageurl): ?\stored_file {
        global $CFG;

        $imageurl = trim($imageurl);
        if ($imageurl === '') {
            return null;
        }

        $parsedpath = parse_url($imageurl, PHP_URL_PATH);
        $path = is_string($parsedpath) && $parsedpath !== ''
            ? $parsedpath
            : (string) preg_replace('#^' . preg_quote($CFG->wwwroot, '#') . '#', '', $imageurl);
        $parts = explode('/pluginfile.php/', $path, 2);
        if (count($parts) !== 2) {
            return null;
        }
        $segmentsraw = explode('/', trim($parts[1], '/'));
        $segments = array_map('rawurldecode', $segmentsraw);
        if (count($segments) < 4) {
            return null;
        }
        if (count($segments) === 4) {
            $contextid = (int) $segments[0];
            $component = (string) $segments[1];
            $filearea = (string) $segments[2];
            $filename = (string) $segments[3];
            $itemid = 0;
            $filepath = '/';
        } else {
            $contextid = (int) array_shift($segments);
            $component = (string) array_shift($segments);
            $filearea = (string) array_shift($segments);
            $itemid = (int) array_shift($segments);
            $filename = (string) array_pop($segments);
            $filepath = '/' . implode('/', $segments) . '/';
            if ($filepath === '//') {
                $filepath = '/';
            }
        }

        $fs = get_file_storage();
        $file = $fs->get_file($contextid, $component, $filearea, $itemid, $filepath, $filename);
        if (!$file || $file->is_directory()) {
            return null;
        }
        return $file;
    }

    /**
     * Encode the file referenced by a pluginfile URL as raw base64 (for image edit API).
     *
     * @param string $imageurl
     * @return string
     * @throws \moodle_exception When the URL does not resolve to a file.
     */
    public static function image_url_to_base64(string $imageurl): string {
        $file = self::get_stored_file_from_pluginfile_url($imageurl);
        if (!$file) {
            throw new \moodle_exception('dixeo_pluginfile_not_found', 'local_dixeo');
        }
        return base64_encode($file->get_content());
    }

    /**
     * Resolve course id from a stored file's context.
     *
     * @param \stored_file $file
     * @return int
     */
    public static function resolve_course_id_for_file(\stored_file $file): int {
        $context = \context::instance_by_id($file->get_contextid(), IGNORE_MISSING);
        if (!$context) {
            return 0;
        }
        $coursecontext = $context->get_course_context(false);
        return $coursecontext ? (int) $coursecontext->instanceid : 0;
    }
}
