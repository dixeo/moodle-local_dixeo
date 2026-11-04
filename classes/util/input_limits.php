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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_dixeo\util;

/**
 * Size bounds for input that is decoded, stored or sent to Dixeo.
 *
 * Sizes are checked before decoding, and the decoder depth is bounded, so an oversized or deeply
 * nested payload is refused before it costs memory. Plugins keep their own domain limits and
 * pass them in.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class input_limits {
    /** @var int Maximum bytes of a topic or activity title sent to Dixeo. */
    public const MAX_TITLE_BYTES = 255;

    /** @var int Maximum bytes of a learner's free-text request sent to Dixeo. */
    public const MAX_REQUEST_BYTES = 2000;

    /**
     * Decode a JSON array or object within size and depth bounds.
     *
     * @param string $json JSON text.
     * @param int $maxbytes Maximum length in bytes.
     * @param int $maxdepth Maximum nesting depth passed to the decoder.
     * @return array|null Decoded value, or null when larger than $maxbytes, deeper than $maxdepth,
     *     invalid, or not a JSON array or object.
     */
    public static function decode_json_array(string $json, int $maxbytes, int $maxdepth): ?array {
        if (strlen($json) > $maxbytes || $maxdepth < 1) {
            return null;
        }
        $decoded = json_decode($json, true, $maxdepth);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }
        return $decoded;
    }

    /**
     * Decode a JSON array or object within size and depth bounds, or stop.
     *
     * @param string $json JSON text.
     * @param int $maxbytes Maximum length in bytes.
     * @param int $maxdepth Maximum nesting depth passed to the decoder.
     * @param string $component Component owning the error strings.
     * @param string $toolargekey Error string when the text is larger than $maxbytes; receives $maxbytes.
     * @param string $invalidkey Error string when the text does not decode to an array or object.
     * @return array Decoded value.
     * @throws \moodle_exception $toolargekey or $invalidkey in $component.
     */
    public static function require_json_array(
        string $json,
        int $maxbytes,
        int $maxdepth,
        string $component,
        string $toolargekey,
        string $invalidkey
    ): array {
        if (strlen($json) > $maxbytes) {
            throw new \moodle_exception($toolargekey, $component, '', $maxbytes);
        }
        $decoded = self::decode_json_array($json, $maxbytes, $maxdepth);
        if ($decoded === null) {
            throw new \moodle_exception($invalidkey, $component);
        }
        return $decoded;
    }

    /**
     * Whether a decoded value stays within item count, string size and nesting depth.
     *
     * @param mixed $value Decoded JSON value.
     * @param int $maxitems Maximum entries of every array.
     * @param int $maxstringbytes Maximum bytes of every string.
     * @param int $maxdepth Maximum nesting depth; the top-level value is depth 0.
     * @return bool
     */
    public static function is_bounded($value, int $maxitems, int $maxstringbytes, int $maxdepth): bool {
        return self::bounded_at($value, $maxitems, $maxstringbytes, $maxdepth, 0);
    }

    /**
     * Stop when a value is longer than a byte limit.
     *
     * @param string $value Value to check.
     * @param int $maxbytes Maximum length in bytes.
     * @param string $field Field name used in the error.
     * @return void
     * @throws \invalid_parameter_exception "<field> is too long".
     */
    public static function require_max_bytes(string $value, int $maxbytes, string $field): void {
        if (strlen($value) > $maxbytes) {
            throw new \invalid_parameter_exception($field . ' is too long');
        }
    }

    /**
     * Stop when a text is longer than a character limit.
     *
     * @param string $value Text to check.
     * @param int $maxchars Maximum number of characters.
     * @param string $errorkey Error string; receives $maxchars.
     * @param string $component Component owning $errorkey.
     * @return void
     * @throws \moodle_exception $errorkey in $component.
     */
    public static function require_max_chars(string $value, int $maxchars, string $errorkey, string $component): void {
        if (\core_text::strlen($value) > $maxchars) {
            throw new \moodle_exception($errorkey, $component, '', $maxchars);
        }
    }

    /**
     * Recursive bound check.
     *
     * @param mixed $value Decoded JSON value.
     * @param int $maxitems Maximum entries of every array.
     * @param int $maxstringbytes Maximum bytes of every string.
     * @param int $maxdepth Maximum nesting depth.
     * @param int $depth Current depth.
     * @return bool
     */
    private static function bounded_at($value, int $maxitems, int $maxstringbytes, int $maxdepth, int $depth): bool {
        if ($depth > $maxdepth) {
            return false;
        }
        if (is_string($value)) {
            return strlen($value) <= $maxstringbytes;
        }
        if (!is_array($value)) {
            return true;
        }
        if (count($value) > $maxitems) {
            return false;
        }
        foreach ($value as $item) {
            if (!self::bounded_at($item, $maxitems, $maxstringbytes, $maxdepth, $depth + 1)) {
                return false;
            }
        }
        return true;
    }
}
