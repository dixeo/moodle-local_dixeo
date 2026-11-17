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
 * Outcome of one credit usage sync run.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class credit_sync_result {
    /** @var string The run read the transactions it could and stored them. */
    public const STATUS_SYNCED = 'synced';

    /** @var string The Dixeo API refused or failed; nothing changed locally. */
    public const STATUS_FAILED = 'failed';

    /** @var string No API credentials are set; no call was made. */
    public const STATUS_NOT_CONFIGURED = 'notconfigured';

    /** @var string A manual run happened less than a minute ago; no call was made. */
    public const STATUS_THROTTLED = 'throttled';

    /** @var string Another run holds the sync lock; no call was made. */
    public const STATUS_BUSY = 'busy';

    /**
     * Constructor.
     *
     * @param string $status One of the STATUS_* constants.
     * @param int $upserted Transactions stored by this run.
     * @param int $lastsuccess Time of the last successful sync (0 = never).
     */
    public function __construct(
        /** @var string One of the STATUS_* constants. */
        public readonly string $status,
        /** @var int Transactions stored by this run. */
        public readonly int $upserted,
        /** @var int Time of the last successful sync (0 = never). */
        public readonly int $lastsuccess
    ) {
    }
}
