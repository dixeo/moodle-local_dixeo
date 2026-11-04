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

use core\task\adhoc_task;
use core\task\manager;
use local_dixeo\external\service_factory;
use local_dixeo\privacy\failure_reporter;
use local_dixeo\privacy\remote_state;

/**
 * Base adhoc task for erasing data held by the Dixeo API.
 *
 * Erasure must survive an unreachable API: core swallows whatever a privacy callback throws
 * and marks the request complete, so queueing the erasure hands the retry to the adhoc runner.
 * A failure, including a missing API key, propagates so the runner retries with its growing
 * delay (60s doubling up to 24h). {@see self::RETRY_ATTEMPTS} covers roughly 20 days of outage.
 * When the last attempt fails, {@see failure_reporter} alerts the site administrators, because
 * the runner will not pick the task up again.
 *
 * Remote erasure never depends on whether Dixeo is enabled for a course or the site.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class remote_erasure_task extends adhoc_task {
    /** @var int Failed runs to allow, covering roughly 20 days of API outage. */
    public const RETRY_ATTEMPTS = 30;

    /**
     * Queue an erasure task with the extended retry budget.
     *
     * A site that never sent anything to Dixeo has nothing to erase, so nothing is queued. A site
     * that did, but lost its key, still queues: the task waits for the key instead of forgetting
     * the erasure.
     *
     * @param self $task Task with its custom data set.
     * @param bool $dedupe Skip queueing when an identical task is already queued.
     * @return void
     */
    protected static function queue_task(self $task, bool $dedupe = true): void {
        if (remote_state::current() === remote_state::NEVER) {
            return;
        }
        $task->set_attempts_available(self::RETRY_ATTEMPTS);
        manager::queue_adhoc_task($task, $dedupe);
    }

    /**
     * Run the erasure, reporting the failure of the last attempt.
     *
     * @return void
     * @throws \Throwable When the API key is missing or the erasure fails; the runner retries.
     */
    final public function execute(): void {
        try {
            if (!service_factory::get_client()->is_configured()) {
                throw remote_state::not_configured_exception();
            }
            $this->erase();
        } catch (\Throwable $e) {
            // The runner decrements the attempts after this run fails.
            if ($this->get_attempts_available() <= 1) {
                failure_reporter::report(
                    $this->get_component_for_report(),
                    $this->get_subject_userid(),
                    failure_reporter::REASON_ABANDONED,
                    $e
                );
            }
            throw $e;
        }
    }

    /**
     * Erase the queued data at Dixeo.
     *
     * @return void
     * @throws \Throwable When a remote call fails.
     */
    abstract protected function erase(): void;

    /**
     * Subject of the erasure, used in the failure report.
     *
     * @return int User id, or 0 when a whole context or course is erased.
     */
    abstract protected function get_subject_userid(): int;

    /**
     * Frankenstyle name of the plugin whose data is erased, used in the failure report.
     *
     * @return string
     */
    abstract protected function get_component_for_report(): string;
}
