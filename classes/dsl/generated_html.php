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
 * Allowlist sanitiser for generated HTML before it is stored.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\dsl;

/**
 * Reduces generated HTML to the elements and attributes allowed by {@see generated_html_policy}.
 *
 * The input is parsed with a real HTML parser (the HTML5 parser of PHP 8.4 when available, libxml otherwise),
 * the tree is walked against the allowlist, and the output is serialized from the tree by this class. Nothing
 * is copied from the input string: elements and attribute names are written in the policy's canonical
 * spelling, attribute values and text are escaped, and comments, processing instructions and disallowed
 * elements (with their content) are never emitted. A browser therefore only ever parses markup made of
 * allowlisted elements and escaped text, whatever the original input looked like.
 *
 * Callers render the result with noclean (page content, module intros, glossary definitions), so
 * {@see generated_html_policy} is the single place to change what generated HTML may contain.
 */
final class generated_html {
    /** @var string Id of the container element that holds the input on the libxml path. */
    private const WRAPPER_ID = 'local-dixeo-generated-html-root';

    /** @var int Flags for htmlspecialchars() on text nodes. */
    private const TEXT_FLAGS = ENT_NOQUOTES | ENT_HTML5 | ENT_SUBSTITUTE;

    /** @var int Flags for htmlspecialchars() on attribute values. */
    private const ATTRIBUTE_FLAGS = ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE;

    /**
     * Return HTML reduced to the allowlist.
     *
     * @param string $html Generated HTML.
     * @return string HTML safe to store and render without further cleaning.
     */
    public static function strip_active_content(string $html): string {
        if ($html === '' || !str_contains($html, '<')) {
            return $html;
        }

        return self::sanitise($html, class_exists(\Dom\HTMLDocument::class));
    }

    /**
     * Run the sanitiser with a chosen parser.
     *
     * Test seam only: production code calls {@see self::strip_active_content()}, which picks the HTML5 parser
     * whenever PHP provides it. Tests use this to compare both parser paths on PHP 8.4 or later.
     *
     * @param string $html Generated HTML.
     * @param bool $usehtml5 True for the HTML5 parser (PHP 8.4 or later), false for libxml.
     * @return string
     * @throws \coding_exception When the HTML5 parser is requested but not available.
     */
    public static function strip_active_content_with(string $html, bool $usehtml5): string {
        if ($usehtml5 && !class_exists(\Dom\HTMLDocument::class)) {
            throw new \coding_exception('The HTML5 parser needs PHP 8.4 or later.');
        }
        if ($html === '' || !str_contains($html, '<')) {
            return $html;
        }

        return self::sanitise($html, $usehtml5);
    }

    /**
     * Parse, walk and serialize.
     *
     * @param string $html Generated HTML.
     * @param bool $usehtml5 Whether to use the HTML5 parser.
     * @return string
     */
    private static function sanitise(string $html, bool $usehtml5): string {
        $roots = $usehtml5 ? self::parse_html5($html) : self::parse_libxml($html);
        $out = '';
        foreach ($roots as $root) {
            $out .= self::walk_children($root, false, 0);
        }
        return $out;
    }

    /**
     * Parse with the HTML5 algorithm and return the body element.
     *
     * @param string $html Generated HTML.
     * @return array Nodes whose children are the parsed input.
     */
    private static function parse_html5(string $html): array {
        $document = \Dom\HTMLDocument::createFromString(
            '<!DOCTYPE html><html><head></head><body>' . $html . '</body></html>',
            LIBXML_NOERROR | LIBXML_COMPACT,
            'UTF-8'
        );
        $body = $document->body;
        return $body === null ? [] : [$body];
    }

    /**
     * Parse with libxml and return the input container.
     *
     * libxml places content that follows an unbalanced closing tag of the container after the container,
     * inside body, so those later siblings are returned too (moved into a detached element). Nothing outside
     * body is ever returned, so parser-implied head content is never emitted.
     *
     * A '<' that cannot start a tag in HTML5 (not followed by an ASCII letter, '/', '!' or '?') is escaped
     * first: libxml versions disagree on such input (2.9.13 reads "a < b and b > c" as a tag), and HTML5
     * always reads it as text.
     *
     * @param string $html Generated HTML.
     * @return array Nodes whose children are the parsed input.
     */
    private static function parse_libxml(string $html): array {
        $html = preg_replace('/<(?![A-Za-z\/!?])/', '&lt;', $html);
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML(
                '<!DOCTYPE html><html><head>'
                    . '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'
                    . '</head><body><div id="' . self::WRAPPER_ID . '">' . $html . '</div></body></html>',
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (!$loaded) {
            return [];
        }

        $body = $document->getElementsByTagName('body')->item(0);
        $wrapper = $body?->firstChild;
        if (
            !$wrapper instanceof \DOMElement
                || strtolower($wrapper->nodeName) !== 'div'
                || $wrapper->getAttribute('id') !== self::WRAPPER_ID
        ) {
            return [];
        }

        $after = $document->createElement('div');
        while ($wrapper->nextSibling !== null) {
            $after->appendChild($wrapper->nextSibling);
        }
        return [$wrapper, $after];
    }

    /**
     * Serialize the allowed children of a node.
     *
     * @param object $parent DOM node (\DOMNode or \Dom\Node).
     * @param bool $insvg Whether the parent is an SVG element.
     * @param int $depth Element depth of the children.
     * @return string
     */
    private static function walk_children(object $parent, bool $insvg, int $depth): string {
        $out = '';
        foreach ($parent->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $out .= htmlspecialchars((string) $child->nodeValue, self::TEXT_FLAGS, 'UTF-8');
            } else if ($child->nodeType === XML_ELEMENT_NODE && $depth < generated_html_policy::MAX_DEPTH) {
                $out .= self::serialize_element($child, $insvg, $depth);
            }
        }
        return $out;
    }

    /**
     * Serialize one element and its allowed descendants, or nothing when the element is not allowed.
     *
     * @param object $element DOM element (\DOMElement or \Dom\Element).
     * @param bool $insvg Whether the parent is an SVG element.
     * @param int $depth Element depth.
     * @return string
     */
    private static function serialize_element(object $element, bool $insvg, int $depth): string {
        $name = strtolower((string) $element->nodeName);

        if (isset(generated_html_policy::UNWRAP_ELEMENTS[$name])) {
            return self::walk_children($element, $insvg, $depth + 1);
        }

        $issvg = $insvg || $name === 'svg';
        $canonical = $issvg
            ? (generated_html_policy::SVG_ELEMENTS[$name] ?? null)
            : (generated_html_policy::HTML_ELEMENTS[$name] ?? null);
        if ($canonical === null) {
            return '';
        }

        $attributes = self::allowed_attributes($element, $name, $issvg);
        if ($name === 'iframe') {
            if (!self::iframe_src_allowed($attributes['src'][1] ?? null)) {
                return '';
            }
            return '<iframe' . self::serialize_attributes($attributes) . '></iframe>';
        }

        $open = '<' . $canonical . self::serialize_attributes($attributes);
        if (!$issvg && isset(generated_html_policy::VOID_ELEMENTS[$name])) {
            return $open . '>';
        }

        $inner = self::walk_children($element, $issvg, $depth + 1);
        if ($issvg && $inner === '') {
            return $open . '/>';
        }
        return $open . '>' . $inner . '</' . $canonical . '>';
    }

    /**
     * Allowed attributes of an element, in source order.
     *
     * @param object $element DOM element.
     * @param string $name Lower-case element name.
     * @param bool $issvg Whether the element is an SVG element.
     * @return array Lower-case name => [canonical name, value].
     */
    private static function allowed_attributes(object $element, string $name, bool $issvg): array {
        $allowed = [];
        foreach ($element->attributes as $attribute) {
            $attrname = strtolower((string) $attribute->nodeName);
            $value = (string) $attribute->value;
            $canonical = self::attribute_name($name, $issvg, $attrname);
            if ($canonical === null || !self::attribute_value_allowed($name, $attrname, $value)) {
                continue;
            }
            $allowed[$attrname] = [$canonical, $value];
        }
        return $allowed;
    }

    /**
     * Canonical attribute name, or null when the attribute is not allowed on the element.
     *
     * @param string $element Lower-case element name.
     * @param bool $issvg Whether the element is an SVG element.
     * @param string $attribute Lower-case attribute name.
     * @return string|null
     */
    private static function attribute_name(string $element, bool $issvg, string $attribute): ?string {
        if (isset(generated_html_policy::GLOBAL_ATTRIBUTES[$attribute])) {
            return generated_html_policy::GLOBAL_ATTRIBUTES[$attribute];
        }
        if (isset(generated_html_policy::DATA_ATTRIBUTES[$attribute])) {
            return $attribute;
        }
        if (preg_match(generated_html_policy::ARIA_ATTRIBUTE_PATTERN, $attribute)) {
            return $attribute;
        }
        if ($issvg) {
            if ($element === 'use' && isset(generated_html_policy::USE_ATTRIBUTES[$attribute])) {
                return generated_html_policy::USE_ATTRIBUTES[$attribute];
            }
            return generated_html_policy::SVG_ATTRIBUTES[$attribute] ?? null;
        }
        return generated_html_policy::ELEMENT_ATTRIBUTES[$element][$attribute] ?? null;
    }

    /**
     * Whether an attribute value may be kept.
     *
     * @param string $element Lower-case element name.
     * @param string $attribute Lower-case attribute name.
     * @param string $value Value as decoded by the parser.
     * @return bool
     */
    private static function attribute_value_allowed(string $element, string $attribute, string $value): bool {
        if (str_contains($value, '<') || str_contains($value, '>')) {
            return false;
        }

        $normalised = strtolower(self::normalise_url($value));
        foreach (generated_html_policy::FORBIDDEN_VALUE_TOKENS as $token) {
            if (str_contains($normalised, $token)) {
                return false;
            }
        }
        if (!self::css_urls_allowed($normalised)) {
            return false;
        }
        $isimagedata = $element === 'img' && $attribute === 'src'
            && preg_match(generated_html_policy::IMAGE_DATA_URI, $normalised);
        if (!$isimagedata && str_contains(self::without_allowed_css_data($normalised), 'data:')) {
            return false;
        }

        if (isset(generated_html_policy::DATA_ATTRIBUTES[$attribute])) {
            return (bool) preg_match(generated_html_policy::DATA_ATTRIBUTES[$attribute], $value);
        }
        if (isset(generated_html_policy::FIXED_VALUES[$attribute])) {
            return $value === generated_html_policy::FIXED_VALUES[$attribute];
        }
        if ($attribute === 'style') {
            foreach (generated_html_policy::FORBIDDEN_STYLE_TOKENS as $token) {
                if (str_contains($normalised, $token)) {
                    return false;
                }
            }
            return true;
        }
        if ($element === 'use' && isset(generated_html_policy::USE_ATTRIBUTES[$attribute])) {
            return str_starts_with($normalised, '#');
        }
        if ($attribute === 'href') {
            $scheme = self::url_scheme($normalised);
            return $scheme === null || in_array($scheme, generated_html_policy::HREF_SCHEMES, true);
        }
        if ($attribute === 'src' && $element === 'img') {
            $scheme = self::url_scheme($normalised);
            return $isimagedata || $scheme === null || in_array($scheme, generated_html_policy::IMG_SRC_SCHEMES, true);
        }
        return true;
    }

    /**
     * Whether every url() in a normalised value points at a fragment, an https URL or an inline image.
     *
     * @param string $normalised Lower-case normalised value.
     * @return bool
     */
    private static function css_urls_allowed(string $normalised): bool {
        $offset = 0;
        while (($found = strpos($normalised, 'url(', $offset)) !== false) {
            $offset = $found + 4;
            if (!preg_match(generated_html_policy::CSS_URL_TARGET, substr($normalised, $offset))) {
                return false;
            }
        }
        return true;
    }

    /**
     * Remove the inline-image targets that url() may use, so any other data: URL can be detected.
     *
     * @param string $normalised Lower-case normalised value.
     * @return string
     */
    private static function without_allowed_css_data(string $normalised): string {
        return preg_replace('#url\(["\']?data:image/(png|gif|jpeg|webp)[;,]#', 'url(', $normalised) ?? $normalised;
    }

    /**
     * Whether an iframe src matches one of the allowed embed patterns.
     *
     * @param string|null $src Value as decoded by the parser.
     * @return bool
     */
    private static function iframe_src_allowed(?string $src): bool {
        if ($src === null) {
            return false;
        }
        $normalised = self::normalise_url($src);
        foreach (generated_html_policy::IFRAME_SRC_PATTERNS as $pattern) {
            if (preg_match($pattern, $normalised)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Lower-case URL scheme, or null for a relative URL.
     *
     * The scheme is everything before the first ':' that comes before any '/', '?' or '#'.
     *
     * @param string $normalised Normalised URL.
     * @return string|null
     */
    private static function url_scheme(string $normalised): ?string {
        $colon = strpos($normalised, ':');
        if ($colon === false || strcspn($normalised, '/?#') < $colon) {
            return null;
        }
        return strtolower(substr($normalised, 0, $colon));
    }

    /**
     * Decode a value the way a browser might before checking it.
     *
     * Numeric character references are decoded with or without their semicolon, then named references, then
     * every character from U+0000 to U+0020 is removed. The parsers have already decoded the value once; this
     * second pass makes both parser paths reach the same verdict and only makes the checks stricter.
     *
     * @param string $value Attribute value.
     * @return string
     */
    private static function normalise_url(string $value): string {
        $decoded = preg_replace_callback(
            '/&#(x[0-9a-f]+|[0-9]+);?/i',
            static function (array $match): string {
                $digits = $match[1];
                $codepoint = ($digits[0] === 'x' || $digits[0] === 'X') ? hexdec(substr($digits, 1)) : (int) $digits;
                if (!is_int($codepoint) || $codepoint < 1 || $codepoint > 0x10FFFF) {
                    return "\u{FFFD}";
                }
                $char = mb_chr($codepoint, 'UTF-8');
                return $char === false ? "\u{FFFD}" : $char;
            },
            $value
        ) ?? $value;
        $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return preg_replace('/[\x00-\x20]+/', '', $decoded) ?? $decoded;
    }

    /**
     * Serialize attributes with double-quoted, escaped values.
     *
     * @param array $attributes Allowed attributes from {@see self::allowed_attributes()}.
     * @return string
     */
    private static function serialize_attributes(array $attributes): string {
        $out = '';
        foreach ($attributes as [$name, $value]) {
            $out .= ' ' . $name . '="' . htmlspecialchars($value, self::ATTRIBUTE_FLAGS, 'UTF-8') . '"';
        }
        return $out;
    }
}
