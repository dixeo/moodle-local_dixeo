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
 * Event observer for file sync triggers.
 *
 * Listens to course creation and course module events. A new course turns sync
 * on when the File sync for new courses setting is on. Module changes queue a
 * sync only when that course already has sync on. The service refuses both
 * when Dixeo is off for the course.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\observer;

use core\event\course_created;
use core\event\course_module_created;
use core\event\course_module_updated;
use core\event\course_module_deleted;
use local_dixeo\external\service_factory;

/**
 * Observer for file sync related events.
 */
class file_sync_observer {
    /** @var array Module types that contain syncable files or SCORM extracts. */
    private const FILE_MODULE_TYPES = ['resource', 'folder', 'scorm'];

    /**
     * Turn file sync on for a new course when the site default says so.
     *
     * @param course_created $event The event.
     * @return void
     */
    public static function course_created(course_created $event): void {
        $courseid = (int) $event->courseid;
        if ($courseid <= SITEID) {
            return;
        }

        service_factory::get_file_sync_service()->enable_new_course($courseid, (int) $event->userid);
    }

    /**
     * Handle course module created event.
     *
     * Queues sync if the module is a resource/folder type in an enabled course.
     *
     * @param course_module_created $event The event.
     * @return void
     */
    public static function course_module_created(course_module_created $event): void {
        self::handle_module_change($event);
    }

    /**
     * Handle course module updated event.
     *
     * Queues sync if the module is a resource/folder type in an enabled course.
     *
     * @param course_module_updated $event The event.
     * @return void
     */
    public static function course_module_updated(course_module_updated $event): void {
        self::handle_module_change($event);
    }

    /**
     * Handle course module deleted event.
     *
     * Queues sync if the module was a resource/folder type in an enabled course.
     *
     * @param course_module_deleted $event The event.
     * @return void
     */
    public static function course_module_deleted(course_module_deleted $event): void {
        self::handle_module_change($event);
    }

    /**
     * Handle a module change event.
     *
     * Common logic for created, updated, and deleted events.
     *
     * @param \core\event\base $event The event.
     * @return void
     */
    private static function handle_module_change(\core\event\base $event): void {
        $data = $event->get_data();
        $other = $data['other'] ?? [];

        // Get module name from event data.
        $modulename = $other['modulename'] ?? '';

        if (!in_array($modulename, self::FILE_MODULE_TYPES, true)) {
            return;
        }

        $courseid = $data['courseid'] ?? 0;
        if ($courseid <= 1) {
            return;
        }

        $service = service_factory::get_file_sync_service();

        if (!$service->is_enabled($courseid)) {
            return;
        }

        // Mark as outdated immediately so the UI reflects the change.
        $service->mark_outdated($courseid);

        // Queue debounced sync.
        $service->queue_sync($courseid);
    }
}
