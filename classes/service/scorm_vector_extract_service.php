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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_dixeo\service;

use local_dixeo\dto\file_upload_part;

/**
 * Builds plain-text extracts from local SCORM packages for Dixeo file sync.
 *
 * Supports Articulate Storyline HTML5 (slide JSON under html5/data/js/) and
 * classic SCO HTML resources from imsmanifest.xml.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scorm_vector_extract_service {
    /** @var string Prefix for upload filenames (stable per cm id). */
    public const UPLOAD_FILENAME_PREFIX = 'dixeo_scorm_cm';

    /** @var string Storyline / Rise-style HTML5 data path inside the SCORM zip. */
    private const ARTICULATE_HTML5_JS_PREFIX = 'html5/data/js/';

    /** @var string[] Bundled JS files to skip (not per-slide data). */
    private const ARTICULATE_JS_EXCLUDES = ['data.js', 'frame.js', 'paths.js'];

    /**
     * Whether a zip file on disk is an Articulate Storyline HTML5 SCORM package.
     *
     * @param string $zippath Absolute path to a local zip file.
     * @return bool
     */
    public function is_articulate_storyline_package(string $zippath): bool {
        if ($zippath === '' || !is_readable($zippath)) {
            return false;
        }

        $zip = new \ZipArchive();
        if ($zip->open($zippath) !== true) {
            return false;
        }

        try {
            return $this->is_articulate_storyline_zip($zip);
        } finally {
            $zip->close();
        }
    }

    /**
     * Whether a Storyline zip contains extractable slide text (marker + non-empty body).
     *
     * Stricter than {@see is_articulate_storyline_package()} for upload validation.
     *
     * @param string $zippath Absolute path to a local zip file.
     * @return bool
     */
    public function is_storyline_extractable(string $zippath): bool {
        if ($zippath === '' || !is_readable($zippath)) {
            return false;
        }

        $zip = new \ZipArchive();
        if ($zip->open($zippath) !== true) {
            return false;
        }

        try {
            if (!$this->is_articulate_storyline_zip($zip)) {
                return false;
            }

            $slides = $this->extract_articulate_storyline_slides($zip);
            return $slides !== '' && trim($slides) !== '';
        } finally {
            $zip->close();
        }
    }

    /**
     * Resolve the SCORM package title from imsmanifest.xml, with filename fallback.
     *
     * @param string $zippath Absolute path to a local zip file.
     * @param string $fallbackfilename Original upload filename (used if manifest has no title).
     * @return string Activity name, max 255 chars.
     */
    public function get_package_title_from_zip_path(string $zippath, string $fallbackfilename): string {
        if ($zippath !== '' && is_readable($zippath)) {
            $zip = new \ZipArchive();
            if ($zip->open($zippath) === true) {
                try {
                    $manifestpath = $this->locate_manifest_in_zip($zip);
                    if ($manifestpath !== null) {
                        $raw = $zip->getFromName($manifestpath);
                        if ($raw !== false && $raw !== '') {
                            $title = $this->parse_package_title_from_manifest_xml($raw);
                            if ($title !== null && $title !== '') {
                                return \core_text::substr($title, 0, 255);
                            }
                        }
                    }
                } finally {
                    $zip->close();
                }
            }
        }

        return $this->activity_name_from_filename($fallbackfilename);
    }

    /** @var html_helper */
    private html_helper $htmlhelper;

    /**
     * Constructor.
     *
     * @param html_helper|null $htmlhelper Optional HTML helper.
     */
    public function __construct(?html_helper $htmlhelper = null) {
        $this->htmlhelper = $htmlhelper ?? new html_helper();
    }

    /**
     * Resolve the package stored_file for a SCORM instance, if any.
     *
     * @param \cm_info $cm Course module (must be scorm).
     * @return \stored_file|null
     */
    public function get_package_file(\cm_info $cm): ?\stored_file {
        global $CFG, $DB;

        if ($cm->modname !== 'scorm') {
            return null;
        }

        require_once($CFG->dirroot . '/mod/scorm/lib.php');

        $scorm = $DB->get_record('scorm', ['id' => $cm->instance], 'id,reference,scormtype', IGNORE_MISSING);
        if (!$scorm || $scorm->reference === '') {
            return null;
        }

        if (!in_array($scorm->scormtype, [SCORM_TYPE_LOCAL, SCORM_TYPE_LOCALSYNC], true)) {
            return null;
        }

        $context = \context_module::instance($cm->id);
        $fs = get_file_storage();

        $package = $fs->get_file($context->id, 'mod_scorm', 'package', 0, '/', $scorm->reference);
        if (!$package || $package->is_directory()) {
            return null;
        }

        if ($package->is_external_file()) {
            $package->import_external_file_contents();
        }

        return $package;
    }

    /**
     * Build a temp text file for upload, or return null if extraction is empty or fails.
     *
     * Empty extraction does not fail the caller; logs via debugging().
     *
     * @param \cm_info $cm Visible SCORM course module.
     * @return file_upload_part|null
     */
    public function try_build_upload_part(\cm_info $cm): ?file_upload_part {
        if ($cm->modname !== 'scorm' || !$cm->visible) {
            return null;
        }

        $package = $this->get_package_file($cm);
        if (!$package) {
            debugging(
                "local_dixeo file sync: SCORM cmid={$cm->id} skipped (no local package file).",
                DEBUG_DEVELOPER
            );
            return null;
        }

        $ext = strtolower(pathinfo($package->get_filename(), PATHINFO_EXTENSION));
        if ($ext !== 'zip' && $ext !== 'pif') {
            debugging(
                "local_dixeo file sync: SCORM cmid={$cm->id} skipped (package is not a zip/pif archive).",
                DEBUG_DEVELOPER
            );
            return null;
        }

        $requestdir = make_request_directory();
        $zippath = $requestdir . '/dixeo_scorm_pkg_' . $cm->id . '_' . uniqid('', true) . '.zip';
        try {
            $package->copy_content_to($zippath);
            $text = $this->extract_sco_text_from_zip_path($zippath);
        } catch (\Throwable $e) {
            debugging(
                "local_dixeo file sync: SCORM cmid={$cm->id} extraction error: " . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            @unlink($zippath);
            return null;
        }

        @unlink($zippath);

        if ($text === '' || trim($text) === '') {
            debugging(
                "local_dixeo file sync: SCORM cmid={$cm->id} skipped (empty SCO text extract).",
                DEBUG_DEVELOPER
            );
            return null;
        }

        $text = $this->prepend_moodle_activity_context($cm, $text);

        $txtpath = $requestdir . '/' . self::UPLOAD_FILENAME_PREFIX . $cm->id . '_' . uniqid('', true) . '.txt';
        if (file_put_contents($txtpath, $text) === false) {
            debugging(
                "local_dixeo file sync: SCORM cmid={$cm->id} failed to write temp extract file.",
                DEBUG_DEVELOPER
            );
            return null;
        }

        $uploadname = self::UPLOAD_FILENAME_PREFIX . $cm->id . '.txt';
        // Keep temp extract on disk after upload when developer debugging is on (same check as core).
        $deleteafterupload = !debugging(null, DEBUG_DEVELOPER);

        return new file_upload_part($txtpath, $uploadname, 'text/plain', $deleteafterupload);
    }

    /**
     * Prepend Moodle activity title and references so RAG matches user-spoken activity names.
     *
     * @param \cm_info $cm SCORM course module.
     * @param string $body Extracted SCO plain text.
     * @return string Full document for upload.
     */
    private function prepend_moodle_activity_context(\cm_info $cm, string $body): string {
        $title = strip_tags(trim($cm->get_formatted_name()));
        $title = trim(preg_replace('/\s+/', ' ', $title) ?? '');
        if ($title === '') {
            $title = 'SCORM';
        }

        $cmid = (int) $cm->id;

        // H1 = exact Moodle activity name (visible in the course). Lines below tie RAG to that name.
        $header = "# Moodle SCORM activity: {$title}\n\n";
        $header .= "- Course module ID: {$cmid}\n";
        $header .= "- Sync filename: " . self::UPLOAD_FILENAME_PREFIX . "{$cmid}.txt\n\n";
        $header .= "---\n\n";

        return $header . $body;
    }

    /**
     * Extract plain text from SCO launch HTML resources in a SCORM zip on disk.
     *
     * @param string $zippath Absolute path to zip.
     * @return string Joined text sections (may be empty).
     */
    public function extract_sco_text_from_zip_path(string $zippath): string {
        $zip = new \ZipArchive();
        if ($zip->open($zippath) !== true) {
            return '';
        }

        try {
            if ($this->is_articulate_storyline_zip($zip)) {
                $storyline = $this->extract_articulate_storyline_slides($zip);
                if ($storyline !== '' && trim($storyline) !== '') {
                    return $storyline;
                }
            }

            $manifestpath = $this->locate_manifest_in_zip($zip);
            if ($manifestpath === null) {
                return '';
            }

            $raw = $zip->getFromName($manifestpath);
            if ($raw === false || $raw === '') {
                return '';
            }

            $hrefs = $this->parse_sco_hrefs_from_manifest_xml($raw);
            if ($hrefs === []) {
                return '';
            }

            $manifestdir = dirname($manifestpath);
            if ($manifestdir === '.' || $manifestdir === '') {
                $manifestdir = '';
            } else {
                $manifestdir = rtrim(str_replace('\\', '/', $manifestdir), '/');
            }

            $sections = [];
            foreach ($hrefs as $href) {
                $resolved = $this->resolve_zip_inner_path($manifestdir, $href);
                if ($resolved === null) {
                    continue;
                }

                $ext = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));
                if (!in_array($ext, ['html', 'htm', 'xhtml', 'xht'], true)) {
                    continue;
                }

                $html = $zip->getFromName($resolved);
                if ($html === false || $html === '') {
                    continue;
                }

                $plain = $this->htmlhelper->clean_html($html);
                if ($plain !== '') {
                    $sections[] = $plain;
                }
            }

            return implode("\n\n---\n\n", $sections);
        } finally {
            $zip->close();
        }
    }

    /**
     * Detect Articulate Storyline (and similar) HTML5 SCORM where slide data lives under html5/data/js/.
     *
     * @param \ZipArchive $zip Open zip.
     * @return bool
     */
    private function is_articulate_storyline_zip(\ZipArchive $zip): bool {
        $marker = self::ARTICULATE_HTML5_JS_PREFIX . 'data.js';
        if ($zip->locateName($marker, \ZipArchive::FL_NOCASE) !== false) {
            return true;
        }

        return $zip->locateName(str_replace('/', '\\', $marker), \ZipArchive::FL_NOCASE) !== false;
    }

    /**
     * Extract markdown-style text from Articulate Storyline slide JS files.
     *
     * @param \ZipArchive $zip Open zip.
     * @return string Markdown sections or empty string.
     */
    private function extract_articulate_storyline_slides(\ZipArchive $zip): string {
        $excludes = array_flip(array_map('strtolower', self::ARTICULATE_JS_EXCLUDES));
        $prefix = self::ARTICULATE_HTML5_JS_PREFIX;
        $slides = [];
        $datajs = $zip->getFromName(self::ARTICULATE_HTML5_JS_PREFIX . 'data.js');
        if ($datajs === false) {
            $datajs = '';
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false || !empty($stat['directory'])) {
                continue;
            }

            $name = str_replace('\\', '/', $stat['name']);
            if (strncasecmp($name, $prefix, strlen($prefix)) !== 0) {
                continue;
            }

            $base = basename($name);
            if (strcasecmp(substr($base, -3), '.js') !== 0) {
                continue;
            }

            if (isset($excludes[strtolower($base)])) {
                continue;
            }

            $slideid = pathinfo($base, PATHINFO_FILENAME);
            if (!preg_match('/^[a-zA-Z0-9]{8,32}$/', $slideid)) {
                continue;
            }

            $js = $zip->getFromIndex($i);
            if ($js === false || $js === '') {
                continue;
            }

            $slide = $this->parse_storyline_slide_js($js);
            if ($slide === null) {
                continue;
            }

            $body = $this->collect_storyline_text_from_slide($slide);
            $body = trim(preg_replace('/[ \t]+/', ' ', $body) ?? '');
            $body = trim(preg_replace("/\n{3,}/", "\n\n", $body) ?? '');

            if ($body === '') {
                continue;
            }

            $slides[] = [
                'id' => $slide['id'] ?? $slideid,
                'slide' => $slide,
                'body' => $body,
            ];
        }

        if ($slides === []) {
            return '';
        }

        $byid = [];
        foreach ($slides as $item) {
            $byid[(string) $item['id']] = $item;
        }

        $slideids = array_keys($byid);
        $order = $datajs !== ''
            ? $this->order_storyline_slide_ids_by_data_js_occurrence($datajs, $slideids)
            : [];

        $ordereditems = [];
        foreach ($order as $sid) {
            if (isset($byid[$sid])) {
                $ordereditems[] = $byid[$sid];
                unset($byid[$sid]);
            }
        }

        $remainder = array_values($byid);
        usort($remainder, function (array $a, array $b): int {
            $na = (int) ($a['slide']['slideNumberInScene'] ?? 0);
            $nb = (int) ($b['slide']['slideNumberInScene'] ?? 0);
            if ($na !== $nb) {
                return $na <=> $nb;
            }
            return strcmp((string) $a['id'], (string) $b['id']);
        });
        $ordereditems = array_merge($ordereditems, $remainder);

        $blocks = [];
        $n = 0;
        foreach ($ordereditems as $item) {
            $n++;
            $title = $item['slide']['title'] ?? 'Slide';
            $title = trim(preg_replace('/\s+/', ' ', (string) $title) ?? '');
            if ($title === '') {
                $title = 'Slide';
            }
            $sid = $item['id'];
            $blocks[] = "## Slide {$n}: {$title}\n\n*Slide ID:* `{$sid}`\n\n" . $item['body'];
        }

        return implode("\n\n", $blocks);
    }

    /**
     * Parse window.globalProvideData('{kind}', '...json...'); payload.
     *
     * @param string $js File contents.
     * @param string $kind e.g. slide, data, paths.
     * @return array|null Decoded JSON or null.
     */
    private function parse_storyline_global_provide_json(string $js, string $kind): ?array {
        $quotedkind = preg_quote($kind, '/');
        $patterns = [
            "window.globalProvideData('{$kind}', '",
            "window.globalProvideData('{$kind}','",
        ];
        $start = -1;
        foreach ($patterns as $needle) {
            $p = strpos($js, $needle);
            if ($p !== false) {
                $start = $p + strlen($needle);
                break;
            }
        }
        if ($start < 0) {
            return null;
        }

        $len = strlen($js);
        $raw = '';
        for ($i = $start; $i < $len; $i++) {
            $c = $js[$i];
            if ($c === '\\' && $i + 1 < $len) {
                $raw .= $c . $js[$i + 1];
                $i++;
                continue;
            }
            if ($c === "'") {
                break;
            }
            $raw .= $c;
        }

        $json = $this->unescape_js_single_quoted_payload($raw);
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Order slide JS basenames by first occurrence of scene.slide references in data.js (menu / project order).
     *
     * @param string $datajs Raw data.js file contents.
     * @param string[] $slideids Slide basenames present in the zip.
     * @return string[]
     */
    private function order_storyline_slide_ids_by_data_js_occurrence(string $datajs, array $slideids): array {
        $wanted = array_flip($slideids);
        // Storyline uses composite ids like 6kzy9ic6inl.5dTHZvvBraa — capture slide file basename.
        preg_match_all('/[a-zA-Z0-9]{8,16}\.([a-zA-Z0-9]{10,22})/', $datajs, $matches);
        $ordered = [];
        $seen = [];
        foreach ($matches[1] as $basename) {
            if (!isset($wanted[$basename]) || isset($seen[$basename])) {
                continue;
            }
            $seen[$basename] = true;
            $ordered[] = $basename;
        }

        return $ordered;
    }

    /**
     * Parse window.globalProvideData('slide', '...json...'); from a Storyline slide .js file.
     *
     * @param string $js File contents.
     * @return array|null Decoded slide or null.
     */
    private function parse_storyline_slide_js(string $js): ?array {
        return $this->parse_storyline_global_provide_json($js, 'slide');
    }

    /**
     * Unescape a substring taken from a JavaScript single-quoted string into JSON source text.
     *
     * @param string $raw Escaped content (still contains JS \\ sequences).
     * @return string
     */
    private function unescape_js_single_quoted_payload(string $raw): string {
        $out = '';
        $len = strlen($raw);
        for ($i = 0; $i < $len; $i++) {
            if ($raw[$i] === '\\' && $i + 1 < $len) {
                $n = $raw[$i + 1];
                if ($n === '\\') {
                    $out .= '\\';
                    $i++;
                    continue;
                }
                if ($n === "'") {
                    $out .= "'";
                    $i++;
                    continue;
                }
                if ($n === 'n') {
                    $out .= "\n";
                    $i++;
                    continue;
                }
                if ($n === 'r') {
                    $out .= "\r";
                    $i++;
                    continue;
                }
                if ($n === 't') {
                    $out .= "\t";
                    $i++;
                    continue;
                }
                if ($n === 'u' && $i + 5 < $len && preg_match('/^u([0-9a-fA-F]{4})/', substr($raw, $i + 1), $m)) {
                    $out .= html_entity_decode('&#x' . $m[1] . ';', ENT_NOQUOTES, 'UTF-8');
                    $i += 5;
                    continue;
                }
                $out .= $n;
                $i++;
                continue;
            }
            $out .= $raw[$i];
        }
        return $out;
    }

    /**
     * Collect readable text fragments from decoded Storyline slide JSON (textLib / spans / etc.).
     *
     * @param array $slide Slide array.
     * @return string Plain text / light markdown.
     */
    private function collect_storyline_text_from_slide(array $slide): string {
        $fragments = [];
        $this->walk_storyline_nodes_for_text($slide, $fragments);
        if ($fragments === []) {
            return '';
        }
        $lines = [];
        $seenlower = [];
        foreach ($fragments as $f) {
            $f = trim($f);
            if ($f === '') {
                continue;
            }
            $key = \core_text::strtolower($f);
            if (isset($seenlower[$key])) {
                continue;
            }
            $seenlower[$key] = true;
            $lines[] = '- ' . $f;
        }
        return implode("\n", $lines);
    }

    /**
     * Recursively collect meaningful Storyline text fragments.
     *
     * @param mixed $node
     * @param string[] $out
     */
    private function walk_storyline_nodes_for_text($node, array &$out): void {
        if (is_array($node)) {
            foreach ($node as $key => $child) {
                if ($key === 'text' && is_string($child) && $this->is_meaningful_storyline_text_fragment($child)) {
                    $out[] = $child;
                } else {
                    $this->walk_storyline_nodes_for_text($child, $out);
                }
            }
        }
    }

    /**
     * Filter UI noise and asset paths from Storyline text fields.
     *
     * @param string $text Raw fragment.
     * @return bool
     */
    private function is_meaningful_storyline_text_fragment(string $text): bool {
        $t = trim($text);
        if (strlen($t) < 2) {
            return false;
        }

        $lower = \core_text::strtolower($t);
        static $skip = null;
        if ($skip === null) {
            $skip = [
                'none' => true,
                'ok' => true,
                'true' => true,
                'false' => true,
            ];
        }
        if (isset($skip[$lower]) && strlen($t) <= 5) {
            return false;
        }

        if (preg_match('#^(story_content/|mobile/|lib/|_player\.|\$)#', $t)) {
            return false;
        }

        if (preg_match('/^[0-9a-f]{8,}$/i', $t)) {
            return false;
        }

        if (preg_match('/^picture\d*\.png$/i', $t)) {
            return false;
        }

        return true;
    }

    /**
     * Derive a Moodle activity name from an upload filename (basename without extension).
     *
     * @param string $filename Original upload filename.
     * @return string Max 255 chars.
     */
    private function activity_name_from_filename(string $filename): string {
        $filename = clean_param($filename, PARAM_FILE);
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $name = ($base !== '') ? $base : $filename;
        return \core_text::substr(trim($name), 0, 255);
    }

    /**
     * Read the course/package title from SCORM manifest XML.
     *
     * Prefers the default organization's direct &lt;title&gt; child.
     *
     * @param string $xml Manifest content.
     * @return string|null Trimmed title or null.
     */
    private function parse_package_title_from_manifest_xml(string $xml): ?string {
        $prev = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (!$loaded) {
            return null;
        }

        foreach ($dom->getElementsByTagNameNS('*', 'organizations') as $orgsel) {
            if (!($orgsel instanceof \DOMElement)) {
                continue;
            }

            $defaultid = $orgsel->getAttribute('default');
            if ($defaultid !== '') {
                foreach ($orgsel->getElementsByTagNameNS('*', 'organization') as $org) {
                    if (!($org instanceof \DOMElement)) {
                        continue;
                    }
                    if ($org->getAttribute('identifier') !== $defaultid) {
                        continue;
                    }
                    $title = $this->first_direct_child_title_text($org);
                    if ($title !== null) {
                        return $title;
                    }
                }
            }

            foreach ($orgsel->getElementsByTagNameNS('*', 'organization') as $org) {
                if (!($org instanceof \DOMElement)) {
                    continue;
                }
                $title = $this->first_direct_child_title_text($org);
                if ($title !== null) {
                    return $title;
                }
            }
        }

        return null;
    }

    /**
     * Return the first direct child title text of a manifest element.
     *
     * @param \DOMElement $parent Organization or item element.
     * @return string|null
     */
    private function first_direct_child_title_text(\DOMElement $parent): ?string {
        foreach ($parent->childNodes as $child) {
            if (!($child instanceof \DOMElement)) {
                continue;
            }
            if (($child->localName ?? '') !== 'title') {
                continue;
            }
            $text = $this->normalize_title($child->textContent ?? '');
            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    /**
     * Normalize raw title text by stripping tags and collapsing whitespace.
     *
     * @param string $text Raw title text.
     * @return string
     */
    private function normalize_title(string $text): string {
        $text = strip_tags($text);
        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    /**
     * Locate imsmanifest.xml inside an open SCORM zip archive.
     *
     * @param \ZipArchive $zip Open zip.
     * @return string|null Path inside zip to imsmanifest.xml.
     */
    private function locate_manifest_in_zip(\ZipArchive $zip): ?string {
        $idx = $zip->locateName('imsmanifest.xml', \ZipArchive::FL_NOCASE);
        if ($idx !== false) {
            $stat = $zip->statIndex($idx);
            if ($stat && !empty($stat['name'])) {
                return $stat['name'];
            }
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }
            if (preg_match('#(^|/)imsmanifest\.xml$#i', $name)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Collect href values for resources marked as SCO (adlcp scormtype).
     *
     * @param string $xml Manifest content.
     * @return string[] Unique hrefs in document order.
     */
    private function parse_sco_hrefs_from_manifest_xml(string $xml): array {
        $prev = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (!$loaded) {
            return [];
        }

        $hrefs = [];
        $seen = [];

        foreach ($dom->getElementsByTagNameNS('*', 'resource') as $res) {
            if (!($res instanceof \DOMElement)) {
                continue;
            }

            $href = $res->getAttribute('href');
            if ($href === '') {
                continue;
            }

            $issco = false;
            foreach ($res->attributes as $attr) {
                $ln = $attr->localName ?? '';
                if (strcasecmp($ln, 'scormtype') === 0 || strcasecmp($ln, 'scormType') === 0) {
                    if (strtolower(trim($attr->value)) === 'sco') {
                        $issco = true;
                        break;
                    }
                }
            }

            if (!$issco) {
                continue;
            }

            if (isset($seen[$href])) {
                continue;
            }
            $seen[$href] = true;
            $hrefs[] = $href;
        }

        return $hrefs;
    }

    /**
     * Resolve a manifest-relative href to a zip entry path without path traversal.
     *
     * @param string $manifestdir Directory of imsmanifest inside zip (no leading/trailing slash), or ''.
     * @param string $href Href from manifest.
     * @return string|null Normalized path inside zip or null if invalid.
     */
    private function resolve_zip_inner_path(string $manifestdir, string $href): ?string {
        $href = str_replace('\\', '/', $href);
        if (strpos($href, '..') !== false) {
            return null;
        }

        $combined = $manifestdir === '' ? $href : $manifestdir . '/' . $href;
        $combined = str_replace('\\', '/', $combined);
        while (strpos($combined, '//') !== false) {
            $combined = str_replace('//', '/', $combined);
        }

        $combined = ltrim($combined, '/');
        $parts = explode('/', $combined);
        $safe = [];
        foreach ($parts as $p) {
            if ($p === '' || $p === '.') {
                continue;
            }
            if ($p === '..') {
                if ($safe === []) {
                    return null;
                }
                array_pop($safe);
                continue;
            }
            $safe[] = $p;
        }

        if ($safe === []) {
            return null;
        }

        return implode('/', $safe);
    }
}
