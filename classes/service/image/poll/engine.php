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


use local_dixeo\api\exception\api_exception;
use local_dixeo\external\service_factory;

/**
 * Shared remote job polling logic for adhoc tasks and client AJAX.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class engine {
    /** @var int Delay before the next poll attempt (adhoc task requeue). */
    public const POLL_INTERVAL_SECONDS = 15;

    /** @var int Max poll attempts before the job is marked failed (~1h at the poll interval). */
    public const MAX_POLL_ATTEMPTS = 240;

    /**
     * Poll remote once.
     * @param string $remotejobid
     * @return \local_dixeo\dto\job_status
     */
    public static function poll_remote_once(string $remotejobid): \local_dixeo\dto\job_status {
        return service_factory::get_job_service()->get_job_status($remotejobid);
    }

    /**
     * Single non-blocking status check.
     *
     * @param string $remotejobid
     * @return array{done: bool, completed: bool, failed: bool, result: array, errormessage: string}
     */
    public static function poll_once(string $remotejobid): array {
        try {
            $jobstatus = self::poll_remote_once($remotejobid);
        } catch (api_exception $e) {
            return self::failed_outcome();
        }

        if ($jobstatus->is_completed()) {
            return [
                'done' => true,
                'completed' => true,
                'failed' => false,
                'result' => self::normalise_result($jobstatus->result),
                'errormessage' => '',
            ];
        }

        if ($jobstatus->is_failed()) {
            $detail = (string) ($jobstatus->errormessage ?? '');
            if ($detail !== '') {
                debugging('Dixeo image job failed: ' . $detail, DEBUG_DEVELOPER);
            }
            return [
                'done' => true,
                'completed' => false,
                'failed' => true,
                'result' => [],
                'errormessage' => get_string('dixeo_image_job_failed', 'local_dixeo'),
            ];
        }

        return [
            'done' => false,
            'completed' => false,
            'failed' => false,
            'result' => [],
            'errormessage' => '',
        ];
    }

    /**
     * Normalise a remote job result payload (JSON string, object, or array) to an array.
     *
     * @param mixed $result
     * @return array
     */
    public static function normalise_result($result): array {
        if (is_string($result)) {
            $decoded = json_decode($result, true);
            return is_array($decoded) ? $decoded : [];
        }
        if (is_array($result)) {
            return $result;
        }
        return $result !== null ? (array) $result : [];
    }

    /**
     * Build a generic failed poll outcome for transport/configuration errors.
     *
     * @return array{done: bool, completed: bool, failed: bool, result: array, errormessage: string}
     */
    private static function failed_outcome(): array {
        return [
            'done' => true,
            'completed' => false,
            'failed' => true,
            'result' => [],
            'errormessage' => get_string('dixeo_image_job_failed', 'local_dixeo'),
        ];
    }
}
