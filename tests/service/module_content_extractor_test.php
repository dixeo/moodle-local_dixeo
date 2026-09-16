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
 * Tests for module_content_extractor edit paths with autosave draft.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Unit tests for module content extractor.
 *
 * @covers \local_dixeo\service\module_content_extractor
 */
final class module_content_extractor_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        // The search manager keeps its engine in a static, which phpunit does not reset.
        \core_search\manager::clear_static();
    }

    public function test_page_merges_intro_from_db_with_draft_content(): void {
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $page = $gen->create_module('page', [
            'course' => $course->id,
            'intro' => '<p>Intro only</p>',
            'introformat' => FORMAT_HTML,
            'content' => '<p>Saved body</p>',
            'contentformat' => FORMAT_HTML,
        ]);
        $cm = get_coursemodule_from_instance('page', $page->id, $course->id);
        $modinfo = get_fast_modinfo($course);
        $cminfo = $modinfo->get_cm($cm->id);

        $extractor = new \local_dixeo\service\module_content_extractor();
        $out = $extractor->get_full_content_for_edit($cminfo, '<p>Draft body</p>');

        $this->assertNotNull($out);
        $this->assertStringContainsString('**Introduction:**', $out);
        $this->assertStringContainsString('<p>Intro only</p>', $out);
        $this->assertStringContainsString('**Content:**', $out);
        $this->assertStringContainsString('<p>Draft body</p>', $out);
        $this->assertStringNotContainsString('<p>Saved body</p>', $out);
    }

    public function test_label_replaces_intro_with_draft(): void {
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $label = $gen->create_module('label', [
            'course' => $course->id,
            'intro' => '<p>Old label</p>',
            'introformat' => FORMAT_HTML,
        ]);
        $cm = get_coursemodule_from_instance('label', $label->id, $course->id);
        $modinfo = get_fast_modinfo($course);
        $cminfo = $modinfo->get_cm($cm->id);

        $extractor = new \local_dixeo\service\module_content_extractor();
        $out = $extractor->get_full_content_for_edit($cminfo, '<p>New label</p>');
        $this->assertSame('<p>New label</p>', $out);
    }

    public function test_null_draft_same_as_omitted_for_page(): void {
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $page = $gen->create_module('page', [
            'course' => $course->id,
            'intro' => '<p>I</p>',
            'introformat' => FORMAT_HTML,
            'content' => '<p>C</p>',
            'contentformat' => FORMAT_HTML,
        ]);
        $cm = get_coursemodule_from_instance('page', $page->id, $course->id);
        $modinfo = get_fast_modinfo($course);
        $cminfo = $modinfo->get_cm($cm->id);

        $extractor = new \local_dixeo\service\module_content_extractor();
        $without = $extractor->get_full_content_for_edit($cminfo);
        $withnull = $extractor->get_full_content_for_edit($cminfo, null);
        $this->assertSame($without, $withnull);
    }

    public function test_page_is_still_read_natively(): void {
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $page = $gen->create_module('page', [
            'course' => $course->id,
            'intro' => '<p>Page intro</p>',
            'introformat' => FORMAT_HTML,
            'content' => '<p>Page body</p>',
            'contentformat' => FORMAT_HTML,
        ]);
        $cminfo = get_fast_modinfo($course->id)->get_cm($page->cmid);

        $extractor = new \local_dixeo\service\module_content_extractor();
        $out = $extractor->get_raw_content($cminfo);

        $this->assertStringContainsString('**Introduction:**', $out);
        $this->assertStringContainsString('<p>Page intro</p>', $out);
        $this->assertStringContainsString('**Content:**', $out);
        $this->assertStringContainsString('<p>Page body</p>', $out);
    }

    public function test_forum_is_read_from_its_search_area(): void {
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $forum = $gen->create_module('forum', [
            'course' => $course->id,
            'intro' => '<p>Forum intro text</p>',
            'introformat' => FORMAT_HTML,
        ]);
        $cminfo = get_fast_modinfo($course->id)->get_cm($forum->cmid);

        $extractor = new \local_dixeo\service\module_content_extractor();
        $out = $extractor->get_raw_content($cminfo);

        $this->assertNotNull($out);
        $this->assertStringContainsString('Forum intro text', $out);
        // The search document holds plain text; the intro fallback would still carry its markup.
        $this->assertStringNotContainsString('<p>', $out);
    }

    public function test_forum_falls_back_to_its_intro_without_a_search_engine(): void {
        $this->setAdminUser();
        set_config('searchengine', '');
        \core_search\manager::clear_static();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $forum = $gen->create_module('forum', [
            'course' => $course->id,
            'intro' => '<p>Forum intro text</p>',
            'introformat' => FORMAT_HTML,
        ]);
        $cminfo = get_fast_modinfo($course->id)->get_cm($forum->cmid);

        $extractor = new \local_dixeo\service\module_content_extractor();

        $this->assertSame('<p>Forum intro text</p>', $extractor->get_raw_content($cminfo));
    }

    public function test_search_area_content_is_cached_until_the_course_cache_is_rebuilt(): void {
        global $DB;

        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $forum = $gen->create_module('forum', [
            'course' => $course->id,
            'intro' => '<p>First intro</p>',
            'introformat' => FORMAT_HTML,
        ]);

        $extractor = new \local_dixeo\service\module_content_extractor();
        $cminfo = get_fast_modinfo($course->id)->get_cm($forum->cmid);
        $this->assertStringContainsString('First intro', $extractor->get_raw_content($cminfo));

        $DB->set_field('forum', 'intro', '<p>Second intro</p>', ['id' => $forum->id]);

        // Course cacherev unchanged: the cached document is served.
        $cminfo = get_fast_modinfo($course->id)->get_cm($forum->cmid);
        $this->assertStringContainsString('First intro', $extractor->get_raw_content($cminfo));

        rebuild_course_cache($course->id, true);

        $cminfo = get_fast_modinfo($course->id)->get_cm($forum->cmid);
        $this->assertStringContainsString('Second intro', $extractor->get_raw_content($cminfo));
    }

    public function test_search_area_content_follows_the_module_timestamp(): void {
        global $DB;

        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $forum = $gen->create_module('forum', [
            'course' => $course->id,
            'intro' => '<p>First intro</p>',
            'introformat' => FORMAT_HTML,
        ]);

        $extractor = new \local_dixeo\service\module_content_extractor();
        $cminfo = get_fast_modinfo($course->id)->get_cm($forum->cmid);
        $this->assertStringContainsString('First intro', $extractor->get_raw_content($cminfo));

        // A refresh outside any course edit (a task) bumps the module timestamp only.
        $DB->set_field('forum', 'intro', '<p>Second intro</p>', ['id' => $forum->id]);
        $DB->set_field('forum', 'timemodified', time() + 10, ['id' => $forum->id]);

        $cminfo = get_fast_modinfo($course->id)->get_cm($forum->cmid);
        $this->assertStringContainsString('Second intro', $extractor->get_raw_content($cminfo));
    }
}
