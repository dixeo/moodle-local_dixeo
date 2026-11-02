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
 * Image target for one card of an H5P flashcards activity.
 *
 * The image lives inside the activity package, not in a file area of its own,
 * so the target is the activity and the card index. The row stores the cmid in
 * objectid and the card index in itemid.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service\image;

/**
 * Target identity for an H5P flashcard image.
 */
final class h5p_card_target implements image_target {
    /**
     * Constructor.
     *
     * @param int $courseid Course id.
     * @param int $cmid h5pactivity course module id.
     * @param int $cardindex 0-based index into content.cards.
     */
    public function __construct(
        /** @var int Course id. */
        private readonly int $courseid,
        /** @var int h5pactivity course module id. */
        private readonly int $cmid,
        /** @var int 0-based index into content.cards. */
        private readonly int $cardindex
    ) {
        if ($courseid < 1 || $cmid < 1 || $cardindex < 0) {
            throw new \invalid_parameter_exception('Invalid H5P card target');
        }
    }

    /**
     * Get cmid.
     * @return int
     */
    public function get_cmid(): int {
        return $this->cmid;
    }

    /**
     * Get card index.
     * @return int
     */
    public function get_cardindex(): int {
        return $this->cardindex;
    }

    /**
     * Get target kind.
     */
    public function get_target_kind(): string {
        return self::KIND_H5P_CARD;
    }

    /**
     * Get location hash.
     */
    public function get_location_hash(): string {
        return sha1(self::KIND_H5P_CARD . ':' . $this->cmid . ':' . $this->cardindex);
    }

    /**
     * Get courseid.
     */
    public function get_courseid(): int {
        return $this->courseid;
    }

    /**
     * Get objectid.
     */
    public function get_objectid(): int {
        return $this->cmid;
    }

    /**
     * To job fields.
     */
    public function to_job_fields(): array {
        $context = \context_module::instance($this->cmid, IGNORE_MISSING);

        return [
            'target_kind' => self::KIND_H5P_CARD,
            'objectid' => $this->cmid,
            'locationhash' => $this->get_location_hash(),
            'courseid' => $this->courseid,
            'placeholderid' => null,
            'contextid' => $context ? (int) $context->id : 0,
            'component' => 'mod_h5pactivity',
            'filearea' => 'package',
            'itemid' => $this->cardindex,
            'filepath' => '/',
            'filename' => '',
            'targettable' => null,
            'targetfield' => null,
            'targetid' => null,
            'cmid' => $this->cmid,
            'origin' => self::KIND_H5P_CARD,
            'prompt' => null,
            'quality' => null,
            'mode' => null,
        ];
    }

    /**
     * To poll custom data.
     */
    public function to_poll_custom_data(): array {
        return [
            'target_kind' => self::KIND_H5P_CARD,
            'courseid' => $this->courseid,
            'objectid' => $this->cmid,
            'cardindex' => $this->cardindex,
            'locationhash' => $this->get_location_hash(),
        ];
    }
}
