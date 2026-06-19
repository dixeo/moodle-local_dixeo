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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Tests that {@see \local_dixeo\service\image_generation_service} submits remote jobs only when
 * local_dixeo image generation settings allow the action (generate vs edit, course vs section).
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\dto\operation_result;
use local_dixeo\service\html_helper;
use local_dixeo\service\image_generation_policy;
use local_dixeo\service\image_generation_service;
use local_dixeo\service\job_service;

/**
 * Unit tests for image generation service policy.
 *
 * @covers \local_dixeo\service\image_generation_service
 */
final class image_generation_service_policy_test extends \advanced_testcase {
    /** @var string Non-null namespace avoids loading Dixeo lib.php in unit context. */
    private const TEST_NAMESPACE = 'phpunit';

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Apply image generation policy settings for a test case.
     *
     * @param int|string|bool $global Global image_generation_enabled config value.
     * @param string $coursemode Course-level image generation mode.
     * @param string $sectionmode Section-level image generation mode.
     */
    private function apply_image_generation_settings($global, string $coursemode, string $sectionmode, string $contentmode = ''): void {
        set_config('image_generation_enabled', $global, 'local_dixeo');
        set_config('image_generation_course_mode', $coursemode, 'local_dixeo');
        set_config('image_generation_section_mode', $sectionmode, 'local_dixeo');
        if ($contentmode !== '') {
            set_config('image_generation_content_mode', $contentmode, 'local_dixeo');
        }
    }

    /**
     * Build an image_generation_service with a mocked job service.
     *
     * @param \PHPUnit\Framework\MockObject\MockObject $jobmock
     * @return image_generation_service
     */
    private function make_service_with_mock_jobs(\PHPUnit\Framework\MockObject\MockObject $jobmock): image_generation_service {
        return new image_generation_service($jobmock, new html_helper(), self::TEST_NAMESPACE);
    }

    /**
     * Build a pending operation_result for assertions.
     *
     * @param string $jobid
     * @return operation_result
     */
    private function pending_result(string $jobid = 'remote-job-1'): operation_result {
        return operation_result::pending($jobid, 'pending', 0);
    }

    public function test_submit_course_image_job_rejected_when_globally_disabled_and_does_not_call_api(): void {
        $this->apply_image_generation_settings(
            0,
            image_generation_policy::MODE_GENERATE_EDIT,
            image_generation_policy::MODE_GENERATE_EDIT
        );

        $jobmock = $this->createMock(job_service::class);
        $jobmock->expects($this->never())->method('submit_job');

        $course = $this->getDataGenerator()->create_course(['fullname' => 'Policy course']);

        $this->expectException(\moodle_exception::class);
        $this->make_service_with_mock_jobs($jobmock)->submit_course_image_job((int) $course->id);
    }

    public function test_submit_course_image_job_rejected_when_course_mode_disabled(): void {
        $this->apply_image_generation_settings(
            1,
            image_generation_policy::MODE_DISABLED,
            image_generation_policy::MODE_GENERATE_EDIT
        );

        $jobmock = $this->createMock(job_service::class);
        $jobmock->expects($this->never())->method('submit_job');

        $course = $this->getDataGenerator()->create_course(['fullname' => 'Policy course']);

        $this->expectException(\moodle_exception::class);
        $this->make_service_with_mock_jobs($jobmock)->submit_course_image_job((int) $course->id);
    }

    public function test_submit_course_image_job_calls_api_when_allowed(): void {
        $this->apply_image_generation_settings(
            1,
            image_generation_policy::MODE_GENERATE_EDIT,
            image_generation_policy::MODE_DISABLED
        );

        $course = $this->getDataGenerator()->create_course(['fullname' => 'Policy course']);

        $jobmock = $this->createMock(job_service::class);
        $jobmock->expects($this->once())->method('submit_job')->with(
            $this->equalTo('/v1/images/generate'),
            $this->callback(static function (array $payload): bool {
                return ($payload['scope'] ?? '') === 'course'
                    && ($payload['title'] ?? '') === 'Policy course';
            })
        )->willReturn($this->pending_result());

        $result = $this->make_service_with_mock_jobs($jobmock)->submit_course_image_job((int) $course->id);
        $this->assertSame('remote-job-1', $result->jobid);
    }

    public function test_submit_course_image_edit_job_rejected_when_course_generate_only(): void {
        $this->apply_image_generation_settings(
            1,
            image_generation_policy::MODE_GENERATE,
            image_generation_policy::MODE_GENERATE_EDIT
        );

        $jobmock = $this->createMock(job_service::class);
        $jobmock->expects($this->never())->method('submit_job');

        $course = $this->getDataGenerator()->create_course(['fullname' => 'Policy course']);

        $this->expectException(\moodle_exception::class);
        $this->make_service_with_mock_jobs($jobmock)->submit_course_image_edit_job(
            (int) $course->id,
            ['Ym9n'],
            'Make it brighter'
        );
    }

    public function test_submit_course_image_edit_job_calls_api_when_allowed(): void {
        $this->apply_image_generation_settings(
            1,
            image_generation_policy::MODE_GENERATE_EDIT,
            image_generation_policy::MODE_DISABLED
        );

        $course = $this->getDataGenerator()->create_course(['fullname' => 'Policy course']);

        $jobmock = $this->createMock(job_service::class);
        $courseid = (int) $course->id;
        $jobmock->expects($this->once())->method('submit_job')->with(
            $this->equalTo('/v1/images/edit'),
            $this->callback(static function (array $payload) use ($courseid): bool {
                return ($payload['courseId'] ?? '') === (string) $courseid
                    && ($payload['instructions'] ?? '') === 'Make it brighter'
                    && isset($payload['images']) && is_array($payload['images']);
            })
        )->willReturn($this->pending_result('edit-job-1'));

        $result = $this->make_service_with_mock_jobs($jobmock)->submit_course_image_edit_job(
            $courseid,
            ['Ym9n'],
            'Make it brighter'
        );
        $this->assertSame('edit-job-1', $result->jobid);
    }

    public function test_submit_section_image_job_rejected_when_section_mode_disabled(): void {
        $this->apply_image_generation_settings(
            1,
            image_generation_policy::MODE_GENERATE_EDIT,
            image_generation_policy::MODE_DISABLED
        );

        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $sectionid = (int) $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 1], MUST_EXIST);

        $jobmock = $this->createMock(job_service::class);
        $jobmock->expects($this->never())->method('submit_job');

        $this->expectException(\moodle_exception::class);
        $this->make_service_with_mock_jobs($jobmock)->submit_section_image_job($sectionid);
    }

    public function test_submit_section_image_job_calls_api_when_allowed(): void {
        $this->apply_image_generation_settings(
            1,
            image_generation_policy::MODE_DISABLED,
            image_generation_policy::MODE_GENERATE
        );

        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $sectionid = (int) $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 1], MUST_EXIST);

        $jobmock = $this->createMock(job_service::class);
        $jobmock->expects($this->once())->method('submit_job')->with(
            $this->equalTo('/v1/images/generate'),
            $this->callback(static function (array $payload): bool {
                return ($payload['scope'] ?? '') === 'section';
            })
        )->willReturn($this->pending_result('sec-gen-1'));

        $result = $this->make_service_with_mock_jobs($jobmock)->submit_section_image_job($sectionid);
        $this->assertSame('sec-gen-1', $result->jobid);
    }

    public function test_submit_section_image_edit_job_rejected_when_section_generate_only(): void {
        $this->apply_image_generation_settings(
            1,
            image_generation_policy::MODE_GENERATE_EDIT,
            image_generation_policy::MODE_GENERATE
        );

        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $sectionid = (int) $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 1], MUST_EXIST);

        $jobmock = $this->createMock(job_service::class);
        $jobmock->expects($this->never())->method('submit_job');

        $this->expectException(\moodle_exception::class);
        $this->make_service_with_mock_jobs($jobmock)->submit_section_image_edit_job(
            $sectionid,
            ['Ym9n'],
            'Change background'
        );
    }

    public function test_submit_section_image_edit_job_calls_api_when_allowed(): void {
        $this->apply_image_generation_settings(
            1,
            image_generation_policy::MODE_DISABLED,
            image_generation_policy::MODE_GENERATE_EDIT
        );

        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $sectionid = (int) $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 1], MUST_EXIST);

        $jobmock = $this->createMock(job_service::class);
        $expectcourseid = (int) $course->id;
        $jobmock->expects($this->once())->method('submit_job')->with(
            $this->equalTo('/v1/images/edit'),
            $this->callback(static function (array $payload) use ($expectcourseid): bool {
                return ($payload['courseId'] ?? '') === (string) $expectcourseid
                    && ($payload['instructions'] ?? '') === 'Change background'
                    && isset($payload['images']) && is_array($payload['images']);
            })
        )->willReturn($this->pending_result('sec-edit-1'));

        $result = $this->make_service_with_mock_jobs($jobmock)->submit_section_image_edit_job(
            $sectionid,
            ['Ym9n'],
            'Change background'
        );
        $this->assertSame('sec-edit-1', $result->jobid);
    }

    public function test_submit_content_image_generate_job_rejected_when_content_mode_disabled(): void {
        $this->apply_image_generation_settings(
            1,
            image_generation_policy::MODE_GENERATE_EDIT,
            image_generation_policy::MODE_GENERATE_EDIT,
            image_generation_policy::MODE_DISABLED
        );

        $jobmock = $this->createMock(job_service::class);
        $jobmock->expects($this->never())->method('submit_job');

        $course = $this->getDataGenerator()->create_course(['fullname' => 'Content course']);

        $this->expectException(\moodle_exception::class);
        $this->make_service_with_mock_jobs($jobmock)->submit_content_image_generate_job(
            (int) $course->id,
            'Activity image',
            'A sunny meadow'
        );
    }

    public function test_submit_content_image_generate_job_calls_api_with_scope_course(): void {
        $this->apply_image_generation_settings(
            1,
            image_generation_policy::MODE_DISABLED,
            image_generation_policy::MODE_DISABLED,
            image_generation_policy::MODE_GENERATE
        );

        $course = $this->getDataGenerator()->create_course(['fullname' => 'Content course']);

        $jobmock = $this->createMock(job_service::class);
        $jobmock->expects($this->once())->method('submit_job')->with(
            $this->equalTo('/v1/images/generate'),
            $this->callback(static function (array $payload): bool {
                return ($payload['scope'] ?? '') === 'course'
                    && ($payload['title'] ?? '') === 'Activity image'
                    && ($payload['summary'] ?? '') === 'A sunny meadow';
            })
        )->willReturn($this->pending_result('content-gen-1'));

        $result = $this->make_service_with_mock_jobs($jobmock)->submit_content_image_generate_job(
            (int) $course->id,
            'Activity image',
            'A sunny meadow'
        );
        $this->assertSame('content-gen-1', $result->jobid);
    }

    public function test_submit_content_image_edit_job_rejected_when_content_generate_only(): void {
        $this->apply_image_generation_settings(
            1,
            image_generation_policy::MODE_GENERATE_EDIT,
            image_generation_policy::MODE_GENERATE_EDIT,
            image_generation_policy::MODE_GENERATE
        );

        $jobmock = $this->createMock(job_service::class);
        $jobmock->expects($this->never())->method('submit_job');

        $course = $this->getDataGenerator()->create_course(['fullname' => 'Content course']);

        $this->expectException(\moodle_exception::class);
        $this->make_service_with_mock_jobs($jobmock)->submit_content_image_edit_job(
            (int) $course->id,
            ['Ym9n'],
            'Brighten the image'
        );
    }
}
