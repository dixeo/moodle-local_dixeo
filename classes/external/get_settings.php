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
 * Web service to read Dixeo settings for one context.
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
 * Returns the catalogue rows from the settings helper for a system or course context.
 */
class get_settings extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'System or course context id'),
        ]);
    }

    /**
     * Read the settings the current user can open at this context.
     *
     * The helper checks the capability and builds the rows. This function does not
     * read the setting table itself.
     *
     * @param int $contextid System or course context id.
     * @return array
     */
    public static function execute(int $contextid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
        ]);
        $context = \context::instance_by_id($params['contextid']);
        self::validate_context($context);
        self::warm_designer_templates();

        return [
            'settings' => service_factory::get_settings_config()->get_layers($context),
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'settings' => new external_multiple_structure(
                new external_single_structure([
                    'component' => new external_value(PARAM_COMPONENT, 'Frankenstyle component'),
                    'name' => new external_value(PARAM_ALPHANUMEXT, 'Catalogue key'),
                    'shortname' => new external_value(PARAM_ALPHANUMEXT, 'Shortname shown in the settings manager'),
                    'type' => new external_value(PARAM_ALPHA, 'Control type'),
                    'feature' => new external_value(PARAM_ALPHANUMEXT, 'Feature group'),
                    'plugingroup' => new external_value(PARAM_ALPHANUMEXT, 'Plugin group'),
                    'help' => new external_value(PARAM_ALPHANUMEXT, 'Help string key'),
                    'options' => new external_multiple_structure(
                        new external_value(PARAM_ALPHANUMEXT, 'Allowed value for a select'),
                        'Allowed values for a select. Empty for every other type.'
                    ),
                    'optionlabels' => new external_multiple_structure(
                        new external_single_structure([
                            'value' => new external_value(PARAM_ALPHANUMEXT, 'Option value'),
                            'label' => new external_value(PARAM_TEXT, 'Option label'),
                        ]),
                        'Display labels for select options. Empty when the modal loads labels from language strings.',
                        VALUE_DEFAULT,
                        []
                    ),
                    'readonly' => new external_value(
                        PARAM_BOOL,
                        'True when the open level cannot change this setting',
                        VALUE_DEFAULT,
                        false
                    ),
                    'coremissing' => new external_value(PARAM_BOOL, 'True when a declared core target cannot be resolved'),
                    'value' => new external_value(PARAM_RAW, 'Effective value'),
                    'source' => new external_value(PARAM_ALPHA, 'Layer that supplied the effective value'),
                    'layers' => new external_multiple_structure(
                        new external_single_structure([
                            'level' => new external_value(PARAM_ALPHA, 'Layer id'),
                            'value' => new external_value(PARAM_RAW, 'Effective value at this layer'),
                            'stored' => new external_value(
                                PARAM_RAW,
                                'Own stored value, or null when this layer has no row',
                                VALUE_REQUIRED,
                                null,
                                NULL_ALLOWED
                            ),
                            'customised' => new external_value(PARAM_BOOL, 'True when this layer has its own stored row'),
                        ])
                    ),
                ])
            ),
        ]);
    }

    /**
     * Fill the pedagogical template cache before the catalogue is read.
     *
     * The designer catalogue reads that cache and does not call the API itself.
     */
    public static function warm_designer_templates(): void {
        global $CFG;
        $lib = $CFG->dirroot . '/blocks/dixeo_designer/lib.php';
        if (!is_readable($lib)) {
            return;
        }
        require_once($lib);
        if (function_exists('block_dixeo_designer_warm_course_templates')) {
            block_dixeo_designer_warm_course_templates();
        }
    }
}
