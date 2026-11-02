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
 * Tests for H5P flashcard images written into the activity package.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service\image\apply;

use local_dixeo\repository\image\job_repository;
use local_dixeo\service\h5p_package_archive;
use local_dixeo\service\h5p_packaging_service;
use local_dixeo\service\image\h5p_card_target;
use local_dixeo\service\image\target_factory;

/**
 * Unit tests for h5p_card_handler.
 *
 * @covers \local_dixeo\service\image\apply\h5p_card_handler
 * @covers \local_dixeo\service\image\apply\registry
 * @covers \local_dixeo\service\image\h5p_card_target
 * @covers \local_dixeo\service\image\target_factory
 * @covers \local_dixeo\service\h5p_packaging_service::replace_package
 */
final class h5p_card_handler_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass h5pactivity instance with cmid. */
    private \stdClass $activity;

    /**
     * Create a flashcards activity with two text-only cards.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();
        $this->activity = $this->getDataGenerator()->create_module('h5pactivity', ['course' => $this->course->id]);

        $path = make_request_directory() . '/package.h5p';
        h5p_package_archive::write($path, [
            'h5p.json' => '{"mainLibrary":"H5P.Flashcards"}',
            h5p_package_archive::CONTENT_JSON => json_encode(['cards' => [['text' => 'Leaf'], ['text' => 'Root']]]),
        ]);
        (new h5p_packaging_service())->replace_package((int) $this->activity->cmid, $path);
    }

    /**
     * The image is added to the package and the card points at it.
     */
    public function test_apply_adds_the_image_to_the_package(): void {
        registry::apply($this->target(1), $this->image_result(4, 3), 0);

        $path = $this->package_copy();
        $content = json_decode(h5p_package_archive::read($path, h5p_package_archive::CONTENT_JSON), true);
        $this->assertSame(['text' => 'Leaf'], $content['cards'][0]);
        $this->assertSame([
            'path' => 'images/card-1.png',
            'mime' => 'image/png',
            'copyright' => ['license' => 'U'],
            'width' => 4,
            'height' => 3,
        ], $content['cards'][1]['image']);
        $this->assertSame(
            base64_decode($this->image_result(4, 3)['image_base64']),
            h5p_package_archive::read($path, 'content/images/card-1.png')
        );
        $this->assertSame('{"mainLibrary":"H5P.Flashcards"}', h5p_package_archive::read($path, 'h5p.json'));
    }

    /**
     * Two card images applied one after the other both end up in the package.
     */
    public function test_apply_keeps_earlier_card_images(): void {
        registry::apply($this->target(0), $this->image_result(2, 2), 0);
        registry::apply($this->target(1), $this->image_result(4, 3), 0);

        $path = $this->package_copy();
        $content = json_decode(h5p_package_archive::read($path, h5p_package_archive::CONTENT_JSON), true);
        $this->assertSame('images/card-0.png', $content['cards'][0]['image']['path']);
        $this->assertSame('images/card-1.png', $content['cards'][1]['image']['path']);
        $this->assertNotNull(h5p_package_archive::read($path, 'content/images/card-0.png'));
    }

    /**
     * Replacing the package removes the h5p row deployed from the previous file, so core redeploys it.
     */
    public function test_apply_drops_the_stale_deployment(): void {
        global $DB;

        $file = $this->package_file();
        $h5pid = $DB->insert_record('h5p', (object) [
            'jsoncontent' => '{}',
            'mainlibraryid' => 0,
            'displayoptions' => 0,
            'pathnamehash' => $file->get_pathnamehash(),
            'contenthash' => $file->get_contenthash(),
            'filtered' => null,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        registry::apply($this->target(0), $this->image_result(2, 2), 0);

        $this->assertNotSame($file->get_contenthash(), $this->package_file()->get_contenthash());
        $this->assertSame($this->package_file()->get_pathnamehash(), $file->get_pathnamehash());
        $this->assertFalse($DB->record_exists('h5p', ['id' => $h5pid]));
    }

    /**
     * A card that no longer exists leaves the package untouched.
     */
    public function test_apply_skips_a_missing_card(): void {
        $before = $this->package_file()->get_contenthash();

        registry::apply($this->target(5), $this->image_result(2, 2), 0);

        $this->assertSame($before, $this->package_file()->get_contenthash());
    }

    /**
     * A card that already has an image keeps it.
     */
    public function test_apply_skips_a_card_that_already_has_an_image(): void {
        registry::apply($this->target(0), $this->image_result(2, 2), 0);
        $before = $this->package_file()->get_contenthash();

        registry::apply($this->target(0), $this->image_result(4, 3), 0);

        $this->assertSame($before, $this->package_file()->get_contenthash());
    }

    /**
     * A deleted activity is skipped without error.
     */
    public function test_apply_skips_a_deleted_activity(): void {
        $target = $this->target(0);
        \core_courseformat\formatactions::cm((int) $this->course->id)->delete((int) $this->activity->cmid);

        registry::apply($target, $this->image_result(2, 2), 0);

        $this->assertFalse(get_coursemodule_from_id('h5pactivity', $target->get_cmid(), 0, false, IGNORE_MISSING));
    }

    /**
     * A result with no image fails, so the job row records the failure.
     */
    public function test_apply_throws_on_an_empty_result(): void {
        $this->expectException(\moodle_exception::class);

        registry::apply($this->target(0), [], 0);
    }

    /**
     * The target round-trips through its job row and its poll data.
     */
    public function test_target_round_trips_through_job_row_and_poll_data(): void {
        $target = $this->target(1);
        $row = job_repository::upsert($target, '', 2);

        $this->assertSame((int) $this->activity->cmid, (int) $row->cmid);
        foreach ([target_factory::from_job_record($row), target_factory::from_poll_data($target->to_poll_custom_data())] as $copy) {
            $this->assertInstanceOf(h5p_card_target::class, $copy);
            $this->assertSame($target->get_location_hash(), $copy->get_location_hash());
            $this->assertSame(1, $copy->get_cardindex());
            $this->assertSame((int) $this->activity->cmid, $copy->get_cmid());
            $this->assertSame((int) $this->course->id, $copy->get_courseid());
        }
    }

    /**
     * Card target of the test activity.
     *
     * @param int $cardindex
     * @return h5p_card_target
     */
    private function target(int $cardindex): h5p_card_target {
        return new h5p_card_target((int) $this->course->id, (int) $this->activity->cmid, $cardindex);
    }

    /**
     * Image job result holding a PNG of the given size.
     *
     * @param int $width
     * @param int $height
     * @return array
     */
    private function image_result(int $width, int $height): array {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();

        return ['image_base64' => base64_encode($bytes)];
    }

    /**
     * Stored package file of the test activity.
     *
     * @return \stored_file
     */
    private function package_file(): \stored_file {
        $context = \context_module::instance($this->activity->cmid);
        $files = get_file_storage()->get_area_files($context->id, 'mod_h5pactivity', 'package', 0, 'id', false);
        $this->assertCount(1, $files);
        return reset($files);
    }

    /**
     * Copy of the stored package on disk.
     *
     * @return string Path.
     */
    private function package_copy(): string {
        $path = make_request_directory() . '/copy.h5p';
        $this->package_file()->copy_content_to($path);
        return $path;
    }
}
