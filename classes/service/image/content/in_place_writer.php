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
 * The owner is always passed explicitly, so a replacement never depends on the session user
 * and works the same in a web request, an adhoc task or CLI.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class in_place_writer {
    /** @var int Attempts at a free draft item id before giving up. */
    private const DRAFT_ITEMID_ATTEMPTS = 20;

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
        $file = $location->get_stored_file();
        if (!$file) {
            throw new \moodle_exception('dixeo_image_not_eligible', 'local_dixeo');
        }
        self::replace_content($file, $binary, $userid, $mimetype);
    }

    /**
     * Replace the bytes of a stored file in place, keeping its record and id.
     *
     * The new bytes pass through a temporary file in the owner's draft area, which is deleted
     * afterwards. The MIME type is updated when it changes.
     *
     * @param \stored_file $file File to overwrite.
     * @param string $binary New content.
     * @param int $userid Owner of the temporary draft file and of the change.
     * @param string|null $mimetype MIME type of $binary, or null to detect it from the bytes.
     * @return void
     */
    public static function replace_content(\stored_file $file, string $binary, int $userid, ?string $mimetype = null): void {
        global $DB;

        $fs = get_file_storage();
        $usercontext = \context_user::instance($userid);
        $temp = $fs->create_file_from_string([
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => self::unused_draft_itemid($userid),
            'filepath' => '/',
            'filename' => 'dixeo-replace-' . time() . '.bin',
            'userid' => $userid,
        ], $binary);
        $file->replace_file_with($temp);
        $temp->delete();

        $mimetype ??= self::detect_mimetype($binary, $file->get_filename());
        if ($mimetype !== $file->get_mimetype()) {
            $DB->set_field('files', 'mimetype', $mimetype, ['id' => $file->get_id()]);
        }
    }

    /**
     * Free draft item id in the user's draft area.
     *
     * Core's file_get_unused_draft_itemid() allocates for the session user, so it is not used here.
     *
     * @param int $userid Draft area owner.
     * @return int
     * @throws \moodle_exception When no free id is found.
     */
    private static function unused_draft_itemid(int $userid): int {
        $fs = get_file_storage();
        $contextid = \context_user::instance($userid)->id;
        for ($attempt = 0; $attempt < self::DRAFT_ITEMID_ATTEMPTS; $attempt++) {
            $itemid = random_int(100000, 999999999);
            if (!$fs->file_exists($contextid, 'user', 'draft', $itemid, '/', '.')) {
                return $itemid;
            }
        }
        throw new \moodle_exception('dixeo_image_job_failed', 'local_dixeo');
    }

    /**
     * MIME type of $binary, from its content, else from the file name.
     *
     * @param string $binary File content.
     * @param string $filename File name used when the content is not recognised.
     * @return string
     */
    private static function detect_mimetype(string $binary, string $filename): string {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $fromcontent = $finfo->buffer($binary);
        if (is_string($fromcontent) && $fromcontent !== '' && $fromcontent !== 'application/octet-stream') {
            return $fromcontent;
        }
        return \file_storage::mimetype('content://', $filename) ?: 'application/octet-stream';
    }
}
