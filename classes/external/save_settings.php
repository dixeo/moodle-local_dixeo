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
 * Web service to save Dixeo settings for one context.
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

/**
 * Writes the submitted catalogue values through the settings helper.
 */
class save_settings extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'System or course context id'),
            'settings' => new external_multiple_structure(
                new external_single_structure([
                    'component' => new external_value(PARAM_COMPONENT, 'Frankenstyle component'),
                    'name' => new external_value(PARAM_ALPHANUMEXT, 'Catalogue key'),
                    'value' => new external_value(PARAM_RAW, 'Value to store'),
                ])
            ),
        ]);
    }

    /**
     * Save the listed settings. Keys that are absent stay as they are.
     *
     * Each value is passed to the helper, which checks the capability and deletes
     * an override that matches its parent. An unknown key, or a key that does not
     * exist at this context, is rejected.
     *
     * @param int $contextid System or course context id.
     * @param array $settings Values to store.
     * @return null
     */
    public static function execute(int $contextid, array $settings) {
        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'settings' => $settings,
        ]);
        $context = \context::instance_by_id($params['contextid']);
        self::validate_context($context);
        get_settings::warm_designer_templates();
        $config = service_factory::get_settings_config();

        foreach ($params['settings'] as $setting) {
            try {
                $config->set($setting['component'], $setting['name'], $context, $setting['value']);
            } catch (\coding_exception $e) {
                throw new \invalid_parameter_exception($e->getMessage());
            }
        }

        $config->clear_applied_cache($context);

        return null;
    }

    /**
     * @return null
     */
    public static function execute_returns() {
        return null;
    }
}
