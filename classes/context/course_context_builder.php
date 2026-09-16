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
 * Constructs markdown context from an entire course structure, limited to the
 * sections and modules the current user may see, and extended with the courses
 * other plugins link to it. Supports two context modes:
 * - Teaching mode: Tiered detail by proximity to target section
 * - Assessment mode: Full content everywhere for quiz/glossary generation
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\context;

use local_dixeo\hook\extend_course_context;
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
        $this->load_course_data();

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

        $lines = array_merge($lines, $this->build_sections_context($this->modinfo, $this->targetsection));
        $lines = array_merge($lines, $this->build_linked_courses_context());

        // Append the planned structure so the AI understands what is still to come.
        if ($this->courseplan !== null) {
            $lines[] = '';
            $lines = array_merge($lines, $this->build_plan_context());
        }

        return $this->finalize_context($lines);
    }

    /**
     * Load course and modinfo data.
     *
     * @return void
     * @throws \dml_exception If course not found.
     */
    private function load_course_data(): void {
        global $DB;

        if ($this->course === null) {
            $this->course = $DB->get_record('course', ['id' => $this->courseid], '*', MUST_EXIST);
            $this->modinfo = get_fast_modinfo($this->course);
        }
    }

    /**
     * Build context for the sections a course lists, as seen by the current user.
     *
     * @param \course_modinfo $modinfo Modinfo of the course, built for the current user.
     * @param int|null $targetsection Section number to mark as the target, null for none.
     * @param array|null $sectionnums Section numbers to keep, null for all of them.
     * @return array Lines of markdown for all sections.
     */
    private function build_sections_context(
        \course_modinfo $modinfo,
        ?int $targetsection,
        ?array $sectionnums = null
    ): array {
        $lines = [];

        // Delegated sections are skipped here: they are described inside the module that owns them.
        foreach ($modinfo->get_listed_section_info_all() as $section) {
            if (!$section->uservisible) {
                continue;
            }

            if ($sectionnums !== null && !in_array((int) $section->section, $sectionnums, true)) {
                continue;
            }

            $lines = array_merge($lines, $this->build_section_context($modinfo, $section, $targetsection, 3));
        }

        return $lines;
    }

    /**
     * Build context for a single section and for the sections its modules delegate.
     *
     * @param \course_modinfo $modinfo Modinfo of the course the section belongs to.
     * @param \section_info $section The section to describe.
     * @param int|null $targetsection Section number to mark as the target, null for none.
     * @param int $headinglevel Markdown heading level for the section title.
     * @return array Lines of markdown for the section.
     */
    private function build_section_context(
        \course_modinfo $modinfo,
        \section_info $section,
        ?int $targetsection,
        int $headinglevel
    ): array {
        $lines = [];
        $heading = str_repeat('#', $headinglevel);
        $sectionnum = (int) $section->section;
        $sectionname = $this->get_section_name($section);
        $detaillevel = $this->get_section_detail_level($sectionnum, $targetsection);

        // Mark target section clearly (only in teaching mode).
        if ($this->mode === self::MODE_TEACHING && $targetsection === $sectionnum) {
            $lines[] = "{$heading} {$sectionname} ← TARGET SECTION";
        } else {
            $lines[] = "{$heading} {$sectionname}";
        }

        if (!empty($section->summary)) {
            $summary = $this->htmlhelper->clean_html($section->summary);
            $lines[] = $summary;
        }

        $lines[] = '';

        foreach ($this->get_cms_in_section($modinfo, $sectionnum) as $cm) {
            if (!$this->is_module_accessible($cm)) {
                continue;
            }

            $delegated = $cm->get_delegated_section_info();

            if ($delegated !== null) {
                if ($delegated->uservisible) {
                    $lines = array_merge(
                        $lines,
                        $this->build_section_context($modinfo, $delegated, $targetsection, $headinglevel + 1)
                    );
                }
                continue;
            }

            $lines = array_merge($lines, $this->build_module_lines($cm, $detaillevel));
        }

        return $lines;
    }

    /**
     * Build context for the courses other plugins link to this one.
     *
     * @return array Lines of markdown for the linked courses.
     */
    private function build_linked_courses_context(): array {
        global $DB;

        $hook = new extend_course_context($this->courseid, $this->mode);
        \core\di::get(\core\hook\manager::class)->dispatch($hook);

        $lines = [];

        foreach ($hook->get_courses() as $courseid => $sectionnums) {
            $course = $DB->get_record('course', ['id' => $courseid], '*', IGNORE_MISSING);

            if (!$course) {
                continue;
            }

            // A course the user cannot even open must not reach the context through a link.
            if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', \context_course::instance($course->id))) {
                continue;
            }

            // Linked courses have no target section of their own, so they use the plain detail level.
            $sectionlines = $this->build_sections_context(get_fast_modinfo($course), null, $sectionnums);

            if (empty($sectionlines)) {
                continue;
            }

            $lines[] = '';
            $lines[] = '## Linked Course: ' . format_string($course->fullname);
            $lines[] = '';
            $lines = array_merge($lines, $sectionlines);
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
     * @param int|null $targetsection The target section number, null when there is none.
     * @return string Detail level: 'full', 'preview', or 'titles'.
     */
    private function get_section_detail_level(int $sectionnum, ?int $targetsection): string {
        // Assessment mode: full content everywhere for comprehensive AI knowledge.
        if ($this->mode === self::MODE_ASSESSMENT) {
            return 'full';
        }

        // Teaching mode: tiered by proximity to target.
        if ($targetsection === null) {
            return 'preview';
        }

        if ($sectionnum === $targetsection) {
            return 'full';
        }

        if (abs($sectionnum - $targetsection) === 1) {
            return 'preview';
        }

        return 'titles';
    }

    /**
     * Build the lines describing one module at the given detail level.
     *
     * @param \cm_info $cm The course module info.
     * @param string $detaillevel Detail level: 'full', 'preview', or 'titles'.
     * @return array Lines for the module.
     */
    private function build_module_lines(\cm_info $cm, string $detaillevel): array {
        $fileannotation = $this->get_file_annotation($cm);

        if ($detaillevel === 'titles') {
            return ["- [{$cm->modname}] {$cm->name}{$fileannotation}"];
        }

        // Full or preview: include content.
        $lines = ["**[{$cm->modname}] {$cm->name}**{$fileannotation}"];

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
    private function build_plan_context(): array {
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
