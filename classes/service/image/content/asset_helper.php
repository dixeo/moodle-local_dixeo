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
 * Bundled PNG assets for content image generation states.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class asset_helper {
    /** @var string Constant PLACEHOLDER_FILE. */
    private const PLACEHOLDER_FILE = 'placeholder.png';
    /** @var string Constant ERROR_FILE. */
    private const ERROR_FILE = 'error.png';

    /** @var string|null */
    private static $placeholderbinary = null;

    /** @var string|null */
    private static $errorbinary = null;

    /**
     * PNG bytes shown while an [img-gen] job is pending.
     *
     * @return string
     */
    public static function get_placeholder_binary(): string {
        if (self::$placeholderbinary === null) {
            self::$placeholderbinary = self::load_plugin_image(self::PLACEHOLDER_FILE);
        }
        return self::$placeholderbinary;
    }

    /**
     * PNG bytes shown when generation fails.
     *
     * @return string
     */
    public static function get_error_binary(): string {
        if (self::$errorbinary === null) {
            self::$errorbinary = self::load_plugin_image(self::ERROR_FILE);
        }
        return self::$errorbinary;
    }

    /**
     * Load plugin image.
     * @param string $filename Basename under local/dixeo/img/.
     * @return string
     */
    private static function load_plugin_image(string $filename): string {
        global $CFG;

        $path = $CFG->dirroot . '/local/dixeo/img/' . $filename;
        if (!is_readable($path)) {
            throw new \coding_exception('Missing Dixeo image asset: ' . $filename);
        }

        $binary = file_get_contents($path);
        if ($binary === false || $binary === '') {
            throw new \coding_exception('Unreadable Dixeo image asset: ' . $filename);
        }

        return $binary;
    }
}
