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
 * Web service to read the current user's Dixeo preferences.
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
 * Returns the preference catalogue for the current user.
 */
class get_preferences extends external_api {
    /**
     * Parameters for the preference read.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Read the current user's Dixeo preferences.
     *
     * @return array
     */
    public static function execute(): array {
        global $USER;

        self::validate_parameters(self::execute_parameters(), []);
        require_login(null, false);
        self::validate_context(\context_system::instance());

        return [
            'preferences' => user_preference_service::get_for_user((int) $USER->id),
        ];
    }

    /**
     * Return structure for the preference read.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'preferences' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_ALPHANUMEXT, 'Preference name'),
                    'value' => new external_value(PARAM_RAW, 'Current value'),
                    'show' => new external_value(PARAM_BOOL, 'Whether the preference is offered'),
                    'options' => new external_multiple_structure(
                        new external_single_structure([
                            'value' => new external_value(PARAM_RAW, 'Option value'),
                            'label' => new external_value(PARAM_TEXT, 'Option label'),
                        ])
                    ),
                ])
            ),
        ]);
    }
}
