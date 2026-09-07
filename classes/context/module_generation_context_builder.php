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
 * Context builder for module generation operations.
 *
 * Constructs markdown context for generating new module content including:
 * - Course and section information
 * - Current module details and content
 * - Adjacent modules in the section for continuity
 *
 * Used primarily when AI needs to understand the surrounding context
 * to generate appropriate new content for a module.
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
 * Builds module generation context markdown for AI processing.
 */
class module_generation_context_builder extends abstract_context_builder {
    use module_data_loader;

    /**
     * Constructor.
     *
     * @param int $cmid The course module ID.
     * @param html_helper|null $htmlhelper Optional HTML helper.
     * @param module_content_extractor|null $contentextractor Optional content extractor.
     * @param bool $includeadjacent Include prev/next modules in section (default true).
     */
    public function __construct(
        int $cmid,
        ?html_helper $htmlhelper = null,
        ?module_content_extractor $contentextractor = null,
        bool $includeadjacent = true
    ) {
        parent::__construct($htmlhelper, $contentextractor);
        $this->cmid = $cmid;
        $this->includeadjacent = $includeadjacent;
    }

    /**
     * Build and return the module generation context markdown.
     *
     * @return string Markdown-formatted module context.
     */
    public function build(): string {
        $this->loadModuleData();

        $lines = [];
        $lines[] = '# Module Context';
        $lines[] = '';
        $lines[] = "## Course: {$this->course->fullname}";
        $lines[] = '';

        $sectionname = $this->get_section_name($this->course, $this->section);
        $lines[] = "## Section: {$sectionname}";
        $lines[] = '';

        if (!empty($this->section->summary)) {
            $lines[] = $this->htmlhelper->clean_html($this->section->summary);
            $lines[] = '';
        }

        $fileannotation = $this->get_file_annotation($this->cminfo);
        $lines[] = "## Module: {$this->cminfo->name}{$fileannotation}";
        $lines[] = "Type: {$this->cminfo->modname}";
        $lines[] = '';

        $content = $this->contentextractor->get_full_content($this->cminfo);

        if (!empty($content)) {
            $lines[] = '### Current Content';
            $lines[] = $content;
            $lines[] = '';
        }

        if ($this->includeadjacent) {
            $lines[] = '### Adjacent Modules in Section';
            $lines[] = '';
            $lines = array_merge($lines, $this->buildAdjacentModulesSimple());
        }

        return $this->finalize_context($lines);
    }

    /**
     * Build simple adjacent modules context (names only).
     *
     * @return array Lines describing adjacent modules.
     */
    private function buildadjacentmodulessimple(): array {
        $lines = [];
        $sectionmodules = $this->get_cms_in_section($this->modinfo, $this->cminfo->sectionnum);
        $adjacent = $this->find_adjacent_modules($sectionmodules, $this->cmid);

        if ($adjacent['prev'] !== null) {
            $prev = $adjacent['prev'];
            $fileannotation = $this->get_file_annotation($prev);
            $lines[] = "- Previous: [{$prev->modname}] {$prev->name}{$fileannotation}";
        }

        if ($adjacent['next'] !== null) {
            $next = $adjacent['next'];
            $fileannotation = $this->get_file_annotation($next);
            $lines[] = "- Next: [{$next->modname}] {$next->name}{$fileannotation}";
        }

        return $lines;
    }
}
