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
 * Tests for the file sync status web service.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\api\client;
use local_dixeo\external\get_file_sync_status;
use local_dixeo\external\service_factory;
use local_dixeo\service\file_sync_service;
use local_dixeo\settings\config;

/**
 * The status service returns per-file rows without the API error text.
 *
 * @covers \local_dixeo\external\get_file_sync_status
 */
final class get_file_sync_status_test extends \advanced_testcase {
    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * A teacher receives synced and problem files. The remote error sentence is not returned.
     */
    public function test_execute_returns_file_rows_without_the_api_message(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        (new config())->set('local_dixeo', 'enabled', \context_system::instance(), '1');
        (new config())->set('local_dixeo', 'enabled', $context, '1');

        $this->getDataGenerator()->create_module('resource', [
            'course' => $course->id,
            'defaultfilename' => 'notes.pdf',
        ]);

        $secret = 'provider said the vector store rejected this file';
        $mockclient = $this->createMock(client::class);
        $mockclient->method('is_configured')->willReturn(true);
        $mockclient->method('list_files')->willReturn([
            'hydra:member' => [
                [
                    'fileName' => 'notes.pdf',
                    'status' => 'uploaded',
                    'errorMessage' => $secret,
                ],
            ],
        ]);
        service_factory::set_test_file_sync_service(new file_sync_service(null, $mockclient));

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $result = get_file_sync_status::execute((int) $course->id);
        get_file_sync_status::clean_returnvalue(get_file_sync_status::execute_returns(), $result);

        $this->assertSame([
            ['filename' => 'notes.pdf', 'state' => 'problem', 'reason' => 'error'],
        ], $result['files']);
        $this->assertStringNotContainsString($secret, json_encode($result));
    }
}
