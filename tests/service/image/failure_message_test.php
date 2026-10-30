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
                'error:content_rejected',
            ],
            'unavailable' => [
                failure_message::CODE_AI_UNAVAILABLE,
                'error:unavailable',
            ],
            'rate limited' => [
                failure_message::CODE_RATE_LIMITED,
                'error:busy',
            ],
            'api payment problem' => [
                'payment_required',
                'error:payment_required',
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
     * An unknown or missing code is stored as the failed code and shows the generic sentence.
     */
    public function test_unknown_code_is_stored_as_failed(): void {
        $this->resetAfterTest();
        $generic = get_string('dixeo_image_job_failed', 'local_dixeo');

        $this->assertFalse(failure_message::is_known('internal'));
        $this->assertFalse(failure_message::is_known(null));
        $this->assertFalse(failure_message::is_known(''));
        $this->assertSame(failure_message::CODE_FAILED, failure_message::for_storage('internal'));
        $this->assertSame(failure_message::CODE_FAILED, failure_message::for_storage(null));
        $this->assertSame($generic, failure_message::for_display(failure_message::CODE_FAILED));
        $this->assertSame($generic, failure_message::for_display('internal'));
        $this->assertSame($generic, failure_message::for_display(null));
        $this->assertSame($generic, failure_message::for_display(''));
    }

    /**
     * The HTTP problem type and the job code for one situation show the same sentence.
     */
    public function test_problem_type_and_job_code_share_one_message(): void {
        $this->resetAfterTest();

        $this->assertSame(
            failure_message::for_display(failure_message::CODE_RATE_LIMITED),
            failure_message::for_display('rate_limit_exceeded')
        );
        $this->assertSame(
            failure_message::for_display(failure_message::CODE_AI_UNAVAILABLE),
            failure_message::for_display('upstream_ai')
        );
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
