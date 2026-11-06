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
 * Tests for {@see \local_dixeo\service\image\poll\client_poll}.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service\image\poll;

use local_dixeo\dto\job_status;
use local_dixeo\external\service_factory;
use local_dixeo\repository\image\job_repository;
use local_dixeo\service\image\apply\registry as apply_registry;
use local_dixeo\service\image\content\location;
use local_dixeo\service\image\content_target;
use local_dixeo\service\job_service;
use local_dixeo\session_user_recording_apply_handler;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../fixtures/session_user_recording_apply_handler.php');

/**
 * A poll by another user applies the result for the job owner without switching the session user.
 *
 * @covers \local_dixeo\service\image\poll\client_poll
 */
final class client_poll_test extends \advanced_testcase {
    protected function tearDown(): void {
        service_factory::set_test_job_service(null);
        apply_registry::set_modal_handler(null);
        parent::tearDown();
    }

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
     * User B polls a completed job of user A: the file is A's, and the session stays B.
     */
    public function test_poll_by_another_user_applies_for_the_owner_without_switching_user(): void {
        global $USER;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $owner = $generator->create_user();
        $poller = $generator->create_user();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $file = get_file_storage()->create_file_from_string([
            'contextid' => \context_module::instance((int) $page->cmid)->id,
            'component' => 'mod_page',
            'filearea' => 'content',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'picture.jpg',
        ], 'original bytes');
        $location = location::from_stored_file($file);
        $target = content_target::from_location($location);
        job_repository::create_job($location, 'remote-1', (int) $owner->id);

        $result = self::png();
        $jobs = $this->createMock(job_service::class);
        $jobs->method('get_job_status')->with('remote-1')->willReturn(new job_status(
            jobid: 'remote-1',
            type: 'image_edit',
            status: job_status::STATUS_COMPLETED,
            progress: 100,
            createdat: time(),
            result: ['image_base64' => base64_encode($result)],
        ));
        service_factory::set_test_job_service($jobs);
        $handler = new session_user_recording_apply_handler();
        apply_registry::set_modal_handler($handler);

        $this->setUser($poller);
        $outcome = client_poll::poll_once('remote-1', $target, (int) $owner->id);

        $this->assertSame('completed', $outcome['status']);
        $this->assertSame([(int) $poller->id], $handler->sessionuserids);
        $this->assertSame([(int) $owner->id], $handler->ownerids);
        $replaced = $location->get_stored_file();
        $this->assertSame(sha1($result), $replaced->get_contenthash());
        $this->assertSame((int) $owner->id, (int) $replaced->get_userid());
        $this->assertSame('image/png', $replaced->get_mimetype());
        $this->assertSame((int) $poller->id, (int) $USER->id);
        $this->assertSame((int) $poller->id, (int) $_SESSION['USER']->id);
    }
}
