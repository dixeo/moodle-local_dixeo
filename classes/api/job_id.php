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

namespace local_dixeo\api;

/**
 * Validates the ids of remote Dixeo jobs.
 *
 * Job ids are UUIDs in the canonical 8-4-4-4-12 hexadecimal form. Any version is accepted,
 * because the id is issued by the Dixeo API. A malformed id is refused with the same error as
 * a job that is not bound to the caller, so the answer never reveals whether a job exists.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class job_id {
    /** @var string Canonical UUID form. */
    public const PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /** @var int Length of a canonical UUID; it also refuses the trailing newline that a PCRE end anchor accepts. */
    private const LENGTH = 36;

    /**
     * Whether a string is a well-formed job id.
     *
     * @param string $jobid Candidate job id.
     * @return bool
     */
    public static function is_valid(string $jobid): bool {
        return strlen($jobid) === self::LENGTH && preg_match(self::PATTERN, $jobid) === 1;
    }

    /**
     * Stop when a string is not a well-formed job id.
     *
     * @param string $jobid Candidate job id.
     * @return void
     * @throws \moodle_exception error:job_not_found, the same message as a missing job binding.
     */
    public static function require_valid(string $jobid): void {
        if (!self::is_valid($jobid)) {
            throw new \moodle_exception('error:job_not_found', 'local_dixeo');
        }
    }
}
