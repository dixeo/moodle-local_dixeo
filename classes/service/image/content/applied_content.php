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

namespace local_dixeo\service\image\content;

/**
 * Decoded edit result, and whether this call was the one that applied it.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class applied_content {
    /**
     * Constructor.
     *
     * @param decode_result $result Decoded content and new placeholder ids.
     * @param bool $firstapplication True when this call decoded the result, false when it was stored already.
     */
    public function __construct(
        /** @var decode_result Decoded content and new placeholder ids. */
        public readonly decode_result $result,
        /** @var bool True when this call decoded the result, false when it was stored already. */
        public readonly bool $firstapplication
    ) {
    }
}
