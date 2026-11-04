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

/**
 * Tests for the tutor message context HTML cleaner.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\service\tutor_context_cleaner
 */
final class tutor_context_cleaner_test extends \advanced_testcase {
    /**
     * Script and event handlers are removed from the three HTML fields.
     */
    public function test_cleans_every_html_field(): void {
        $cleaned = tutor_context_cleaner::clean([
            'introhtml' => '<p>Intro</p><script>alert(1)</script>',
            'contenthtml' => '<img src="x" onerror="alert(2)">Body',
            'questions' => [
                ['prompt' => 'Q1', 'feedbackHtml' => '<b onclick="x()">Well done</b><script>bad()</script>'],
                'not an array',
            ],
        ]);

        $this->assertStringContainsString('Intro', $cleaned['introhtml']);
        $this->assertStringNotContainsString('<script', $cleaned['introhtml']);
        $this->assertStringContainsString('Body', $cleaned['contenthtml']);
        $this->assertStringNotContainsString('onerror', $cleaned['contenthtml']);
        $this->assertStringContainsString('Well done', $cleaned['questions'][0]['feedbackHtml']);
        $this->assertStringNotContainsString('onclick', $cleaned['questions'][0]['feedbackHtml']);
        $this->assertStringNotContainsString('<script', $cleaned['questions'][0]['feedbackHtml']);
        $this->assertSame('Q1', $cleaned['questions'][0]['prompt']);
        $this->assertSame('not an array', $cleaned['questions'][1]);
    }

    /**
     * Non-string values become empty strings, and other keys are unchanged.
     */
    public function test_non_strings_and_unknown_keys(): void {
        $context = [
            'schema' => 'anything',
            'introhtml' => ['nested'],
            'contenthtml' => 42,
            'questions' => [['feedbackHtml' => null]],
            'body' => '<script>kept()</script>',
        ];

        $cleaned = tutor_context_cleaner::clean($context);

        $this->assertSame('', $cleaned['introhtml']);
        $this->assertSame('', $cleaned['contenthtml']);
        $this->assertSame('', $cleaned['questions'][0]['feedbackHtml']);
        $this->assertSame('anything', $cleaned['schema']);
        $this->assertSame('<script>kept()</script>', $cleaned['body']);
        $this->assertSame(['url' => 'x'], tutor_context_cleaner::clean(['url' => 'x']));
    }
}
