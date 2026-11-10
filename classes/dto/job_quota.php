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

namespace local_dixeo\dto;

/**
 * A per-user, per-course-module limit on jobs started in a sliding window.
 *
 * The caller owns the limit; job_service enforces it atomically around the count, the remote
 * POST and the job registration, keyed on the user and course module it registers the job for.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class job_quota {
    /** @var string Lock type used to serialise quota checks. */
    public const LOCK_TYPE = 'local_dixeo_job_quota';

    /**
     * Constructor.
     *
     * @param int $limit Jobs allowed in the window, at least 1.
     * @param int $windowseconds Length of the window in seconds, at least 1.
     * @param int $jobsneeded Jobs the calling flow will start, at least 1. The send is refused
     *                        unless the jobs already in the window plus this number fit the limit.
     * @param int $locktimeout Seconds to wait for a concurrent send by the same user and module, at least 0.
     * @throws \coding_exception When a value is out of range.
     */
    public function __construct(
        /** @var int Jobs allowed in the window. */
        public readonly int $limit,
        /** @var int Length of the window in seconds. */
        public readonly int $windowseconds,
        /** @var int Jobs the calling flow will start. */
        public readonly int $jobsneeded = 1,
        /** @var int Seconds to wait for a concurrent send by the same user and module. */
        public readonly int $locktimeout = 5,
    ) {
        if ($limit < 1 || $windowseconds < 1 || $jobsneeded < 1 || $locktimeout < 0) {
            throw new \coding_exception('Invalid job quota.');
        }
    }

    /**
     * Lock key that serialises sends by one user from one component for one course module.
     *
     * @param string $component Originating frankenstyle component.
     * @param int $userid User the jobs are registered for.
     * @param int $cmid Course module id.
     * @return string
     */
    public static function lock_key(string $component, int $userid, int $cmid): string {
        return $component . '_' . $userid . '_' . $cmid;
    }
}
