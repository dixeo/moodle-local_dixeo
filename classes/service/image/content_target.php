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


use local_dixeo\service\image\content\location;

/**
 * image_target wrapper for pluginfile content locations.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class content_target implements image_target {
    /** @var location */
    private location $location;

    /**
     *   construct.
     * @param location $location
     */
    private function __construct(location $location) {
        $this->location = $location;
    }

    /**
     * From location.
     * @param location $location
     * @return self
     */
    public static function from_location(location $location): self {
        return new self($location);
    }

    /**
     * Get location.
     * @return location
     */
    public function get_location(): location {
        return $this->location;
    }

    /**
     * Get target kind.
     */
    public function get_target_kind(): string {
        return self::KIND_CONTENT;
    }

    /**
     * Get location hash.
     */
    public function get_location_hash(): string {
        return $this->location->hash();
    }

    /**
     * Get courseid.
     */
    public function get_courseid(): int {
        return $this->location->courseid;
    }

    /**
     * Get objectid.
     */
    public function get_objectid(): int {
        return 0;
    }

    /**
     * To job fields.
     */
    public function to_job_fields(): array {
        return array_merge($this->location->to_record_fields(), [
            'target_kind' => self::KIND_CONTENT,
            'objectid' => null,
        ]);
    }

    /**
     * To poll custom data.
     */
    public function to_poll_custom_data(): array {
        return array_merge($this->location->to_record_fields(), [
            'target_kind' => self::KIND_CONTENT,
        ]);
    }
}
