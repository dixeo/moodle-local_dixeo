<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

namespace local_dixeo\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Course-context capability checks for embedded content image jobs.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class content_image_capability {

    /**
     * Require local/dixeo:contentimagegenerate in the course.
     *
     * @param int $courseid
     * @return void
     */
    public static function require_generate(int $courseid): void {
        require_capability('local/dixeo:contentimagegenerate', \context_course::instance($courseid));
    }

    /**
     * Require local/dixeo:contentimageedit in the course.
     *
     * @param int $courseid
     * @return void
     */
    public static function require_edit(int $courseid): void {
        require_capability('local/dixeo:contentimageedit', \context_course::instance($courseid));
    }
}
