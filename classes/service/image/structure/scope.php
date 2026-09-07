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

namespace local_dixeo\service\image\structure;


use local_dixeo\service\image\image_target;

/**
 * Structure image apply scopes (course overview + format section).
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scope {
    /** Apply job bytes to Moodle course overview files. objectid = course id. */
    public const SCOPE_COURSE_OVERVIEW = image_target::KIND_COURSE_OVERVIEW;

    /** Apply job bytes to format_dixeo chapter image file area. objectid = course_sections.id. */
    public const SCOPE_FORMAT_SECTION = image_target::KIND_FORMAT_SECTION;

    /**
     * Is valid.
     * @param string $scope
     * @return bool
     */
    public static function is_valid(string $scope): bool {
        return in_array($scope, [self::SCOPE_COURSE_OVERVIEW, self::SCOPE_FORMAT_SECTION], true);
    }
}
