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
 * Size bounds for images stored in course content and for images sent to Dixeo for editing.
 *
 * Display bounds keep every participant's browser from decoding a decompression bomb. Edit
 * source bounds mirror the Dixeo image edit contract, so an image the API would refuse is
 * never read or uploaded.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class image_bounds {
    /** @var int Stored display image: maximum pixels per side. */
    public const MAX_DIMENSION = 8000;

    /** @var int Stored display image: maximum total pixels (40 MP). */
    public const MAX_PIXELS = 40000000;

    /** @var int Dixeo image edit source: maximum bytes (4 MiB). */
    public const SOURCE_MAX_BYTES = 4194304;

    /** @var int Dixeo image edit source: maximum pixels per side. */
    public const SOURCE_MAX_DIMENSION = 4096;

    /** @var int Dixeo image edit source: maximum total pixels (8 MP). */
    public const SOURCE_MAX_PIXELS = 8388608;

    /** @var string[] Dixeo image edit source: accepted MIME types. */
    public const SOURCE_MIMETYPES = ['image/png', 'image/jpeg', 'image/webp'];

    /**
     * Whether an image of this size may be stored for display.
     *
     * @param int $width Width in pixels.
     * @param int $height Height in pixels.
     * @return bool
     */
    public static function within_display_bounds(int $width, int $height): bool {
        return $width >= 1 && $height >= 1
            && $width <= self::MAX_DIMENSION && $height <= self::MAX_DIMENSION
            && $width * $height <= self::MAX_PIXELS;
    }

    /**
     * Stop when an image of this size may not be stored for display.
     *
     * @param int $width Width in pixels.
     * @param int $height Height in pixels.
     * @return void
     * @throws \moodle_exception error:image_dimensions, with $a = {dimension, megapixels}.
     */
    public static function require_display_size(int $width, int $height): void {
        if (!self::within_display_bounds($width, $height)) {
            throw new \moodle_exception('error:image_dimensions', 'local_dixeo', '', (object) [
                'dimension' => self::MAX_DIMENSION,
                'megapixels' => intdiv(self::MAX_PIXELS, 1000000),
            ]);
        }
    }

    /**
     * Base64 content of a stored image that Dixeo accepts as an edit source.
     *
     * Type and byte size are checked from the file record before any content is read.
     *
     * @param \stored_file $file Image to send.
     * @return string Raw base64, with no data URI prefix.
     * @throws \moodle_exception error:image_edit_source, with $a = {size, dimension}.
     */
    public static function encode_edit_source(\stored_file $file): string {
        if (
            !in_array((string) $file->get_mimetype(), self::SOURCE_MIMETYPES, true)
            || $file->get_filesize() > self::SOURCE_MAX_BYTES
        ) {
            self::refuse_edit_source();
        }
        $info = $file->get_imageinfo();
        $width = is_array($info) ? (int) ($info['width'] ?? 0) : 0;
        $height = is_array($info) ? (int) ($info['height'] ?? 0) : 0;
        if (
            $width < 1 || $height < 1
            || $width > self::SOURCE_MAX_DIMENSION || $height > self::SOURCE_MAX_DIMENSION
            || $width * $height > self::SOURCE_MAX_PIXELS
        ) {
            self::refuse_edit_source();
        }
        return base64_encode($file->get_content());
    }

    /**
     * Refuse an image that is not a valid Dixeo edit source.
     *
     * @return never
     * @throws \moodle_exception error:image_edit_source.
     */
    private static function refuse_edit_source(): never {
        throw new \moodle_exception('error:image_edit_source', 'local_dixeo', '', (object) [
            'size' => display_size(self::SOURCE_MAX_BYTES),
            'dimension' => self::SOURCE_MAX_DIMENSION,
        ]);
    }
}
