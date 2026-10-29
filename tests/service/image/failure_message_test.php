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

namespace local_dixeo\service\image;

/**
 * Tests for the image-job failure code map.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\service\image\failure_message
 */
final class failure_message_test extends \advanced_testcase {
    /**
     * Known API codes and the string each one displays.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function known_code_provider(): array {
        return [
            'content rejected' => [
                failure_message::CODE_CONTENT_REJECTED,
                'dixeo_image_job_content_rejected',
            ],
            'unavailable' => [
                failure_message::CODE_AI_UNAVAILABLE,
                'dixeo_image_job_unavailable',
            ],
            'rate limited' => [
                failure_message::CODE_RATE_LIMITED,
                'dixeo_image_job_rate_limited',
            ],
        ];
    }

    /**
     * A known code is stored as itself and shown in the current language.
     *
     * @dataProvider known_code_provider
     * @param string $code API error code.
     * @param string $stringkey local_dixeo string key.
     */
    public function test_known_code_is_stored_and_translated(string $code, string $stringkey): void {
        $this->resetAfterTest();

        $this->assertTrue(failure_message::is_known($code));
        $this->assertSame($code, failure_message::for_storage($code));
        $this->assertSame(
            get_string($stringkey, 'local_dixeo'),
            failure_message::for_display($code)
        );
    }

    /**
     * An unknown code, and a missing code, become the generic failure sentence.
     */
    public function test_unknown_code_uses_the_generic_sentence(): void {
        $this->resetAfterTest();
        $generic = get_string('dixeo_image_job_failed', 'local_dixeo');

        $this->assertFalse(failure_message::is_known('internal'));
        $this->assertFalse(failure_message::is_known(null));
        $this->assertFalse(failure_message::is_known(''));
        $this->assertSame($generic, failure_message::for_storage('internal'));
        $this->assertSame($generic, failure_message::for_storage(null));
        $this->assertSame($generic, failure_message::for_display('internal'));
        $this->assertSame($generic, failure_message::for_display(null));
        $this->assertSame($generic, failure_message::for_display(''));
    }

    /**
     * A sentence already stored on an older row is shown unchanged.
     */
    public function test_stored_sentence_is_shown_as_is(): void {
        $this->resetAfterTest();
        $sentence = get_string('dixeo_image_job_failed', 'local_dixeo');

        $this->assertSame($sentence, failure_message::for_display($sentence));
    }
}
