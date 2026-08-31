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
 * Tests for structure_image writer (course overview + Dixeo section images, cache).
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use context_course;
use core_course\external\course_summary_exporter;
use local_dixeo\service\image\structure\scope;
use local_dixeo\service\image\structure\writer;



defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
$formatdixeolib = $CFG->dirroot . '/course/format/dixeo/lib.php';
if (is_readable($formatdixeolib)) {
    require_once($formatdixeolib);
}

/**
 * Tests for writer_test.
 * @covers \local_dixeo\service\image\structure\writer
 */
final class writer_test extends \advanced_testcase {
    /**
     * Core filestorage fixture: PNG overview / first generation.
     *
     * @return string
     */
    private static function fixture_png_bytes(): string {
        global $CFG;
        return (string) file_get_contents($CFG->dirroot . '/lib/filestorage/tests/fixtures/testimage.png');
    }

    /**
     * Core filestorage fixture: JPEG with different bytes and extension from the PNG.
     *
     * @return string
     */
    private static function fixture_jpeg_bytes(): string {
        global $CFG;
        return (string) file_get_contents($CFG->dirroot . '/lib/filestorage/tests/fixtures/testimage.jpg');
    }

    /**
     * Skip when format_dixeo is not available (e.g. standalone local_dixeo CI).
     */
    private function require_format_dixeo(): void {
        global $CFG;
        if (!is_readable($CFG->dirroot . '/course/format/dixeo/lib.php')) {
            $this->markTestSkipped('format_dixeo is not installed in this environment.');
        }
    }

    /**
     * Reset after each test.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**

     * Test apply course overview on fresh course sets image and url.

     */
    public function test_apply_course_overview_on_fresh_course_sets_image_and_url(): void {
        global $USER;

        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['format' => 'topics'], ['createsections' => true]);
        $binary = self::fixture_png_bytes();

        writer::apply_image_binary_to_course_overview((int) $course->id, $binary, (int) $USER->id);

        $fresh = get_course($course->id);
        $url = course_summary_exporter::get_course_image($fresh);
        $this->assertNotEmpty($url);
        $this->assertStringContainsString('pluginfile.php', (string) $url);

        $context = context_course::instance($course->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'id', false);
        $this->assertCount(1, $files);
    }

    /**

     * Test apply section on fresh section sets pluginfile url.

     */
    public function test_apply_section_on_fresh_section_sets_pluginfile_url(): void {
        global $USER;

        $this->require_format_dixeo();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['format' => 'dixeo', 'numsections' => 2], ['createsections' => true]);
        $section = $this->get_section_one($course->id);
        $binary = self::fixture_png_bytes();

        writer::apply_from_job_result(
            scope::SCOPE_FORMAT_SECTION,
            (int) $section->id,
            ['image_base64' => base64_encode($binary)],
            (int) $USER->id
        );

        $fresh = get_course($course->id);
        $modinfo = get_fast_modinfo($fresh);
        $sectioninfo = $modinfo->get_section_info_by_id((int) $section->id);
        $this->assertNotNull($sectioninfo);
        $imageurl = \format_dixeo::get_section_image_url($fresh, $sectioninfo);
        $this->assertNotNull($imageurl);
        $this->assertStringContainsString('pluginfile.php', $imageurl);
        $this->assertStringContainsString('chapterimage', $imageurl);

        $context = context_course::instance($course->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $context->id,
            'format_dixeo',
            \format_dixeo::SECTION_IMAGE_FILEAREA,
            (int) $section->id,
            'id',
            false
        );
        $this->assertCount(1, $files);
    }

    /**

     * Test apply course overview clears stale course image cache.

     */
    public function test_apply_course_overview_clears_stale_course_image_cache(): void {
        global $USER;

        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['format' => 'topics'], ['createsections' => true]);
        $courseid = (int) $course->id;

        $stale = 'https://example.invalid/stale-course-image.png';
        \cache::make('core', 'course_image')->set($courseid, $stale);
        $this->assertSame($stale, course_summary_exporter::get_course_image(get_course($courseid)));

        writer::apply_image_binary_to_course_overview($courseid, self::fixture_png_bytes(), (int) $USER->id);

        $url = course_summary_exporter::get_course_image(get_course($courseid));
        $this->assertNotSame($stale, $url);
        $this->assertNotEmpty($url);
        $this->assertStringContainsString('pluginfile.php', (string) $url);
    }

    /**

     * Test apply section clears stale course image cache.

     */
    public function test_apply_section_clears_stale_course_image_cache(): void {
        global $USER;

        $this->require_format_dixeo();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['format' => 'dixeo', 'numsections' => 2], ['createsections' => true]);
        $courseid = (int) $course->id;
        $section = $this->get_section_one($courseid);

        $stale = 'https://example.invalid/stale-after-section.png';
        \cache::make('core', 'course_image')->set($courseid, $stale);
        $this->assertSame($stale, course_summary_exporter::get_course_image(get_course($courseid)));

        writer::apply_from_job_result(
            scope::SCOPE_FORMAT_SECTION,
            (int) $section->id,
            ['image_base64' => base64_encode(self::fixture_png_bytes())],
            (int) $USER->id
        );

        $url = course_summary_exporter::get_course_image(get_course($courseid));
        $this->assertNotSame($stale, $url);
        // No course overview file: datasource returns false; cache must not keep the stale string.
        $this->assertTrue($url === false || (is_string($url) && str_contains($url, 'pluginfile.php')));
    }

    /**

     * Test regenerate course overview replaces file content.

     */
    public function test_regenerate_course_overview_replaces_file_content(): void {
        global $USER;

        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['format' => 'topics'], ['createsections' => true]);
        $courseid = (int) $course->id;
        $first = self::fixture_png_bytes();
        $second = self::fixture_jpeg_bytes();
        $this->assertNotSame(sha1($first), sha1($second));

        writer::apply_image_binary_to_course_overview($courseid, $first, (int) $USER->id);
        $context = context_course::instance($courseid);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'id', false);
        $this->assertCount(1, $files);
        $hashafterfirst = reset($files)->get_contenthash();

        writer::apply_image_binary_to_course_overview($courseid, $second, (int) $USER->id);
        $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'id', false);
        $this->assertCount(1, $files);
        $this->assertNotSame($hashafterfirst, reset($files)->get_contenthash());
    }

    /**

     * Test regenerate section replaces file content.

     */
    public function test_regenerate_section_replaces_file_content(): void {
        global $USER;

        $this->require_format_dixeo();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['format' => 'dixeo', 'numsections' => 2], ['createsections' => true]);
        $section = $this->get_section_one($course->id);
        $sid = (int) $section->id;
        $first = self::fixture_png_bytes();
        $second = self::fixture_jpeg_bytes();
        $this->assertNotSame(sha1($first), sha1($second));

        writer::apply_from_job_result(
            scope::SCOPE_FORMAT_SECTION,
            $sid,
            ['image_base64' => base64_encode($first)],
            (int) $USER->id
        );

        $context = context_course::instance($course->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'format_dixeo', \format_dixeo::SECTION_IMAGE_FILEAREA, $sid, 'id', false);
        $this->assertCount(1, $files);
        $hashafterfirst = reset($files)->get_contenthash();

        writer::apply_from_job_result(
            scope::SCOPE_FORMAT_SECTION,
            $sid,
            ['image_base64' => base64_encode($second)],
            (int) $USER->id
        );
        $files = $fs->get_area_files($context->id, 'format_dixeo', \format_dixeo::SECTION_IMAGE_FILEAREA, $sid, 'id', false);
        $this->assertCount(1, $files);
        $this->assertNotSame($hashafterfirst, reset($files)->get_contenthash());
    }

    /**

     * Test apply from job result course scope matches direct overview apply.

     */
    public function test_apply_from_job_result_course_scope_matches_direct_overview_apply(): void {
        global $USER;

        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['format' => 'topics'], ['createsections' => true]);
        $binary = self::fixture_png_bytes();

        writer::apply_from_job_result(
            scope::SCOPE_COURSE_OVERVIEW,
            (int) $course->id,
            ['image_base64' => base64_encode($binary)],
            (int) $USER->id
        );

        $url = course_summary_exporter::get_course_image(get_course($course->id));
        $this->assertNotEmpty($url);
        $this->assertStringContainsString('pluginfile.php', (string) $url);
    }

    /**
     * Fetch course section 1 for the given course.
     *
     * @param int $courseid
     * @return \stdClass course_sections row
     */
    private function get_section_one(int $courseid): \stdClass {
        global $DB;
        return $DB->get_record('course_sections', ['course' => $courseid, 'section' => 1], '*', MUST_EXIST);
    }
}
