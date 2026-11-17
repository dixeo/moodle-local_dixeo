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

use local_dixeo\service\credit_usage_sync_service;

/**
 * Hourly sync of Dixeo credit transactions into the local credit usage table.
 *
 * The sync only reads credit transactions and sends no course or user content, so it also
 * runs while Dixeo is off. A failed run is not an error: the next run retries, and the
 * credit report keeps showing the data of the last successful sync.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_credit_usage extends \core\task\scheduled_task {
    /**
     * Localised task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_sync_credit_usage', 'local_dixeo');
    }

    /**
     * Sync the credit transactions since the last run.
     */
    public function execute(): void {
        $result = $this->sync_service()->sync_recent();
        mtrace(sprintf(
            'Credit usage sync: status=%s upserted=%d lastsuccess=%d',
            $result->status,
            $result->upserted,
            $result->lastsuccess
        ));
    }

    /**
     * Sync service used by the task.
     *
     * @return credit_usage_sync_service
     */
    protected function sync_service(): credit_usage_sync_service {
        return new credit_usage_sync_service();
    }
}
