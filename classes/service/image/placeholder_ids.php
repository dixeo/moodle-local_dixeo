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

use local_dixeo\api\job_id;

/**
 * Bounds and cleans the image placeholder ids a client asks about.
 *
 * Placeholder ids are UUIDs in the canonical form that {@see \core\uuid::generate()} produces,
 * the same form as remote job ids. The count is checked before any other work, so an
 * oversized list costs neither cleaning nor database access.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class placeholder_ids {
    /** @var int Maximum placeholder ids accepted by one status request. */
    public const MAX_PER_REQUEST = 50;

    /**
     * Trimmed, well-formed, distinct placeholder ids in first-seen order.
     *
     * Non-string and malformed items are dropped.
     *
     * @param array $ids Raw ids from a client.
     * @return string[]
     * @throws \invalid_parameter_exception When count($ids) > MAX_PER_REQUEST (checked before any other work).
     */
    public static function normalise(array $ids): array {
        if (count($ids) > self::MAX_PER_REQUEST) {
            throw new \invalid_parameter_exception('Too many placeholder ids');
        }

        $result = [];
        foreach ($ids as $id) {
            if (!is_string($id)) {
                continue;
            }
            $id = trim($id);
            if (!job_id::is_valid($id) || in_array($id, $result, true)) {
                continue;
            }
            $result[] = $id;
        }
        return $result;
    }
}
