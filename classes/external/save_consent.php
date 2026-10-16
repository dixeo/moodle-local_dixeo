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
 * Web service that records a choice made in the terms review modal.
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
 * Saves analytics consent or withdraws Terms of Use acceptance.
 */
class save_consent extends external_api {
    /**
     * Parameters for a choice made in the review modal.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'action' => new external_value(PARAM_ALPHA, 'saveprocessing or withdrawterms'),
            'processing' => new external_value(PARAM_BOOL, 'Analytics checkbox', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Store the choice and return the updated review.
     *
     * @param string $action saveprocessing or withdrawterms.
     * @param bool $processing Analytics checkbox.
     * @return array
     */
    public static function execute(string $action, bool $processing): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'action' => $action,
            'processing' => $processing,
        ]);
        require_login(null, false);
        self::validate_context(\context_system::instance());

        $service = new consent_service();
        $userid = (int) $USER->id;
        if (!$service->show_manage_link($userid)) {
            throw new \moodle_exception('nopermissions', 'error', '', get_string('consent_manage', 'local_dixeo'));
        }

        if ($params['action'] === 'saveprocessing') {
            if (!(bool) $params['processing']) {
                throw new \invalid_parameter_exception('processing');
            }
            $service->set_processing($userid, true);
            $message = get_string('consent_saved_processing', 'local_dixeo');
        } else if ($params['action'] === 'withdrawterms') {
            $service->withdraw_terms($userid);
            $message = get_string('consent_withdrawn_terms', 'local_dixeo');
        } else {
            throw new \invalid_parameter_exception('action');
        }

        return [
            'show' => true,
            'termsstatus' => $service->terms_status_text($userid),
            'html' => $service->manage_html($userid),
            'message' => $message,
            'reload' => $params['action'] === 'withdrawterms',
        ];
    }

    /**
     * Return structure after a saved choice.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'show' => new external_value(PARAM_BOOL, 'Whether My preferences shows the row'),
            'termsstatus' => new external_value(PARAM_RAW, 'Terms status line'),
            'html' => new external_value(PARAM_RAW, 'Updated review modal markup'),
            'message' => new external_value(PARAM_TEXT, 'Confirmation shown to the user'),
            'reload' => new external_value(PARAM_BOOL, 'Whether the course page must reload so the terms gate returns'),
        ]);
    }
}
