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
 * Shared return shape for the tutor synthesis web services.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\external;

use core_external\external_single_structure;
use core_external\external_value;

/**
 * Return structure for a stored course-period synthesis.
 */
class tutor_synthesis_response {
    /**
     * Web service return structure.
     *
     * @return external_single_structure
     */
    public static function returns(): external_single_structure {
        return new external_single_structure([
            'state' => new external_value(PARAM_ALPHA, 'empty, pending, ready, or failed'),
            'unchanged' => new external_value(PARAM_BOOL, 'True when the stored synthesis was kept'),
            'summary' => new external_value(PARAM_RAW, 'Sanitised synthesis HTML'),
            'truncated' => new external_value(PARAM_BOOL, 'True when the transcript was cut'),
            'empty' => new external_value(PARAM_BOOL, 'True when the window had no student messages'),
            'jobid' => new external_value(PARAM_TEXT, 'In-flight job id, or an empty string'),
            'window' => new external_value(PARAM_TEXT, 'Description of the analysed window'),
        ]);
    }

    /**
     * Normalise a service view model for the web service.
     *
     * @param array $view Service view model.
     * @return array
     */
    public static function from_view(array $view): array {
        return [
            'state' => (string) $view['state'],
            'unchanged' => !empty($view['unchanged']),
            'summary' => (string) $view['summary'],
            'truncated' => !empty($view['truncated']),
            'empty' => !empty($view['empty']),
            'jobid' => (string) ($view['jobid'] ?? ''),
            'window' => (string) ($view['window'] ?? ''),
        ];
    }
}
