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
 * Tests for {@see \local_dixeo\service\image\content\in_place_writer}.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service\image\content;

/**
 * In-place replacement uses the owner's draft area, whoever the session user is.
 *
 * @covers \local_dixeo\service\image\content\in_place_writer
 */
final class in_place_writer_test extends \advanced_testcase {
    /**
     * PNG bytes from the core file storage fixtures.
     *
     * @return string
     */
    private static function png(): string {
        global $CFG;
        return (string) file_get_contents($CFG->dirroot . '/lib/filestorage/tests/fixtures/testimage.png');
    }

    /**
     * Course file used as the replacement target.
     *
     * @return \stored_file
     */
    private function create_target(): \stored_file {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        return get_file_storage()->create_file_from_string([
            'contextid' => \context_module::instance((int) $page->cmid)->id,
            'component' => 'mod_page',
            'filearea' => 'content',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'picture.jpg',
        ], 'original bytes');
    }

    /**
     * The guest session can replace a file for an owner; the temporary draft file is gone afterwards.
     */
    public function test_replace_content_as_guest_uses_and_clears_the_owner_draft_area(): void {
        global $DB;

        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $file = $this->create_target();
        $this->setGuestUser();

        in_place_writer::replace_content($file, self::png(), (int) $owner->id);

        $replaced = get_file_storage()->get_file_by_id($file->get_id());
        $this->assertSame(sha1(self::png()), $replaced->get_contenthash());
        $this->assertSame('image/png', $replaced->get_mimetype());
        $this->assertSame((int) $owner->id, (int) $replaced->get_userid());
        $ownerdraft = \context_user::instance((int) $owner->id)->id;
        $this->assertSame(0, $DB->count_records_select(
            'files',
            "contextid = :contextid AND component = 'user' AND filearea = 'draft' AND filename <> '.'",
            ['contextid' => $ownerdraft]
        ));
        $guestdraft = \context_user::instance((int) guest_user()->id)->id;
        $this->assertSame(0, $DB->count_records('files', ['contextid' => $guestdraft, 'filearea' => 'draft']));
    }

    /**
     * An explicit MIME type is stored as given.
     */
    public function test_replace_content_stores_the_given_mimetype(): void {
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $file = $this->create_target();

        in_place_writer::replace_content($file, self::png(), (int) $owner->id, 'image/webp');

        $this->assertSame('image/webp', get_file_storage()->get_file_by_id($file->get_id())->get_mimetype());
    }
}
