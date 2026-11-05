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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Access gate for Dixeo activity generation on behalf of a named user.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service;

use local_dixeo\external\service_factory;

/**
 * Decides whether a named user may still have Dixeo generate an activity in a course.
 *
 * For paths that act for a user other than the session user, such as tasks that submit
 * work queued earlier. Web service entry points use the capability_check trait instead.
 */
final class generation_access {
    /**
     * Why $userid may not have Dixeo generate a $type activity in $courseid now.
     *
     * The first failing rule wins:
     *  1. the course is above SITEID and exists, else error:generation_not_allowed;
     *  2. Dixeo is on for the course, else error:dixeo_disabled;
     *  3. the user exists, is a real user, is not deleted or suspended, does not use the nologin
     *     auth, has active access to the course, and holds local/dixeo:generate and
     *     moodle/course:manageactivities in it, else error:generation_not_allowed;
     *  4. the type resolves to an activity the user may add, else error:activity_not_available;
     *  5. the user has accepted the current Terms of Use, else error:consentrequired.
     *
     * @param int $userid User the generation is done for.
     * @param int $courseid Course that will own the activity.
     * @param string $type Dixeo module type.
     * @return \lang_string|null The refusal reason, or null when generation is allowed. Never throws for a refusal.
     */
    public static function denial_reason(int $userid, int $courseid, string $type): ?\lang_string {
        global $DB;

        $course = $courseid > SITEID ? $DB->get_record('course', ['id' => $courseid]) : false;
        if (!$course) {
            return new \lang_string('error:generation_not_allowed', 'local_dixeo');
        }

        if (!service_factory::get_settings_config()->enabled_for_course($courseid)) {
            return new \lang_string('error:dixeo_disabled', 'local_dixeo');
        }

        if (!self::user_may_generate($userid, $course)) {
            return new \lang_string('error:generation_not_allowed', 'local_dixeo');
        }

        $module = service_factory::get_module_types_service()->resolve_generation_module($type);
        if ($module === null || !module_addinstance_service::allows($courseid, $module, $userid)) {
            return new \lang_string('error:activity_not_available', 'local_dixeo', clean_param($type, PARAM_ALPHANUMEXT));
        }

        if (!(new consent_service())->terms_current($userid)) {
            return new \lang_string('error:consentrequired', 'local_dixeo');
        }

        return null;
    }

    /**
     * Whether an active, real user may generate activities in the course.
     *
     * @param int $userid User id.
     * @param \stdClass $course Course record.
     * @return bool
     */
    private static function user_may_generate(int $userid, \stdClass $course): bool {
        if ($userid <= 0 || !\core_user::is_real_user($userid)) {
            return false;
        }

        $user = \core_user::get_user($userid);
        if (!$user || !empty($user->deleted) || !empty($user->suspended) || $user->auth === 'nologin') {
            return false;
        }

        if (!can_access_course($course, $user, '', true)) {
            return false;
        }

        $context = \context_course::instance((int) $course->id);
        return has_capability('local/dixeo:generate', $context, $user)
            && has_capability('moodle/course:manageactivities', $context, $user);
    }
}
