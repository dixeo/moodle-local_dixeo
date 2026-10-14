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

namespace local_dixeo\settings;

/**
 * A catalogue entry names a core Moodle setting that is not in the admin tree.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unresolved_core_target_exception extends \coding_exception {
    /**
     * Name the catalogue key and the admin setting that could not be resolved.
     *
     * @param string $settingkey Catalogue key that declared the target.
     * @param string $adminsettingname Admin-tree name that could not be resolved.
     */
    public function __construct(string $settingkey, string $adminsettingname) {
        parent::__construct(
            "Dixeo setting \"{$settingkey}\" declares core target \"{$adminsettingname}\", which is not an admin setting."
        );
    }
}
