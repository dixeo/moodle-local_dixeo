<?php
// This file is part of Moodle - http://moodle.org/
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

namespace local_dixeo\service;

use core_text;
use local_dixeo\api\exception\api_exception;
use local_dixeo\util\input_limits;

/**
 * Validates a course-structure payload before designer finalize creates the Moodle course.
 *
 * `path` values match {@see block_dixeo_designer} `data-path` attributes (0-based section/module indices).
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class designer_structure_finalize_validation_service {
    /** @var int Matches {course}.fullname in core install.xml. */
    private const COURSE_FULLNAME_MAX = 254;

    /** @var int Matches {course_sections}.name. */
    private const SECTION_NAME_MAX = 255;

    /** @var int Typical mod instance name column size. */
    private const MODULE_NAME_MAX = 255;

    /** @var int Soft cap for HTML / long text fields (sanity). */
    private const LONG_TEXT_SOFT_MAX = 65535;

    /** @var int Same minimum as block_dixeo_modulegen AI instructions. */
    public const MIN_MODULE_INSTRUCTIONS_CHARS = 10;

    /** @var module_types_service|null */
    private $moduletypesservice;

    /**
     * Constructor.
     *
     * @param module_types_service|null $moduletypesservice Injected for tests.
     */
    public function __construct(?module_types_service $moduletypesservice = null) {
        $this->moduletypesservice = $moduletypesservice;
    }

    /**
     * Human-readable error strings (already passed through get_string).
     *
     * @param array $structureroot Raw decoded structure (may include course_structure wrapper).
     * @return string[] Empty when valid.
     */
    public function validate(array $structureroot): array {
        $messages = [];
        foreach ($this->validate_with_field_issues($structureroot) as $row) {
            $messages[] = $this->format_message_for_aggregate_list($row);
        }
        return $messages;
    }

    /**
     * Validation issues with Moodle-form-style field paths for the designer UI.
     *
     * Each item: `path` matches a `data-path` on an editable (e.g. title, sections[0].modules[1].summary),
     * or empty path when no single field applies.
     *
     * @param array $structureroot Raw decoded structure (may include course_structure wrapper).
     * @return array<int, array{path: string, message: string}>
     */
    public function validate_with_field_issues(array $structureroot): array {
        $data = $structureroot['course_structure'] ?? $structureroot;
        if (!is_array($data)) {
            return [[
                'path' => 'title',
                'message' => get_string('designerstructurevalidate_invalid_root', 'local_dixeo'),
            ]];
        }

        $issues = [];
        $this->validate_course_level($data, $issues);

        $sections = $data['sections'] ?? [];
        if (!is_array($sections)) {
            $issues[] = [
                'path' => '',
                'message' => get_string('designerstructurevalidate_sections_not_array', 'local_dixeo'),
            ];
            return $issues;
        }

        $bytype = $this->load_module_type_index();

        foreach (array_values($sections) as $sidx => $sectiondata) {
            if (!is_array($sectiondata)) {
                $issues[] = [
                    'path' => 'sections[' . (int) $sidx . ']',
                    'message' => get_string('designerstructurevalidate_section_invalid', 'local_dixeo', $sidx + 1),
                ];
                continue;
            }
            $this->validate_section($sectiondata, $sidx, $issues);
            $modules = $sectiondata['modules'] ?? [];
            if (!is_array($modules)) {
                $issues[] = [
                    'path' => 'sections[' . (int) $sidx . '].title',
                    'message' => get_string('designerstructurevalidate_modules_not_array', 'local_dixeo', $sidx + 1),
                ];
                continue;
            }
            foreach (array_values($modules) as $midx => $module) {
                if (!is_array($module)) {
                    $issues[] = [
                        'path' => 'sections[' . (int) $sidx . '].modules[' . (int) $midx . '].title',
                        'message' => get_string('designerstructurevalidate_module_invalid', 'local_dixeo', [
                            'section' => $sidx + 1,
                            'module' => $midx + 1,
                        ]),
                    ];
                    continue;
                }
                $this->validate_module($module, $sidx, $midx, $bytype, $issues);
            }
        }

        return $issues;
    }

    /**
     * Validation issues for a single designer field path (inline edit save).
     *
     * For full-structure validation use {@see validate_with_field_issues()} instead.
     *
     * @param array $structureroot Raw decoded structure (may include course_structure wrapper).
     * @param string $path Non-empty data-path value (e.g. sections[0].modules[1].title).
     * @return array<int, array{path: string, message: string}>
     */
    public function validate_issues_for_path(array $structureroot, string $path): array {
        $path = trim($path);
        if ($path === '') {
            return [];
        }

        $all = $this->validate_with_field_issues($structureroot);
        return array_values(array_filter($all, static function (array $row) use ($path): bool {
            return ($row['path'] ?? '') === $path;
        }));
    }

    /**
     * Same payload as {@see \block_dixeo_designer\service\designer_course_creation_service::build_module_instructions()}.
     *
     * @param array $module
     */
    public static function build_fill_instruction_payload(array $module): string {
        return trim((string) ($module['instructions'] ?? ''));
    }

    /**
     * Validate course-level title and summary constraints.
     *
     * @param array $data Unwrapped course_structure array.
     * @param array $issues Accumulator of path/message issue rows (passed by reference).
     */
    private function validate_course_level(array $data, array &$issues): void {
        $title = isset($data['title']) ? trim((string) $data['title']) : '';
        if ($title === '') {
            $issues[] = $this->field_issue('title', 'designerstructurevalidate_course_title_required');
        } else if (core_text::strlen($title) > self::COURSE_FULLNAME_MAX) {
            $issues[] = $this->field_issue('title', 'designerstructurevalidate_course_title_too_long', [
                'max' => self::COURSE_FULLNAME_MAX,
            ]);
        }

        if (isset($data['summary']) && $data['summary'] !== null && $data['summary'] !== '') {
            $summary = (string) $data['summary'];
            if (core_text::strlen($summary) > self::LONG_TEXT_SOFT_MAX) {
                $issues[] = $this->field_issue('summary', 'designerstructurevalidate_course_summary_too_long', [
                    'max' => self::LONG_TEXT_SOFT_MAX,
                ]);
            }
        }
    }

    /**
     * Validate one section and its nested modules.
     *
     * @param array $sectiondata Section data from the course structure.
     * @param int $sidx0 Zero-based section index.
     * @param array $issues Accumulator of path/message issue rows (passed by reference).
     */
    private function validate_section(array $sectiondata, int $sidx0, array &$issues): void {
        $p = 'sections[' . $sidx0 . ']';
        if (isset($sectiondata['title']) && $sectiondata['title'] !== null && $sectiondata['title'] !== '') {
            $t = trim((string) $sectiondata['title']);
            if (core_text::strlen($t) > self::SECTION_NAME_MAX) {
                $issues[] = $this->field_issue($p . '.title', 'designerstructurevalidate_section_title_too_long', [
                    'max' => self::SECTION_NAME_MAX,
                ]);
            }
        }
        if (isset($sectiondata['summary']) && $sectiondata['summary'] !== null && $sectiondata['summary'] !== '') {
            $s = (string) $sectiondata['summary'];
            if (core_text::strlen($s) > self::LONG_TEXT_SOFT_MAX) {
                $issues[] = $this->field_issue($p . '.summary', 'designerstructurevalidate_section_summary_too_long', [
                    'max' => self::LONG_TEXT_SOFT_MAX,
                ]);
            }
        }
    }

    /**
     * Build a field-scoped validation issue for the designer UI (no section/activity prefix in the message).
     *
     * @param string $path data-path value
     * @param string $stringid lang string id in local_dixeo
     * @param array|object|null $params optional get_string params
     * @return array{path: string, message: string}
     */
    private function field_issue(string $path, string $stringid, $params = null): array {
        return [
            'path' => $path,
            'message' => get_string($stringid, 'local_dixeo', $params),
        ];
    }

    /**
     * Prefix a field message with section/activity context for flat error lists (e.g. API exceptions).
     *
     * @param array $row Issue row with keys path and message.
     * @return string
     */
    private function format_message_for_aggregate_list(array $row): string {
        if ($row['path'] === '') {
            return $row['message'];
        }
        $location = $this->location_from_field_path($row['path']);
        if ($location === null) {
            return $row['message'];
        }
        if (isset($location['module'])) {
            return get_string('designerstructurevalidate_aggregate_prefix_section', 'local_dixeo', $location)
                . ' ' . $row['message'];
        }
        return get_string('designerstructurevalidate_aggregate_prefix_section_only', 'local_dixeo', $location)
            . ' ' . $row['message'];
    }

    /**
     * Parse a designer data-path into 1-based section/module numbers for aggregate messages.
     *
     * @param string $path
     * @return array{section: int, module?: int}|null
     */
    private function location_from_field_path(string $path): ?array {
        if (preg_match('/^sections\[(\d+)\]\.modules\[(\d+)\]/', $path, $matches)) {
            return [
                'section' => (int) $matches[1] + 1,
                'module' => (int) $matches[2] + 1,
            ];
        }
        if (preg_match('/^sections\[(\d+)\]/', $path, $matches)) {
            return [
                'section' => (int) $matches[1] + 1,
            ];
        }
        return null;
    }

    /**
     * Validate one module entry against catalogue and field constraints.
     *
     * @param array $module Module data from the course structure.
     * @param int $sidx0 Zero-based section index.
     * @param int $midx0 Zero-based module index.
     * @param array $bytype Map of API type string to catalogue row.
     * @param array $issues Accumulator of path/message issue rows (passed by reference).
     */
    private function validate_module(
        array $module,
        int $sidx0,
        int $midx0,
        array $bytype,
        array &$issues
    ): void {
        $bp = 'sections[' . $sidx0 . '].modules[' . $midx0 . ']';

        $type = isset($module['type']) ? trim((string) $module['type']) : '';
        if ($type === '') {
            $issues[] = $this->field_issue($bp . '.type', 'designerstructurevalidate_module_type_required');
        } else if (!$this->is_module_type_usable($type, $bytype)) {
            $issues[] = $this->field_issue($bp . '.type', 'designerstructurevalidate_module_type_not_usable', [
                'type' => $type,
            ]);
        }

        $title = isset($module['title']) ? trim((string) $module['title']) : '';
        if ($title === '') {
            $issues[] = $this->field_issue($bp . '.title', 'designerstructurevalidate_module_title_required');
        } else {
            if (core_text::strlen($title) > self::MODULE_NAME_MAX) {
                $issues[] = $this->field_issue($bp . '.title', 'designerstructurevalidate_module_title_too_long', [
                    'max' => self::MODULE_NAME_MAX,
                ]);
            }
            if ($this->string_matches_block_placeholder('designer_new_module_title', $title)) {
                $issues[] = $this->field_issue($bp . '.title', 'designerstructurevalidate_module_title_placeholder');
            }
        }

        $summary = isset($module['summary']) ? trim((string) $module['summary']) : '';
        if ($summary !== '') {
            if (core_text::strlen($summary) > self::LONG_TEXT_SOFT_MAX) {
                $issues[] = $this->field_issue($bp . '.summary', 'designerstructurevalidate_module_summary_too_long', [
                    'max' => self::LONG_TEXT_SOFT_MAX,
                ]);
            }
            if ($this->string_matches_block_placeholder('designer_new_module_summary', $summary)) {
                $issues[] = $this->field_issue($bp . '.summary', 'designerstructurevalidate_module_summary_placeholder');
            }
        }

        $payload = self::build_fill_instruction_payload($module);
        if (trim($payload) === '') {
            $issues[] = $this->field_issue($bp . '.instructions', 'designerstructurevalidate_module_instructions_required', [
                'min' => self::MIN_MODULE_INSTRUCTIONS_CHARS,
            ]);
        } else if (core_text::strlen($payload) < self::MIN_MODULE_INSTRUCTIONS_CHARS) {
            $issues[] = $this->field_issue($bp . '.instructions', 'designerstructurevalidate_instructions_api_min', [
                'min' => self::MIN_MODULE_INSTRUCTIONS_CHARS,
            ]);
        } else if (core_text::strlen($payload) > input_limits::MAX_INSTRUCTIONS_CHARS) {
            $issues[] = $this->field_issue($bp . '.instructions', 'designerstructurevalidate_fill_instructions_too_long', [
                'max' => input_limits::MAX_INSTRUCTIONS_CHARS,
            ]);
        }
    }

    /**
     * True when $value equals the current-language block_dixeo_designer placeholder string.
     *
     * @param string $stringkey Lang string id in block_dixeo_designer.
     * @param string $value Value to compare against the placeholder.
     * @return bool
     */
    private function string_matches_block_placeholder(string $stringkey, string $value): bool {
        $sm = get_string_manager();
        if (!$sm->string_exists($stringkey, 'block_dixeo_designer')) {
            return false;
        }
        $placeholder = trim(get_string($stringkey, 'block_dixeo_designer'));

        return $placeholder !== '' && $value === $placeholder;
    }

    /**
     * Load a type-keyed index of usable module catalogue rows.
     *
     * @return array<string, array> Map of API type string to catalogue row.
     */
    private function load_module_type_index(): array {
        try {
            $svc = $this->moduletypesservice ?? new module_types_service();
            $rows = $svc->get_module_types_cached();
        } catch (api_exception $e) {
            return [];
        }

        $bytype = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $tid = $row['type'] ?? '';
            if (is_string($tid) && $tid !== '') {
                $bytype[$tid] = $row;
            }
        }
        return $bytype;
    }

    /**
     * Whether the type can be submitted for a fill job on this site (plugin + catalogue requirements when known).
     *
     * @param string $type API module type string.
     * @param array $bytype Map of API type string to catalogue row.
     * @return bool
     */
    private function is_module_type_usable(string $type, array $bytype): bool {
        if (isset($bytype[$type])) {
            return plugin_installation_service::is_module_type_installed($bytype[$type]);
        }

        if (preg_match('/^h5p_/i', $type)) {
            $modname = 'h5pactivity';
        } else {
            $modname = strtolower(preg_replace('/^mod_/i', '', $type));
        }
        return isset(plugin_installation_service::get_installed_plugin_map('mod')[$modname]);
    }
}
