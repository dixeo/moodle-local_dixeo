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

use local_dixeo\entry_effect;
use local_dixeo\external\service_factory;
use local_dixeo\service\consent_service;

/**
 * Trait providing capability checking for external API endpoints.
 *
 * Each endpoint declares its {@see entry_effect}, which decides the Dixeo gates:
 * - SEND (default): starts or advances Dixeo processing, or stores input that will be sent.
 *   Needs Dixeo enabled for the course and the current Terms of Use.
 * - FEATURE_READ: reads feature data such as a catalogue. Needs Dixeo enabled only.
 * - STOP_OR_STATUS: stops, cancels, turns off, erases, or reads the status of existing work.
 *   Needs neither, so a user can stop processing, check it and remove data after Dixeo is
 *   turned off or acceptance is withdrawn.
 * Context and capability checks run for every effect; job ownership is enforced by job_service.
 */
trait capability_check {
    /**
     * Validate course context and check required capabilities.
     *
     * Use this for endpoints that operate on specific courses. Checks run in this order:
     * course id, context, capabilities, then the Dixeo gates required by the effect.
     *
     * @param int $courseid The course ID.
     * @param bool $requiremanageactivities Whether to also check moodle/course:manageactivities.
     * @param entry_effect $effect What the endpoint does, which decides the Dixeo gates.
     * @return \context_course The validated course context.
     * @throws \invalid_parameter_exception If course id is invalid.
     * @throws \required_capability_exception If capability check fails.
     * @throws \moodle_exception error:dixeo_disabled when the effect needs Dixeo and it is off for the course,
     *     error:consentrequired when the effect needs the current Terms of Use and they are not accepted.
     */
    protected static function validate_course_capability(
        int $courseid,
        bool $requiremanageactivities = false,
        entry_effect $effect = entry_effect::SEND
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

        self::require_entry_effect($coursecontext, $effect);

        return $coursecontext;
    }

    /**
     * Validate course context and require local/dixeo:syncfiles.
     *
     * Use for enable/disable/trigger of external course file transfer. Checks run in this order:
     * course id, context, capability, then the Dixeo gates required by the effect.
     *
     * @param int $courseid The course ID.
     * @param entry_effect $effect What the endpoint does, which decides the Dixeo gates.
     * @return \context_course The validated course context.
     * @throws \invalid_parameter_exception If course id is invalid.
     * @throws \required_capability_exception If capability check fails.
     * @throws \moodle_exception error:dixeo_disabled when the effect needs Dixeo and it is off for the course,
     *     error:consentrequired when the effect needs the current Terms of Use and they are not accepted.
     */
    protected static function validate_course_sync_capability(
        int $courseid,
        entry_effect $effect = entry_effect::SEND
    ): \context_course {
        if ($courseid <= 1) {
            throw new \invalid_parameter_exception('Invalid course id');
        }

        $coursecontext = \context_course::instance($courseid);
        self::validate_context($coursecontext);
        require_capability('local/dixeo:syncfiles', $coursecontext);

        self::require_entry_effect($coursecontext, $effect);

        return $coursecontext;
    }

    /**
     * Apply the Dixeo switch, then the Terms of Use check, as required by the effect.
     *
     * @param \context_course $context Validated course context.
     * @param entry_effect $effect What the endpoint does.
     * @throws \moodle_exception error:dixeo_disabled or error:consentrequired.
     */
    private static function require_entry_effect(\context_course $context, entry_effect $effect): void {
        global $USER;

        if ($effect->requires_enabled()) {
            service_factory::get_settings_config()->require_enabled($context);
        }

        if ($effect->requires_terms()) {
            (new consent_service())->require_accepted((int) $USER->id, $context);
        }
    }
}
