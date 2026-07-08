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

namespace local_dixeo\service\image\structure;

use context_course;
use core_plugin_manager;
use local_dixeo\service\image\result_helper;
use local_dixeo\service\plugin_installation_service;

/**
 * Writes async image job results into course overview and Dixeo section images.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class writer {
    /** @var array<string,string> */
    private const MIME_TO_EXT = [
        'image/gif' => 'gif',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/svg+xml' => 'svg',
    ];

    /**
     * Apply a remote job result to storage for the given scope.
     *
     * @param string $scope One of {@see scope::SCOPE_COURSE_OVERVIEW} or {@see scope::SCOPE_FORMAT_SECTION}.
     * @param int $objectid Course id (overview) or course_sections.id (format section).
     * @param array $result Raw job result (same shapes as image API).
     * @param int $userid User id stored on the file record.
     * @return void
     */
    public static function apply_from_job_result(string $scope, int $objectid, array $result, int $userid): void {
        $binary = result_helper::extract_image_binary_from_result($result);
        if ($binary === '') {
            throw new \moodle_exception('dixeo_image_job_empty_result', 'local_dixeo');
        }
        if ($scope === scope::SCOPE_COURSE_OVERVIEW) {
            self::apply_image_binary_to_course_overview($objectid, $binary, $userid);
            return;
        }
        if ($scope === scope::SCOPE_FORMAT_SECTION) {
            self::apply_binary_to_format_section($objectid, $binary, $userid);
            return;
        }
        throw new \coding_exception('Unknown image apply scope: ' . $scope);
    }

    /**
     * Store image bytes on the course overview file area.
     *
     * @param int $courseid
     * @param string $binary Raw image bytes.
     * @param int $userid
     * @return void
     */
    public static function apply_image_binary_to_course_overview(int $courseid, string $binary, int $userid): void {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid], 'id', IGNORE_MISSING);
        if (!$course) {
            throw new \moodle_exception('invalidcourseid', 'error');
        }

        $context = context_course::instance($courseid, MUST_EXIST);
        require_capability('moodle/course:update', $context);

        [$ext, $mimetype] = self::resolve_allowed_type($binary);
        $filename = self::build_unique_filename('course-cover', $ext);

        $record = [
            'contextid' => $context->id,
            'component' => 'course',
            'filearea' => 'overviewfiles',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
            'userid' => $userid,
            'mimetype' => $mimetype,
        ];

        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'course', 'overviewfiles', 0);
        $fs->create_file_from_string($record, $binary);

        \cache::make('core', 'course_image')->delete($courseid);
        rebuild_course_cache($courseid, true);
    }

    /**
     * Confirm format_dixeo is installed and load {@see \format_dixeo} when missing.
     *
     * @return void
     * @throws \coding_exception When format_dixeo is not installed.
     */
    public static function require_format_dixeo_class_loaded(): void {
        global $CFG;

        $installed = false;
        if (class_exists(plugin_installation_service::class, false)) {
            $installed = plugin_installation_service::is_component_installed('format_dixeo');
        } else {
            $installed = core_plugin_manager::instance()->get_plugin_info('format_dixeo') !== null;
        }

        if (!$installed) {
            throw new \coding_exception('The format_dixeo plugin must be installed to use this code path.');
        }

        if (!class_exists(\format_dixeo::class, false)) {
            require_once($CFG->dirroot . '/course/format/dixeo/lib.php');
        }
    }

    /**
     * Store a chapter cover image for a format_dixeo course section.
     *
     * @param int $sectionid course_sections.id
     * @param string $binary Raw image bytes.
     * @param int $userid
     * @return void
     */
    private static function apply_binary_to_format_section(int $sectionid, string $binary, int $userid): void {
        global $DB;

        $section = $DB->get_record('course_sections', ['id' => $sectionid], 'id, course', MUST_EXIST);
        $courseid = (int) $section->course;

        self::require_format_dixeo_class_loaded();

        $context = context_course::instance($courseid, MUST_EXIST);
        require_capability('moodle/course:update', $context);

        [$ext, $mimetype] = self::resolve_allowed_type($binary);
        $filename = self::build_unique_filename('chapter-cover-' . $sectionid, $ext);

        $record = [
            'contextid' => $context->id,
            'component' => 'format_dixeo',
            'filearea' => \format_dixeo::SECTION_IMAGE_FILEAREA,
            'itemid' => $sectionid,
            'filepath' => '/',
            'filename' => $filename,
            'userid' => $userid,
            'mimetype' => $mimetype,
        ];

        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'format_dixeo', \format_dixeo::SECTION_IMAGE_FILEAREA, $sectionid);
        $fs->create_file_from_string($record, $binary);

        \cache::make('core', 'course_image')->delete($courseid);
        rebuild_course_cache($courseid, true);
    }

    /**
     * Resolve an allowed image extension and MIME type from binary bytes.
     *
     * @param string $binary
     * @return array{0:string,1:string}
     */
    private static function resolve_allowed_type(string $binary): array {
        $mime = self::normalise_mime(self::finfo_mime($binary));
        if (isset(self::MIME_TO_EXT[$mime])) {
            return [self::MIME_TO_EXT[$mime], $mime];
        }
        throw new \moodle_exception('dixeo_course_image_unsupported_type', 'local_dixeo');
    }

    /**
     * Detect MIME type from raw image bytes.
     *
     * @param string $binary Raw image bytes.
     * @return string
     */
    private static function finfo_mime(string $binary): string {
        if (!function_exists('finfo_open')) {
            return '';
        }
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        if (!$fi) {
            return '';
        }
        $mime = (string) finfo_buffer($fi, $binary);
        finfo_close($fi);
        return $mime;
    }

    /**
     * Normalise a MIME type string to its primary type.
     *
     * @param string $raw Raw MIME type (possibly with parameters).
     * @return string
     */
    private static function normalise_mime(string $raw): string {
        $raw = strtolower(trim($raw));
        if ($raw === '') {
            return '';
        }
        $parts = explode(';', $raw, 2);
        return trim($parts[0]);
    }

    /**
     * Build a unique filename from prefix and extension.
     *
     * @param string $prefix Filename prefix.
     * @param string $ext File extension without dot.
     * @return string
     */
    private static function build_unique_filename(string $prefix, string $ext): string {
        $suffix = time() . '-' . random_int(1000, 9999);
        return $prefix . '-' . $suffix . '.' . $ext;
    }
}
