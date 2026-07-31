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
 * Unified identity for content and structure image jobs.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface image_target {
    /** @var string Constant KIND_CONTENT. */
    public const KIND_CONTENT = 'content';
    /** @var string Constant KIND_COURSE_OVERVIEW. */
    public const KIND_COURSE_OVERVIEW = 'course_overview';
    /** @var string Constant KIND_FORMAT_SECTION. */
    public const KIND_FORMAT_SECTION = 'format_section';

    /**
     * Get target kind.
     * @return string One of the KIND_* constants.
     */
    public function get_target_kind(): string;

    /**
     * Stable dedupe hash stored in local_dixeo_image_job.locationhash.
     *
     * @return string
     */
    public function get_location_hash(): string;

    /**
     * Get courseid.
     * @return int
     */
    public function get_courseid(): int;

    /**
     * Course or section id for structure targets; 0 for content.
     *
     * @return int
     */
    public function get_objectid(): int;

    /**
     * To job fields.
     * @return array<string, mixed> Fields for job_repository upsert (excluding jobid/status/userid).
     */
    public function to_job_fields(): array;

    /**
     * To poll custom data.
     * @return array<string, mixed> Custom data for adhoc poll tasks.
     */
    public function to_poll_custom_data(): array;
}
