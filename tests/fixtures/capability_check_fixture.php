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

use core_external\external_api;
use local_dixeo\external\traits\capability_check;

/**
 * Exposes the capability_check trait methods to tests.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class capability_check_fixture extends external_api {
    use capability_check;

    /**
     * Run validate_course_capability().
     *
     * @param int $courseid Course id.
     * @param bool $requiremanageactivities Whether moodle/course:manageactivities is required.
     * @param bool $requireconsent Whether the current Terms of Use are required.
     * @return \context_course
     */
    public static function course(
        int $courseid,
        bool $requiremanageactivities = false,
        bool $requireconsent = true
    ): \context_course {
        return self::validate_course_capability($courseid, $requiremanageactivities, $requireconsent);
    }

    /**
     * Run validate_course_sync_capability().
     *
     * @param int $courseid Course id.
     * @param bool $requireconsent Whether the current Terms of Use are required.
     * @return \context_course
     */
    public static function sync(int $courseid, bool $requireconsent = true): \context_course {
        return self::validate_course_sync_capability($courseid, $requireconsent);
    }
}
