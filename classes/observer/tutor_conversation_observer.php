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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_dixeo\observer;

use core\event\course_deleted;
use core\event\user_deleted;
use local_dixeo\task\erase_tutor_conversations;

/**
 * Queues the erasure at Dixeo of the tutor conversations of a deleted course or user.
 *
 * The contexts are gone once the deletion completes, so the privacy API can no longer reach
 * these conversations. The erasure runs as an adhoc task with its own retry budget, and
 * nothing is queued on a site that never sent data to Dixeo.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tutor_conversation_observer {
    /**
     * Queue the erasure of every conversation of a deleted course.
     *
     * @param course_deleted $event The event.
     * @return void
     */
    public static function course_deleted(course_deleted $event): void {
        erase_tutor_conversations::queue((int) $event->objectid, null);
    }

    /**
     * Queue the erasure of every conversation of a deleted user.
     *
     * @param user_deleted $event The event.
     * @return void
     */
    public static function user_deleted(user_deleted $event): void {
        erase_tutor_conversations::queue(null, (int) $event->objectid);
    }
}
