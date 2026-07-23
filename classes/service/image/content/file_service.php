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


use local_dixeo\service\image\result_helper;

/**
 * Stub creation and in-place file replacement for content images.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class file_service {
    /** @var string Filename prefix for generated stub files. */
    public const STUB_FILENAME_PREFIX = 'dixeo-gen-';

    /**
     * Create a pending stub file in the html_field_target filearea.
     *
     * @param location $location
     * @param int $userid
     * @return \stored_file
     */
    public static function create_stub(location $location, int $userid): \stored_file {
        $fs = get_file_storage();
        $existing = $location->get_stored_file();
        if ($existing) {
            $existing->delete();
        }

        $stored = $fs->create_file_from_string([
            'contextid' => $location->contextid,
            'component' => $location->component,
            'filearea' => $location->filearea,
            'itemid' => $location->itemid,
            'filepath' => $location->filepath,
            'filename' => $location->filename,
            'userid' => $userid,
            'mimetype' => 'image/png',
        ], asset_helper::get_placeholder_binary());

        return $stored;
    }

    /**
     * Replace file bytes in-place without version history.
     *
     * @param location $location
     * @param string $binary
     * @param int $userid
     * @param string $mimetype
     * @return void
     */
    public static function replace_binary(
        location $location,
        string $binary,
        int $userid,
        string $mimetype = 'image/png'
    ): void {
        in_place_writer::replace_binary($location, $binary, $userid, $mimetype);
    }

    /**
     * Apply job result.
     * @param location $location
     * @param array $jobresult
     * @param int $userid
     * @return void
     */
    public static function apply_job_result(location $location, array $jobresult, int $userid): void {
        $binary = result_helper::extract_image_binary_from_result($jobresult);
        if ($binary === '') {
            throw new \moodle_exception('dixeo_image_job_empty_result', 'local_dixeo');
        }
        self::replace_binary($location, $binary, $userid, result_helper::detect_image_mimetype($binary));
    }

    /**
     * Apply failed placeholder.
     * @param location $location
     * @param int $userid
     * @return void
     */
    public static function apply_failed_placeholder(location $location, int $userid): void {
        self::replace_binary($location, asset_helper::get_error_binary(), $userid);
    }

    /**
     * Stub filename for placeholder.
     * @param string $placeholderid
     * @return string
     */
    public static function stub_filename_for_placeholder(string $placeholderid): string {
        return self::STUB_FILENAME_PREFIX . $placeholderid . '.png';
    }

    /**
     * Is generated stub filename.
     * @param string $filename
     * @return bool
     */
    public static function is_generated_stub_filename(string $filename): bool {
        return str_starts_with($filename, self::STUB_FILENAME_PREFIX);
    }

    /**
     * Extract the placeholder UUID from a generated stub filename.
     *
     * @param string $filename Stored filename (e.g. dixeo-gen-{uuid}.png).
     * @return string|null Placeholder id or null when not a stub filename.
     */
    public static function placeholderid_from_stub_filename(string $filename): ?string {
        if (!self::is_generated_stub_filename($filename)) {
            return null;
        }

        $uuid = substr($filename, strlen(self::STUB_FILENAME_PREFIX), -4);
        if ($uuid === '' || !preg_match('/^[a-f0-9-]+$/i', $uuid)) {
            return null;
        }

        return $uuid;
    }
}
