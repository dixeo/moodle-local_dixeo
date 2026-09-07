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
 * Shared in-place stored_file replacement for content images.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class in_place_writer {
    /**
     * Replace bytes for an existing pluginfile location, keeping the file record (and id).
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
        global $DB;

        $file = $location->get_stored_file();
        if (!$file) {
            throw new \moodle_exception('dixeo_image_not_eligible', 'local_dixeo');
        }

        $fs = get_file_storage();
        $temp = $fs->create_file_from_string([
            'contextid' => $location->contextid,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => file_get_unused_draft_itemid(),
            'filepath' => '/',
            'filename' => 'dixeo-replace-' . time() . '.bin',
            'userid' => $userid,
        ], $binary);
        $file->replace_file_with($temp);
        $temp->delete();

        if ($mimetype !== $file->get_mimetype()) {
            $DB->set_field('files', 'mimetype', $mimetype, ['id' => $file->get_id()]);
        }
    }
}
