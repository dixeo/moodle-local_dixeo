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

namespace local_dixeo\service\image;


/**
 * Resolve pluginfile URLs to stored files and encode image bytes for API payloads.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class pluginfile_helper {
    /**
     * Resolve a wwwroot-relative or absolute pluginfile URL to a stored file.
     *
     * Handles revision-as-itemid URLs used by mod_page / mod_resource / etc.
     * via component_callback('get_path_from_pluginfile'), matching core H5P
     * and pluginfile serving behaviour.
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

        // Accept pluginfile.php, tokenpluginfile.php, and webservice/pluginfile.php.
        if (!preg_match('#/(?:(?:webservice/)?)(?:token)?pluginfile\.php/#', $path, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $slashargs = substr($path, $m[0][1] + strlen($m[0][0]));
        $segmentsraw = explode('/', trim($slashargs, '/'));
        $segments = array_map('rawurldecode', $segmentsraw);
        if (count($segments) < 3) {
            return null;
        }

        // Tokenpluginfile.php / {token} / {contextid} / ...
        if (stripos($path, '/tokenpluginfile.php/') !== false) {
            array_shift($segments);
        }

        if (count($segments) < 3) {
            return null;
        }

        $contextid = (int) array_shift($segments);
        $component = (string) array_shift($segments);
        $filearea = (string) array_shift($segments);
        if ($contextid < 1 || $component === '' || $filearea === '' || $segments === []) {
            return null;
        }

        $filename = (string) array_pop($segments);
        if ($filename === '') {
            return null;
        }

        // Some modules (page, resource, …) put a cache-busting revision where
        // itemid would be, but store files with itemid 0. Prefer the component
        // callback when present (same approach as core_h5p).
        $pathdata = null;
        $context = \context::instance_by_id($contextid, IGNORE_MISSING);
        if ($context && in_array($context->contextlevel, [CONTEXT_MODULE, CONTEXT_BLOCK], true)) {
            $pathdata = component_callback($component, 'get_path_from_pluginfile', [$filearea, $segments], null);
        }

        if (is_array($pathdata) && array_key_exists('itemid', $pathdata) && array_key_exists('filepath', $pathdata)) {
            $itemid = (int) $pathdata['itemid'];
            $filepath = (string) $pathdata['filepath'];
        } else {
            $hasnullitemid = false;
            $hasnullitemid = $hasnullitemid || ($component === 'user' && ($filearea === 'private' || $filearea === 'profile'));
            $hasnullitemid = $hasnullitemid || (str_starts_with($component, 'mod_') && $filearea === 'intro');
            $hasnullitemid = $hasnullitemid || ($component === 'course' &&
                    ($filearea === 'summary' || $filearea === 'overviewfiles'));
            $hasnullitemid = $hasnullitemid || ($component === 'coursecat' && $filearea === 'description');
            $hasnullitemid = $hasnullitemid || ($component === 'backup' &&
                    ($filearea === 'course' || $filearea === 'activity' || $filearea === 'automated'));

            if ($hasnullitemid) {
                $itemid = 0;
            } else if ($segments === []) {
                // .../context/component/filearea/filename (implicit itemid 0).
                $itemid = 0;
            } else {
                $itemid = (int) array_shift($segments);
            }

            if ($segments === []) {
                $filepath = '/';
            } else {
                $filepath = '/' . implode('/', $segments) . '/';
            }
        }

        if ($filepath === '' || $filepath === '//') {
            $filepath = '/';
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
