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

namespace local_dixeo\exception;

/**
 * The acting user has not accepted the current Dixeo Terms of Use.
 *
 * Thrown by consent_service::require_accepted() and require_accepted_for_course(). Callers that must
 * degrade instead of failing (status reads, background tasks) catch this class.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class consent_required_exception extends \moodle_exception {
    /**
     * Constructor.
     *
     * @param string $errorkey Language string key.
     * @param string $component Component owning the string.
     */
    public function __construct(string $errorkey = 'error:consentrequired', string $component = 'local_dixeo') {
        parent::__construct($errorkey, $component, '');
    }
}
