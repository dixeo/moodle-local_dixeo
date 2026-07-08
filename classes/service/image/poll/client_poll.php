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

namespace local_dixeo\service\image\poll;


use local_dixeo\repository\image\job_repository;
use local_dixeo\service\image\apply\registry as apply_registry;
use local_dixeo\service\image\image_target;

/**
 * Single-request poll + apply for AJAX clients (format_dixeo teacher quick edit).
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class client_poll {
    /**
     * Poll once.
     * @param string $remotejobid
     * @param image_target $target
     * @param int $userid
     * @param callable|null $imageurlresolver Called when apply succeeds; returns image URL string.
     * @return array{status: string, imageurl?: string, errormessage?: string}
     */
    public static function poll_once(
        string $remotejobid,
        image_target $target,
        int $userid,
        ?callable $imageurlresolver = null
    ): array {
        $jobstatus = engine::poll_remote_once($remotejobid);

        if ($jobstatus->is_failed()) {
            $detail = (string) ($jobstatus->errormessage ?? '');
            if ($detail !== '') {
                debugging('Dixeo image job failed: ' . $detail, DEBUG_DEVELOPER);
            }
            $message = get_string('dixeo_image_job_failed', 'local_dixeo');
            $job = job_repository::get_by_target($target);
            if ($job) {
                job_repository::mark_failed((int) $job->id, $message);
            }
            return ['status' => 'failed', 'errormessage' => $message];
        }

        if (!$jobstatus->is_completed()) {
            return ['status' => $jobstatus->is_processing() ? 'processing' : 'pending'];
        }

        $result = engine::normalise_result($jobstatus->result);

        try {
            $job = job_repository::get_by_target($target);
            apply_registry::apply($target, $result, $userid, 'generated', $job);
            if ($job) {
                job_repository::update_status((int) $job->id, job_repository::STATUS_APPLIED);
            }

            $imageurl = '';
            if ($imageurlresolver !== null) {
                $imageurl = (string) $imageurlresolver();
            }

            return ['status' => 'completed', 'imageurl' => $imageurl];
        } catch (\Throwable $e) {
            debugging('Dixeo image apply failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $message = get_string('dixeo_image_job_failed', 'local_dixeo');
            return ['status' => 'failed', 'errormessage' => $message];
        }
    }
}
