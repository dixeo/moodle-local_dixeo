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

namespace local_dixeo\dsl;

use local_dixeo\dsl\actions\create_slides_action;
use local_dixeo\service\plugin_installation_service;

/**
 * Tests for generated slideshow slides.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\dsl\actions\create_slides_action
 */
final class create_slides_action_test extends \advanced_testcase {
    /**
     * Skip when the slideshow activity is not installed.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        if (!plugin_installation_service::is_plugin_installed('mod_slideshow')) {
            $this->markTestSkipped('mod_slideshow is not installed');
        }
        set_config('image_generation_enabled', '0', 'local_dixeo');
    }

    /**
     * An older response has no opening title, so the first slide uses the activity name.
     */
    public function test_old_payload_opens_on_a_title_only_slide(): void {
        $slides = $this->create_slideshow('Cells', [
            'name' => 'Cells',
            'intro' => '<p>How cells work.</p>',
            'slides' => [
                ['title' => 'Membrane', 'content' => '<p>The boundary.</p>'],
            ],
        ], false);

        $this->assertCount(2, $slides);
        $opening = $slides[0];
        $this->assertSame('Cells', $opening->name);
        $this->assertSame('titleonly', $opening->layout);
        $this->assertSame('', $opening->content);
        $this->assertSame('0', $opening->hidetitle);
        $this->assertSame('0', $opening->sortorder);

        $content = $slides[1];
        $this->assertSame('Membrane', $content->name);
        $this->assertSame('background', $content->layout);
        $this->assertStringContainsString('The boundary.', $content->content);
        $this->assertSame('1', $content->sortorder);
    }

    /**
     * The API opening title is the first slide, not the activity name.
     */
    public function test_opening_title_is_the_first_slide(): void {
        $slides = $this->create_slideshow('Cells', [
            'name' => 'Cells',
            'intro' => '<p>How cells work.</p>',
            'openingTitle' => 'A look at cells',
            'slides' => [
                ['title' => 'Membrane', 'content' => '<p>The boundary.</p>'],
            ],
        ], true);

        $this->assertSame('A look at cells', $slides[0]->name);
        $this->assertSame('titleonly', $slides[0]->layout);
        $this->assertSame('Cells', $this->activity_name((int) $slides[0]->slideshow));
    }

    /**
     * A content layout without a featured image is stored as background.
     */
    public function test_missing_featured_image_forces_background(): void {
        $slides = $this->create_slideshow('Cells', [
            'name' => 'Cells',
            'intro' => '<p>How cells work.</p>',
            'openingTitle' => 'A look at cells',
            'slides' => [
                [
                    'title' => 'Membrane',
                    'content' => '<p>The boundary.</p>',
                    'layout' => 'contentleft',
                    'featuredImage' => '',
                ],
                [
                    'title' => 'Nucleus',
                    'content' => '<p>The control centre.</p>',
                    'layout' => 'not-a-layout',
                    'featuredImage' => '[img-gen prompt="A nucleus" mode="portrait"]',
                ],
            ],
        ], true);

        $this->assertSame('background', $slides[1]->layout);
        $this->assertSame('background', $slides[2]->layout);
    }

    /**
     * Image generation is off, so a content layout with a shortcode becomes background.
     */
    public function test_featured_image_is_dropped_when_generation_is_disabled(): void {
        set_config('image_generation_enabled', '0', 'local_dixeo');

        $slides = $this->create_slideshow('Cells', [
            'name' => 'Cells',
            'intro' => '<p>How cells work.</p>',
            'openingTitle' => 'A look at cells',
            'slides' => [
                [
                    'title' => 'Membrane',
                    'content' => '<p>The boundary.</p>',
                    'layout' => 'contentleft',
                    'featuredImage' => '[img-gen prompt="A cell membrane" mode="portrait"]',
                ],
            ],
        ], true);

        $this->assertSame('background', $slides[1]->layout);
        $this->assertStringNotContainsString('[img-gen', $slides[1]->content);
    }

    /**
     * A queued featured image keeps the content layout and is stored beside the slide.
     */
    public function test_featured_image_shortcode_is_stored_in_the_slide_image_area(): void {
        global $DB;

        set_config('image_generation_enabled', '1', 'local_dixeo');
        set_config('image_generation_content_mode', 'generate', 'local_dixeo');

        $slides = $this->create_slideshow('Cells', [
            'name' => 'Cells',
            'intro' => '<p>How cells work.</p>',
            'openingTitle' => 'A look at cells',
            'slides' => [
                [
                    'title' => 'Membrane',
                    'content' => '<p>The boundary.</p>',
                    'layout' => 'contentleft',
                    'featuredImage' => '[img-gen prompt="A cell membrane" mode="portrait"]',
                ],
            ],
        ], true);

        $this->assertSame('contentleft', $slides[1]->layout);
        $this->assertStringNotContainsString('[img-gen', $slides[1]->content);
        $cm = get_coursemodule_from_instance('slideshow', (int) $slides[1]->slideshow, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $files = get_file_storage()->get_area_files(
            $context->id,
            'mod_slideshow',
            'image',
            (int) $slides[1]->id,
            'filename',
            false
        );
        $this->assertCount(1, $files);
        $job = $DB->get_record('local_dixeo_image_job', [
            'component' => 'mod_slideshow',
            'filearea' => 'image',
            'itemid' => $slides[1]->id,
        ], '*', MUST_EXIST);
        $this->assertSame('slide_featured', $job->origin);
        $this->assertSame('', $job->jobid);
        $this->assertSame('pending', $job->status);

        $tasks = \core\task\manager::get_adhoc_tasks(\local_dixeo\task\poll_image_job::class);
        $this->assertCount(1, $tasks);
        $request = reset($tasks)->get_custom_data()->request;
        $this->assertSame('A cell membrane', $request->prompt);
        $this->assertSame('1024x1536', $request->size);
    }

    /**
     * Create a slideshow from DSL and return its slides in sort order.
     *
     * @param string $activityname Activity name.
     * @param array $data AI response.
     * @param bool $withlayout True to map layout and featured image fields.
     * @param create_slides_action|null $slidesaction Slides action, or null for the default one.
     * @return \stdClass[]
     */
    private function create_slideshow(
        string $activityname,
        array $data,
        bool $withlayout,
        ?create_slides_action $slidesaction = null
    ): array {
        global $DB;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1], '*', MUST_EXIST);
        $context = [
            'courseid' => (int) $course->id,
            'sectionid' => (int) $section->id,
            'sectionnum' => 1,
            'modulename' => 'slideshow',
            'userid' => (int) $this->getDataGenerator()->create_user()->id,
            'contextid' => \context_course::instance($course->id)->id,
        ];

        $fields = [
            'title' => ['source' => '$.title'],
            'content' => ['source' => '$.content'],
        ];
        if ($withlayout) {
            $fields['layout'] = ['source' => '$.layout'];
            $fields['featuredimage'] = ['source' => '$.featuredImage'];
        }

        $cmid = (new interpreter(null, null, null, $slidesaction))->execute([
            [
                'action' => 'create_module',
                'save_as' => 'module',
                'fields' => [
                    'name' => ['source' => '$.name'],
                    'intro' => ['source' => '$.intro'],
                    'introformat' => ['value' => FORMAT_HTML],
                ],
            ],
            [
                'action' => 'add_slides',
                'module_ref' => '$module',
                'foreach' => '$.slides',
                'fields' => $fields,
            ],
        ], $data, $context);

        $cm = get_coursemodule_from_id('slideshow', $cmid, (int) $course->id, false, MUST_EXIST);
        $this->assertSame($activityname, $cm->name);
        $slides = $DB->get_records('slideshow_slide', ['slideshow' => $cm->instance], 'sortorder ASC');

        return array_values($slides);
    }

    /**
     * Activity name stored for a slideshow instance.
     *
     * @param int $instanceid Slideshow instance id.
     * @return string
     */
    private function activity_name(int $instanceid): string {
        global $DB;

        return (string) $DB->get_field('slideshow', 'name', ['id' => $instanceid], MUST_EXIST);
    }
}
