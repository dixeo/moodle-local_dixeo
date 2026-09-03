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
 * Context builder for module editing/regeneration operations.
 *
 * Constructs markdown context specifically designed for AI edit operations:
 * - Surrounding context (course, section, adjacent modules with excerpts)
 * - Module to edit with clear markers for AI processing
 * - Current content with delimiters for identification
 *
 * The output format is optimized for AI to understand what needs editing
 * and what context to use for generating replacement content.
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
 * Builds module edit context markdown for AI processing.
 */
class module_edit_context_builder extends abstract_context_builder {
    use module_data_loader;

    /** @var string|null Trimmed HTML from tiny_autosave, or null to use DB-only content. */
    private ?string $autosavedrafthtml;

    /**
     * Constructor.
     *
     * @param int $cmid The course module ID.
     * @param html_helper|null $htmlhelper Optional HTML helper.
     * @param module_content_extractor|null $contentextractor Optional content extractor.
     * @param string|null $autosavedrafthtml Optional draft HTML from tiny_autosave.
     */
    public function __construct(
        int $cmid,
        ?html_helper $htmlhelper = null,
        ?module_content_extractor $contentextractor = null,
        ?string $autosavedrafthtml = null
    ) {
        parent::__construct($htmlhelper, $contentextractor);
        $this->cmid = $cmid;
        $this->autosavedrafthtml = $autosavedrafthtml;
    }

    /**
     * Build and return the module edit context markdown.
     *
     * @return string Markdown-formatted edit context.
     */
    public function build(): string {
        $this->loadModuleData();

        $lines = [];
        $lines[] = '# Edit Context';
        $lines[] = '';

        // Surrounding context section.
        $lines[] = '## SURROUNDING_CONTEXT';
        $lines[] = '';
        $lines = array_merge($lines, $this->buildSurroundingContext());

        // Separator.
        $lines[] = '---';
        $lines[] = '';

        // Module to edit section.
        $lines = array_merge($lines, $this->buildModuleToEditSection());

        return $this->finalize_context($lines);
    }

    /**
     * Build surrounding context (course, section, adjacent modules).
     *
     * @return array Lines of markdown for surrounding context.
     */
    private function buildsurroundingcontext(): array {
        $lines = [];

        // Course metadata with total sections count.
        $lines[] = '### Course';
        $lines[] = "- **Name:** {$this->course->fullname}";
        $lines[] = "- **Category:** {$this->get_course_category_path($this->course)}";
        $lines[] = "- **Format:** {$this->course->format}";

        $totalsections = count($this->modinfo->get_section_info_all());
        $lines[] = "- **Total Sections:** {$totalsections}";
        $lines[] = '';

        // Current section info.
        $sectionname = $this->get_section_name($this->section);
        $lines[] = "### Current Section: {$sectionname} (Section {$this->section->section})";

        if (!empty($this->section->summary)) {
            $lines[] = $this->htmlhelper->clean_html($this->section->summary);
        }
        $lines[] = '';

        // Adjacent modules with excerpts.
        $lines = array_merge($lines, $this->buildAdjacentModulesDetailed());

        return $lines;
    }

    /**
     * Build module to edit section with markers.
     *
     * @return array Lines of markdown for module to edit.
     */
    private function buildmoduletoeditsection(): array {
        $lines = [];

        $lines[] = '## MODULE_TO_EDIT';
        $lines[] = "- **Type:** {$this->cminfo->modname}";
        $lines[] = "- **Name:** {$this->cminfo->name}";

        $position = $this->get_module_position($this->cmid, $this->modinfo, $this->cminfo->sectionnum);
        $lines[] = "- **Position:** {$position}";
        $lines[] = '';

        // Current content with clear markers for AI.
        $lines[] = '## CURRENT_CONTENT';
        $lines[] = '>>> MODULE TO EDIT <<<';

        $content = $this->contentextractor->get_full_content_for_edit($this->cminfo, $this->autosavedrafthtml);

        if (!empty($content)) {
            $lines[] = $content;
        }

        $lines[] = '>>> END MODULE TO EDIT <<<';

        return $lines;
    }

    /**
     * Build detailed adjacent modules context with excerpts.
     *
     * @return array Lines describing adjacent modules with content excerpts.
     */
    private function buildadjacentmodulesdetailed(): array {
        $lines = [];
        $sectionmodules = $this->get_cms_in_section($this->modinfo, $this->cminfo->sectionnum);
        $adjacent = $this->find_adjacent_modules($sectionmodules, $this->cmid);

        if ($adjacent['prev'] !== null) {
            $lines = array_merge($lines, $this->buildModuleDetailBlock('Previous Module', $adjacent['prev']));
        }

        if ($adjacent['next'] !== null) {
            $lines = array_merge($lines, $this->buildModuleDetailBlock('Next Module', $adjacent['next']));
        }

        return $lines;
    }

    /**
     * Build a detailed module block with type, name, and excerpt.
     *
     * @param string $label Block label (e.g., "Previous Module").
     * @param \cm_info $cm The course module info.
     * @return array Lines for the module block.
     */
    private function buildmoduledetailblock(string $label, \cm_info $cm): array {
        $lines = [];
        $lines[] = "### {$label}";
        $fileannotation = $this->get_file_annotation($cm);
        $lines[] = "- **Type:** {$cm->modname}";
        $lines[] = "- **Name:** {$cm->name}{$fileannotation}";

        $excerpt = $this->contentextractor->get_excerpt($cm);

        if ($excerpt !== null) {
            $lines[] = "- **Excerpt:** {$excerpt}";
        }

        $lines[] = '';

        return $lines;
    }
}
