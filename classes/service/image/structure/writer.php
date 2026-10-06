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
 * Writes async image job results into course overview and format section images.
 *
 * Section images are stored for the course's current format: format_dixeo chapter
 * images, or format_tiles tile photos (file area plus the filename the format reads).
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
     * Store a section image for the course format that will display it.
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
        $format = (string) $DB->get_field('course', 'format', ['id' => $courseid], IGNORE_MISSING);

        if ($format === 'tiles') {
            self::apply_binary_to_tiles_section($sectionid, $courseid, $binary, $userid);
            return;
        }

        self::apply_binary_to_dixeo_section($sectionid, $courseid, $binary, $userid);
    }

    /**
     * Store a chapter cover image for a format_dixeo course section.
     *
     * @param int $sectionid course_sections.id
     * @param int $courseid
     * @param string $binary Raw image bytes.
     * @param int $userid
     * @return void
     */
    private static function apply_binary_to_dixeo_section(
        int $sectionid,
        int $courseid,
        string $binary,
        int $userid
    ): void {
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

        self::refresh_course_after_section_image($courseid);
    }

    /**
     * Store a tile photo where format_tiles reads section images.
     *
     * File API matches {@see \format_tiles\local\tile_photo::file_api_params()}:
     * component format_tiles, filearea tilephoto, filepath /tilephoto/, itemid = section id.
     * The filename is also recorded as the section photo option so the tile renders it.
     *
     * @param int $sectionid course_sections.id
     * @param int $courseid
     * @param string $binary Raw image bytes.
     * @param int $userid
     * @return void
     */
    private static function apply_binary_to_tiles_section(
        int $sectionid,
        int $courseid,
        string $binary,
        int $userid
    ): void {
        $context = context_course::instance($courseid, MUST_EXIST);
        require_capability('moodle/course:update', $context);

        [$ext, $mimetype] = self::resolve_allowed_type($binary);
        $filename = self::build_unique_filename('tile-photo-' . $sectionid, $ext);
        $params = self::tiles_file_api_params();

        // Legacy tile_photo deletes stored photos in its constructor when no option is set.
        $legacyphoto = null;
        if (!class_exists(\format_tiles\local\tile_photo::class) && class_exists(\format_tiles\tile_photo::class)) {
            $legacyphoto = new \format_tiles\tile_photo($courseid, $sectionid);
        }

        $record = [
            'contextid' => $context->id,
            'component' => $params['component'],
            'filearea' => $params['filearea'],
            'itemid' => $sectionid,
            'filepath' => $params['filepath'],
            'filename' => $filename,
            'userid' => $userid,
            'mimetype' => $mimetype,
        ];

        $fs = get_file_storage();
        $fs->delete_area_files($context->id, $params['component'], $params['filearea'], $sectionid);
        $file = $fs->create_file_from_string($record, $binary);

        if (class_exists(\format_tiles\local\tile_photo::class)) {
            $photo = new \format_tiles\local\tile_photo($context, $sectionid);
            $photo->set_file($file);
        } else if ($legacyphoto !== null) {
            $legacyphoto->set_file($file);
        } else {
            self::register_tiles_section_photo_records($courseid, $sectionid, $file->get_filename());
        }

        self::refresh_course_after_section_image($courseid);
    }

    /**
     * File-storage coordinates used by format_tiles tile photos.
     *
     * @return array{component:string,filearea:string,filepath:string}
     */
    private static function tiles_file_api_params(): array {
        if (class_exists(\format_tiles\local\tile_photo::class)) {
            $params = \format_tiles\local\tile_photo::file_api_params();
            return [
                'component' => (string) $params['component'],
                'filearea' => (string) $params['filearea'],
                'filepath' => (string) $params['filepath'],
            ];
        }
        if (class_exists(\format_tiles\tile_photo::class)) {
            $params = \format_tiles\tile_photo::file_api_params();
            return [
                'component' => (string) $params['component'],
                'filearea' => (string) $params['filearea'],
                'filepath' => (string) $params['filepath'],
            ];
        }
        return [
            'component' => 'format_tiles',
            'filearea' => 'tilephoto',
            'filepath' => '/tilephoto/',
        ];
    }

    /**
     * Record the tile photo filename for formats that are not loaded in this request.
     *
     * Writes the legacy course format option (name tilephoto) and, when the current
     * format_tiles table exists, the section photo row (optiontype 1).
     *
     * @param int $courseid
     * @param int $sectionid course_sections.id
     * @param string $filename Stored file name.
     * @return void
     */
    private static function register_tiles_section_photo_records(int $courseid, int $sectionid, string $filename): void {
        global $DB;

        $params = [
            'courseid' => $courseid,
            'format' => 'tiles',
            'sectionid' => $sectionid,
            'name' => 'tilephoto',
        ];
        $existing = $DB->get_record('course_format_options', $params, '*', IGNORE_MISSING);
        if ($existing) {
            $existing->value = $filename;
            $DB->update_record('course_format_options', $existing);
        } else {
            $record = (object) $params;
            $record->value = $filename;
            $DB->insert_record('course_format_options', $record);
        }

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('format_tiles_tile_options')) {
            return;
        }

        // Option types match format_tiles\local\format_option: 1 photo, 3 icon.
        // A photo replaces an icon for the same section.
        $DB->delete_records('format_tiles_tile_options', [
            'courseid' => $courseid,
            'elementid' => $sectionid,
            'optiontype' => 3,
        ]);
        $DB->delete_records('format_tiles_tile_options', [
            'courseid' => $courseid,
            'elementid' => $sectionid,
            'optiontype' => 1,
        ]);
        $DB->insert_record('format_tiles_tile_options', (object) [
            'courseid' => $courseid,
            'elementid' => $sectionid,
            'optiontype' => 1,
            'optionvalue' => $filename,
        ]);

        try {
            \cache::make('format_tiles', 'formatoptions')->purge();
            \cache::make('format_tiles', 'formatoptionelementids')->purge();
        } catch (\Throwable $e) {
            // Cache definitions are registered only when format_tiles is installed.
            debugging(
                'format_tiles option caches were not purged: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }

    /**
     * Drop cached course images after a section image is replaced.
     *
     * @param int $courseid
     * @return void
     */
    private static function refresh_course_after_section_image(int $courseid): void {
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
