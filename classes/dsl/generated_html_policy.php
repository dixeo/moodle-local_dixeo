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
 * Allowlist used by the generated HTML sanitiser.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\dsl;

/**
 * Elements, attributes and URL rules that generated HTML may keep.
 *
 * This class is the single place that decides what generated page, intro and glossary HTML may contain.
 * Keys are lower-case names (lookups are case-insensitive); values are the canonical spelling written to
 * the output, so libxml's lower-cased `viewbox` and the HTML5 parser's `viewBox` both serialize as `viewBox`.
 *
 * Anything not listed is removed. These elements are never allowed, because each one either runs or loads
 * active content or is parsed differently by browsers and server-side parsers (raw text, RCDATA, foreign
 * content): script, style, textarea, title, noscript, noembed, noframes, xmp, plaintext, template, iframe
 * (unless its src matches {@see self::IFRAME_SRC_PATTERNS}), object, embed, form, base, meta, link,
 * foreignObject, animate, set, animateMotion, animateTransform, math, image and desc.
 */
final class generated_html_policy {
    /** @var array<string, string> HTML elements allowed outside SVG (iframe is subject to the src patterns). */
    public const HTML_ELEMENTS = [
        'a' => 'a', 'abbr' => 'abbr', 'article' => 'article', 'aside' => 'aside', 'b' => 'b',
        'blockquote' => 'blockquote', 'br' => 'br', 'caption' => 'caption', 'cite' => 'cite', 'code' => 'code',
        'col' => 'col', 'colgroup' => 'colgroup', 'dd' => 'dd', 'del' => 'del', 'details' => 'details',
        'dfn' => 'dfn', 'div' => 'div', 'dl' => 'dl', 'dt' => 'dt', 'em' => 'em', 'figcaption' => 'figcaption',
        'figure' => 'figure', 'footer' => 'footer', 'h1' => 'h1', 'h2' => 'h2', 'h3' => 'h3', 'h4' => 'h4',
        'h5' => 'h5', 'h6' => 'h6', 'header' => 'header', 'hr' => 'hr', 'i' => 'i', 'img' => 'img',
        'ins' => 'ins', 'kbd' => 'kbd', 'li' => 'li', 'mark' => 'mark', 'ol' => 'ol', 'p' => 'p', 'pre' => 'pre',
        'q' => 'q', 's' => 's', 'samp' => 'samp', 'section' => 'section', 'small' => 'small', 'span' => 'span',
        'strong' => 'strong', 'sub' => 'sub', 'summary' => 'summary', 'sup' => 'sup', 'table' => 'table',
        'tbody' => 'tbody', 'td' => 'td', 'tfoot' => 'tfoot', 'th' => 'th', 'thead' => 'thead', 'tr' => 'tr',
        'u' => 'u', 'ul' => 'ul', 'iframe' => 'iframe',
    ];

    /** @var array<string, string> SVG elements, allowed only inside an svg element (svg itself starts one). */
    public const SVG_ELEMENTS = [
        'svg' => 'svg', 'g' => 'g', 'defs' => 'defs', 'symbol' => 'symbol', 'use' => 'use', 'path' => 'path',
        'rect' => 'rect', 'circle' => 'circle', 'ellipse' => 'ellipse', 'line' => 'line',
        'polyline' => 'polyline', 'polygon' => 'polygon', 'text' => 'text', 'tspan' => 'tspan',
        'marker' => 'marker', 'lineargradient' => 'linearGradient', 'radialgradient' => 'radialGradient',
        'stop' => 'stop', 'clippath' => 'clipPath', 'mask' => 'mask', 'pattern' => 'pattern',
    ];

    /** @var array<string, true> Unknown but harmless inline wrappers: the element is removed and its children kept. */
    public const UNWRAP_ELEMENTS = ['font' => true, 'center' => true];

    /** @var array<string, true> HTML elements written without a closing tag. */
    public const VOID_ELEMENTS = ['br' => true, 'col' => true, 'hr' => true, 'img' => true];

    /** @var array<string, string> Attributes allowed on every element. `aria-*` is matched separately. */
    public const GLOBAL_ATTRIBUTES = [
        'class' => 'class', 'id' => 'id', 'title' => 'title', 'lang' => 'lang', 'dir' => 'dir', 'role' => 'role',
        'style' => 'style',
    ];

    /** @var string Pattern for allowed ARIA attribute names. */
    public const ARIA_ATTRIBUTE_PATTERN = '/^aria-[a-z]+(?:-[a-z]+)*$/D';

    /**
     * @var array<string, string> Bootstrap collapse attributes used by generated accordions, mapped to the pattern
     * their value must match. Any other data-* attribute is removed, because Bootstrap tooltip and popover options
     * can carry HTML.
     */
    public const DATA_ATTRIBUTES = [
        'data-toggle' => '/^collapse$/D',
        'data-bs-toggle' => '/^collapse$/D',
        'data-target' => '/^[#.]?[A-Za-z0-9_-]{1,64}$/D',
        'data-bs-target' => '/^[#.]?[A-Za-z0-9_-]{1,64}$/D',
        'data-parent' => '/^[#.]?[A-Za-z0-9_-]{1,64}$/D',
        'data-bs-parent' => '/^[#.]?[A-Za-z0-9_-]{1,64}$/D',
    ];

    /** @var array<string, string> SVG presentation and geometry attributes, allowed on every SVG element. */
    public const SVG_ATTRIBUTES = [
        'viewbox' => 'viewBox', 'xmlns' => 'xmlns', 'width' => 'width', 'height' => 'height', 'x' => 'x',
        'y' => 'y', 'x1' => 'x1', 'y1' => 'y1', 'x2' => 'x2', 'y2' => 'y2', 'cx' => 'cx', 'cy' => 'cy', 'r' => 'r',
        'rx' => 'rx', 'ry' => 'ry', 'd' => 'd', 'points' => 'points', 'fill' => 'fill', 'stroke' => 'stroke',
        'stroke-width' => 'stroke-width', 'stroke-dasharray' => 'stroke-dasharray',
        'stroke-linecap' => 'stroke-linecap', 'stroke-linejoin' => 'stroke-linejoin', 'opacity' => 'opacity',
        'fill-opacity' => 'fill-opacity', 'stroke-opacity' => 'stroke-opacity', 'transform' => 'transform',
        'font-size' => 'font-size', 'font-family' => 'font-family', 'font-weight' => 'font-weight',
        'text-anchor' => 'text-anchor', 'dominant-baseline' => 'dominant-baseline', 'offset' => 'offset',
        'stop-color' => 'stop-color', 'stop-opacity' => 'stop-opacity', 'gradientunits' => 'gradientUnits',
        'gradienttransform' => 'gradientTransform', 'marker-start' => 'marker-start', 'marker-mid' => 'marker-mid',
        'marker-end' => 'marker-end', 'markerwidth' => 'markerWidth', 'markerheight' => 'markerHeight',
        'refx' => 'refX', 'refy' => 'refY', 'orient' => 'orient', 'preserveaspectratio' => 'preserveAspectRatio',
        'clip-path' => 'clip-path', 'mask' => 'mask', 'dx' => 'dx', 'dy' => 'dy', 'patternunits' => 'patternUnits',
    ];

    /** @var array<string, array<string, string>> Attributes allowed on specific HTML elements. */
    public const ELEMENT_ATTRIBUTES = [
        'a' => ['href' => 'href', 'target' => 'target', 'rel' => 'rel'],
        'img' => ['src' => 'src', 'alt' => 'alt', 'width' => 'width', 'height' => 'height', 'loading' => 'loading'],
        'td' => ['colspan' => 'colspan', 'rowspan' => 'rowspan', 'scope' => 'scope'],
        'th' => ['colspan' => 'colspan', 'rowspan' => 'rowspan', 'scope' => 'scope'],
        'ol' => ['start' => 'start', 'type' => 'type'],
        'col' => ['span' => 'span'],
        'colgroup' => ['span' => 'span'],
        'iframe' => [
            'src' => 'src', 'width' => 'width', 'height' => 'height', 'title' => 'title', 'allow' => 'allow',
            'allowfullscreen' => 'allowfullscreen', 'frameborder' => 'frameborder', 'loading' => 'loading',
            'referrerpolicy' => 'referrerpolicy',
        ],
    ];

    /** @var array<string, string> Link attributes of the SVG use element; the value must start with '#'. */
    public const USE_ATTRIBUTES = ['href' => 'href', 'xlink:href' => 'xlink:href'];

    /** @var array<string, string> Attributes whose value must be exactly the given value. */
    public const FIXED_VALUES = ['target' => '_blank'];

    /** @var string[] URL schemes allowed in href. Scheme-less relative URLs and fragments are also allowed. */
    public const HREF_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /** @var string[] URL schemes allowed in img src, besides relative URLs and {@see self::IMAGE_DATA_URI}. */
    public const IMG_SRC_SCHEMES = ['http', 'https'];

    /** @var string Inline image data URI allowed in img src. */
    public const IMAGE_DATA_URI = '#^data:image/(png|gif|jpeg|webp);base64,[A-Za-z0-9+/=]+$#Di';

    /**
     * @var string[] Anchored patterns an iframe src must match, after URL normalisation. Each pattern fixes the
     * scheme, host and path prefix as literals. Never allow a URL on the basis of a parsed host alone: URL parsers
     * disagree with browsers (for example on backslashes), so the whole normalised URL is matched instead.
     */
    public const IFRAME_SRC_PATTERNS = [
        '#^https://(www\.)?youtube(-nocookie)?\.com/embed/[A-Za-z0-9_-]{6,64}(\?[A-Za-z0-9_=&%.-]*)?$#D',
        '#^https://player\.vimeo\.com/video/[0-9]{1,20}(\?[A-Za-z0-9_=&%.-]*)?$#D',
    ];

    /** @var string[] Substrings that remove any attribute whose normalised value contains them. */
    public const FORBIDDEN_VALUE_TOKENS = ['javascript:', 'vbscript:'];

    /** @var string[] Substrings that remove a style attribute whose normalised value contains them. */
    public const FORBIDDEN_STYLE_TOKENS = ['expression', 'javascript:', 'vbscript:', '@import', 'behavior', '-moz-binding'];

    /** @var string Pattern for the start of a url() target that may be kept. */
    public const CSS_URL_TARGET = '#^["\']?(\#|https:|data:image/(png|gif|jpeg|webp)[;,])#i';

    /** @var int Elements nested deeper than this are removed. */
    public const MAX_DEPTH = 256;
}
