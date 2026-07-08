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

namespace local_dixeo\service\image\apply;


use local_dixeo\service\image\structure\structure_target;
use local_dixeo\service\image\structure\writer;

/**
 * Apply structure image job results.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class structure_handler {
    /**
     * Apply.
     * @param structure_target $target
     * @param array $result
     * @param int $userid
     * @return void
     */
    public static function apply(structure_target $target, array $result, int $userid): void {
        writer::apply_from_job_result($target->get_scope(), $target->get_objectid(), $result, $userid);
    }
}
