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
 * Cache-busting helpers for pluginfile image URLs.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class url_helper {
    /**
     * Append a stable cache-busting query param derived from file contenthash.
     *
     * @param string $url Image src URL (may already include a rev param).
     * @param string $contenthash Stored file contenthash.
     * @return string
     */
    public static function append_image_rev(string $url, string $contenthash): string {
        if ($contenthash === '') {
            return $url;
        }

        $url = preg_replace('/([?&])rev=[^&]*/', '', $url) ?? $url;
        $url = rtrim($url, '?&');
        $separator = (strpos($url, '?') !== false) ? '&' : '?';

        return $url . $separator . 'rev=' . rawurlencode($contenthash);
    }

    /**
     * Get current image url.
     * @param location $location
     * @return string
     */
    public static function get_current_image_url(location $location): string {
        $file = $location->get_stored_file();
        $contenthash = $file ? $file->get_contenthash() : '';
        return self::append_image_rev($location->get_pluginfile_url(), $contenthash);
    }
}
