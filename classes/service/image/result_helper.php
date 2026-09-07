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
 * Shared helpers for Dixeo image API job result payloads.
 *
 * Used by structure images (course/section), content images, and filter version history.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class result_helper {
    /**
     * Extract raw image bytes from an API result payload.
     *
     * @param array $result
     * @return string
     */
    public static function extract_image_binary_from_result(array $result): string {
        $base64 = '';
        if (!empty($result['image_base64']) && is_string($result['image_base64'])) {
            $base64 = $result['image_base64'];
        }
        $base64 = trim($base64);
        if ($base64 === '') {
            return '';
        }
        if (strpos($base64, 'base64,') !== false) {
            $parts = explode('base64,', $base64, 2);
            $base64 = $parts[1];
        }
        $binary = base64_decode($base64, true);
        if ($binary === false) {
            return '';
        }
        return $binary;
    }

    /**
     * Detect image MIME type from raw bytes.
     *
     * @param string $binary
     * @return string
     */
    public static function detect_image_mimetype(string $binary): string {
        if (strncmp($binary, "\xFF\xD8\xFF", 3) === 0) {
            return 'image/jpeg';
        }
        if (strncmp($binary, "\x89PNG\r\n\x1a\n", 8) === 0) {
            return 'image/png';
        }
        if (strncmp($binary, 'GIF87a', 6) === 0 || strncmp($binary, 'GIF89a', 6) === 0) {
            return 'image/gif';
        }
        if (strncmp($binary, 'RIFF', 4) === 0 && substr($binary, 8, 4) === 'WEBP') {
            return 'image/webp';
        }
        return 'image/png';
    }
}
