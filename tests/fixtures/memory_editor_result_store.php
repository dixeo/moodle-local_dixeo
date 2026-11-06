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

namespace local_dixeo;

use local_dixeo\service\image\content\decode_result;
use local_dixeo\service\image\content\editor_result_store;

/**
 * In-memory editor result store, one result per session.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class memory_editor_result_store implements editor_result_store {
    /** @var array<int, array{jobid: string, result: decode_result}> Stored results by session id. */
    public array $results = [];

    /** @var int Number of store calls. */
    public int $stores = 0;

    /**
     * Result stored for $jobid on the session.
     *
     * @param int $sessionid Editor session id.
     * @param string $jobid Remote edit job id.
     * @return decode_result|null
     */
    public function get_applied_result(int $sessionid, string $jobid): ?decode_result {
        $entry = $this->results[$sessionid] ?? null;
        return ($entry !== null && $entry['jobid'] === $jobid) ? $entry['result'] : null;
    }

    /**
     * Store the result of $jobid on the session.
     *
     * @param int $sessionid Editor session id.
     * @param string $jobid Remote edit job id.
     * @param decode_result $result Decoded result.
     * @return void
     */
    public function store_applied_result(int $sessionid, string $jobid, decode_result $result): void {
        $this->stores++;
        $this->results[$sessionid] = ['jobid' => $jobid, 'result' => $result];
    }
}
