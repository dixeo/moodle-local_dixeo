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
 * Resolves file-sync defaults and Dixeo block presence for courses.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service;

/**
 * Policy helpers for per-course file sync defaults and UI gating.
 */
final class file_sync_policy {
    /** @var string[] Block types whose course pages show the file sync status. */
    private const SYNC_BLOCK_NAMES = ['dixeo_tutor', 'dixeo_modulegen'];

    /**
     * Block plugin names that show the file sync status on a course.
     *
     * @return string[]
     */
    public static function get_sync_block_names(): array {
        $names = [];
        foreach (self::SYNC_BLOCK_NAMES as $blockname) {
            $component = 'block_' . $blockname;
            if (plugin_installation_service::is_component_installed($component)) {
                $names[] = $blockname;
            }
        }
        return $names;
    }

    /**
     * Whether the course has at least one tutor or modulegen block instance.
     *
     * @param int $courseid The course ID.
     * @return bool
     */
    public static function course_has_sync_blocks(int $courseid): bool {
        if ($courseid <= SITEID) {
            return false;
        }

        $blocknames = self::get_sync_block_names();
        if ($blocknames === []) {
            return false;
        }

        global $DB;

        $coursecontext = \context_course::instance($courseid);
        [$insql, $params] = $DB->get_in_or_equal($blocknames, SQL_PARAMS_NAMED, 'bn');
        $params['coursectx'] = $coursecontext->id;
        $params['coursepath'] = $coursecontext->path . '/%';

        $sql = "SELECT 1
                  FROM {block_instances} bi
                  JOIN {context} ctx ON ctx.id = bi.parentcontextid
                 WHERE bi.blockname $insql
                   AND (ctx.id = :coursectx OR ctx.path LIKE :coursepath)";

        return $DB->record_exists_sql($sql, $params);
    }

    /**
     * Whether the sync indicator pill should render on this course page.
     *
     * @param int $courseid The course ID.
     * @return bool
     */
    public static function should_show_sync_indicator(int $courseid): bool {
        return self::course_has_sync_blocks($courseid);
    }

    /**
     * Resolve a course ID from a block parent context.
     *
     * @param int $parentcontextid Block parent context ID.
     * @return int|null Course ID, or null when not in a course.
     */
    public static function resolve_courseid_from_block_parent(int $parentcontextid): ?int {
        if ($parentcontextid <= 0) {
            return null;
        }

        try {
            $context = \context::instance_by_id($parentcontextid);
            $coursecontext = $context->get_course_context(false);
            if (!$coursecontext) {
                return null;
            }
            $courseid = (int) $coursecontext->instanceid;
            return $courseid > SITEID ? $courseid : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Course IDs that already have tutor or modulegen blocks (for upgrade re-opt-in).
     *
     * @return int[]
     */
    public static function get_courseids_with_sync_blocks(): array {
        global $DB;

        $blocknames = self::get_sync_block_names();
        if ($blocknames === []) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($blocknames, SQL_PARAMS_NAMED, 'bn');
        $sql = "SELECT DISTINCT bi.parentcontextid
                  FROM {block_instances} bi
                 WHERE bi.blockname $insql";

        $parentcontextids = $DB->get_fieldset_sql($sql, $params);
        $courseids = [];
        foreach ($parentcontextids as $parentcontextid) {
            $courseid = self::resolve_courseid_from_block_parent((int) $parentcontextid);
            if ($courseid !== null) {
                $courseids[$courseid] = $courseid;
            }
        }

        return array_values($courseids);
    }
}
