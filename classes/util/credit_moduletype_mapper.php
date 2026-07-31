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

namespace local_dixeo\util;

/**
 * Human-readable labels for credit report activity types.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credit_moduletype_mapper {
    /**
     * Canonical activity type codes for credit report filters.
     *
     * @return string[]
     */
    public static function get_known_moduletypes(): array {
        return [
            'glossary',
            'h5pactivity',
            'label',
            'page',
            'quiz',
            'simplequiz2',
            'slideshow',
        ];
    }

    /**
     * Resolve a human-readable label for a stored module type code.
     *
     * @param string|null $moduletype Stored activity type code.
     * @return string
     */
    public static function get_label(?string $moduletype): string {
        $moduletype = trim((string) $moduletype);
        if ($moduletype === '') {
            return '';
        }

        $key = 'credit_moduletype_' . $moduletype;
        if (get_string_manager()->string_exists($key, 'local_dixeo')) {
            return get_string($key, 'local_dixeo');
        }

        $component = 'mod_' . $moduletype;
        if (get_string_manager()->string_exists('modulename', $component)) {
            return get_string('modulename', $component);
        }

        return ucwords(str_replace('_', ' ', $moduletype));
    }
}
