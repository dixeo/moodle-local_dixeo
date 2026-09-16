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

namespace local_dixeo\service\image\content;


/**
 * Course cache invalidation after a content image HTML write.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class modinfo_helper {
    /**
     * Drop the cached modinfo entry holding the module's formatted intro/content.
     *
     * Intro HTML is rendered into cm_info by *_get_coursemodule_info() and cached,
     * so a direct DB write stays invisible on the course page until it is purged.
     *
     * @param int $courseid
     * @param int|null $cmid Course-module id when known.
     * @return void
     */
    public static function purge_module(int $courseid, ?int $cmid): void {
        if ($courseid < 1) {
            return;
        }

        if ($cmid !== null && $cmid > 0) {
            \course_modinfo::purge_course_module_cache($courseid, $cmid);
            return;
        }

        rebuild_course_cache($courseid, true);
    }
}
