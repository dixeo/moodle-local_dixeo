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
 * Context builder for editing a single slide in a slideshow module.
 *
 * Produces markdown context tailored for per-slide AI edits:
 * - Course + section metadata
 * - Parent slideshow metadata (name, intro)
 * - All sibling slide titles with a marker on the one being edited
 * - Current slide HTML under **Content:** markers
 *
 * Pluginfile tokens in the slide content are rewritten to absolute URLs
 * before being sent to the AI so the model preserves them verbatim; the
 * standard save pipeline (file_save_draft_area_files) converts them back
 * to @@PLUGINFILE@@ on write.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\context;

use context_module;
use local_dixeo\service\html_helper;
use local_dixeo\service\module_content_extractor;
use moodle_exception;

/**
 * Builds markdown edit context for a single slideshow slide.
 */
class slide_edit_context_builder extends abstract_context_builder {
    use module_data_loader;

    /** @var int The slideshow_slide row ID being edited. */
    private int $slideid;

    /** @var object|null The slideshow_slide record. */
    private ?object $slide = null;

    /** @var object|null The parent slideshow record. */
    private ?object $slideshow = null;

    /** @var array<int, object>|null All sibling slides (including the one being edited). */
    private ?array $siblingslides = null;

    /**
     * Constructor.
     *
     * @param int $cmid Course module id of the slideshow.
     * @param int $slideid slideshow_slide row id being edited.
     * @param html_helper|null $htmlhelper Optional HTML helper.
     * @param module_content_extractor|null $contentextractor Optional content extractor.
     */
    public function __construct(
        int $cmid,
        int $slideid,
        ?html_helper $htmlhelper = null,
        ?module_content_extractor $contentextractor = null
    ) {
        parent::__construct($htmlhelper, $contentextractor);
        $this->cmid = $cmid;
        $this->slideid = $slideid;
    }

    /**
     * Build the markdown context for editing the configured slide.
     *
     * @return string
     */
    public function build(): string {
        // Inherited from module_data_loader trait (camelCase — pre-existing).
        $this->loadModuleData();
        $this->load_slide_data();

        if ($this->cminfo->modname !== 'slideshow') {
            throw new moodle_exception('error:notslideshow', 'local_dixeo');
        }

        if ((int) $this->slide->slideshow !== (int) $this->cminfo->instance) {
            throw new moodle_exception('error:slidenotinslideshow', 'local_dixeo');
        }

        $lines = [];
        $lines[] = '# Edit Context';
        $lines[] = '';

        $lines[] = '## SURROUNDING_CONTEXT';
        $lines[] = '';
        $lines = array_merge($lines, $this->build_surrounding_context());

        $lines[] = '---';
        $lines[] = '';

        $lines = array_merge($lines, $this->build_slide_to_edit_section());

        return $this->finalize_context($lines);
    }

    /**
     * Load slide, parent slideshow and sibling slides.
     */
    private function load_slide_data(): void {
        global $DB;

        $this->slide = $DB->get_record('slideshow_slide', ['id' => $this->slideid], '*', MUST_EXIST);
        $this->slideshow = $DB->get_record('slideshow', ['id' => $this->cminfo->instance], '*', MUST_EXIST);
        $this->siblingslides = $DB->get_records(
            'slideshow_slide',
            ['slideshow' => $this->cminfo->instance],
            'sortorder ASC',
            'id, name, sortorder, hidden'
        );
    }

    /**
     * Build surrounding context: course, section, parent slideshow, sibling slides.
     *
     * @return array<int, string>
     */
    private function build_surrounding_context(): array {
        $lines = [];

        $lines[] = '### Course';
        $lines[] = "- **Name:** {$this->course->fullname}";
        $lines[] = "- **Category:** {$this->get_course_category_path($this->course)}";
        $lines[] = "- **Format:** {$this->course->format}";
        $lines[] = '';

        $sectionname = $this->get_section_name($this->section);
        $lines[] = "### Current Section: {$sectionname} (Section {$this->section->section})";
        if (!empty($this->section->summary)) {
            $lines[] = $this->htmlhelper->clean_html($this->section->summary);
        }
        $lines[] = '';

        $lines[] = '### Parent Slideshow';
        $lines[] = "- **Name:** {$this->slideshow->name}";
        if (!empty($this->slideshow->intro)) {
            $lines[] = '- **Intro:** ' . $this->htmlhelper->clean_html($this->slideshow->intro);
        }
        $lines[] = '';

        $lines[] = '### All slides in this slideshow (in order)';
        foreach ($this->siblingslides as $sibling) {
            $name = !empty($sibling->name) ? $sibling->name : '(untitled)';
            $hidden = !empty($sibling->hidden) ? ' (hidden)' : '';
            $marker = ((int) $sibling->id === $this->slideid) ? ' ← **EDITING THIS ONE**' : '';
            $lines[] = "- [slide {$sibling->sortorder}] {$name}{$hidden}{$marker}";
        }
        $lines[] = '';

        return $lines;
    }

    /**
     * Build the >>> MODULE TO EDIT <<< section with the slide's current HTML content.
     *
     * @return array<int, string>
     */
    private function build_slide_to_edit_section(): array {
        $lines = [];

        $slidetitle = !empty($this->slide->name) ? $this->slide->name : '(untitled)';
        $totalslides = $this->siblingslides === null ? 0 : count($this->siblingslides);

        $lines[] = '## MODULE_TO_EDIT';
        $lines[] = '- **Type:** slideshow (editing a single slide)';
        $lines[] = "- **Slideshow:** {$this->slideshow->name}";
        $lines[] = "- **Slide title:** {$slidetitle}";
        $lines[] = "- **Slide position:** slide {$this->slide->sortorder} of {$totalslides}";
        $lines[] = '';

        $lines[] = '## CURRENT_CONTENT';
        $lines[] = '>>> MODULE TO EDIT <<<';

        $rewritten = file_rewrite_pluginfile_urls(
            (string) $this->slide->content,
            'pluginfile.php',
            context_module::instance($this->cmid)->id,
            'mod_slideshow',
            'content',
            $this->slideid
        );

        $lines[] = "**Content:**\n" . $rewritten;
        $lines[] = '>>> END MODULE TO EDIT <<<';

        return $lines;
    }
}
