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
 * Web service to start or reuse a course tutor synthesis.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use local_dixeo\service\tutor_synthesis_service;
use local_dixeo\service\tutor_usage_report_service;

/**
 * Starts a synthesis for one course report period, or returns the in-flight job.
 */
class request_tutor_synthesis extends external_api {
    /**
     * Parameters for the request.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
        ]);
    }

    /**
     * Request a synthesis for the course.
     *
     * @param int $courseid Course id.
     * @return array
     */
    public static function execute(int $courseid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
        ]);
        self::require_report_access((int) $params['courseid']);

        $view = (new tutor_synthesis_service())->request((int) $params['courseid']);
        return tutor_synthesis_response::from_view($view);
    }

    /**
     * Return structure.
     *
     * @return \core_external\external_single_structure
     */
    public static function execute_returns(): \core_external\external_single_structure {
        return tutor_synthesis_response::returns();
    }

    /**
     * Require the same course report access the page uses.
     *
     * @param int $courseid Course id.
     */
    public static function require_report_access(int $courseid): void {
        if ($courseid <= 1) {
            throw new \invalid_parameter_exception('Invalid course id');
        }
        $context = \context_course::instance($courseid);
        self::validate_context($context);
        if (!tutor_usage_report_service::can_view_course($courseid)) {
            require_capability('local/dixeo:viewtutorusage', $context);
        }
    }
}
