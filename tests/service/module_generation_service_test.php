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
 * Tests for module_generation_service payload building.
 *
 * @covers \local_dixeo\service\module_generation_service
 * @package local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class module_generation_service_test extends \advanced_testcase {
    /**
     * SetUp.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('image_generation_enabled', 1, 'local_dixeo');
        set_config('image_generation_content_mode', 'generate', 'local_dixeo');
    }

    /**
     * Content images enabled: the API is asked for images through the flag, not through instructions.
     */
    public function test_payload_carries_generate_images_flag(): void {
        $payload = (new module_generation_service())
            ->build_edit_payload('page', 'Rewrite the intro', 'Course context');

        $this->assertTrue($payload['generateImages']);
        $this->assertTrue($payload['packageImages']);
        $this->assertStringNotContainsString('[img-gen', $payload['instructions']);
        $this->assertSame('Rewrite the intro', $payload['instructions']);
    }

    /**
     * Content image generation disabled by policy: the flag is omitted.
     */
    public function test_payload_omits_flag_when_policy_disabled(): void {
        set_config('image_generation_content_mode', 'disabled', 'local_dixeo');

        $payload = (new module_generation_service())
            ->build_edit_payload('page', 'Rewrite the intro', 'Course context');

        $this->assertArrayNotHasKey('generateImages', $payload);
        $this->assertArrayNotHasKey('packageImages', $payload);
    }

    /**
     * Ephemeral callers opting out: the flag is omitted even when the policy allows images.
     */
    public function test_payload_omits_flag_when_generate_images_off(): void {
        $service = new module_generation_service();
        $service->set_generate_images(false);

        $payload = $service->build_edit_payload('page', 'Rewrite the intro', 'Course context');

        $this->assertArrayNotHasKey('generateImages', $payload);
        $this->assertArrayNotHasKey('packageImages', $payload);
    }

    /**
     * Course submissions stop before reading the course or posting when Dixeo is off.
     *
     * @dataProvider disabled_layer_provider
     * @param string $layer Layer turned off: site or course.
     */
    public function test_course_submissions_refuse_while_dixeo_is_off(string $layer): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $context = $layer === 'site' ? \context_system::instance() : \context_course::instance($course->id);
        (new \local_dixeo\settings\config())->set('local_dixeo', 'enabled', $context, '0');

        $service = new module_generation_service($this->job_service_without_posts(), 'default');
        $calls = [
            fn() => $service->submit_generate_job_for_course('page', 'Write an intro', (int) $course->id, 1),
            fn() => $service->submit_fill_job_for_course('page', 'Write an intro', (int) $course->id, 1, 'Title', 'Summary'),
        ];
        foreach ($calls as $call) {
            try {
                $call();
                $this->fail('A course submission must not run while Dixeo is off');
            } catch (\moodle_exception $e) {
                $this->assertSame('error:dixeo_disabled', $e->errorcode);
            }
        }
    }

    /**
     * Layers that can turn Dixeo off for a course.
     *
     * @return array
     */
    public static function disabled_layer_provider(): array {
        return [
            'site' => ['site'],
            'course' => ['course'],
        ];
    }

    /**
     * Job service whose API client must never post.
     *
     * @return job_service
     */
    private function job_service_without_posts(): job_service {
        $client = $this->createMock(\local_dixeo\api\client::class);
        $client->expects($this->never())->method('post');
        $client->expects($this->never())->method('post_multipart');
        return new job_service($client);
    }

    /**
     * Instructions are capped in characters, not bytes, before anything is posted.
     */
    public function test_instructions_are_capped_before_posting(): void {
        $max = \local_dixeo\util\input_limits::MAX_INSTRUCTIONS_CHARS;
        $client = $this->createMock(\local_dixeo\api\client::class);
        $client->expects($this->once())
            ->method('post')
            ->willReturn(['id' => \core\uuid::generate()]);
        $service = new module_generation_service(new job_service($client), 'default');

        $result = $service->submit_generate_job('page', str_repeat('é', $max), 'Course context');
        $this->assertNotSame('', $result->jobid);

        try {
            $service->submit_generate_job('page', str_repeat('é', $max + 1), 'Course context');
            $this->fail('Instructions above the limit must be refused');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:instructions_too_long', $e->errorcode);
        }
    }
}
