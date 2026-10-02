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
 * Removes active content from HTML before it is stored.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\dsl;

/**
 * Strips script, event handlers, and javascript: URLs from generated HTML.
 *
 * Inline SVG is left in place. Iframes are kept only for the embed hosts below.
 */
final class generated_html {
    /** @var string[] HTTPS hosts whose iframes may be stored. */
    private const IFRAME_HOSTS = [
        'www.youtube.com',
        'youtube.com',
        'www.youtube-nocookie.com',
        'youtube-nocookie.com',
        'player.vimeo.com',
    ];

    /** @var string[] Attributes whose values are URLs. */
    private const URL_ATTRIBUTES = [
        'href',
        'src',
        'action',
        'formaction',
        'poster',
        'data',
        'cite',
        'background',
        'xlink:href',
    ];

    /**
     * Return HTML with active content removed.
     *
     * @param string $html Generated HTML.
     * @return string HTML safe to store.
     */
    public static function strip_active_content(string $html): string {
        if ($html === '' || !str_contains($html, '<')) {
            return $html;
        }

        return self::rewrite($html);
    }

    /**
     * Walk tags once, dropping script and disallowed iframes and cleaning attributes.
     *
     * @param string $html Generated HTML.
     * @return string
     */
    private static function rewrite(string $html): string {
        $length = strlen($html);
        $out = '';
        $i = 0;

        while ($i < $length) {
            $start = strpos($html, '<', $i);
            if ($start === false) {
                $out .= substr($html, $i);
                break;
            }

            $out .= substr($html, $i, $start - $i);
            if (!self::is_tag_start($html, $start)) {
                $out .= '<';
                $i = $start + 1;
                continue;
            }

            if (self::starts_with_ci($html, $start, '<!--')) {
                $end = strpos($html, '-->', $start + 4);
                if ($end === false) {
                    break;
                }
                $out .= substr($html, $start, $end + 3 - $start);
                $i = $end + 3;
                continue;
            }

            $tagend = self::find_tag_end($html, $start);
            $tag = substr($html, $start, $tagend - $start);
            $name = self::tag_name($tag);

            if ($name === 'script') {
                $close = self::find_closing_tag($html, $tagend, 'script');
                $i = $close === null ? $length : self::find_tag_end($html, $close);
                continue;
            }

            if ($name === 'iframe') {
                $close = self::find_closing_tag($html, $tagend, 'iframe');
                $inner = '';
                $closelen = 0;
                if ($close !== null) {
                    $closetagend = self::find_tag_end($html, $close);
                    $closelen = $closetagend - $close;
                    $inner = substr($html, $tagend, $close - $tagend);
                }
                $cleaned = self::clean_attributes($tag);
                if (self::iframe_is_allowed($cleaned)) {
                    $out .= $cleaned . self::rewrite($inner);
                    if ($close !== null) {
                        $out .= substr($html, $close, $closelen);
                    }
                }
                $i = $close === null ? $tagend : $close + $closelen;
                continue;
            }

            $out .= self::clean_attributes($tag);
            $i = $tagend;
        }

        return $out;
    }

    /**
     * Whether the character at $offset begins a tag rather than a bare less-than.
     *
     * @param string $html Source HTML.
     * @param int $offset Offset of '<'.
     * @return bool
     */
    private static function is_tag_start(string $html, int $offset): bool {
        $next = $html[$offset + 1] ?? '';
        return $next !== '' && (ctype_alpha($next) || $next === '/' || $next === '!');
    }

    /**
     * Case-insensitive prefix test.
     *
     * @param string $html Source HTML.
     * @param int $offset Start offset.
     * @param string $prefix Prefix to match.
     * @return bool
     */
    private static function starts_with_ci(string $html, int $offset, string $prefix): bool {
        return strncasecmp(substr($html, $offset, strlen($prefix)), $prefix, strlen($prefix)) === 0;
    }

    /**
     * Offset just after the tag that starts at $offset, respecting quoted '>'.
     *
     * @param string $html Source HTML.
     * @param int $offset Offset of '<'.
     * @return int
     */
    private static function find_tag_end(string $html, int $offset): int {
        $length = strlen($html);
        $quote = '';
        for ($i = $offset + 1; $i < $length; $i++) {
            $char = $html[$i];
            if ($quote !== '') {
                if ($char === $quote) {
                    $quote = '';
                }
                continue;
            }
            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }
            if ($char === '>') {
                return $i + 1;
            }
        }
        return $length;
    }

    /**
     * Offset of the closing tag, or null when it is absent.
     *
     * @param string $html Source HTML.
     * @param int $from Offset to start searching.
     * @param string $name Lower-case tag name.
     * @return int|null
     */
    private static function find_closing_tag(string $html, int $from, string $name): ?int {
        $needle = '</' . $name;
        $offset = $from;
        $length = strlen($html);
        while ($offset < $length) {
            $found = stripos($html, $needle, $offset);
            if ($found === false) {
                return null;
            }
            $after = $html[$found + strlen($needle)] ?? '';
            if ($after === '' || $after === '>' || ctype_space($after)) {
                return $found;
            }
            $offset = $found + strlen($needle);
        }
        return null;
    }

    /**
     * Lower-case tag name, or an empty string for a closing or unknown tag.
     *
     * @param string $tag Full tag including angle brackets.
     * @return string
     */
    private static function tag_name(string $tag): string {
        if (!preg_match('/^<\s*([a-zA-Z0-9:-]+)/', $tag, $matches)) {
            return '';
        }
        return strtolower($matches[1]);
    }

    /**
     * Drop event handlers, srcdoc, and javascript: URL attributes from one tag.
     *
     * @param string $tag Full tag including angle brackets.
     * @return string
     */
    private static function clean_attributes(string $tag): string {
        $cleaned = preg_replace_callback(
            '/\s+[^\s=\/>]+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'=<>\x60]+))?/',
            static function (array $match): string {
                if (!preg_match('/^\s+([^\s=\/>]+)/', $match[0], $name)) {
                    return $match[0];
                }
                $attrname = strtolower($name[1]);
                if (str_starts_with($attrname, 'on') || $attrname === 'srcdoc') {
                    return '';
                }
                if (
                    in_array($attrname, self::URL_ATTRIBUTES, true)
                        && self::is_javascript_url(self::attribute_value($match[0]))
                ) {
                    return '';
                }
                return $match[0];
            },
            $tag
        );

        return is_string($cleaned) ? $cleaned : $tag;
    }

    /**
     * Unquoted or quoted attribute value.
     *
     * @param string $attribute Attribute text including the leading space.
     * @return string
     */
    private static function attribute_value(string $attribute): string {
        if (!preg_match('/=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>\x60]+))/', $attribute, $matches)) {
            return '';
        }
        return $matches[1] !== '' ? $matches[1] : ($matches[2] !== '' ? $matches[2] : ($matches[3] ?? ''));
    }

    /**
     * Whether a URL attribute value is a javascript: URL, including encoded forms.
     *
     * @param string $value Raw attribute value.
     * @return bool
     */
    private static function is_javascript_url(string $value): bool {
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = preg_replace('/[\x00-\x20]+/', '', $decoded) ?? $decoded;
        return stripos($decoded, 'javascript:') === 0;
    }

    /**
     * Whether an iframe tag has an allowlisted HTTPS src.
     *
     * @param string $tag Opening iframe tag.
     * @return bool
     */
    private static function iframe_is_allowed(string $tag): bool {
        if (!preg_match('/\ssrc\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag, $matches)) {
            return false;
        }
        $src = $matches[1] !== '' ? $matches[1] : ($matches[2] !== '' ? $matches[2] : ($matches[3] ?? ''));
        if (self::is_javascript_url($src)) {
            return false;
        }
        $parts = parse_url(html_entity_decode($src, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        if (strtolower((string) $parts['scheme']) !== 'https') {
            return false;
        }
        return in_array(strtolower((string) $parts['host']), self::IFRAME_HOSTS, true);
    }
}
