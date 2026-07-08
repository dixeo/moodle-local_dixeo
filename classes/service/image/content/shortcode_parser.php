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


use local_dixeo\service\image_generation_service;

/**
 * Parses [img-gen ...] shortcodes from HTML.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class shortcode_parser {
    /** @var string */
    public const SHORTCODE_PATTERN = '/\[img-gen\s+([^\]]+)\]/iu';

    /** @var string */
    private const ATTR_PATTERN = '/(\w+)="([^"]*)"/u';

    /** @var string */
    public const PRESERVE_PROMPT = '(preserve existing image)';

    /** @var array<string, string> */
    private const MODE_SIZES = [
        'landscape' => '1536x1024',
        'portrait' => '1024x1536',
        'square' => '1024x1024',
    ];

    /**
     * Contains shortcode.
     * @param string $html
     * @return bool
     */
    public static function contains_shortcode(string $html): bool {
        return preg_match(self::SHORTCODE_PATTERN, $html) === 1;
    }

    /**
     * Remove all shortcode tokens from HTML.
     *
     * @param string $html
     * @return string
     */
    public static function strip_all(string $html): string {
        if (!self::contains_shortcode($html)) {
            return $html;
        }
        return (string) preg_replace(self::SHORTCODE_PATTERN, '', $html);
    }

    /**
     * Find all.
     * @param string $html
     * @return array<int, array<string, mixed>>
     */
    public static function find_all(string $html): array {
        if (!preg_match_all(self::SHORTCODE_PATTERN, $html, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $parsed = [];
        foreach ($matches as $match) {
            $attrs = self::parse_token_attributes($match[1] ?? '');
            if ($attrs === null) {
                continue;
            }
            $attrs['fullmatch'] = $match[0];
            $parsed[] = $attrs;
        }
        return $parsed;
    }

    /**
     * Whether this token requests a newly generated image (not a keep ref/file).
     *
     * @param array $parsed Parsed shortcode attributes.
     * @return bool
     */
    public static function is_new_generation(array $parsed): bool {
        return empty($parsed['ref']) && empty($parsed['file']);
    }

    /**
     * Build ref shortcode.
     * @param string $ref
     * @param string $prompt
     * @param string $quality
     * @param string $mode
     * @return string
     */
    public static function build_ref_shortcode(
        string $ref,
        string $prompt = self::PRESERVE_PROMPT,
        string $quality = '',
        string $mode = ''
    ): string {
        $parts = ['ref="' . self::escape_attr($ref) . '"'];
        $parts[] = 'prompt="' . self::escape_attr($prompt) . '"';
        if ($quality !== '') {
            $parts[] = 'quality="' . self::escape_attr($quality) . '"';
        }
        if ($mode !== '') {
            $parts[] = 'mode="' . self::escape_attr($mode) . '"';
        }
        return '[img-gen ' . implode(' ', $parts) . ']';
    }

    /**
     * Build file shortcode.
     * @param string $filename
     * @param string $prompt
     * @param int $draftitemid User form draft area itemid when the file only exists there.
     * @return string
     */
    public static function build_file_shortcode(
        string $filename,
        string $prompt = self::PRESERVE_PROMPT,
        int $draftitemid = 0
    ): string {
        $parts = ['file="' . self::escape_attr($filename) . '"'];
        if ($draftitemid > 0) {
            $parts[] = 'draftitemid="' . $draftitemid . '"';
        }
        $parts[] = 'prompt="' . self::escape_attr($prompt) . '"';
        return '[img-gen ' . implode(' ', $parts) . ']';
    }

    /**
     * Escape attr.
     * @param string $value
     * @return string
     */
    private static function escape_attr(string $value): string {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }

    /**
     * Parse token attributes.
     * @param string $attrstring
     * @return array<string, mixed>|null
     */
    private static function parse_token_attributes(string $attrstring): ?array {
        if (!preg_match_all(self::ATTR_PATTERN, $attrstring, $attrmatches, PREG_SET_ORDER)) {
            return null;
        }

        $attrs = [];
        foreach ($attrmatches as $attrmatch) {
            $attrs[strtolower($attrmatch[1])] = $attrmatch[2];
        }

        $allowed = ['prompt', 'quality', 'mode', 'ref', 'file', 'draftitemid'];
        foreach (array_keys($attrs) as $name) {
            if (!in_array($name, $allowed, true)) {
                return null;
            }
        }

        $ref = trim($attrs['ref'] ?? '');
        $file = trim($attrs['file'] ?? '');
        if ($ref !== '' && $file !== '') {
            return null;
        }

        $prompt = trim($attrs['prompt'] ?? '');

        if ($ref === '' && $file === '' && $prompt === '') {
            return null;
        }

        if (($ref !== '' || $file !== '') && $prompt === '') {
            $prompt = self::PRESERVE_PROMPT;
        }

        $quality = strtolower(trim($attrs['quality'] ?? image_generation_service::DEFAULT_QUALITY));
        if (!in_array($quality, ['low', 'medium', 'high'], true)) {
            $quality = image_generation_service::DEFAULT_QUALITY;
        }

        $mode = strtolower(trim($attrs['mode'] ?? 'landscape'));
        if (!isset(self::MODE_SIZES[$mode])) {
            $mode = 'landscape';
        }

        return [
            'prompt' => $prompt,
            'quality' => $quality,
            'mode' => $mode,
            'size' => self::MODE_SIZES[$mode],
            'ref' => $ref,
            'file' => $file,
            'draftitemid' => (int) ($attrs['draftitemid'] ?? 0),
        ];
    }
}
