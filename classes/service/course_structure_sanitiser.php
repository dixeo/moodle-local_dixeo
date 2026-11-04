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

namespace local_dixeo\service;

/**
 * Sanitises a course structure payload received from a browser before it is stored.
 *
 * The structure is the shape read by {@see designer_structure_finalize_validation_service}:
 * an optional course_structure wrapper holding sections[].modules[]. A module type is a
 * machine identifier (page, quiz, h5p_flashcards) from which icon URLs and CSS custom
 * properties are built, so a malformed type is replaced by an empty string, which the finalize
 * validator reports so the user picks a valid type. An image URL is kept only when the caller
 * accepts it, so a structure never references a file its owner may not use.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_structure_sanitiser {
    /** @var string Identifier shape accepted for a module type, case-insensitive like the finalize validator. */
    public const MODULE_TYPE_PATTERN = '/^[a-z][a-z0-9_]{0,49}$/i';

    /**
     * Whether a value is an acceptable module type identifier.
     *
     * @param mixed $type Stored value.
     * @return bool
     */
    public static function is_valid_module_type($type): bool {
        return is_string($type) && !str_ends_with($type, "\n") && preg_match(self::MODULE_TYPE_PATTERN, $type) === 1;
    }

    /**
     * Sanitise a decoded structure in one pass.
     *
     * Malformed module types become ''. The image key of the root and of course_structure is
     * removed unless it is a string accepted by $isallowedimage; an empty or null image, which
     * references nothing, is kept.
     *
     * @param array $structure Decoded structure, with or without the course_structure wrapper.
     * @param callable $isallowedimage fn(string $url): bool deciding whether an image URL may be stored.
     * @return array
     */
    public static function sanitise(array $structure, callable $isallowedimage): array {
        $structure = self::sanitise_level($structure, $isallowedimage);
        if (isset($structure['course_structure']) && is_array($structure['course_structure'])) {
            $structure['course_structure'] = self::sanitise_level($structure['course_structure'], $isallowedimage);
        }
        return $structure;
    }

    /**
     * Sanitise the image key and the module types of one structure level.
     *
     * @param array $level Structure level.
     * @param callable $isallowedimage Image URL predicate.
     * @return array
     */
    private static function sanitise_level(array $level, callable $isallowedimage): array {
        if (array_key_exists('image', $level) && $level['image'] !== null && $level['image'] !== '') {
            if (!is_string($level['image']) || !$isallowedimage($level['image'])) {
                unset($level['image']);
            }
        }

        if (empty($level['sections']) || !is_array($level['sections'])) {
            return $level;
        }
        foreach ($level['sections'] as $sidx => $section) {
            if (!is_array($section) || empty($section['modules']) || !is_array($section['modules'])) {
                continue;
            }
            foreach ($section['modules'] as $midx => $module) {
                if (is_array($module) && array_key_exists('type', $module) && !self::is_valid_module_type($module['type'])) {
                    $level['sections'][$sidx]['modules'][$midx]['type'] = '';
                }
            }
        }
        return $level;
    }
}
