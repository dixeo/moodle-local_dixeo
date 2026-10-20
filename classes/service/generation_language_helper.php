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
 * Resolve and localize generation output language for AI instructions.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service;


/**
 * Helpers for tutor generation language selection.
 */
class generation_language_helper {
    /** @var string User preference for quiz and lesson generation language. */
    public const PREFERENCE = 'local_dixeo_generation_language';

    /**
     * Generation language for a user.
     *
     * A stored preference wins when that language is still installed. Otherwise the
     * user's interface language is used.
     *
     * @param int $userid
     * @return string Moodle language code.
     */
    public static function default_for_user(int $userid): string {
        $translations = get_string_manager()->get_list_of_translations();
        if ($userid > 0) {
            $stored = get_user_preferences(self::PREFERENCE, null, $userid);
            if (is_string($stored) && $stored !== '' && isset($translations[$stored])) {
                return $stored;
            }
        }

        $user = \core_user::get_user($userid, 'id,lang', IGNORE_MISSING);
        if ($user && !empty($user->lang) && $user->lang !== 'auto' && isset($translations[$user->lang])) {
            return $user->lang;
        }

        $current = current_language();
        if (isset($translations[$current])) {
            return $current;
        }

        return array_key_first($translations) ?: 'en';
    }

    /**
     * Store the generation language for a user.
     *
     * @param int $userid
     * @param string $language Installed Moodle language code.
     * @return string Stored language code.
     */
    public static function store_for_user(int $userid, string $language): string {
        $translations = get_string_manager()->get_list_of_translations();
        $language = clean_param(trim($language), PARAM_LANG);
        if ($language === '' || !isset($translations[$language])) {
            throw new \invalid_parameter_exception('language');
        }
        set_user_preference(self::PREFERENCE, $language, $userid);
        return $language;
    }

    /**
     * Validate a requested language code against installed Moodle languages.
     *
     * @param string $language Requested language code (empty = user default).
     * @param int $userid User id when falling back to preference.
     * @return string Valid Moodle language code.
     */
    public static function resolve(string $language, int $userid = 0): string {
        $translations = get_string_manager()->get_list_of_translations();
        $language = clean_param(trim($language), PARAM_LANG);
        if ($language !== '' && isset($translations[$language])) {
            return $language;
        }

        if ($userid > 0) {
            return self::default_for_user($userid);
        }

        $current = current_language();
        return isset($translations[$current]) ? $current : (array_key_first($translations) ?: 'en');
    }

    /**
     * Human-readable language name for a Moodle language code.
     *
     * @param string $langcode
     * @return string
     */
    public static function display_name(string $langcode): string {
        $translations = get_string_manager()->get_list_of_translations();
        return $translations[$langcode] ?? $langcode;
    }

    /**
     * Fetch a local_dixeo string in a specific language.
     *
     * @param string $identifier
     * @param string|object|null $a
     * @param string $lang
     * @return string
     */
    public static function get_string(string $identifier, $a, string $lang): string {
        return get_string_manager()->get_string($identifier, 'local_dixeo', $a, $lang);
    }

    /**
     * Append the mandatory output-language line to generation instructions.
     *
     * @param string $instructions
     * @param string $lang Moodle language code for generated content.
     * @return string
     */
    public static function append_output_language_instruction(string $instructions, string $lang): string {
        $langname = self::display_name($lang);
        $line = self::get_string('generation_output_language', (object) [
            'language' => $langname,
        ], $lang);

        return rtrim($instructions) . "\n\n" . $line;
    }

    /**
     * Build language options for the generation language preference.
     *
     * @param int $userid
     * @return array{defaultlanguage: string, languages: array<int, array{code: string, name: string, selected: bool}>}
     */
    public static function build_options(int $userid): array {
        $translations = get_string_manager()->get_list_of_translations();
        $default = self::default_for_user($userid);
        $languages = [];

        foreach ($translations as $code => $name) {
            $languages[] = [
                'code' => $code,
                'name' => $name,
                'selected' => $code === $default,
            ];
        }

        return [
            'defaultlanguage' => $default,
            'languages' => $languages,
        ];
    }
}
