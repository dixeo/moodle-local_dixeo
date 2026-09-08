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
 * Inject client-side pending-image frame wrapping (filter-independent shimmer host).
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\hook\output;

use core\hook\output\before_standard_top_of_body_html_generation;

/**
 * Loads AMD that wraps pending/failed content images and injects status labels.
 */
class content_image_pending_injector {
    /**
     * @param before_standard_top_of_body_html_generation $hook
     * @return void
     */
    public static function callback(before_standard_top_of_body_html_generation $hook): void {
        global $COURSE, $PAGE;

        if (in_array($PAGE->pagelayout, ['embedded', 'popup', 'print', 'frametop', 'secure', 'maintenance'], true)) {
            return;
        }

        // Course and activity pages (site home excluded).
        if (!isset($COURSE->id) || $COURSE->id <= 1) {
            return;
        }

        $PAGE->requires->js_call_amd('local_dixeo/content_image_pending', 'init');
    }
}
