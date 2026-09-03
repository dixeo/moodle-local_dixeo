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
 * Abstract base class for context builders with shared helper methods.
 *
 * Provides common functionality used across all context builder implementations:
 * - Module visibility/accessibility checks
 * - Course metadata formatting
 * - Section name resolution
 * - Adjacent module discovery
 * - Category path resolution using Moodle's built-in API
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
 * Abstract base class providing shared context building utilities.
 */
abstract class abstract_context_builder implements context_builder_interface {
    /** @var html_helper HTML processing helper. */
    protected html_helper $htmlhelper;

    /** @var module_content_extractor Module content extractor. */
    protected module_content_extractor $contentextractor;

    /**
     * Constructor with optional dependency injection.
     *
     * @param html_helper|null $htmlhelper Optional HTML helper.
     * @param module_content_extractor|null $contentextractor Optional content extractor.
     */
    public function __construct(
        ?html_helper $htmlhelper = null,
        ?module_content_extractor $contentextractor = null
    ) {
        $this->htmlhelper = $htmlhelper ?? new html_helper();
        $this->contentextractor = $contentextractor ?? new module_content_extractor($this->htmlhelper);
    }

    /**
     * Check if a module is accessible (visible and available to users).
     *
     * Provides consistent visibility filtering across all context builders.
     *
     * @param \cm_info $cm The course module info.
     * @return bool True if module should be included in context.
     */
    protected function is_module_accessible(\cm_info $cm): bool {
        return $cm->visible && $cm->available;
    }

    /**
     * Filter modules to only include accessible ones.
     *
     * @param array $modules Array of cm_info objects.
     * @return array Filtered array of accessible modules.
     */
    protected function get_accessible_modules(array $modules): array {
        return array_filter($modules, fn($cm) => $this->is_module_accessible($cm));
    }

    /**
     * Get the section name for the AI context.
     *
     * Unnamed sections get a language-neutral label instead of Moodle's localized
     * default ("General", "Généralités"...), which would leak the server or UI
     * language into the context and bias the generated language.
     *
     * @param object $section The section object or section_info.
     * @return string The section name.
     */
    protected function get_section_name(object $section): string {
        if (!empty($section->name)) {
            return format_string($section->name);
        }

        return "Section {$section->section}";
    }

    /**
     * Get course category path using Moodle's built-in API.
     *
     * Uses core_course_category to avoid manual DB queries in loops.
     *
     * @param object $course The course object (must have 'category' property).
     * @return string Category path (e.g., "Science > Life Sciences") or "Unknown".
     */
    protected function get_course_category_path(object $course): string {
        $category = \core_course_category::get($course->category, IGNORE_MISSING);

        if (!$category) {
            return 'Unknown';
        }

        return $category->get_nested_name(false, ' > ');
    }

    /**
     * Get course modules in a specific section.
     *
     * @param \course_modinfo $modinfo The course module info object.
     * @param int $sectionnum The section number.
     * @return array Array of cm_info objects keyed by cmid.
     */
    protected function get_cms_in_section(\course_modinfo $modinfo, int $sectionnum): array {
        $sections = $modinfo->get_sections();
        $cmids = $sections[$sectionnum] ?? [];
        $modules = [];

        foreach ($cmids as $cmid) {
            $modules[$cmid] = $modinfo->get_cm($cmid);
        }

        return $modules;
    }

    /**
     * Find adjacent modules (previous and next) for a given module ID.
     *
     * Returns only visible and available modules.
     *
     * @param array $modules Array of cm_info objects from the section.
     * @param int $cmid The current module ID.
     * @return array ['prev' => cm_info|null, 'next' => cm_info|null]
     */
    protected function find_adjacent_modules(array $modules, int $cmid): array {
        $accessiblemodules = $this->get_accessible_modules($modules);
        $moduleids = array_keys($accessiblemodules);
        $currentindex = array_search($cmid, $moduleids);

        if ($currentindex === false) {
            return ['prev' => null, 'next' => null];
        }

        $prevmodule = null;
        $nextmodule = null;

        if ($currentindex > 0) {
            $prevmodule = $accessiblemodules[$moduleids[$currentindex - 1]];
        }

        if ($currentindex < count($moduleids) - 1) {
            $nextmodule = $accessiblemodules[$moduleids[$currentindex + 1]];
        }

        return ['prev' => $prevmodule, 'next' => $nextmodule];
    }

    /**
     * Build course metadata lines for markdown output.
     *
     * @param object $course The course object.
     * @param int $headinglevel Markdown heading level (default 2 for ##).
     * @return array Lines of markdown for course metadata.
     */
    protected function build_course_metadata_lines(object $course, int $headinglevel = 2): array {
        $heading = str_repeat('#', $headinglevel);
        $lines = [];

        $lines[] = "{$heading} Course";
        $lines[] = "- **Name:** {$course->fullname}";
        $lines[] = "- **Category:** {$this->get_course_category_path($course)}";
        $lines[] = "- **Format:** {$course->format}";

        return $lines;
    }

    /**
     * Get file annotation for resource/folder modules.
     *
     * Returns a parenthesized list of filenames contained in the module,
     * helping the AI map activity names to actual filenames in the vector store.
     *
     * @param \cm_info $cm The course module info.
     * @return string File annotation (e.g., " (files: doc.pdf, notes.docx)") or empty string.
     */
    protected function get_file_annotation(\cm_info $cm): string {
        if (!in_array($cm->modname, ['resource', 'folder'], true)) {
            return '';
        }

        $fs = get_file_storage();
        $context = \context_module::instance($cm->id);
        $component = 'mod_' . $cm->modname;
        $areafiles = $fs->get_area_files($context->id, $component, 'content', 0, 'sortorder', false);

        $filenames = [];
        foreach ($areafiles as $file) {
            if ($file->is_directory()) {
                continue;
            }
            $filenames[] = $file->get_filename();
        }

        if (empty($filenames)) {
            return '';
        }

        return ' (files: ' . implode(', ', $filenames) . ')';
    }

    /**
     * Build adjacent sections context lines.
     *
     * @param \course_modinfo $modinfo The course module info.
     * @param object $course The course object.
     * @param int $sectionnum The current section number.
     * @return array Lines describing adjacent sections.
     */
    protected function build_adjacent_sections(\course_modinfo $modinfo, object $course, int $sectionnum): array {
        $lines = [];

        $prevsection = $sectionnum > 0 ? $modinfo->get_section_info($sectionnum - 1) : null;
        $nextsection = $modinfo->get_section_info($sectionnum + 1);

        if ($prevsection && $prevsection->visible) {
            $prevname = $this->get_section_name($prevsection);
            $lines[] = "- Previous: {$prevname}";
        }

        if ($nextsection && $nextsection->visible) {
            $nextname = $this->get_section_name($nextsection);
            $lines[] = "- Next: {$nextname}";
        }

        return $lines;
    }

    /**
     * Get module position within its section.
     *
     * @param int $cmid The course module ID.
     * @param \course_modinfo $modinfo The course module info object.
     * @param int $sectionnum The section number.
     * @return string Position string (e.g., "3/7 in section").
     */
    protected function get_module_position(int $cmid, \course_modinfo $modinfo, int $sectionnum): string {
        $sectionmodules = $this->get_cms_in_section($modinfo, $sectionnum);
        $accessiblemodules = $this->get_accessible_modules($sectionmodules);
        $moduleids = array_keys($accessiblemodules);
        $position = array_search($cmid, $moduleids);

        if ($position === false) {
            return 'unknown';
        }

        return ($position + 1) . '/' . count($accessiblemodules) . ' in section';
    }

    /**
     * Truncate and return the final context string.
     *
     * @param array $lines Array of markdown lines.
     * @return string Truncated markdown context.
     */
    protected function finalize_context(array $lines): string {
        return $this->htmlhelper->truncate_context(implode("\n", $lines));
    }
}
