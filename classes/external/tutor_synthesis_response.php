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
            'state' => new external_value(PARAM_ALPHA, 'none, pending, ready, or failed'),
            'jobid' => new external_value(PARAM_TEXT, 'In-flight job id, or an empty string'),
            'hasstatus' => new external_value(PARAM_BOOL, 'True when a status line is shown'),
            'pending' => new external_value(PARAM_BOOL, 'True while a job runs'),
            'pendinglabel' => new external_value(PARAM_TEXT, 'Status of the running job, naming its period when it is another one'),
            'failed' => new external_value(PARAM_BOOL, 'True when the latest attempt for this period failed'),
            'unchanged' => new external_value(PARAM_BOOL, 'True when the stored synthesis was kept'),
            'unavailable' => new external_value(PARAM_BOOL, 'True when the synthesis service cannot be reached'),
            'showintro' => new external_value(PARAM_BOOL, 'True when nothing was requested yet'),
            'hasresult' => new external_value(PARAM_BOOL, 'True when a completed synthesis is stored'),
            'stale' => new external_value(PARAM_BOOL, 'True when the stored synthesis covers another period'),
            'stalelabel' => new external_value(PARAM_TEXT, 'Period the stored synthesis covers, when stale'),
            'nostudent' => new external_value(PARAM_BOOL, 'True when no student used the tutor'),
            'toofew' => new external_value(PARAM_BOOL, 'True when too few students wrote for an anonymous synthesis'),
            'hascoverage' => new external_value(PARAM_BOOL, 'True when the coverage line is shown'),
            'hasshare' => new external_value(PARAM_BOOL, 'True when the enrolled count is known'),
            'coveragelabel' => new external_value(PARAM_TEXT, 'Students who used the tutor'),
            'coveragepercent' => new external_value(PARAM_INT, 'Share of enrolled students who used the tutor'),
            'coveragelow' => new external_value(PARAM_BOOL, 'True when under half the enrolled students used the tutor'),
            'messagelabel' => new external_value(PARAM_TEXT, 'Analysed student messages'),
            'hassummary' => new external_value(PARAM_BOOL, 'True when a summary text is stored'),
            'summary' => new external_value(PARAM_RAW, 'Sanitised synthesis HTML'),
            'truncated' => new external_value(PARAM_BOOL, 'True when a sample of each student\'s recent exchanges was read'),
            'buttonlabel' => new external_value(PARAM_TEXT, 'Label of the request button'),
        ]);
    }

    /**
     * Keep only the keys the web services return.
     *
     * @param array $view Service view model.
     * @return array
     */
    public static function from_view(array $view): array {
        return array_intersect_key($view, self::returns()->keys);
    }
}
