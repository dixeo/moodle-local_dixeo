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

namespace local_dixeo;

use local_dixeo\service\image\content\apply_handler;
use local_dixeo\service\image\content\in_place_writer;
use local_dixeo\service\image\content\location;
use local_dixeo\service\image\result_helper;

/**
 * Modal apply handler that records the session user and writes the result in place.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_user_recording_apply_handler implements apply_handler {
    /** @var int[] Session user id seen by each apply. */
    public array $sessionuserids = [];

    /** @var int[] Owner id passed to each apply. */
    public array $ownerids = [];

    /**
     * Record the session user and owner, then replace the file bytes for the owner.
     *
     * @param location $location Target file.
     * @param array $jobresult Completed job result.
     * @param int $userid Job owner.
     * @param string $source Version source.
     * @return void
     */
    public function apply_job_result(location $location, array $jobresult, int $userid, string $source): void {
        global $USER;

        $this->sessionuserids[] = (int) $USER->id;
        $this->ownerids[] = $userid;
        $file = $location->get_stored_file();
        if (!$file) {
            throw new \moodle_exception('dixeo_image_not_eligible', 'local_dixeo');
        }
        in_place_writer::replace_content($file, result_helper::extract_image_binary_from_result($jobresult), $userid);
    }
}
