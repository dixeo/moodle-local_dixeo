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


/**
 * Resolves image-generation availability from the Dixeo settings catalogue.
 *
 * image_generation_enabled follows the Dixeo master switch: Off at the Dixeo layer is
 * off in every course, including a course that stores On. A course can turn the feature
 * off only while the Dixeo layer is On. Course, section, and content modes are read for
 * the course when the context is in a course, and for the Dixeo layer otherwise.
 * Externals should also enforce capabilities ({@see image\content\capability})
 * and filter UI gates ({@see \filter_dixeo_imageeditor\adapter\feature_gate}).
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class policy {
    /** @var string Constant ENTITY_COURSE. */
    public const ENTITY_COURSE = 'course';
    /** @var string Constant ENTITY_SECTION. */
    public const ENTITY_SECTION = 'section';
    /** @var string Constant ENTITY_CONTENT. */
    /** @var string Entity key for content-level image generation. */
    public const ENTITY_CONTENT = 'content';

    /** @var string Constant ACTION_GENERATE. */
    public const ACTION_GENERATE = 'generate';
    /** @var string Constant ACTION_EDIT. */
    public const ACTION_EDIT = 'edit';

    /** @var string Constant MODE_DISABLED. */
    public const MODE_DISABLED = 'disabled';
    /** @var string Constant MODE_GENERATE. */
    public const MODE_GENERATE = 'generate';
    /** @var string Constant MODE_GENERATE_EDIT. */
    public const MODE_GENERATE_EDIT = 'generate_edit';

    /**
     * Whether the Dixeo layer allows image generation.
     *
     * This ignores course overrides. A course check belongs in {@see self::is_enabled()}.
     *
     * @return bool
     */
    public static function is_globally_enabled(): bool {
        return self::allows_image_generation(\context_system::instance());
    }

    /**
     * Check whether one entity/action pair is allowed.
     *
     * @param string $entity course|section|content
     * @param string $action generate|edit
     * @param \context|null $context Course context, a context inside a course, or null for the Dixeo layer.
     * @return bool
     */
    public static function is_enabled(string $entity, string $action, ?\context $context = null): bool {
        $context = $context ?? \context_system::instance();
        if (!self::allows_image_generation($context)) {
            return false;
        }

        $mode = self::get_mode_for_entity($entity, $context);
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
     * @param \context|null $context Course context, a context inside a course, or null for the Dixeo layer.
     * @return void
     */
    public static function assert_enabled(string $entity, string $action, ?\context $context = null): void {
        if (!self::is_enabled($entity, $action, $context)) {
            throw new \moodle_exception('dixeo_image_generation_disabled', 'local_dixeo');
        }
    }

    /**
     * Dixeo off blocks every course. A course may only turn the feature off while Dixeo is on.
     *
     * @param \context $context System, course, or a context inside a course.
     * @return bool
     */
    private static function allows_image_generation(\context $context): bool {
        $config = \local_dixeo\external\service_factory::get_settings_config();
        $system = \context_system::instance();
        if ($config->get('local_dixeo', 'image_generation_enabled', $system)->get_value() !== '1') {
            return false;
        }
        $course = $context->get_course_context(false);
        if (!$course instanceof \context) {
            return true;
        }
        return $config->get('local_dixeo', 'image_generation_enabled', $course)->get_value() === '1';
    }

    /**
     * Context whose stored mode applies: the course when one is available, otherwise the Dixeo layer.
     *
     * @param \context $context Requested context.
     * @return \context
     */
    private static function mode_context(\context $context): \context {
        $course = $context->get_course_context(false);
        if ($course instanceof \context) {
            return $course;
        }
        return \context_system::instance();
    }

    /**
     * Get mode for entity.
     *
     * @param string $entity course|section|content
     * @param \context $context Course context, a context inside a course, or the system context.
     * @return string One of {@see self::MODE_DISABLED}, {@see self::MODE_GENERATE}, {@see self::MODE_GENERATE_EDIT}.
     */
    private static function get_mode_for_entity(string $entity, \context $context): string {
        $entity = trim($entity);
        $keys = [
            self::ENTITY_COURSE => 'image_generation_course_mode',
            self::ENTITY_SECTION => 'image_generation_section_mode',
            self::ENTITY_CONTENT => 'image_generation_content_mode',
        ];
        if (!isset($keys[$entity])) {
            throw new \coding_exception('Unsupported image generation entity: ' . $entity);
        }

        $value = \local_dixeo\external\service_factory::get_settings_config()
            ->get('local_dixeo', $keys[$entity], self::mode_context($context))
            ->get_value();
        $mode = trim($value);

        $allowed = [self::MODE_DISABLED, self::MODE_GENERATE, self::MODE_GENERATE_EDIT];
        if (in_array($mode, $allowed, true)) {
            return $mode;
        }

        // Fail safe: an invalid stored value disables the feature.
        return self::MODE_DISABLED;
    }
}
