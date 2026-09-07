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
 * Context builder for full course context.
 *
 * Constructs markdown context from an entire course structure including
 * all sections and modules. Supports two context modes:
 * - Teaching mode: Tiered detail by proximity to target section
 * - Assessment mode: Full content everywhere for quiz/glossary generation
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
 * Builds course-wide context markdown for AI processing.
 */
class course_context_builder extends abstract_context_builder {
    /**
     * Context mode for teaching content (page, label, book).
     * Uses tiered approach: full detail for target, preview for adjacent, titles for rest.
     */
    public const MODE_TEACHING = 'teaching';

    /**
     * Context mode for assessment content (quiz, glossary).
     * Provides full content everywhere so AI knows what to test/reference.
     */
    public const MODE_ASSESSMENT = 'assessment';

    /** @var int Preview length for adjacent section modules. */
    private const CONTENT_LENGTH_PREVIEW = 500;

    /** @var int The course ID. */
    private int $courseid;

    /** @var int|null Target section number for tiered detail (teaching mode). */
    private ?int $targetsection;

    /** @var string Context mode: MODE_TEACHING or MODE_ASSESSMENT. */
    private string $mode;

    /** @var object|null Cached course object. */
    private ?object $course = null;

    /** @var \course_modinfo|null Cached modinfo. */
    private ?\course_modinfo $modinfo = null;

    /** @var array|null Injected course plan for structure-aware context generation. */
    private ?array $courseplan = null;

    /**
     * Constructor.
     *
     * @param int $courseid The course ID.
     * @param int|null $targetsection Target section number (used in teaching mode).
     * @param string $mode Context mode: MODE_TEACHING or MODE_ASSESSMENT.
     * @param html_helper|null $htmlhelper Optional HTML helper.
     * @param module_content_extractor|null $contentextractor Optional content extractor.
     */
    public function __construct(
        int $courseid,
        ?int $targetsection = null,
        string $mode = self::MODE_TEACHING,
        ?html_helper $htmlhelper = null,
        ?module_content_extractor $contentextractor = null
    ) {
        parent::__construct($htmlhelper, $contentextractor);
        $this->courseid = $courseid;
        $this->targetsection = $targetsection;
        $this->mode = $mode;
    }

    /**
     * Inject a course plan for structure-aware context generation.
     *
     * Called by block_dixeo_coursegen when generating modules from a plan so the AI
     * receives the full planned structure (with [COMPLETED]/[GENERATING]/[PLANNED] markers)
     * in addition to the actual Moodle course content that already exists.
     *
     * @param array $plan The decoded course structure plan array.
     * @return static Fluent interface.
     */
    public function with_course_plan(array $plan): static {
        $this->courseplan = $plan;
        return $this;
    }

    /**
     * Build and return the course context markdown.
     *
     * @return string Markdown-formatted course context.
     */
    public function build(): string {
        $this->loadCourseData();

        $lines = [];
        $lines[] = '# Course Context';
        $lines[] = '';

        $lines = array_merge($lines, $this->build_course_metadata_lines($this->course));
        $lines[] = '';

        if (!empty($this->course->summary)) {
            $summary = $this->htmlhelper->clean_html($this->course->summary);
            $lines[] = '### Course Summary';
            $lines[] = $summary;
            $lines[] = '';
        }

        $lines[] = '## Course Structure';
        $lines[] = '';

        $lines = array_merge($lines, $this->buildSectionsContext());

        // Append the planned structure so the AI understands what is still to come.
        if ($this->courseplan !== null) {
            $lines[] = '';
            $lines = array_merge($lines, $this->buildPlanContext());
        }

        return $this->finalize_context($lines);
    }

    /**
     * Load course and modinfo data.
     *
     * @return void
     * @throws \dml_exception If course not found.
     */
    private function loadcoursedata(): void {
        global $DB;

        if ($this->course === null) {
            $this->course = $DB->get_record('course', ['id' => $this->courseid], '*', MUST_EXIST);
            $this->modinfo = get_fast_modinfo($this->course);
        }
    }

    /**
     * Build context for all visible sections.
     *
     * @return array Lines of markdown for all sections.
     */
    private function buildsectionscontext(): array {
        $lines = [];

        foreach ($this->modinfo->get_section_info_all() as $section) {
            if (!$section->visible) {
                continue;
            }

            $sectionnum = $section->section;
            $sectionname = $this->get_section_name($section);
            $detaillevel = $this->getSectionDetailLevel($sectionnum);

            // Mark target section clearly (only in teaching mode).
            if ($this->mode === self::MODE_TEACHING && $this->targetsection === $sectionnum) {
                $lines[] = "### {$sectionname} ← TARGET SECTION";
            } else {
                $lines[] = "### {$sectionname}";
            }

            if (!empty($section->summary)) {
                $summary = $this->htmlhelper->clean_html($section->summary);
                $lines[] = $summary;
            }

            $modules = $this->get_cms_in_section($this->modinfo, $sectionnum);

            if (!empty($modules)) {
                $lines[] = '';
                $lines = array_merge($lines, $this->buildModuleList($modules, $detaillevel));
            } else {
                $lines[] = '';
            }
        }

        return $lines;
    }

    /**
     * Determine the detail level for a section based on mode and proximity.
     *
     * Uses strategy pattern via mode to determine behavior:
     * - Assessment mode: Always full detail
     * - Teaching mode: Tiered by proximity to target section
     *
     * @param int $sectionnum The section number.
     * @return string Detail level: 'full', 'preview', or 'titles'.
     */
    private function getsectiondetaillevel(int $sectionnum): string {
        // Assessment mode: full content everywhere for comprehensive AI knowledge.
        if ($this->mode === self::MODE_ASSESSMENT) {
            return 'full';
        }

        // Teaching mode: tiered by proximity to target.
        if ($this->targetsection === null) {
            return 'preview';
        }

        if ($sectionnum === $this->targetsection) {
            return 'full';
        }

        if (abs($sectionnum - $this->targetsection) === 1) {
            return 'preview';
        }

        return 'titles';
    }

    /**
     * Build module list with appropriate detail level.
     *
     * @param array $modules Array of cm_info objects.
     * @param string $detaillevel Detail level: 'full', 'preview', or 'titles'.
     * @return array Lines for the module list.
     */
    private function buildmodulelist(array $modules, string $detaillevel): array {
        $lines = [];

        foreach ($modules as $cm) {
            if (!$this->is_module_accessible($cm)) {
                continue;
            }

            $fileannotation = $this->get_file_annotation($cm);

            if ($detaillevel === 'titles') {
                $lines[] = "- [{$cm->modname}] {$cm->name}{$fileannotation}";
                continue;
            }

            // Full or preview: include content.
            $lines[] = "**[{$cm->modname}] {$cm->name}**{$fileannotation}";

            // Full level: untruncated content so the tutor and assessment generators
            // see the entire module. Preview level: short excerpt for adjacent sections
            // in teaching mode.
            $content = ($detaillevel === 'full')
                ? $this->contentextractor->get_full_content($cm)
                : $this->contentextractor->get_preview($cm, self::CONTENT_LENGTH_PREVIEW);

            if (!empty($content)) {
                $lines[] = $content;
            }

            $lines[] = '';
        }

        return $lines;
    }

    /**
     * Build a plan-awareness section from the injected course plan.
     *
     * Each module in the plan is annotated with its generation state by
     * cross-referencing the modulegen queue (via get_fast_modinfo on the course):
     * - [COMPLETED]: The module exists in the course already.
     * - [GENERATING]: A queue task is currently processing this slot.
     * - [PLANNED]: The module has not started yet.
     *
     * This gives the AI full awareness of the intended final shape of the course
     * even when only some modules have been generated so far.
     *
     * @return array Lines of markdown representing the planned structure.
     */
    private function buildplancontext(): array {
        $lines = [];
        $lines[] = '## Planned Course Structure';
        $lines[] = '_The following is the complete intended structure. Modules already generated appear as [COMPLETED]._';
        $lines[] = '';

        // Build a lookup of how many visible cms each section contains for status inference.
        $sectioncmcounts = [];
        if ($this->modinfo !== null) {
            foreach ($this->modinfo->get_sections() as $sectionnum => $cmids) {
                $count = 0;
                foreach ($cmids as $cmid) {
                    $cm = $this->modinfo->get_cm($cmid);
                    if ($cm->visible && $cm->uservisible) {
                        $count++;
                    }
                }
                $sectioncmcounts[$sectionnum] = $count;
            }
        }

        foreach ($this->courseplan['sections'] ?? [] as $sectionindex => $section) {
            // Plan sections are 0-indexed but map to Moodle sections 1..N (section 0 is General).
            $sectionnum = $sectionindex + 1;
            $sectiontitle = $section['title'] ?? 'Section ' . $sectionnum;
            $lines[] = "### {$sectiontitle}";

            if (!empty($section['summary'])) {
                $lines[] = $section['summary'];
            }

            $lines[] = '';

            foreach ($section['modules'] ?? [] as $moduleindex => $module) {
                $moduletype = $module['type'] ?? 'page';
                $moduletitle = $module['title'] ?? $module['summary'] ?? 'Module';

                // Infer status: if a real cm exists at this position it is completed.
                $existingcount = $sectioncmcounts[$sectionnum] ?? 0;
                if ($moduleindex < $existingcount) {
                    $marker = '[COMPLETED]';
                } else {
                    // No real cm yet; mark as planned (we cannot easily detect GENERATING here).
                    $marker = '[PLANNED]';
                }

                $lines[] = "- {$marker} [{$moduletype}] {$moduletitle}";
            }

            $lines[] = '';
        }

        return $lines;
    }
}
