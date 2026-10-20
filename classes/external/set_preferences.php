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
 * Web service to store the current user's Dixeo preferences.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_dixeo\service\user_preference_service;

/**
 * Stores submitted user preferences and clears the applied course cache.
 */
class set_preferences extends external_api {
    /**
     * Parameters for the preference write.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Context whose applied cache is cleared', VALUE_DEFAULT, 0),
            'courseid' => new external_value(
                PARAM_INT,
                'Course used when the opener has no context id',
                VALUE_DEFAULT,
                0
            ),
            'preferences' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_ALPHANUMEXT, 'Preference name'),
                    'value' => new external_value(PARAM_RAW, 'Preference value'),
                ])
            ),
        ]);
    }

    /**
     * Store preferences for the current user and clear the applied cache.
     *
     * An empty list changes nothing. A course context clears that course. Any other
     * accepted context clears every course cache, matching a Dixeo-layer settings save.
     *
     * @param int $contextid System or course context. Zero uses $courseid or the system.
     * @param int $courseid Course id when $contextid is zero.
     * @param array $preferences Preferences to store.
     * @return null
     */
    public static function execute(int $contextid, int $courseid, array $preferences) {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'courseid' => $courseid,
            'preferences' => $preferences,
        ]);
        require_login(null, false);
        if ($params['preferences'] === []) {
            return null;
        }

        $context = self::applied_context((int) $params['contextid'], (int) $params['courseid']);
        self::validate_context($context);
        user_preference_service::store_for_user((int) $USER->id, $params['preferences']);
        service_factory::get_settings_config()->clear_applied_cache($context);

        return null;
    }

    /**
     * The write returns nothing.
     *
     * @return null
     */
    public static function execute_returns() {
        return null;
    }

    /**
     * Context whose course cache is cleared after the preference write.
     *
     * @param int $contextid
     * @param int $courseid
     * @return \context
     */
    private static function applied_context(int $contextid, int $courseid): \context {
        if ($contextid > 0) {
            $context = \context::instance_by_id($contextid);
        } else if ($courseid > 0) {
            $context = \context_course::instance($courseid);
        } else {
            $context = \context_system::instance();
        }
        if ($context->contextlevel != CONTEXT_SYSTEM && $context->contextlevel != CONTEXT_COURSE) {
            throw new \invalid_parameter_exception('contextid');
        }
        return $context;
    }
}
