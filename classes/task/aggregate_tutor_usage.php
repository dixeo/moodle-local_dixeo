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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_dixeo\task;

use local_dixeo\service\tutor_usage_aggregator;

/**
 * Daily aggregation of tutor usage events into rollups and sessions.
 *
 * Catches up from the last successfully aggregated day through yesterday.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aggregate_tutor_usage extends \core\task\scheduled_task {
    /**
     * Localised task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_aggregate_tutor_usage', 'local_dixeo');
    }

    /**
     * Execute aggregation for all pending calendar days through yesterday.
     */
    public function execute(): void {
        $aggregator = new tutor_usage_aggregator();
        $result = $aggregator->aggregate_pending_days();
        $daycount = count($result['days']);

        if ($daycount === 0) {
            mtrace(sprintf(
                'Tutor usage aggregation up to date (last daystart=%d)',
                $aggregator->get_last_aggregated_day()
            ));
            return;
        }

        mtrace(sprintf(
            'Tutor usage aggregated %d day(s) from daystart=%d to daystart=%d',
            $daycount,
            $result['from'],
            $result['to']
        ));
        foreach ($result['days'] as $day) {
            mtrace(sprintf(
                '  daystart=%d dailyrows=%d sessionsclosed=%d',
                $day['daystart'],
                $day['dailyrows'],
                $day['sessionsclosed']
            ));
        }
    }
}
