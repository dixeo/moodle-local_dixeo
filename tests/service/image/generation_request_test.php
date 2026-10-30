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

/**
 * Tests for the queued image generation request.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service\image;

use local_dixeo\dto\job_binding_metadata;
use local_dixeo\dto\operation_result;
use local_dixeo\external\service_factory;
use local_dixeo\service\image_generation_service;
use local_dixeo\service\job_service;

/**
 * Tests for generation_request.
 *
 * @covers \local_dixeo\service\image\generation_request
 */
final class generation_request_test extends \advanced_testcase {
    /**
     * Enable content and section image generation.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('image_generation_enabled', 1, 'local_dixeo');
        set_config('image_generation_content_mode', policy::MODE_GENERATE, 'local_dixeo');
        set_config('image_generation_section_mode', policy::MODE_GENERATE, 'local_dixeo');
    }

    /**
     * Restore the shared services.
     */
    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * The request survives the JSON round trip of adhoc task custom data.
     */
    public function test_round_trip_through_task_custom_data(): void {
        $request = generation_request::for_content(
            'local_dixeo',
            12,
            'Cells',
            'A cell membrane',
            '1024x1536',
            'medium',
            new job_binding_metadata('page', 34, 56)
        );

        $decoded = json_decode(json_encode((object) ['request' => $request->to_array()]));
        $restored = generation_request::from_array($decoded->request);

        $this->assertEquals($request, $restored);
        $this->assertNotSame('', $restored->idempotencykey);
    }

    /**
     * Each request gets its own idempotency key.
     */
    public function test_each_request_has_its_own_key(): void {
        $first = generation_request::for_section('block_dixeo_designer', 1, 2);
        $second = generation_request::for_section('block_dixeo_designer', 1, 2);

        $this->assertNotSame($first->idempotencykey, $second->idempotencykey);
    }

    /**
     * A content request posts a content generate job with its idempotency key.
     */
    public function test_submit_content_sends_the_idempotency_key(): void {
        $course = $this->getDataGenerator()->create_course();
        $request = generation_request::for_content(
            'local_dixeo',
            (int) $course->id,
            'Cells',
            'A cell membrane',
            '1024x1536',
            'medium'
        );

        $jobmock = $this->createMock(job_service::class);
        $jobmock->expects($this->once())
            ->method('submit_job')
            ->with(
                '/v1/images/generate',
                $this->callback(static fn(array $payload): bool => $payload['scope'] === 'content'
                    && $payload['prompt'] === 'A cell membrane'
                    && $payload['size'] === '1024x1536'),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                [],
                $request->idempotencykey
            )
            ->willReturn(operation_result::pending('remote-1', 'pending'));
        service_factory::set_test_image_generation_service(new image_generation_service($jobmock));

        $this->assertSame('remote-1', $request->submit());
    }

    /**
     * A section request posts a section generate job with its idempotency key.
     */
    public function test_submit_section_sends_the_idempotency_key(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $sectionid = (int) $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 1]);
        $request = generation_request::for_section('block_dixeo_designer', (int) $course->id, $sectionid);

        $jobmock = $this->createMock(job_service::class);
        $jobmock->expects($this->once())
            ->method('submit_job')
            ->with(
                '/v1/images/generate',
                $this->callback(static fn(array $payload): bool => $payload['scope'] === 'section'),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                [],
                $request->idempotencykey
            )
            ->willReturn(operation_result::pending('remote-2', 'pending'));
        service_factory::set_test_image_generation_service(new image_generation_service($jobmock));

        $this->assertSame('remote-2', $request->submit());
    }

    /**
     * An answer without a job id is an error, not an empty remote id.
     */
    public function test_submit_without_job_id_throws(): void {
        $course = $this->getDataGenerator()->create_course();
        $request = generation_request::for_content('local_dixeo', (int) $course->id, 'T', 'P', '1024x1024', 'medium');

        $jobmock = $this->createMock(job_service::class);
        $jobmock->method('submit_job')->willReturn(operation_result::pending('', 'pending'));
        service_factory::set_test_image_generation_service(new image_generation_service($jobmock));

        $this->expectException(\moodle_exception::class);
        $request->submit();
    }

    /**
     * Custom data with an unknown kind is rejected.
     */
    public function test_unknown_kind_is_rejected(): void {
        $this->expectException(\coding_exception::class);
        generation_request::from_array(['kind' => 'course_overview']);
    }
}
