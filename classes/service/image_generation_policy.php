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
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

namespace local_dixeo\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Resolves image-generation availability from local_dixeo settings.
 *
 * Course, section, and embedded content each use a mode select: disabled, generate only, or generate+edit.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class image_generation_policy {
    public const ENTITY_COURSE = 'course';
    public const ENTITY_SECTION = 'section';
    public const ENTITY_CONTENT = 'content';

    public const ACTION_GENERATE = 'generate';
    public const ACTION_EDIT = 'edit';

    public const MODE_DISABLED = 'disabled';
    public const MODE_GENERATE = 'generate';
    public const MODE_GENERATE_EDIT = 'generate_edit';

    /**
     * Check whether image generation/edit is globally enabled.
     *
     * @return bool
     */
    public static function is_globally_enabled(): bool {
        return (bool) get_config('local_dixeo', 'image_generation_enabled');
    }

    /**
     * Check whether one entity/action pair is allowed.
     *
     * @param string $entity course|section|content
     * @param string $action generate|edit
     * @return bool
     */
    public static function is_enabled(string $entity, string $action): bool {
        if (!self::is_globally_enabled()) {
            return false;
        }

        $mode = self::get_mode_for_entity($entity);
        if ($mode === self::MODE_DISABLED) {
            return false;
        }

        if ($action === self::ACTION_GENERATE) {
            return true;
        }
        if ($action === self::ACTION_EDIT) {
            return $mode === self::MODE_GENERATE_EDIT;
        }

        throw new \coding_exception('Unsupported image generation action: ' . $action);
    }

    /**
     * Throw when one entity/action pair is disabled.
     *
     * @param string $entity course|section|content
     * @param string $action generate|edit
     * @return void
     */
    public static function assert_enabled(string $entity, string $action): void {
        if (!self::is_enabled($entity, $action)) {
            throw new \moodle_exception('dixeo_image_generation_disabled', 'local_dixeo');
        }
    }

    /**
     * @param string $entity course|section|content
     * @return string One of {@see self::MODE_DISABLED}, {@see self::MODE_GENERATE}, {@see self::MODE_GENERATE_EDIT}.
     */
    private static function get_mode_for_entity(string $entity): string {
        $entity = trim($entity);
        $keys = [
            self::ENTITY_COURSE => 'image_generation_course_mode',
            self::ENTITY_SECTION => 'image_generation_section_mode',
            self::ENTITY_CONTENT => 'image_generation_content_mode',
        ];
        if (!isset($keys[$entity])) {
            throw new \coding_exception('Unsupported image generation entity: ' . $entity);
        }

        $key = $keys[$entity];
        $raw = get_config('local_dixeo', $key);
        $mode = is_string($raw) ? trim($raw) : '';

        $allowed = [self::MODE_DISABLED, self::MODE_GENERATE, self::MODE_GENERATE_EDIT];
        if (in_array($mode, $allowed, true)) {
            return $mode;
        }

        return self::MODE_GENERATE_EDIT;
    }
}
