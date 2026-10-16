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
 * Web service that fills the My preferences terms row and its review modal.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_dixeo\service\consent_service;

/**
 * Returns the terms status line and the review modal markup.
 */
class get_consent_manage extends external_api {
    /**
     * No parameters. The current user is the subject.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Read the review for the logged-in user.
     *
     * @return array
     */
    public static function execute(): array {
        global $USER;

        self::validate_parameters(self::execute_parameters(), []);
        require_login(null, false);
        self::validate_context(\context_system::instance());

        $service = new consent_service();
        $userid = (int) $USER->id;
        if (!$service->show_manage_link($userid)) {
            return [
                'show' => false,
                'termsstatus' => '',
                'html' => '',
            ];
        }

        return [
            'show' => true,
            'termsstatus' => $service->terms_status_text($userid),
            'html' => $service->manage_html($userid),
        ];
    }

    /**
     * Return structure for the review read.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'show' => new external_value(PARAM_BOOL, 'Whether My preferences shows the row'),
            'termsstatus' => new external_value(PARAM_RAW, 'Terms status line'),
            'html' => new external_value(PARAM_RAW, 'Review modal markup'),
        ]);
    }
}
