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
     * Checkbox text for the Terms of Use.
     *
     * @return string
     */
    public function terms_statement(): string {
        return get_string('consent_terms_checkbox', 'local_dixeo');
    }

    /**
     * Checkbox text for tutor usage analytics.
     *
     * @return string
     */
    public function processing_statement(): string {
        return get_string('consent_processing_checkbox', 'local_dixeo');
    }

    /**
     * SHA-256 of the version, language, and text shown.
     *
     * @return string
     */
    public function hash(): string {
        return hash('sha256', consent_service::VERSION . "\n" . current_language() . "\n" . $this->document_text());
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
            $chunks[] = get_string('consent_privacy_dataprotection', 'local_dixeo') . "\n" . $url;
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
            'dataprotectionlabel' => get_string('consent_privacy_dataprotection', 'local_dixeo'),
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
                'heading' => get_string('consent_terms_heading', 'local_dixeo'),
                'blocks' => $this->body_blocks(get_string('consent_terms_body', 'local_dixeo', $a)),
            ],
            [
                'id' => 'local-dixeo-consent-privacy-section',
                'heading' => get_string('consent_privacy_heading', 'local_dixeo'),
                'blocks' => $this->body_blocks(get_string('consent_privacy_body', 'local_dixeo', $a)),
            ],
            [
                'id' => 'local-dixeo-consent-processing-section',
                'heading' => get_string('consent_processing_heading', 'local_dixeo'),
                'blocks' => $this->body_blocks(get_string('consent_processing_body', 'local_dixeo', $a)),
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
        global $CFG;

        if (!is_readable($CFG->dirroot . '/admin/tool/dataprivacy/mydatarequests.php')) {
            return null;
        }
        return (new \moodle_url('/admin/tool/dataprivacy/mydatarequests.php'))->out(false);
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
