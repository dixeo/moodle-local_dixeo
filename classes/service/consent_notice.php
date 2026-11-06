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

namespace local_dixeo\service;

/**
 * Terms of Use, Privacy Notice, and tutor usage text shown before a Dixeo course.
 *
 * The three sections follow the privacy metadata this plugin declares. Terms are the
 * contract for using Dixeo. The privacy section is the GDPR Articles 13 and 14 notice.
 * Tutor usage analytics are recorded with that use. Marketing is not a purpose here.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class consent_notice {
    /**
     * Lang string keys of the notice text, in display order.
     *
     * The sections, the stored text and the terms hash all read these keys, so a string
     * added to the notice is always covered by the terms hash.
     */
    private const TERMS_STRINGS = [
        'termsheading' => 'consent_terms_heading',
        'termsbody' => 'consent_terms_body',
        'privacyheading' => 'consent_privacy_heading',
        'privacybody' => 'consent_privacy_body',
        'processingheading' => 'consent_processing_heading',
        'processingbody' => 'consent_processing_body',
        'termscheckbox' => 'consent_terms_checkbox',
        'processingcheckbox' => 'consent_processing_checkbox',
    ];

    /** @var string Lang string key of the data-privacy link label, shown when that tool is installed. */
    private const DATA_PROTECTION_STRING = 'consent_privacy_dataprotection';

    /**
     * Checkbox text for the Terms of Use.
     *
     * @return string
     */
    public function terms_statement(): string {
        return get_string(self::TERMS_STRINGS['termscheckbox'], 'local_dixeo');
    }

    /**
     * Checkbox text for tutor usage analytics.
     *
     * @return string
     */
    public function processing_statement(): string {
        return get_string(self::TERMS_STRINGS['processingcheckbox'], 'local_dixeo');
    }

    /**
     * SHA-256 of the terms: version, language, raw consent strings and notice structure.
     *
     * Strings are read without placeholder values, so the site name, contact, Dixeo API URL
     * and site URL are not covered. Local string customisations are. It identifies the stored
     * notice row, and an acceptance stays current while this value is unchanged.
     *
     * @return string
     */
    public function terms_hash(): string {
        $dataprotection = $this->has_data_protection();
        $strings = [];
        foreach (self::TERMS_STRINGS as $key) {
            $strings[$key] = get_string($key, 'local_dixeo');
        }
        if ($dataprotection) {
            $strings[self::DATA_PROTECTION_STRING] = get_string(self::DATA_PROTECTION_STRING, 'local_dixeo');
        }
        $payload = [
            'version' => consent_service::VERSION,
            'lang' => current_language(),
            'strings' => $strings,
            'dataprotection' => $dataprotection,
        ];
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /**
     * Plain text of everything shown in the gate, kept as proof of what the user saw.
     *
     * @return string
     */
    public function document_text(): string {
        $chunks = [];
        foreach ($this->sections() as $section) {
            $chunks[] = $section['heading'];
            foreach ($section['blocks'] as $block) {
                if (!empty($block['paragraph'])) {
                    $chunks[] = $block['paragraph'];
                }
                if (!empty($block['items'])) {
                    foreach ($block['items'] as $item) {
                        $chunks[] = '- ' . $item['text'];
                    }
                }
            }
        }
        $url = $this->data_protection_url();
        if ($url !== null) {
            $chunks[] = get_string(self::DATA_PROTECTION_STRING, 'local_dixeo') . "\n" . $url;
        }
        $chunks[] = $this->terms_statement();
        $chunks[] = $this->processing_statement();
        return implode("\n\n", $chunks);
    }

    /**
     * Template context shared by the course gate and the manage page.
     *
     * @return array
     */
    public function template_context(): array {
        $url = $this->data_protection_url();
        return [
            'title' => get_string('consent_title', 'local_dixeo'),
            'sections' => $this->sections(),
            'termscheckbox' => $this->terms_statement(),
            'processingcheckbox' => $this->processing_statement(),
            'continuelabel' => get_string('consent_continue', 'local_dixeo'),
            'exitlabel' => get_string('consent_exit', 'local_dixeo'),
            'version' => consent_service::VERSION,
            'dataprotectionurl' => $url,
            'hasdataprotection' => $url !== null,
            'dataprotectionlabel' => get_string(self::DATA_PROTECTION_STRING, 'local_dixeo'),
        ];
    }

    /**
     * Three sections, in the order they are shown.
     *
     * @return array[]
     */
    public function sections(): array {
        $a = $this->placeholders();
        return [
            [
                'id' => 'local-dixeo-consent-terms-section',
                'heading' => get_string(self::TERMS_STRINGS['termsheading'], 'local_dixeo'),
                'blocks' => $this->body_blocks(get_string(self::TERMS_STRINGS['termsbody'], 'local_dixeo', $a)),
            ],
            [
                'id' => 'local-dixeo-consent-privacy-section',
                'heading' => get_string(self::TERMS_STRINGS['privacyheading'], 'local_dixeo'),
                'blocks' => $this->body_blocks(get_string(self::TERMS_STRINGS['privacybody'], 'local_dixeo', $a)),
            ],
            [
                'id' => 'local-dixeo-consent-processing-section',
                'heading' => get_string(self::TERMS_STRINGS['processingheading'], 'local_dixeo'),
                'blocks' => $this->body_blocks(get_string(self::TERMS_STRINGS['processingbody'], 'local_dixeo', $a)),
            ],
        ];
    }

    /**
     * Split one section string into paragraphs and a bullet list.
     *
     * A line that starts with "- " is a list item. Other lines are paragraphs.
     * Blank lines separate them.
     *
     * @param string $text Section body.
     * @return array[]
     */
    private function body_blocks(string $text): array {
        $blocks = [];
        $items = [];
        $pushlist = function () use (&$blocks, &$items): void {
            if (!$items) {
                return;
            }
            $blocks[] = $this->bullets($items);
            $items = [];
        };
        foreach (preg_split("/\r\n|\n|\r/", trim($text)) as $line) {
            $line = trim($line);
            if ($line === '') {
                $pushlist();
                continue;
            }
            if (strpos($line, '- ') === 0) {
                $items[] = substr($line, 2);
                continue;
            }
            $pushlist();
            $blocks[] = $this->paragraph($line);
        }
        $pushlist();
        return $blocks;
    }

    /**
     * Site data-privacy requests URL, when that tool is installed.
     *
     * @return string|null
     */
    public function data_protection_url(): ?string {
        if (!$this->has_data_protection()) {
            return null;
        }
        return (new \moodle_url('/admin/tool/dataprivacy/mydatarequests.php'))->out(false);
    }

    /**
     * Whether the notice shows the data-privacy requests link.
     *
     * @return bool
     */
    public function has_data_protection(): bool {
        global $CFG;

        return is_readable($CFG->dirroot . '/admin/tool/dataprivacy/mydatarequests.php');
    }

    /**
     * Values substituted into the notice.
     *
     * @return \stdClass
     */
    private function placeholders(): \stdClass {
        return (object) [
            'sitename' => $this->site_name(),
            'contact' => $this->contact(),
            'apiurl' => $this->api_url(),
            'version' => consent_service::VERSION,
        ];
    }

    /**
     * Organisation name shown as the controller.
     *
     * @return string
     */
    private function site_name(): string {
        global $SITE;

        $name = format_string($SITE->fullname ?? '', true, [
            'context' => \context_system::instance(),
            'escape' => false,
        ]);
        $name = trim($name);
        return $name !== '' ? $name : 'Moodle';
    }

    /**
     * Controller contact: the site support address, or a pointer to the site administrator.
     *
     * @return string
     */
    private function contact(): string {
        global $CFG;

        $email = trim((string) ($CFG->supportemail ?? ''));
        if ($email !== '' && validate_email($email)) {
            return $email;
        }
        return get_string('consent_privacy_contact_admin', 'local_dixeo');
    }

    /**
     * Dixeo API base URL the site is configured to call.
     *
     * @return string
     */
    private function api_url(): string {
        $url = trim((string) (get_config('local_dixeo', 'api_url') ?: ''));
        return $url !== '' ? $url : 'https://api.dixeo.io';
    }

    /**
     * One paragraph block.
     *
     * @param string $text Paragraph text.
     * @return array
     */
    private function paragraph(string $text): array {
        return ['paragraph' => $text];
    }

    /**
     * One bullet list block.
     *
     * @param string[] $texts List items.
     * @return array
     */
    private function bullets(array $texts): array {
        $items = [];
        foreach ($texts as $text) {
            $items[] = ['text' => $text];
        }
        return [
            'list' => true,
            'items' => $items,
        ];
    }
}
