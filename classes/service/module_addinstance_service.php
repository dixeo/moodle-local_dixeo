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
 * Authorisation for creating a Moodle activity from Dixeo.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service;

/**
 * Requires mod/<type>:addinstance before Dixeo creates an activity.
 */
class module_addinstance_service {
    /**
     * Require that the current user may add this activity type in the course.
     *
     * The activity plugin must be installed and enabled on the site. The user
     * must hold mod/<type>:addinstance in the course context.
     *
     * @param int $courseid Course that will own the activity.
     * @param string $modulename Moodle module name, such as quiz or page.
     * @return int modules.id for the enabled module.
     * @throws \moodle_exception When the type is missing or disabled.
     * @throws \required_capability_exception When addinstance is not allowed.
     */
    public static function require_for_course(int $courseid, string $modulename): int {
        $modulename = clean_param($modulename, PARAM_PLUGIN);
        if ($modulename === '' || !plugin_installation_service::is_component_installed('mod_' . $modulename)) {
            throw new \moodle_exception('moduledoesnotexist');
        }

        $moduleid = self::enabled_module_id($modulename);
        if ($moduleid === null) {
            throw new \moodle_exception('error:activity_not_available', 'local_dixeo', '', $modulename);
        }

        $context = \context_course::instance($courseid);
        require_capability('mod/' . $modulename . ':addinstance', $context);

        return $moduleid;
    }

    /**
     * Whether a user may add this activity type in the course.
     *
     * Same rules as {@see self::require_for_course()}, for a named user, without throwing.
     *
     * @param int $courseid Course that will own the activity.
     * @param string $modulename Moodle module name, such as quiz or page.
     * @param int $userid User who would create the activity.
     * @return bool True when the module is installed and enabled and the user holds mod/<type>:addinstance.
     */
    public static function allows(int $courseid, string $modulename, int $userid): bool {
        $modulename = clean_param($modulename, PARAM_PLUGIN);
        if (self::enabled_module_id($modulename) === null) {
            return false;
        }

        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context instanceof \context_course) {
            return false;
        }

        return has_capability('mod/' . $modulename . ':addinstance', $context, $userid);
    }

    /**
     * Id of an installed activity plugin that is enabled on the site.
     *
     * @param string $modulename Cleaned Moodle module name.
     * @return int|null modules.id, or null when the module is missing or disabled.
     */
    private static function enabled_module_id(string $modulename): ?int {
        global $DB;

        if ($modulename === '' || !plugin_installation_service::is_component_installed('mod_' . $modulename)) {
            return null;
        }

        $moduleid = $DB->get_field('modules', 'id', ['name' => $modulename, 'visible' => 1]);
        return $moduleid ? (int) $moduleid : null;
    }
}
