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

namespace local_dixeo\exception;

/**
 * A job send was refused because the user's job quota for the course module is used up.
 *
 * Thrown by job_service before anything is posted, also when another send by the same user
 * for the same module holds the quota lock past the quota's lock timeout.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class job_quota_exceeded_exception extends \moodle_exception {
    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct('error:job_quota_exceeded', 'local_dixeo');
    }
}
