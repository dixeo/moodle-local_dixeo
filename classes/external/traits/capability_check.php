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

/**
 * Trait for shared capability checking in external API classes.
 *
 * Validates course context and local/dixeo capabilities (CONTEXT_COURSE).
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\external\traits;

use local_dixeo\external\service_factory;

/**
 * Trait providing capability checking for external API endpoints.
 */
trait capability_check {
    /**
     * Validate course context and check required capabilities.
     *
     * Use this for endpoints that operate on specific courses.
     *
     * @param int $courseid The course ID.
     * @param bool $requiremanageactivities Whether to also check moodle/course:manageactivities.
     * @return \context_course The validated course context.
     * @throws \invalid_parameter_exception If course id is invalid.
     * @throws \required_capability_exception If capability check fails.
     * @throws \moodle_exception If Dixeo is turned off for the course.
     */
    protected static function validate_course_capability(
        int $courseid,
        bool $requiremanageactivities = false
    ): \context_course {
        if ($courseid <= 1) {
            throw new \invalid_parameter_exception('Invalid course id');
        }

        $coursecontext = \context_course::instance($courseid);
        self::validate_context($coursecontext);
        require_capability('local/dixeo:generate', $coursecontext);

        if ($requiremanageactivities) {
            require_capability('moodle/course:manageactivities', $coursecontext);
        }

        service_factory::get_settings_config()->require_enabled($coursecontext);

        return $coursecontext;
    }

    /**
     * Validate course context and require local/dixeo:syncfiles.
     *
     * Use for enable/disable/trigger of external course file transfer.
     *
     * @param int $courseid The course ID.
     * @return \context_course The validated course context.
     * @throws \invalid_parameter_exception If course id is invalid.
     * @throws \required_capability_exception If capability check fails.
     * @throws \moodle_exception If Dixeo is turned off for the course.
     */
    protected static function validate_course_sync_capability(int $courseid): \context_course {
        if ($courseid <= 1) {
            throw new \invalid_parameter_exception('Invalid course id');
        }

        $coursecontext = \context_course::instance($courseid);
        self::validate_context($coursecontext);
        require_capability('local/dixeo:syncfiles', $coursecontext);
        service_factory::get_settings_config()->require_enabled($coursecontext);

        return $coursecontext;
    }
}
