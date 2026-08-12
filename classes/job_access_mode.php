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

namespace local_dixeo;

// phpcs:disable moodle.Commenting.InlineComment.DocBlock -- Older moodle-cs omits T_ENUM from allowed docblock targets.
/**
 * Access mode stored on local_dixeo_jobs and enforced by job_service.
 *
 * Default is initiator-scoped. Course-shared is opt-in and persisted on the
 * job row at registration.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum job_access_mode: string {
    // phpcs:enable moodle.Commenting.InlineComment.DocBlock

    // Default. Job must match courseid and initiating userid.
    case INITIATOR_SCOPED = 'initiator_scoped';

    // Opt-in collaborative course work. Any user with course capability may
    // act if the job is bound to that course.
    case COURSE_SHARED = 'course_shared';

    /**
     * Parse a stored DB value; unknown or empty values fail closed to initiator.
     *
     * @param string|null $value Stored accessmode column.
     * @return self
     */
    public static function from_storage(?string $value): self {
        if ($value === self::COURSE_SHARED->value) {
            return self::COURSE_SHARED;
        }
        return self::INITIATOR_SCOPED;
    }
}
