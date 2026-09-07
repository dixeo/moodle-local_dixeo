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
 * File-area coordinates for an editor session draft image.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class editor_draft_fileareas {
    /** @var string Constant COMPONENT. */
    public const COMPONENT = 'local_dixeo_editor';

    /**
     * For modname.
     * @param string $modname page|label|slideshow
     * @return string
     */
    public static function for_modname(string $modname): string {
        return match ($modname) {
            'page' => 'draft_page',
            'label' => 'draft_label',
            'slideshow' => 'draft_slideshow',
            default => throw new \coding_exception('Unsupported editor module for draft filearea: ' . $modname),
        };
    }
}
