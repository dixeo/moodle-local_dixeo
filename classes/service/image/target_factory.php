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

namespace local_dixeo\service\image;


use local_dixeo\service\image\content\location;
use local_dixeo\service\image\structure\structure_target;

/**
 * Build image_target instances from DB rows and poll task payloads.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class target_factory {
    /**
     * From poll data.
     * @param \stdClass|array $data
     * @return image_target
     */
    public static function from_poll_data($data): image_target {
        $params = is_array($data) ? $data : (array) $data;
        $kind = (string) ($params['target_kind'] ?? image_target::KIND_CONTENT);

        if ($kind === image_target::KIND_CONTENT) {
            return content_target::from_location(location::from_params($params));
        }

        $courseid = (int) ($params['courseid'] ?? 0);
        $objectid = (int) ($params['objectid'] ?? 0);
        return new structure_target($kind, $courseid, $objectid);
    }

    /**
     * From job record.
     * @param \stdClass $record Job DB row.
     * @return image_target
     */
    public static function from_job_record(\stdClass $record): image_target {
        $kind = (string) ($record->target_kind ?? image_target::KIND_CONTENT);
        if ($kind === image_target::KIND_CONTENT) {
            return content_target::from_location(location::from_job_record($record));
        }
        return new structure_target($kind, (int) $record->courseid, (int) $record->objectid);
    }

    /**
     * From location.
     * @param location $location
     * @return content_target
     */
    public static function from_location(location $location): content_target {
        return content_target::from_location($location);
    }
}
