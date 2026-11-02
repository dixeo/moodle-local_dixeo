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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Apply a generated image to one card of an H5P flashcards package.
 *
 * Card images of one activity complete in separate poll tasks, each rewriting
 * the same package, so the read-modify-write runs under a lock per activity.
 * An activity deleted or edited meanwhile is left alone: the card stays text-only.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service\image\apply;

use core\lock\lock_config;
use local_dixeo\service\h5p_package_archive;
use local_dixeo\service\h5p_packaging_service;
use local_dixeo\service\image\h5p_card_target;
use local_dixeo\service\image\result_helper;

/**
 * Writes a card image into the stored package and redeploys it.
 */
final class h5p_card_handler {
    /** @var int Seconds to wait for another card of the same activity. */
    private const LOCK_TIMEOUT_SECONDS = 60;

    /** @var array<string, string> Image MIME types H5P accepts as content files, with their extension. */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
    ];

    /**
     * Apply a completed image job result.
     *
     * @param h5p_card_target $target
     * @param array $result Image job result.
     * @return void
     * @throws \moodle_exception When the result has no usable image or the lock times out.
     */
    public static function apply(h5p_card_target $target, array $result): void {
        $binary = result_helper::extract_image_binary_from_result($result);
        if ($binary === '') {
            throw new \moodle_exception('dixeo_image_job_empty_result', 'local_dixeo');
        }
        $mimetype = result_helper::detect_image_mimetype($binary);
        if (!isset(self::EXTENSIONS[$mimetype])) {
            throw new \moodle_exception('invalidfiletype', 'error', '', $mimetype);
        }

        $cm = get_coursemodule_from_id('h5pactivity', $target->get_cmid(), 0, false, IGNORE_MISSING);
        if (!$cm || !empty($cm->deletioninprogress)) {
            return;
        }

        $lock = lock_config::get_lock_factory('local_dixeo_h5p_card')
            ->get_lock('cm' . $cm->id, self::LOCK_TIMEOUT_SECONDS);
        if (!$lock) {
            throw new \moodle_exception('locktimeout');
        }
        try {
            self::add_card_image((int) $cm->id, $target->get_cardindex(), $binary, $mimetype);
        } finally {
            $lock->release();
        }
    }

    /**
     * Add the image to the package and point the card at it.
     *
     * @param int $cmid
     * @param int $cardindex
     * @param string $binary Image bytes.
     * @param string $mimetype Image MIME type.
     * @return void
     */
    private static function add_card_image(int $cmid, int $cardindex, string $binary, string $mimetype): void {
        $context = \context_module::instance($cmid);
        $files = get_file_storage()->get_area_files($context->id, 'mod_h5pactivity', 'package', 0, 'id', false);
        $package = reset($files);
        if (!$package) {
            return;
        }

        // Same filename, so core_h5p redeploys the existing h5p row.
        $path = make_request_directory() . DIRECTORY_SEPARATOR . $package->get_filename();
        $package->copy_content_to($path);

        $content = json_decode((string) h5p_package_archive::read($path, h5p_package_archive::CONTENT_JSON), true);
        $card = $content['cards'][$cardindex] ?? null;
        if (!is_array($card) || !empty($card['image'])) {
            return;
        }

        $size = getimagesizefromstring($binary) ?: [0, 0];
        $imagepath = 'images/card-' . $cardindex . '.' . self::EXTENSIONS[$mimetype];
        $content['cards'][$cardindex]['image'] = [
            'path' => $imagepath,
            'mime' => $mimetype,
            'copyright' => ['license' => 'U'],
            'width' => (int) $size[0],
            'height' => (int) $size[1],
        ];

        h5p_package_archive::write($path, [
            'content/' . $imagepath => $binary,
            h5p_package_archive::CONTENT_JSON => json_encode($content, h5p_package_archive::JSON_FLAGS),
        ]);
        (new h5p_packaging_service())->replace_package($cmid, $path);
    }
}
