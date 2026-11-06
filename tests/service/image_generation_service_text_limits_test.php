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
 * Tests for the image prompt and edit instruction caps of {@see \local_dixeo\service\image_generation_service}.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\dto\operation_result;
use local_dixeo\service\html_helper;
use local_dixeo\service\image\policy;
use local_dixeo\service\image_generation_service;
use local_dixeo\service\job_service;
use local_dixeo\util\input_limits;

/**
 * Image text sent to Dixeo stays within the API limits.
 *
 * @covers \local_dixeo\service\image_generation_service
 */
final class image_generation_service_text_limits_test extends \advanced_testcase {
    /** @var int Course used for submits. */
    private int $courseid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->get_plugin_generator('local_dixeo')->create_terms_acceptance(['userid' => $user->id]);
        $this->setUser($user);
        set_config('image_generation_enabled', 1, 'local_dixeo');
        set_config('image_generation_content_mode', policy::MODE_GENERATE_EDIT, 'local_dixeo');
        $this->courseid = (int) $this->getDataGenerator()->create_course()->id;
    }

    /**
     * Service with a job service mock that expects $calls submits.
     *
     * @param int $calls Expected number of submit_job calls.
     * @return image_generation_service
     */
    private function service(int $calls): image_generation_service {
        $jobs = $this->createMock(job_service::class);
        $jobs->expects($this->exactly($calls))->method('submit_job')
            ->willReturn(operation_result::pending('remote-job-1', 'pending', 0));
        return new image_generation_service($jobs, new html_helper(), 'phpunit');
    }

    /**
     * Assert that a call is refused with the given local_dixeo error.
     *
     * @param string $errorcode Expected error code.
     * @param callable $call Call under test.
     * @return void
     */
    private function assert_refused(string $errorcode, callable $call): void {
        try {
            $call();
            $this->fail('Expected ' . $errorcode);
        } catch (\moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode);
            $this->assertSame('local_dixeo', $e->module);
        }
    }

    /**
     * A prompt one character over the limit is refused before any submit.
     */
    public function test_generate_refuses_a_prompt_over_the_limit(): void {
        $prompt = str_repeat('é', input_limits::MAX_IMAGE_PROMPT_CHARS + 1);

        $this->assert_refused('error:prompt_too_long', fn() => $this->service(0)
            ->submit_content_image_generate_job($this->courseid, 'Title', $prompt));
    }

    /**
     * A prompt of exactly the limit is submitted.
     */
    public function test_generate_accepts_a_prompt_at_the_limit(): void {
        $prompt = str_repeat('é', input_limits::MAX_IMAGE_PROMPT_CHARS);

        $result = $this->service(1)->submit_content_image_generate_job($this->courseid, 'Title', $prompt);

        $this->assertSame('remote-job-1', $result->jobid);
    }

    /**
     * Instructions one character over the limit are refused before any submit.
     */
    public function test_edit_refuses_instructions_over_the_limit(): void {
        $instructions = str_repeat('a', input_limits::MAX_IMAGE_INSTRUCTIONS_CHARS + 1);

        $this->assert_refused('error:instructions_too_long', fn() => $this->service(0)
            ->submit_content_image_edit_job($this->courseid, ['aGVsbG8='], $instructions));
    }

    /**
     * Instructions of exactly the limit are submitted.
     */
    public function test_edit_accepts_instructions_at_the_limit(): void {
        $instructions = str_repeat('a', input_limits::MAX_IMAGE_INSTRUCTIONS_CHARS);

        $result = $this->service(1)->submit_content_image_edit_job($this->courseid, ['aGVsbG8='], $instructions);

        $this->assertSame('remote-job-1', $result->jobid);
    }
}
