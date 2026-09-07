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
 * Factory for creating context builder instances.
 *
 * Provides convenient static methods to create the appropriate context
 * builder based on the use case. Encapsulates builder construction and
 * allows for shared dependency injection.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\context;

use local_dixeo\service\html_helper;
use local_dixeo\service\module_content_extractor;

/**
 * Factory for creating context builders.
 */
class context_builder_factory {
    /** @var html_helper|null Shared HTML helper instance. */
    private static ?html_helper $sharedhtmlhelper = null;

    /** @var module_content_extractor|null Shared content extractor instance. */
    private static ?module_content_extractor $sharedcontentextractor = null;

    /**
     * Create a course context builder.
     *
     * @param int $courseid The course ID.
     * @param int|null $targetsection Target section for tiered detail (teaching mode).
     * @param string $mode Context mode: 'teaching' or 'assessment'.
     * @return course_context_builder The configured builder.
     */
    public static function course(
        int $courseid,
        ?int $targetsection = null,
        string $mode = course_context_builder::MODE_TEACHING
    ): course_context_builder {
        return new course_context_builder(
            $courseid,
            $targetsection,
            $mode,
            self::gethtmlhelper(),
            self::getcontentextractor()
        );
    }

    /**
     * Create a section context builder.
     *
     * @param int $sectionid The section ID (course_sections.id).
     * @return section_context_builder The configured builder.
     */
    public static function section(int $sectionid): section_context_builder {
        return new section_context_builder(
            $sectionid,
            self::gethtmlhelper(),
            self::getcontentextractor()
        );
    }

    /**
     * Create a module generation context builder.
     *
     * @param int $cmid The course module ID.
     * @param bool $includeadjacent Include prev/next modules in section (default true).
     * @return module_generation_context_builder The configured builder.
     */
    public static function modulegeneration(
        int $cmid,
        bool $includeadjacent = true
    ): module_generation_context_builder {
        return new module_generation_context_builder(
            $cmid,
            self::gethtmlhelper(),
            self::getcontentextractor(),
            $includeadjacent
        );
    }

    /**
     * Create a module edit context builder.
     *
     * @param int $cmid The course module ID.
     * @param string|null $autosavedrafthtml Optional HTML from tiny_autosave (null = use saved module content only).
     * @return module_edit_context_builder The configured builder.
     */
    public static function moduleedit(int $cmid, ?string $autosavedrafthtml = null): module_edit_context_builder {
        return new module_edit_context_builder(
            $cmid,
            self::gethtmlhelper(),
            self::getcontentextractor(),
            $autosavedrafthtml
        );
    }

    /**
     * Create a slide edit context builder.
     *
     * @param int $cmid The slideshow course module ID.
     * @param int $slideid The slideshow_slide row ID being edited.
     * @return slide_edit_context_builder The configured builder.
     */
    public static function slide_edit(int $cmid, int $slideid): slide_edit_context_builder {
        return new slide_edit_context_builder(
            $cmid,
            $slideid,
            self::gethtmlhelper(),
            self::getcontentextractor()
        );
    }

    /**
     * Build course context directly (convenience method).
     *
     * @param int $courseid The course ID.
     * @param int|null $targetsection Target section for tiered detail.
     * @param string $mode Context mode: 'teaching' or 'assessment'.
     * @return string The built markdown context.
     */
    public static function buildcoursecontext(
        int $courseid,
        ?int $targetsection = null,
        string $mode = course_context_builder::MODE_TEACHING
    ): string {
        return self::course($courseid, $targetsection, $mode)->build();
    }

    /**
     * Build section context directly (convenience method).
     *
     * @param int $sectionid The section ID.
     * @return string The built markdown context.
     */
    public static function buildsectioncontext(int $sectionid): string {
        return self::section($sectionid)->build();
    }

    /**
     * Build section context by course ID and section number.
     *
     * @param int $courseid The course ID.
     * @param int $sectionnum The section number (course_sections.section).
     * @return string The built markdown context.
     */
    public static function buildsectioncontextfornumber(int $courseid, int $sectionnum): string {
        global $DB;

        $section = $DB->get_record(
            'course_sections',
            ['course' => $courseid, 'section' => $sectionnum],
            'id',
            MUST_EXIST
        );

        return self::buildsectioncontext((int) $section->id);
    }

    /**
     * Build focused module context for practice quiz activity scope.
     *
     * @param int $cmid The course module ID.
     * @return string The built markdown context.
     */
    public static function buildmodulepracticecontext(int $cmid): string {
        return self::modulegeneration($cmid, false)->build();
    }

    /**
     * Build module generation context directly (convenience method).
     *
     * @param int $cmid The course module ID.
     * @return string The built markdown context.
     */
    public static function buildmodulegenerationcontext(int $cmid): string {
        return self::modulegeneration($cmid)->build();
    }

    /**
     * Build module edit context directly (convenience method).
     *
     * @param int $cmid The course module ID.
     * @param string|null $autosavedrafthtml Optional HTML from tiny_autosave (null = use saved module content only).
     * @return string The built markdown context.
     */
    public static function buildmoduleeditcontext(int $cmid, ?string $autosavedrafthtml = null): string {
        return self::moduleedit($cmid, $autosavedrafthtml)->build();
    }

    /**
     * Build slide edit context directly (convenience method).
     *
     * @param int $cmid The slideshow course module ID.
     * @param int $slideid The slideshow_slide row ID being edited.
     * @return string The built markdown context.
     */
    public static function build_slide_edit_context(int $cmid, int $slideid): string {
        return self::slide_edit($cmid, $slideid)->build();
    }

    /**
     * Build an edit context for any supported module, dispatching to the
     * appropriate specialised builder based on the module type.
     *
     * This is the single entry point that callers (AJAX endpoints, services)
     * should use — they pass the cmid and optional sub-id and do not need to
     * know whether the target is a simple module or a composite (slideshow).
     *
     * @param int $cmid The course module ID.
     * @param int|null $subid Optional child record ID for composite modules.
     * @param string|null $autosavedrafthtml Optional draft HTML from tiny_autosave.
     * @return string The built markdown context.
     *
     * @throws \coding_exception If a composite module is targeted without a subid.
     */
    public static function build_edit_context(
        int $cmid,
        ?int $subid = null,
        ?string $autosavedrafthtml = null
    ): string {
        $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);

        if ($cm->modname === 'slideshow') {
            if ($subid === null || $subid <= 0) {
                throw new \coding_exception('subid (slideid) is required when building a slideshow edit context');
            }
            return self::build_slide_edit_context($cmid, $subid);
        }

        return self::buildmoduleeditcontext($cmid, $autosavedrafthtml);
    }

    /**
     * Build context for structure-based module fill.
     *
     * Combines course context with module metadata (title/summary) so the AI
     * generates content coherent with the planned module identity.
     * Used when creating modules from a course structure where name/intro
     * are already defined and only content needs to be generated.
     *
     * @param int $courseid The course ID.
     * @param int|null $targetsection Target section for tiered detail.
     * @param string $mode Context mode: 'teaching' or 'assessment'.
     * @param string $title The module title from the course structure.
     * @param string $summary The module summary from the course structure.
     * @return string The built markdown context with module metadata prepended.
     */
    public static function buildmodulefillcontext(
        int $courseid,
        ?int $targetsection,
        string $mode,
        string $title,
        string $summary = ''
    ): string {
        $coursecontext = self::buildcoursecontext($courseid, $targetsection, $mode);

        $lines = ['## Module to Fill'];
        $lines[] = "- **Title:** {$title}";
        if (!empty($summary)) {
            $lines[] = "- **Summary:** {$summary}";
        }
        $lines[] = '';

        return implode("\n", $lines) . $coursecontext;
    }

    /**
     * Get or create shared HTML helper.
     *
     * @return html_helper The shared instance.
     */
    private static function gethtmlhelper(): html_helper {
        if (self::$sharedhtmlhelper === null) {
            self::$sharedhtmlhelper = new html_helper();
        }

        return self::$sharedhtmlhelper;
    }

    /**
     * Get or create shared content extractor.
     *
     * @return module_content_extractor The shared instance.
     */
    private static function getcontentextractor(): module_content_extractor {
        if (self::$sharedcontentextractor === null) {
            self::$sharedcontentextractor = new module_content_extractor(self::gethtmlhelper());
        }

        return self::$sharedcontentextractor;
    }

    /**
     * Reset shared instances (useful for testing).
     *
     * @return void
     */
    public static function reset(): void {
        self::$sharedhtmlhelper = null;
        self::$sharedcontentextractor = null;
    }
}
