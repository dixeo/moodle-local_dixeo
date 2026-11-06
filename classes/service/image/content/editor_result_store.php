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
 * Storage for the decoded result of a completed edit job, kept on an editor session.
 *
 * The editor that owns the session implements this over its own persistence.
 * {@see editor_regenerate_service::apply_api_content_once()} uses it so that a completed
 * job is decoded once per session, however often its status is read.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface editor_result_store {
    /**
     * Result stored for $jobid on the session, or null when none is stored for that job.
     *
     * @param int $sessionid Editor session id.
     * @param string $jobid Remote edit job id.
     * @return decode_result|null
     */
    public function get_applied_result(int $sessionid, string $jobid): ?decode_result;

    /**
     * Store the result of $jobid on the session, replacing any earlier job's result.
     *
     * @param int $sessionid Editor session id.
     * @param string $jobid Remote edit job id.
     * @param decode_result $result Decoded content and new placeholder ids.
     * @return void
     */
    public function store_applied_result(int $sessionid, string $jobid, decode_result $result): void;
}
