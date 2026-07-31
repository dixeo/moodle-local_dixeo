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
 * Context builder for section-level context.
 *
 * Constructs markdown context for a specific section including:
 * - Course name
 * - Section name and summary
 * - All modules in the section with content
 * - Adjacent sections for navigation context
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
 * Builds section-level context markdown for AI processing.
 */
class section_context_builder extends abstract_context_builder {
    /** @var int The section ID (course_sections.id). */
    private int $sectionid;

    /** @var object|null Cached section record. */
    private ?object $section = null;

    /** @var object|null Cached course object. */
    private ?object $course = null;

    /** @var \course_modinfo|null Cached modinfo. */
    private ?\course_modinfo $modinfo = null;

    /**
     * Constructor.
     *
     * @param int $sectionid The section ID (course_sections.id).
     * @param html_helper|null $htmlhelper Optional HTML helper.
     * @param module_content_extractor|null $contentextractor Optional content extractor.
     */
    public function __construct(
        int $sectionid,
        ?html_helper $htmlhelper = null,
        ?module_content_extractor $contentextractor = null
    ) {
        parent::__construct($htmlhelper, $contentextractor);
        $this->sectionid = $sectionid;
    }

    /**
     * Build and return the section context markdown.
     *
     * @return string Markdown-formatted section context.
     */
    public function build(): string {
        $this->loadSectionData();

        $lines = [];
        $lines[] = '# Section Context';
        $lines[] = '';
        $lines[] = "## Course: {$this->course->fullname}";
        $lines[] = '';

        $sectionname = $this->get_section_name($this->course, $this->section);
        $lines[] = "## Section: {$sectionname}";
        $lines[] = '';

        if (!empty($this->section->summary)) {
            $lines[] = '### Section Summary';
            $lines[] = $this->htmlhelper->clean_html($this->section->summary);
            $lines[] = '';
        }

        $lines = array_merge($lines, $this->buildSectionModules());

        $lines[] = '### Adjacent Sections';
        $lines[] = '';
        $lines = array_merge($lines, $this->build_adjacent_sections(
            $this->modinfo,
            $this->course,
            $this->section->section
        ));

        return $this->finalize_context($lines);
    }

    /**
     * Load section, course, and modinfo data.
     *
     * @return void
     * @throws \dml_exception If section or course not found.
     */
    private function loadsectiondata(): void {
        global $DB;

        if ($this->section === null) {
            $this->section = $DB->get_record('course_sections', ['id' => $this->sectionid], '*', MUST_EXIST);
            $this->course = $DB->get_record('course', ['id' => $this->section->course], '*', MUST_EXIST);
            $this->modinfo = get_fast_modinfo($this->course);
        }
    }

    /**
     * Build module list for the section.
     *
     * @return array Lines of markdown for section modules.
     */
    private function buildsectionmodules(): array {
        $sectioninfo = $this->modinfo->get_section_info($this->section->section);

        if (!$sectioninfo) {
            return [];
        }

        $modules = $this->get_cms_in_section($this->modinfo, $this->section->section);

        if (empty($modules)) {
            return [];
        }

        $lines = [];
        $lines[] = '### Modules in this Section';
        $lines[] = '';

        foreach ($modules as $cm) {
            if (!$this->is_module_accessible($cm)) {
                continue;
            }

            $fileannotation = $this->get_file_annotation($cm);
            $lines[] = "#### [{$cm->modname}] {$cm->name}{$fileannotation}";

            $content = $this->contentextractor->get_full_content($cm);

            if (!empty($content)) {
                $lines[] = $content;
            }

            $lines[] = '';
        }

        return $lines;
    }
}
