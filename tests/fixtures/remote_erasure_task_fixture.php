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

namespace local_dixeo;

use local_dixeo\api\exception\api_exception;
use local_dixeo\task\remote_erasure_task;

/**
 * Remote erasure task whose erase step succeeds or fails on demand.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class remote_erasure_task_fixture extends remote_erasure_task {
    /** @var int Number of erase() runs in this request. */
    public static int $runs = 0;

    /**
     * Queue a fixture task.
     *
     * @param int $userid Subject user id.
     * @param bool $fail Whether erase() throws.
     * @return void
     */
    public static function queue(int $userid, bool $fail): void {
        $task = new self();
        $task->set_custom_data((object) ['userid' => $userid, 'fail' => $fail]);
        self::queue_task($task);
    }

    /**
     * Count the run, then fail when asked to.
     *
     * @return void
     * @throws api_exception When the custom data asks for a failure.
     */
    protected function erase(): void {
        self::$runs++;
        if (!empty($this->get_custom_data()->fail)) {
            throw new api_exception('service_unavailable', 'Remote erasure failed', 503);
        }
    }

    /**
     * Subject user id from the custom data.
     *
     * @return int
     */
    protected function get_subject_userid(): int {
        return (int) ($this->get_custom_data()->userid ?? 0);
    }

    /**
     * Component named in the failure report.
     *
     * @return string
     */
    protected function get_component_for_report(): string {
        return 'local_dixeo';
    }
}
