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
 * Service for extracting content from Moodle modules.
 *
 * Handles content retrieval from various module types (page, label, book, etc.)
 * and provides both raw and processed content for AI context building. Any other
 * module type is read from its search area, the extract_module_content hook, or its intro.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service;

use cache;
use core_search\document;
use core_search\manager;
use local_dixeo\hook\extract_module_content;

/**
 * Service for extracting content from Moodle modules.
 */
class module_content_extractor {
    /** @var string Cache holding the search area text of a module. */
    private const SEARCH_TEXT_CACHE = 'modulesearchtext';

    /** @var string[] Search document fields holding module text, in reading order. */
    private const SEARCH_TEXT_FIELDS = ['content', 'description1', 'description2'];

    /** @var html_helper HTML processing helper. */
    private html_helper $htmlhelper;

    /** @var bool|null Whether a search engine answered, remembered for the life of this instance. */
    private ?bool $searchengineready = null;

    /**
     * Constructor.
     *
     * @param html_helper|null $htmlhelper Optional HTML helper (creates new if not provided).
     */
    public function __construct(?html_helper $htmlhelper = null) {
        $this->htmlhelper = $htmlhelper ?? new html_helper();
    }

    /**
     * Get raw content from a module based on its type.
     *
     * Returns the HTML content as stored in the database. Module types handled here take
     * precedence; any other type falls back to {@see get_fallback_content()}.
     *
     * @param \cm_info $cm The course module info.
     * @return string|null The raw HTML content, or null if not applicable.
     */
    public function get_raw_content(\cm_info $cm): ?string {
        global $DB;

        return match ($cm->modname) {
            'page' => $this->get_page_full_content($cm->instance),
            'label' => $DB->get_field('label', 'intro', ['id' => $cm->instance]),
            'book' => $this->get_book_content($cm->instance),
            'url' => $DB->get_field('url', 'intro', ['id' => $cm->instance]),
            'resource' => $DB->get_field('resource', 'intro', ['id' => $cm->instance]),
            'assign' => $this->get_assign_content($cm->instance),
            default => $this->get_fallback_content($cm),
        };
    }

    /**
     * Get assign name, intro and activity instructions for AI context.
     *
     * @param int $instanceid Assign instance id.
     * @return string|null Combined content, or null when the record is missing.
     */
    private function get_assign_content(int $instanceid): ?string {
        global $DB;

        $assign = $DB->get_record('assign', ['id' => $instanceid], 'name,intro,activity');
        if (!$assign) {
            return null;
        }

        $parts = array_filter([
            trim((string) $assign->name),
            trim((string) ($assign->intro ?? '')),
            trim((string) ($assign->activity ?? '')),
        ], static fn(string $part): bool => $part !== '');

        if ($parts === []) {
            return null;
        }

        return implode("\n\n", $parts);
    }

    /**
     * Get the content of a module type this class does not read natively.
     *
     * The module search area comes first as the standard source of module text, then the
     * extract_module_content hook for plugins that describe themselves, then the module intro.
     *
     * @param \cm_info $cm The course module info.
     * @return string|null The content, or null if no step could provide any.
     */
    private function get_fallback_content(\cm_info $cm): ?string {
        return $this->blank_to_null($this->get_search_area_content($cm))
            ?? $this->blank_to_null($this->get_hooked_content($cm))
            ?? $this->blank_to_null($this->get_intro_content($cm));
    }

    /**
     * Get the text of the module search area document, cached per module.
     *
     * @param \cm_info $cm The course module info.
     * @return string The document text, empty when the module has no usable search area.
     */
    private function get_search_area_content(\cm_info $cm): string {
        // Nothing is cached without an engine, so the modules are read again once one is back.
        if (!$this->is_search_engine_ready()) {
            return '';
        }

        $area = manager::get_search_area("mod_{$cm->modname}-activity");
        if (!$area) {
            return '';
        }

        $cache = cache::make('local_dixeo', self::SEARCH_TEXT_CACHE);
        $version = $this->get_search_area_version($cm, $area);

        // Empty results are cached too, so a module whose document says nothing is only built once.
        $cached = $cache->get_versioned($cm->id, $version);
        if ($cached !== false) {
            return (string) $cached;
        }

        $content = $this->build_search_area_content($cm, $area);
        $cache->set_versioned($cm->id, $version, $content);

        return $content;
    }

    /**
     * Version of the cached document: the latest of the course cache revision and the module
     * timestamp core_search itself indexes on, so content refreshed outside a course edit is seen.
     *
     * @param \cm_info $cm The course module info.
     * @param \core_search\base $area The module search area.
     * @return int The version.
     */
    private function get_search_area_version(\cm_info $cm, \core_search\base $area): int {
        global $DB;

        $version = (int) $cm->get_course()->cacherev;
        if (!$area instanceof \core_search\base_activity) {
            return $version;
        }

        try {
            $modified = $DB->get_field($cm->modname, $area::MODIFIED_FIELD_NAME, ['id' => $cm->instance]);
        } catch (\Throwable $e) {
            return $version;
        }

        return max($version, (int) $modified);
    }

    /**
     * Check that a search engine is usable, once per instance.
     *
     * Documents are built through the configured engine (global search does not have to be
     * enabled), so a missing or unreachable one must cost a single attempt, not one per module.
     *
     * @return bool True when an engine answered.
     */
    private function is_search_engine_ready(): bool {
        if ($this->searchengineready === null) {
            try {
                manager::instance(true);
                $this->searchengineready = true;
            } catch (\Throwable $e) {
                $this->searchengineready = false;
            }
        }

        return $this->searchengineready;
    }

    /**
     * Build the module text from its mod_<name>\search\activity document.
     *
     * @param \cm_info $cm The course module info.
     * @param \core_search\base $area The module search area.
     * @return string The document text, empty when the area answered nothing.
     */
    private function build_search_area_content(\cm_info $cm, \core_search\base $area): string {
        $recordset = null;
        $parts = [];

        try {
            $recordset = $area->get_document_recordset(0, $cm->context);
            if (!$recordset) {
                return '';
            }

            foreach ($recordset as $record) {
                $document = $area->get_document($record);
                if (!$document instanceof document) {
                    continue;
                }

                foreach (self::SEARCH_TEXT_FIELDS as $field) {
                    if ($document->is_set($field)) {
                        $parts[] = (string) $document->get($field);
                    }
                }
            }
        } catch (\Throwable $e) {
            // A search area failing on its own data must not break the context: the next step takes over.
            return '';
        } finally {
            if ($recordset instanceof \moodle_recordset) {
                $recordset->close();
            }
        }

        return trim(implode("\n", $parts));
    }

    /**
     * Ask other plugins for the content of a module type handled by none of the above.
     *
     * @param \cm_info $cm The course module info.
     * @return string|null The content provided by a plugin, or null if none did.
     */
    private function get_hooked_content(\cm_info $cm): ?string {
        $hook = new extract_module_content($cm);
        \core\di::get(\core\hook\manager::class)->dispatch($hook);

        return $hook->get_content();
    }

    /**
     * Get the intro of a module that declares one.
     *
     * @param \cm_info $cm The course module info.
     * @return string|null The intro, or null when the module has none.
     */
    private function get_intro_content(\cm_info $cm): ?string {
        global $DB;

        if (!plugin_supports('mod', $cm->modname, FEATURE_MOD_INTRO, false)) {
            return null;
        }

        $intro = $DB->get_field($cm->modname, 'intro', ['id' => $cm->instance]);

        return $intro === false ? null : $intro;
    }

    /**
     * Treat a blank candidate as no content, so the next step of the chain is tried.
     *
     * @param string|null $content The candidate content.
     * @return string|null The content, or null when blank.
     */
    private function blank_to_null(?string $content): ?string {
        return ($content === null || trim($content) === '') ? null : $content;
    }

    /**
     * Get module content preview (truncated and cleaned).
     *
     * Used for displaying module content in context summaries.
     *
     * @param \cm_info $cm The course module info.
     * @param int $maxlength Maximum preview length.
     * @return string|null The content preview, or null if not available.
     */
    public function get_preview(\cm_info $cm, int $maxlength = html_helper::PREVIEW_LENGTH): ?string {
        $content = $this->get_raw_content($cm);

        if ($content === null) {
            return null;
        }

        $cleaned = $this->htmlhelper->clean_html($content);

        return $this->htmlhelper->truncate_text($cleaned, $maxlength);
    }

    /**
     * Get module excerpt for sibling context.
     *
     * Shorter than preview, used for adjacent module descriptions.
     *
     * @param \cm_info $cm The course module info.
     * @param int $maxlength Maximum excerpt length.
     * @return string|null The excerpt, or null if content unavailable.
     */
    public function get_excerpt(\cm_info $cm, int $maxlength = html_helper::EXCERPT_LENGTH): ?string {
        $content = $this->get_raw_content($cm);

        if ($content === null) {
            return null;
        }

        $cleaned = $this->htmlhelper->clean_html($content);

        return $this->htmlhelper->truncate_text($cleaned, $maxlength);
    }

    /**
     * Get full module content cleaned for AI processing.
     *
     * HTML is converted to plain text for generation operations.
     *
     * @param \cm_info $cm The course module info.
     * @return string|null The full cleaned content, or null if not available.
     */
    public function get_full_content(\cm_info $cm): ?string {
        $content = $this->get_raw_content($cm);

        if ($content === null) {
            return null;
        }

        return $this->htmlhelper->clean_html($content);
    }

    /**
     * Get full module content for edit operations.
     *
     * Preserves HTML structure for editing, with special handling for page modules.
     * When $autosavedrafthtml is non-empty after trim, it replaces the in-editor body from Tiny autosave;
     * for page modules the introduction stays loaded from the database and only the main content uses the draft.
     *
     * @param \cm_info $cm The course module info.
     * @param string|null $autosavedrafthtml Optional HTML from tiny_autosave.
     * @return string|null The full content for editing, or null if not available.
     */
    public function get_full_content_for_edit(\cm_info $cm, ?string $autosavedrafthtml = null): ?string {
        $draft = $autosavedrafthtml !== null ? trim($autosavedrafthtml) : '';
        $usedraft = $draft !== '';

        if ($cm->modname === 'page') {
            return $usedraft
                ? $this->get_page_full_content_with_content_override($cm->instance, $draft)
                : $this->get_page_full_content($cm->instance);
        }

        if ($usedraft) {
            return $draft;
        }

        return $this->get_raw_content($cm);
    }

    /**
     * Get full content from a page module.
     *
     * Includes both intro and content fields with labels.
     * Returns raw HTML - cleaning is done by the caller if needed.
     *
     * @param int $pageid The page instance ID.
     * @return string|null The full page content as raw HTML, or null if not found.
     */
    private function get_page_full_content(int $pageid): ?string {
        global $DB;

        $page = $DB->get_record('page', ['id' => $pageid], 'intro, content');

        if (!$page) {
            return null;
        }

        $result = '';

        if (!empty($page->intro)) {
            $result .= "**Introduction:**\n" . $page->intro . "\n\n";
        }

        if (!empty($page->content)) {
            $result .= "**Content:**\n" . $page->content;
        }

        return !empty($result) ? $result : null;
    }

    /**
     * Page intro from DB plus content body from autosave draft (Dixeo editor edits content only).
     *
     * @param int $pageid Page instance id.
     * @param string $contentdraft HTML for the content field.
     * @return string|null
     */
    private function get_page_full_content_with_content_override(int $pageid, string $contentdraft): ?string {
        global $DB;

        $page = $DB->get_record('page', ['id' => $pageid], 'intro, content', IGNORE_MISSING);
        if (!$page) {
            return null;
        }

        $result = '';
        if (!empty($page->intro)) {
            $result .= "**Introduction:**\n" . $page->intro . "\n\n";
        }
        if ($contentdraft !== '') {
            $result .= "**Content:**\n" . $contentdraft;
        }

        return $result !== '' ? $result : null;
    }

    /**
     * Get content from a book module.
     *
     * Returns intro and first few chapters.
     *
     * @param int $bookid The book instance ID.
     * @return string|null The book content preview.
     */
    private function get_book_content(int $bookid): ?string {
        global $DB;

        $book = $DB->get_record('book', ['id' => $bookid]);

        if (!$book) {
            return null;
        }

        // Limit to first 3 chapters to avoid excessive context.
        $chapters = $DB->get_records(
            'book_chapters',
            ['bookid' => $bookid],
            'pagenum ASC',
            'id,title,content',
            0,
            3
        );

        if (empty($chapters)) {
            return $book->intro;
        }

        $content = $book->intro . "\n\n";

        foreach ($chapters as $chapter) {
            $content .= "## {$chapter->title}\n{$chapter->content}\n\n";
        }

        return $content;
    }
}
