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
use local_dixeo\service\consent_service;

/**
 * Trait providing capability checking for external API endpoints.
 *
 * Terms of Use gate: an endpoint that starts or advances Dixeo processing, or stores input
 * that will be sent, keeps the default $requireconsent = true. An endpoint that only reads
 * status or a catalogue, or that stops, cancels, turns off or deletes, passes false, so a
 * user who withdrew acceptance can still stop processing and remove data.
 */
trait capability_check {
    /**
     * Validate course context and check required capabilities.
     *
     * Use this for endpoints that operate on specific courses. Checks run in this order:
     * course id, context, capabilities, Dixeo enabled for the course, then the Terms of Use.
     *
     * @param int $courseid The course ID.
     * @param bool $requiremanageactivities Whether to also check moodle/course:manageactivities.
     * @param bool $requireconsent Whether the acting user must have accepted the current Terms of Use.
     * @return \context_course The validated course context.
     * @throws \invalid_parameter_exception If course id is invalid.
     * @throws \required_capability_exception If capability check fails.
     * @throws \moodle_exception error:dixeo_disabled when Dixeo is turned off for the course,
     *     error:consentrequired when the current Terms of Use are not accepted.
     */
    protected static function validate_course_capability(
        int $courseid,
        bool $requiremanageactivities = false,
        bool $requireconsent = true
    ): \context_course {
        global $USER;

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

        if ($requireconsent) {
            (new consent_service())->require_accepted((int) $USER->id, $coursecontext);
        }

        return $coursecontext;
    }

    /**
     * Validate course context and require local/dixeo:syncfiles.
     *
     * Use for enable/disable/trigger of external course file transfer. Checks run in this order:
     * course id, context, capability, Dixeo enabled for the course, then the Terms of Use.
     *
     * @param int $courseid The course ID.
     * @param bool $requireconsent Whether the acting user must have accepted the current Terms of Use.
     * @return \context_course The validated course context.
     * @throws \invalid_parameter_exception If course id is invalid.
     * @throws \required_capability_exception If capability check fails.
     * @throws \moodle_exception error:dixeo_disabled when Dixeo is turned off for the course,
     *     error:consentrequired when the current Terms of Use are not accepted.
     */
    protected static function validate_course_sync_capability(int $courseid, bool $requireconsent = true): \context_course {
        global $USER;

        if ($courseid <= 1) {
            throw new \invalid_parameter_exception('Invalid course id');
        }

        $coursecontext = \context_course::instance($courseid);
        self::validate_context($coursecontext);
        require_capability('local/dixeo:syncfiles', $coursecontext);
        service_factory::get_settings_config()->require_enabled($coursecontext);

        if ($requireconsent) {
            (new consent_service())->require_accepted((int) $USER->id, $coursecontext);
        }

        return $coursecontext;
    }
}
