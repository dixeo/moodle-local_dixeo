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

namespace local_dixeo\service\image\content;


/**
 * Maps module HTML fields to pluginfile storage for img-gen shortcodes.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class target_registry {
    /** @var array<string, string> HTML field => format field suffix map. */
    private const FORMAT_FIELDS = [
        'intro' => 'introformat',
        'content' => 'contentformat',
        'definition' => 'definitionformat',
    ];

    /**
     * @var array<string, array<string, array{component: string, filearea: string, itemid: string, table: string}>>
     * itemid values: 'zero' | 'instance' | 'record'
     */
    private const HANDLERS = [
        'page' => [
            'intro' => ['component' => 'mod_page', 'filearea' => 'intro', 'itemid' => 'zero', 'table' => 'page'],
            'content' => ['component' => 'mod_page', 'filearea' => 'content', 'itemid' => 'zero', 'table' => 'page'],
        ],
        'label' => [
            'intro' => ['component' => 'mod_label', 'filearea' => 'intro', 'itemid' => 'zero', 'table' => 'label'],
        ],
        'resource' => [
            'intro' => ['component' => 'mod_resource', 'filearea' => 'intro', 'itemid' => 'zero', 'table' => 'resource'],
        ],
        'url' => [
            'intro' => ['component' => 'mod_url', 'filearea' => 'intro', 'itemid' => 'zero', 'table' => 'url'],
        ],
        'h5pactivity' => [
            'intro' => ['component' => 'mod_h5pactivity', 'filearea' => 'intro', 'itemid' => 'zero', 'table' => 'h5pactivity'],
        ],
        'slideshow_slide' => [
            'content' => [
                'component' => 'mod_slideshow',
                'filearea' => 'content',
                'itemid' => 'record',
                'table' => 'slideshow_slide',
            ],
        ],
        'glossary_entry' => [
            'definition' => [
                'component' => 'mod_glossary',
                'filearea' => 'entry',
                'itemid' => 'record',
                'table' => 'glossary_entries',
            ],
        ],
    ];

    /**
     * Resolve filearea html_field_target for a module HTML field after insert.
     *
     * @param string $modname Module plugin name or logical entity (slideshow_slide, glossary_entry).
     * @param string $fieldname HTML field on the record.
     * @param int $instanceid Instance or row id.
     * @param int $contextid Module context id.
     * @param int $courseid Course id.
     * @param int|null $cmid Course-module id when available.
     * @return html_field_target|null Null when unmapped.
     */
    public static function resolve(
        string $modname,
        string $fieldname,
        int $instanceid,
        int $contextid,
        int $courseid,
        ?int $cmid = null
    ): ?html_field_target {
        if (!isset(self::HANDLERS[$modname][$fieldname])) {
            debugging('img-gen shortcode skipped: no filearea for ' . $modname . '.' . $fieldname, DEBUG_DEVELOPER);
            return null;
        }

        $handler = self::HANDLERS[$modname][$fieldname];
        $itemid = match ($handler['itemid']) {
            'record' => $instanceid,
            default => 0,
        };

        $formatfield = self::FORMAT_FIELDS[$fieldname] ?? $fieldname . 'format';

        return new html_field_target(
            $modname,
            $fieldname,
            $handler['component'],
            $handler['filearea'],
            $itemid,
            $handler['table'],
            $fieldname,
            $instanceid,
            $contextid,
            $courseid,
            $cmid,
            $formatfield
        );
    }

    /**
     * Database table holding the HTML fields of an entity.
     *
     * Falls back to the module name (Moodle convention) for unmapped modules.
     *
     * @param string $modname Module plugin name or logical entity (slideshow_slide, glossary_entry).
     * @return string
     */
    public static function get_table_for_entity(string $modname): string {
        foreach (self::HANDLERS[$modname] ?? [] as $handler) {
            return $handler['table'];
        }
        return $modname;
    }

    /**
     * Get format field name.
     * @param string $fieldname
     * @return string|null
     */
    public static function get_format_field_name(string $fieldname): ?string {
        return self::FORMAT_FIELDS[$fieldname] ?? null;
    }

    /**
     * Is html field.
     * @param \stdClass $record
     * @param string $fieldname
     * @return bool
     */
    public static function is_html_field(\stdClass $record, string $fieldname): bool {
        $formatfield = self::get_format_field_name($fieldname);
        if ($formatfield === null || !property_exists($record, $formatfield)) {
            return false;
        }
        return (int) $record->{$formatfield} === (int) FORMAT_HTML;
    }
}
