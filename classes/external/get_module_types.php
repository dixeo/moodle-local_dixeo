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
 * Web service to get available module types.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;
use local_dixeo\external\traits\capability_check;
use local_dixeo\api\exception\api_exception;
use local_dixeo\service\plugin_installation_service;

/**
 * External function to get available module types.
 */
class get_module_types extends external_api {
    use capability_check;

    /**
     * Define parameters for the web service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(
                PARAM_INT,
                'Course id for capability/language (0 = course designer system context)',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Validate access for module type listing.
     *
     * @param int $courseid Course id, or 0 for course designer (system context).
     */
    private static function validate_access_for_module_types(int $courseid): void {
        if ($courseid < 0) {
            throw new \invalid_parameter_exception('Invalid course id');
        }

        if ($courseid > 0) {
            self::validate_course_for_module_types($courseid);
            return;
        }

        self::validate_designer_system_access();
    }

    /**
     * Require course designer access at system context (courseid = 0 callers only).
     *
     * Rejects the request when the designer block is unavailable.
     */
    private static function validate_designer_system_access(): void {
        global $PAGE;

        if (!plugin_installation_service::is_component_installed('block_dixeo_designer')) {
            throw new \invalid_parameter_exception('Invalid course id');
        }

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/dixeo:create', $context);
        service_factory::get_settings_config()->require_enabled($context);
        $PAGE->set_context($context);
    }

    /**
     * Require course access and set page context so get_string matches course language (e.g. forced lang).
     *
     * @param int $courseid Course id.
     */
    private static function validate_course_for_module_types(int $courseid): void {
        global $PAGE;
        require_course_login($courseid);
        self::validate_course_capability($courseid, true);
        $PAGE->set_context(\context_course::instance($courseid));
    }

    /**
     * Get available module types.
     *
     * @param int $courseid Course id for UI language alignment and capability check (0 = designer).
     * @return array The list of available module types.
     */
    public static function execute(int $courseid = 0): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
        ]);

        self::validate_access_for_module_types((int) $params['courseid']);

        try {
            return self::resolve_module_types_response();
        } catch (api_exception $e) {
            return response_factory::from_api_exception($e, ['types' => []]);
        }
    }

    /**
     * Build the module types success payload.
     *
     * @return array
     */
    private static function resolve_module_types_response(): array {
        $types = service_factory::get_module_types_service()->get_module_types_resolved();

        // Non-admins only see installed module types (no lock icon / "plugin required" options).
        if (!is_siteadmin()) {
            $types = array_values(array_filter($types, function ($t) {
                return !empty($t['installed']);
            }));
        }

        return response_factory::success(['types' => $types]);
    }

    /**
     * Define the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the request was successful'),
            'types' => new external_multiple_structure(
                new external_single_structure([
                    'type' => new external_value(PARAM_ALPHANUMEXT, 'Module type identifier'),
                    'label' => new external_value(PARAM_RAW, 'Display label'),
                    'description' => new external_value(PARAM_RAW, 'Module type description'),
                    'category' => new external_value(PARAM_ALPHANUMEXT, 'Category for grouping'),
                    'component' => new external_value(PARAM_ALPHANUMEXT, 'Moodle component identifier'),
                    'installed' => new external_value(PARAM_BOOL, 'Whether the module is fully usable on this site'),
                    'requirements' => new external_multiple_structure(
                        new external_value(PARAM_RAW, 'Required platform asset identifier (e.g. H5P library)'),
                        'Platform-specific requirements',
                        VALUE_OPTIONAL,
                    ),
                ]),
                'List of available module types'
            ),
            'errormessage' => new external_value(PARAM_RAW, 'Error message if failed', VALUE_OPTIONAL),
            'errorcode' => new external_value(PARAM_ALPHANUMEXT, 'Error code if failed', VALUE_OPTIONAL),
        ]);
    }
}
