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

namespace local_dixeo\task;

use local_dixeo\external\service_factory;

/**
 * Adhoc task erasing tutor conversations held by the Dixeo API.
 *
 * Queued by the privacy provider when the API cannot be reached, and by the course and
 * user deletion observers, whose contexts are gone by the time a retry could run. The
 * scope is a course, a user, or a single user in a course.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class erase_tutor_conversations extends remote_erasure_task {
    /**
     * Get the task name for display.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_erase_tutor_conversations', 'local_dixeo');
    }

    /**
     * Queue an erasure for the given scope.
     *
     * An identical queued task is reused, so a scope retried by several privacy requests
     * does not pile up duplicates.
     *
     * @param int|null $courseid The course to erase, or null for every course.
     * @param int|null $userid The user to erase, or null for every user.
     * @return void
     */
    public static function queue(?int $courseid, ?int $userid): void {
        $task = new static();
        $task->set_custom_data((object) ['courseid' => $courseid, 'userid' => $userid]);
        self::queue_task($task, true);
    }

    /**
     * Erase the conversations of the queued scope.
     *
     * @return void
     * @throws \local_dixeo\api\exception\api_exception When the API refuses or is down.
     */
    protected function erase(): void {
        [$courseid, $userid] = $this->scope();
        $deleted = service_factory::get_tutor_service()->delete_conversations($courseid, $userid);

        mtrace("erase_tutor_conversations: erased {$deleted} conversation(s) " .
            '(courseid=' . ($courseid ?? 'all') . ', userid=' . ($userid ?? 'all') . ')');
    }

    /**
     * Subject of the erasure.
     *
     * @return int User id, or 0 when a whole course is erased.
     */
    protected function get_subject_userid(): int {
        return (int) ($this->scope()[1] ?? 0);
    }

    /**
     * Component named in the failure report.
     *
     * @return string
     */
    protected function get_component_for_report(): string {
        return 'local_dixeo';
    }

    /**
     * Queued course and user ids.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function scope(): array {
        $data = $this->get_custom_data();
        return [
            isset($data->courseid) ? (int) $data->courseid : null,
            isset($data->userid) ? (int) $data->userid : null,
        ];
    }
}
