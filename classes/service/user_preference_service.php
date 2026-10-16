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

/**
 * Named user preferences edited from the Dixeo settings modal.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service;

/**
 * Reads and stores the user preferences offered in Dixeo settings.
 *
 * Each preference is a Moodle user preference. Adding one means a new name in this
 * catalogue, not a new web service.
 */
class user_preference_service {
    /** @var string Generation language for quizzes and lessons. */
    public const GENERATION_LANGUAGE = 'generation_language';

    /**
     * Preferences the user can edit, including ones the modal may hide.
     *
     * @param int $userid
     * @return array List of name, value, show, and options.
     */
    public static function get_for_user(int $userid): array {
        return [
            self::generation_language($userid),
        ];
    }

    /**
     * Store the submitted preferences for one user.
     *
     * Unknown names and invalid values are rejected. Preferences omitted from the
     * list stay unchanged.
     *
     * @param int $userid User id.
     * @param array $preferences Submitted preferences. Each item has name and value.
     */
    public static function store_for_user(int $userid, array $preferences): void {
        foreach ($preferences as $preference) {
            $name = (string) ($preference['name'] ?? '');
            $value = (string) ($preference['value'] ?? '');
            self::store_one($userid, $name, $value);
        }
    }

    /**
     * Store one catalogue preference.
     *
     * @param int $userid
     * @param string $name Catalogue name.
     * @param string $value Submitted value.
     */
    private static function store_one(int $userid, string $name, string $value): void {
        switch ($name) {
            case self::GENERATION_LANGUAGE:
                generation_language_helper::store_for_user($userid, $value);
                return;
            default:
                throw new \invalid_parameter_exception('name');
        }
    }

    /**
     * Current generation language and the installed choices.
     *
     * @param int $userid
     * @return array Name, value, show, and options.
     */
    private static function generation_language(int $userid): array {
        $built = generation_language_helper::build_options($userid);
        $options = [];
        foreach ($built['languages'] as $language) {
            $options[] = [
                'value' => $language['code'],
                'label' => $language['name'],
            ];
        }

        return [
            'name' => self::GENERATION_LANGUAGE,
            'value' => $built['defaultlanguage'],
            'show' => count($options) > 1,
            'options' => $options,
        ];
    }
}
