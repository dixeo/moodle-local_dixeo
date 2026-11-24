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

namespace local_dixeo\dsl;

/**
 * Tests for the generated HTML allowlist sanitiser.
 *
 * PHP 8.4 or later runs every case on both parser paths (HTML5 and libxml); earlier versions run libxml only.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\dsl\generated_html
 * @covers     \local_dixeo\dsl\generated_html_policy
 */
final class generated_html_test extends \advanced_testcase {
    /** @var string Elements that must never appear in the output. */
    private const NEVER_ALLOWED = 'script|style|textarea|title|noscript|noembed|noframes|xmp|plaintext|template|object'
        . '|embed|form|base|meta|link|foreignobject|animate|set|animatemotion|animatetransform|math|image|desc';

    /**
     * Parser paths available on this PHP version.
     *
     * @return bool[] Values for the usehtml5 argument of the test seam.
     */
    private static function parser_paths(): array {
        return class_exists(\Dom\HTMLDocument::class) ? [true, false] : [false];
    }

    /**
     * Inputs that try to smuggle active content past the sanitiser.
     *
     * @return array<string, array{0: string}>
     */
    public static function bypass_provider(): array {
        $payload = '<img src=x onerror=alert(1)>';
        $cases = [];
        foreach (['textarea', 'noscript', 'title', 'style', 'xmp', 'noembed', 'noframes', 'template'] as $element) {
            $cases['raw text ' . $element] = [
                '<' . $element . '><p title="</' . $element . '>' . $payload . '"></p></' . $element . '>',
            ];
        }
        $cases['plaintext'] = ['<plaintext>' . $payload];
        $cases['prefixed svg style'] = ['<svg><SVG:STYLE><p title="</SVG:STYLE>' . $payload . '"></p></SVG:STYLE></svg>'];

        $cases['comment bang end'] = ['<!-- --!><img src=x onerror=alert(1)> -->'];
        $cases['comment short end'] = ['<!--><img src=x onerror=alert(2)>-->'];
        $cases['comment dash end'] = ['<!---><img src=x onerror=alert(3)>-->'];
        $cases['cdata'] = ['<![CDATA[<img src=x onerror=alert(4)>]]>'];
        $cases['processing instruction'] = ['<?x <img src=x onerror=alert(5)>?>'];

        $cases['numeric reference without semicolon'] = ['<a href="&#106avascript:alert(1)">x</a>'];
        $cases['tab reference'] = ['<a href="java&#9script:alert(1)">x</a>'];
        $cases['colon reference unquoted'] = ['<a href=javascript&#58alert(1)>x</a>'];
        $cases['hex references'] = ['<a href="&#x6A&#x61vascript:alert(1)">x</a>'];
        $cases['zero padded reference'] = ['<a href="&#0000106avascript:alert(1)">x</a>'];

        $cases['style import'] = ['<style>@import url(https://evil.example/x.css);</style><p>x</p>'];

        $cases['svg animate href'] = [
            '<svg><a><animate attributeName="href" values="javascript:alert(1)"/><text y="20">x</text></a></svg>',
        ];
        $cases['svg set href'] = ['<svg><a><set attributeName="href" to="javascript:alert(1)"/><text>x</text></a></svg>'];
        $cases['svg animate xlink'] = [
            '<svg><a><animate attributeName="xlink:href" values="javascript:alert(1)"/><text>x</text></a></svg>',
        ];
        $cases['svg animate value list'] = [
            '<svg><a><animate attributeName="href" values="#;javascript:alert(1)"/><text>x</text></a></svg>',
        ];
        $cases['svg animate encoded'] = [
            '<svg><a><animate attributeName="href" values="&#106;avascript:alert(1)"/><text>x</text></a></svg>',
        ];
        $cases['svg animate upper case'] = [
            '<svg><a><SVG:ANIMATE attributeName="href" values="javascript:alert(1)"/><text>x</text></a></svg>',
        ];
        $cases['form'] = ['<form action="https://evil.example/login"><input name="p"></form>'];
        $cases['base'] = ['<base href="https://evil.example/">'];
        $cases['object'] = ['<object data="https://evil.example/x.swf"></object>'];
        $cases['embed'] = ['<embed src="https://evil.example/x.swf">'];
        $cases['meta refresh'] = ['<meta http-equiv="refresh" content="0;url=https://evil.example/">'];

        $cases['script'] = ['<p>a</p><script>alert(1)</script>'];
        $cases['event handlers'] = [
            '<p onclick="alert(1)">a</p><svg onload="alert(1)"></svg><img src="a.png" onerror="alert(1)">',
        ];
        $cases['srcdoc'] = ['<iframe srcdoc="<script>alert(1)</script>"></iframe>'];
        $cases['javascript href'] = ['<a href="javascript:alert(1)">x</a>'];
        $cases['other iframe'] = ['<iframe src="https://evil.example/x"></iframe>'];

        $iframes = [
            'iframe backslash userinfo' => 'https://evil.example\\@www.youtube.com/embed/xxxxxxxx',
            'iframe userinfo' => 'https://www.youtube.com@evil.example/embed/xxxxxxxx',
            'iframe lookalike host' => 'https://www.youtube.com.evil.example/embed/xxxxxxxx',
            'iframe path' => 'https://evil.example/https://www.youtube.com/embed/xxxxxxxx',
            'iframe protocol relative' => '//www.youtube.com/embed/xxxxxxxx',
            'iframe encoded backslash' => 'https://evil.example&#92;@www.youtube.com/embed/xxxxxxxx',
        ];
        foreach ($iframes as $name => $src) {
            $cases[$name] = ['<iframe src="' . $src . '"></iframe>'];
        }

        $cases['modal toggle'] = ['<button data-bs-toggle="modal">x</button><div data-bs-toggle="modal">x</div>'];
        $cases['tooltip toggle'] = ['<div data-toggle="tooltip" data-html="true" title="x">x</div>'];
        $cases['data href'] = ['<a href="data:text/html,&lt;script&gt;alert(1)&lt;/script&gt;">x</a>'];
        $cases['cross origin use'] = ['<svg><use href="https://evil.example/x.svg#a"/></svg>'];
        $cases['style javascript url'] = ['<p style="background:url(javascript:alert(1))">x</p>'];
        $cases['style expression'] = ['<p style="x:expression(alert(1))">x</p>'];
        return $cases;
    }

    /**
     * Bypass attempts never produce active content, on every parser path.
     *
     * @dataProvider bypass_provider
     * @param string $html Input.
     */
    public function test_bypass_attempts_are_neutralised(string $html): void {
        foreach (self::parser_paths() as $usehtml5) {
            $this->assert_safe(generated_html::strip_active_content_with($html, $usehtml5), $usehtml5);
        }
        $this->assert_safe(generated_html::strip_active_content($html), class_exists(\Dom\HTMLDocument::class));
    }

    /**
     * Content generated pages rely on, with the exact output expected.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function keep_provider(): array {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 50" width="100%" role="img" aria-label="Flow">'
            . '<defs><linearGradient id="lg" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#fff"/>'
            . '<stop offset="1" stop-color="#000"/></linearGradient>'
            . '<marker id="m" markerWidth="4" markerHeight="4" refX="2" refY="2" orient="auto"><path d="M0 0L4 2L0 4z"/>'
            . '</marker></defs><g fill="url(#lg)" stroke="#333"><path d="M10 10L90 40" marker-end="url(#m)"/>'
            . '<text x="10" y="45" text-anchor="start">Step 1</text><use href="#m" x="5"/></g></svg>';
        $collapse = '<div class="accordion" id="acc"><h3><a href="#c1" data-bs-toggle="collapse" data-bs-target="#c1"'
            . ' aria-expanded="false">Title</a></h3><div id="c1" class="collapse" data-bs-parent="#acc">Body</div></div>';
        $table = '<table><thead><tr><th scope="col">A</th><th scope="col">B</th></tr></thead>'
            . '<tbody><tr><td colspan="2">Both</td></tr></tbody></table>';
        $formatting = '<p><strong>Bold</strong> <em>it</em> <code>x = 1</code></p><pre>line 1' . "\n" . 'line 2</pre>';
        return [
            'svg diagram' => [$svg, $svg],
            'table' => [$table, $table],
            'youtube iframe' => [
                '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ?start=10" width="560" height="315" allowfullscreen>'
                    . '</iframe>',
                '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ?start=10" width="560" height="315"'
                    . ' allowfullscreen=""></iframe>',
            ],
            'youtube nocookie iframe' => [
                '<iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"></iframe>',
                '<iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"></iframe>',
            ],
            'vimeo iframe' => [
                '<iframe src="https://player.vimeo.com/video/76979871" title="Video"></iframe>',
                '<iframe src="https://player.vimeo.com/video/76979871" title="Video"></iframe>',
            ],
            'pluginfile image' => [
                '<img src="@@PLUGINFILE@@/a.png" alt="">',
                '<img src="@@PLUGINFILE@@/a.png" alt="">',
            ],
            'collapse accordion' => [$collapse, $collapse],
            'inline formatting' => [$formatting, $formatting],
            'inline style' => [
                "<div style='color:#333;margin:0 auto'>Styled</div>",
                '<div style="color:#333;margin:0 auto">Styled</div>',
            ],
            'shortcode' => [
                '<p>Before</p>[img-gen prompt="A volcano" alt="Volcano"]<p>After</p>',
                '<p>Before</p>[img-gen prompt="A volcano" alt="Volcano"]<p>After</p>',
            ],
            'utf-8 text' => [
                '<p>Éléphant déjà vu, العربية, 🙂🚀</p>',
                '<p>Éléphant déjà vu, العربية, 🙂🚀</p>',
            ],
            'bare less-than' => [
                '<p>a < b and b > c</p>',
                '<p>a &lt; b and b &gt; c</p>',
            ],
            'external link' => [
                '<a href="https://moodle.org/" target="_blank" rel="noopener">Moodle</a>',
                '<a href="https://moodle.org/" target="_blank" rel="noopener">Moodle</a>',
            ],
            'font wrapper' => [
                '<p><font color="red">Red</font></p><center>Mid</center>',
                '<p>Red</p>Mid',
            ],
        ];
    }

    /**
     * Legitimate generated content is kept, in canonical form, identically on every parser path.
     *
     * @dataProvider keep_provider
     * @param string $html Input.
     * @param string $expected Expected output.
     */
    public function test_keeps_generated_content(string $html, string $expected): void {
        foreach (self::parser_paths() as $usehtml5) {
            $this->assertSame($expected, generated_html::strip_active_content_with($html, $usehtml5));
        }
        $this->assertSame($expected, generated_html::strip_active_content($html));
    }

    /**
     * Plain text without markup is returned unchanged.
     */
    public function test_plain_text_is_unchanged(): void {
        $this->assertSame('Plain & simple', generated_html::strip_active_content('Plain & simple'));
        $this->assertSame('', generated_html::strip_active_content(''));
    }

    /**
     * Elements nested beyond the depth cap are removed.
     *
     * The HTML5 parser keeps the content after the deep branch. libxml stops parsing at its own nesting limit,
     * so on that path the rest of the input is dropped, which fails closed.
     */
    public function test_depth_cap(): void {
        $html = str_repeat('<span>', 300) . 'deep' . str_repeat('</span>', 300) . '<p>after</p>';

        foreach (self::parser_paths() as $usehtml5) {
            $result = generated_html::strip_active_content_with($html, $usehtml5);
            $this->assertStringNotContainsString('deep', $result);
            $this->assertLessThanOrEqual(generated_html_policy::MAX_DEPTH, substr_count($result, '<span>'));
            if ($usehtml5) {
                $this->assertStringEndsWith('<p>after</p>', $result);
            }
        }
    }

    /**
     * Real generated content fixtures.
     *
     * @return array<string, array{0: string}>
     */
    public static function real_content_provider(): array {
        return [
            'page with diagram' => ['page_diagram.html'],
            'label' => ['label.html'],
            'glossary entry' => ['glossary_entry.html'],
        ];
    }

    /**
     * Sanitising real generated content changes neither its visible text nor its media and table counts.
     *
     * @dataProvider real_content_provider
     * @param string $fixture Fixture file name.
     */
    public function test_real_content_is_preserved(string $fixture): void {
        $html = file_get_contents(__DIR__ . '/../fixtures/generated_html/' . $fixture);

        foreach (self::parser_paths() as $usehtml5) {
            $result = generated_html::strip_active_content_with($html, $usehtml5);
            $this->assertSame($this->visible_text($html), $this->visible_text($result));
            foreach (['svg', 'img', 'table', 'iframe'] as $element) {
                $this->assertSame(
                    preg_match_all('/<' . $element . '\b/i', $html),
                    preg_match_all('/<' . $element . '\b/i', $result),
                    $fixture . ': ' . $element
                );
            }
        }
    }

    /**
     * The page fixture keeps its diagram attributes in canonical case and its inline style.
     */
    public function test_real_page_diagram_keeps_svg_attributes(): void {
        $html = file_get_contents(__DIR__ . '/../fixtures/generated_html/page_diagram.html');

        $result = generated_html::strip_active_content($html);

        $this->assertStringContainsString('<svg width="100%" viewBox="0 0 700 240" role="img"', $result);
        $this->assertStringContainsString('<path d="M70 160 C180 150, 250 140, 330 120 S500 90, 640 70" fill="none"', $result);
        $this->assertStringContainsString('style="background:#f7f9fc;', $result);
    }

    /**
     * Assert the safety properties every sanitised output must have.
     *
     * @param string $result Sanitised HTML.
     * @param bool $usehtml5 Parser path, for the failure message.
     */
    private function assert_safe(string $result, bool $usehtml5): void {
        $path = $usehtml5 ? 'html5' : 'libxml';
        $this->assertDoesNotMatchRegularExpression('/<[^>]*\son[a-z]+\s*=/i', $result, $path);
        $this->assertDoesNotMatchRegularExpression('/<\s*\/?\s*(?:[a-z]+:)?(?:' . self::NEVER_ALLOWED . ')\b/i', $result, $path);
        $this->assertDoesNotMatchRegularExpression('/<\/?[a-z]+:/i', $result, $path);
        $this->assertStringNotContainsString('<iframe', $result, $path);
        $this->assertStringNotContainsString('evil.example', $result, $path);
        $this->assertStringNotContainsString('@import', $result, $path);
        $this->assertStringNotContainsString('expression(', $result, $path);
        $this->assertStringNotContainsString('srcdoc', $result, $path);
        $this->assertDoesNotMatchRegularExpression('/data-(?:bs-)?toggle="(?!collapse")/', $result, $path);
        $this->assertDoesNotMatchRegularExpression('/data-(?:bs-)?html/', $result, $path);

        $decoded = preg_replace_callback(
            '/&#(x[0-9a-f]+|[0-9]+);?/i',
            static function (array $match): string {
                $digits = $match[1];
                $codepoint = ($digits[0] === 'x' || $digits[0] === 'X') ? (int) hexdec(substr($digits, 1)) : (int) $digits;
                return (string) mb_chr($codepoint, 'UTF-8');
            },
            $result
        );
        $decoded = preg_replace('/[\x00-\x20]+/', '', html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $decoded, $path);
        $this->assertStringNotContainsStringIgnoringCase('vbscript:', $decoded, $path);
        $this->assertStringNotContainsStringIgnoringCase('data:text', $decoded, $path);
    }

    /**
     * Visible text with entities decoded and whitespace collapsed.
     *
     * @param string $html HTML.
     * @return string
     */
    private function visible_text(string $html): string {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
