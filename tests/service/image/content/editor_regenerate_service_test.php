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

use local_dixeo\memory_editor_result_store;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../fixtures/memory_editor_result_store.php');

/**
 * A completed edit result is applied once per editor session and job.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\service\image\content\editor_regenerate_service
 */
final class editor_regenerate_service_test extends \advanced_testcase {
    /** @var string API content with two new images. */
    private const CONTENT = '<p>Body</p>[img-gen prompt="A volcano"][img-gen prompt="A glacier"]';

    /** @var editor_image_context Editor image context of a page. */
    private editor_image_context $ctx;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('image_generation_enabled', '1', 'local_dixeo');
        set_config('image_generation_content_mode', 'generate', 'local_dixeo');
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $this->ctx = new editor_image_context(
            \context_module::instance($page->cmid)->id,
            (int) $course->id,
            (int) $page->cmid,
            'page',
            'mod_page',
            'content',
            0,
            'content',
            'page',
            (int) $page->id,
            31
        );
    }

    /**
     * A second application of the same job returns the stored result and queues nothing more.
     */
    public function test_same_job_is_decoded_once(): void {
        global $DB;

        $store = new memory_editor_result_store();
        $userid = (int) get_admin()->id;

        $first = editor_regenerate_service::apply_api_content_once('job-a', self::CONTENT, $this->ctx, $userid, $store);
        $second = editor_regenerate_service::apply_api_content_once('job-a', self::CONTENT, $this->ctx, $userid, $store);

        $this->assertTrue($first->firstapplication);
        $this->assertFalse($second->firstapplication);
        $this->assertSame($first->result, $second->result);
        $this->assertCount(2, $first->result->newplaceholderids);
        $this->assertSame(1, $store->stores);
        $this->assertSame(2, $DB->count_records('local_dixeo_image_job'));
        $this->assertSame(2, $this->count_draft_stubs());
    }

    /**
     * Another job on the same session decodes again and replaces the stored result.
     */
    public function test_different_job_decodes_again(): void {
        $store = new memory_editor_result_store();
        $userid = (int) get_admin()->id;

        $first = editor_regenerate_service::apply_api_content_once('job-a', self::CONTENT, $this->ctx, $userid, $store);
        $other = editor_regenerate_service::apply_api_content_once('job-b', '<p>Other</p>', $this->ctx, $userid, $store);

        $this->assertTrue($first->firstapplication);
        $this->assertTrue($other->firstapplication);
        $this->assertSame(2, $store->stores);
        $this->assertSame('job-b', $store->results[$this->ctx->sessionid]['jobid']);
        $this->assertNull($store->get_applied_result($this->ctx->sessionid, 'job-a'));
    }

    /**
     * A held session lock makes the call report that the result is not applied yet.
     */
    public function test_busy_lock_returns_null(): void {
        global $CFG;

        // Database row locks are not re-entrant within one connection, unlike the default MySQL named locks.
        $CFG->lock_factory = '\\core\\lock\\db_record_lock_factory';
        $factory = \core\lock\lock_config::get_lock_factory('local_dixeo_editor_result');
        $lock = $factory->get_lock('session_' . $this->ctx->sessionid, 0);
        $this->assertNotFalse($lock);
        $store = new memory_editor_result_store();

        try {
            $result = editor_regenerate_service::apply_api_content_once(
                'job-a',
                self::CONTENT,
                $this->ctx,
                (int) get_admin()->id,
                $store
            );
        } finally {
            $lock->release();
        }

        $this->assertNull($result);
        $this->assertSame(0, $store->stores);
    }

    /**
     * Number of generated stub files in the session draft area.
     *
     * @return int
     */
    private function count_draft_stubs(): int {
        $files = get_file_storage()->get_area_files(
            $this->ctx->contextid,
            editor_draft_fileareas::COMPONENT,
            editor_draft_fileareas::for_modname('page'),
            $this->ctx->sessionid,
            'id',
            false
        );
        return count(array_filter($files, fn(\stored_file $f) => file_service::is_generated_stub_filename($f->get_filename())));
    }
}
