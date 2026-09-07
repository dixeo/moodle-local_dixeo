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
 * Result of decoding API HTML for the editor.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class decode_result {
    /** @var string */
    public string $html;
    /** @var string[] */
    public array $newplaceholderids;
    /** @var string[] */
    public array $restoredplaceholderids;

    /**
     *   construct.
     * @param string $html
     * @param string[] $newplaceholderids
     * @param string[] $restoredplaceholderids
     */
    public function __construct(string $html, array $newplaceholderids, array $restoredplaceholderids) {
        $this->html = $html;
        $this->newplaceholderids = $newplaceholderids;
        $this->restoredplaceholderids = $restoredplaceholderids;
    }
}
