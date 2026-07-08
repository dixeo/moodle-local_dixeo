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

namespace local_dixeo\service\image\content;


use local_dixeo\service\image_generation_service;
use local_dixeo\service\job_service;

/**
 * Tests for shortcode_service.
 *
 * @covers \local_dixeo\service\image\content\shortcode_service
 * @package local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class shortcode_service_test extends \advanced_testcase {
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
     * Test process html creates stub and job.
     */
    public function test_process_html_creates_stub_and_job(): void {
        global $DB, $USER;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Hello</p>',
            'contentformat' => FORMAT_HTML,
        ]);
        $context = \context_module::instance($page->cmid);

        $jobmock = $this->getMockBuilder(job_service::class)
            ->onlyMethods(['submit_job'])
            ->getMock();
        $jobmock->expects($this->once())
            ->method('submit_job')
            ->willReturn(\local_dixeo\dto\operation_result::pending('job-123', 'pending'));

        $imageservice = new image_generation_service($jobmock);
        $service = new shortcode_service($imageservice);

        $target = new html_field_target(
            'page',
            'content',
            'mod_page',
            'content',
            0,
            'page',
            'content',
            (int) $page->id,
            $context->id,
            (int) $course->id,
            (int) $page->cmid,
            'contentformat'
        );

        $html = 'Intro [img-gen prompt="A diagram of cells"] end';
        $processed = $service->process_html($html, $target, (int) $USER->id);

        $this->assertStringNotContainsString('[img-gen', $processed);
        $this->assertStringContainsString('data-dixeo-img-gen=', $processed);
        $this->assertStringContainsString('dixeo-img-gen-pending', $processed);
        $this->assertSame(1, $DB->count_records('local_dixeo_image_job'));
    }

    /**
     * Test process html strips when policy disabled.
     */
    public function test_process_html_strips_when_policy_disabled(): void {
        set_config('image_generation_content_mode', 'disabled', 'local_dixeo');
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $context = \context_module::instance($page->cmid);

        $service = new shortcode_service(new image_generation_service(new job_service()));
        $target = new html_field_target(
            'page',
            'content',
            'mod_page',
            'content',
            0,
            'page',
            'content',
            (int) $page->id,
            $context->id,
            (int) $course->id,
            (int) $page->cmid,
            'contentformat'
        );

        $processed = $service->process_html('[img-gen prompt="Remove me"]', $target, 2);
        $this->assertSame('', trim($processed));
    }

    /**
     * Test multiple shortcodes in one field.
     */
    public function test_multiple_shortcodes_in_one_field(): void {
        global $DB, $USER;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $context = \context_module::instance($page->cmid);

        $jobmock = $this->getMockBuilder(job_service::class)
            ->onlyMethods(['submit_job'])
            ->getMock();
        $jobmock->expects($this->exactly(3))
            ->method('submit_job')
            ->willReturnOnConsecutiveCalls(
                \local_dixeo\dto\operation_result::pending('job-1', 'pending'),
                \local_dixeo\dto\operation_result::pending('job-2', 'pending'),
                \local_dixeo\dto\operation_result::pending('job-3', 'pending')
            );

        $service = new shortcode_service(new image_generation_service($jobmock));
        $target = new html_field_target(
            'page',
            'content',
            'mod_page',
            'content',
            0,
            'page',
            'content',
            (int) $page->id,
            $context->id,
            (int) $course->id,
            (int) $page->cmid,
            'contentformat'
        );

        $html = '[img-gen prompt="One"] [img-gen prompt="Two"] [img-gen prompt="Three"]';
        $processed = $service->process_html($html, $target, (int) $USER->id);

        $this->assertSame(3, substr_count($processed, 'data-dixeo-img-gen='));
        $this->assertSame(3, $DB->count_records('local_dixeo_image_job'));
    }

    /**
     * Test glossary image prompt targets entry definitions.
     */
    public function test_glossary_image_prompt_targets_entry_definitions(): void {
        $prompt = shortcode_service::get_image_prompt_for_module('glossary');
        $this->assertStringContainsString('create_entries', $prompt);
        $this->assertStringContainsString('definition', $prompt);
        $this->assertStringContainsString('[img-gen', $prompt);
    }
}
