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

namespace local_dixeo\service\image\structure;


use local_dixeo\service\image\image_target;

/**
 * Target identity for course overview and format section images.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class structure_target implements image_target {
    /** @var string */
    private string $kind;
    /** @var int */
    private int $courseid;
    /** @var int */
    private int $objectid;

    /**
     *   construct.
     * @param string $kind course_overview|format_section
     * @param int $courseid
     * @param int $objectid Course id (overview) or section id (format_section).
     */
    public function __construct(string $kind, int $courseid, int $objectid) {
        if (!scope::is_valid($kind)) {
            throw new \coding_exception('Invalid structure target kind: ' . $kind);
        }
        if ($courseid < 1) {
            throw new \invalid_parameter_exception('Invalid course id');
        }
        if ($objectid < 1) {
            throw new \invalid_parameter_exception('Invalid object id');
        }
        $this->kind = $kind;
        $this->courseid = $courseid;
        $this->objectid = $objectid;
    }

    /**
     * Course overview.
     * @param int $courseid
     * @return self
     */
    public static function course_overview(int $courseid): self {
        return new self(self::KIND_COURSE_OVERVIEW, $courseid, $courseid);
    }

    /**
     * Format section.
     * @param int $courseid
     * @param int $sectionid course_sections.id
     * @return self
     */
    public static function format_section(int $courseid, int $sectionid): self {
        return new self(self::KIND_FORMAT_SECTION, $courseid, $sectionid);
    }

    /**
     * Get target kind.
     */
    public function get_target_kind(): string {
        return $this->kind;
    }

    /**
     * Get location hash.
     */
    public function get_location_hash(): string {
        if ($this->kind === self::KIND_COURSE_OVERVIEW) {
            return sha1('course_overview:' . $this->courseid);
        }
        return sha1('format_section:' . $this->objectid);
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
        return $this->objectid;
    }

    /**
     * To job fields.
     */
    public function to_job_fields(): array {
        return [
            'target_kind' => $this->kind,
            'objectid' => $this->objectid,
            'locationhash' => $this->get_location_hash(),
            'courseid' => $this->courseid,
            'placeholderid' => null,
            'contextid' => 0,
            'component' => '',
            'filearea' => '',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => '',
            'targettable' => null,
            'targetfield' => null,
            'targetid' => null,
            'cmid' => null,
            'origin' => 'structure',
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
            'target_kind' => $this->kind,
            'courseid' => $this->courseid,
            'objectid' => $this->objectid,
            'locationhash' => $this->get_location_hash(),
        ];
    }

    /**
     * Get scope.
     * @return string Scope constant for writer::apply_from_job_result.
     */
    public function get_scope(): string {
        return $this->kind;
    }
}
