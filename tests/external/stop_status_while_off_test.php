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

namespace local_dixeo;

use local_dixeo\api\client;
use local_dixeo\external\cancel_job;
use local_dixeo\external\generate_module;
use local_dixeo\external\get_file_sync_status;
use local_dixeo\external\get_job_status;
use local_dixeo\external\get_module_types;
use local_dixeo\external\service_factory;
use local_dixeo\external\set_file_sync_enabled;
use local_dixeo\external\trigger_file_sync;
use local_dixeo\repository\job_repository;
use local_dixeo\service\file_sync_service;
use local_dixeo\service\job_service;

/**
 * Stop and status web services work while Dixeo is off; sending services do not.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\entry_effect
 * @covers     \local_dixeo\external\traits\capability_check
 * @covers     \local_dixeo\external\cancel_job
 * @covers     \local_dixeo\external\get_job_status
 * @covers     \local_dixeo\external\get_file_sync_status
 * @covers     \local_dixeo\external\set_file_sync_enabled
 * @covers     \local_dixeo\external\generate_module
 * @covers     \local_dixeo\external\trigger_file_sync
 * @covers     \local_dixeo\external\get_module_types
 */
final class stop_status_while_off_test extends \advanced_testcase {
    /** @var string Job id registered for the owner. */
    private const JOBID = '0b0f8a3c-1d2e-4f5a-9b6c-7d8e9f0a1b2c';

    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass Editing teacher who owns the job, without accepted terms. */
    private \stdClass $owner;

    /** @var \stdClass Another editing teacher of the course. */
    private \stdClass $other;

    /** @var \stdClass Student of the course. */
    private \stdClass $student;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->owner = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->other = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        (new job_repository())->register(
            self::JOBID,
            (int) $this->course->id,
            (int) $this->owner->id,
            'default',
            'module_generate'
        );
    }

    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * Only stop and status actions skip the switch; only sending actions need the terms.
     */
    public function test_entry_effect_truth_table(): void {
        $this->assertTrue(entry_effect::SEND->requires_enabled());
        $this->assertTrue(entry_effect::SEND->requires_terms());
        $this->assertTrue(entry_effect::FEATURE_READ->requires_enabled());
        $this->assertFalse(entry_effect::FEATURE_READ->requires_terms());
        $this->assertFalse(entry_effect::STOP_OR_STATUS->requires_enabled());
        $this->assertFalse(entry_effect::STOP_OR_STATUS->requires_terms());
    }

    /**
     * With the course off, the owner cancels and reads status; others are refused.
     */
    public function test_job_cancel_and_status_while_course_is_off(): void {
        $this->set_enabled(\context_course::instance($this->course->id), '0');

        $this->assert_job_owner_checks();
    }

    /**
     * With the site off, the owner cancels and reads status; others are refused.
     */
    public function test_job_cancel_and_status_while_site_is_off(): void {
        $this->set_enabled(\context_system::instance(), '0');

        $this->assert_job_owner_checks();
    }

    /**
     * File sync status and turning sync off work while the course is off.
     */
    public function test_file_sync_status_and_disable_while_off(): void {
        $courseid = (int) $this->course->id;
        $this->set_enabled(\context_course::instance($courseid), '0');
        $service = $this->createMock(file_sync_service::class);
        $service->method('get_live_status')->willReturn((object) ['enabled' => true, 'status' => 'syncing']);
        $service->method('list_status_files')->willReturn([]);
        $service->expects($this->once())->method('disable_sync')->with($courseid, (int) $this->owner->id, true);
        $service->method('get_status')->willReturn((object) ['status' => 'none']);
        service_factory::set_test_file_sync_service($service);
        $this->setUser($this->owner);

        $this->assertSame('syncing', get_file_sync_status::execute($courseid)['status']);
        $this->assertTrue(set_file_sync_enabled::execute($courseid, false, true)['success']);
    }

    /**
     * Sending services and the feature read still stop while the course is off.
     */
    public function test_sending_services_and_feature_read_refuse_while_off(): void {
        $courseid = (int) $this->course->id;
        $this->set_enabled(\context_course::instance($courseid), '0');
        $service = $this->createMock(file_sync_service::class);
        $service->expects($this->never())->method('enable_sync');
        $service->expects($this->never())->method('trigger_sync');
        service_factory::set_test_file_sync_service($service);
        $this->setUser($this->owner);

        $calls = [
            fn() => generate_module::execute('page', 'Photosynthesis', '', $courseid),
            fn() => trigger_file_sync::execute($courseid),
            fn() => set_file_sync_enabled::execute($courseid, true),
            fn() => get_module_types::execute($courseid),
        ];
        foreach ($calls as $call) {
            try {
                $call();
                $this->fail('Dixeo off must stop the request.');
            } catch (\moodle_exception $e) {
                $this->assertSame('error:dixeo_disabled', $e->errorcode);
                $this->assertSame('local_dixeo', $e->module);
            }
        }
    }

    /**
     * Owner cancels and reads status; another teacher and a student are refused.
     */
    private function assert_job_owner_checks(): void {
        $courseid = (int) $this->course->id;
        $client = $this->createMock(client::class);
        $client->expects($this->once())
            ->method('post')
            ->with('/v1/jobs/' . self::JOBID . '/cancel', [])
            ->willReturn(['status' => 'cancelled']);
        $client->expects($this->once())
            ->method('get')
            ->with('/v1/jobs/' . self::JOBID)
            ->willReturn([
                'id' => self::JOBID,
                'type' => 'module',
                'status' => 'processing',
                'progress' => 40,
                'createdAt' => '2026-11-06T10:00:00Z',
            ]);
        service_factory::set_test_job_service(new job_service($client, null, new job_repository()));
        $calls = [
            fn() => cancel_job::execute(self::JOBID, $courseid),
            fn() => get_job_status::execute(self::JOBID, $courseid),
        ];

        $this->setUser($this->owner);
        $this->assertTrue(cancel_job::execute(self::JOBID, $courseid)['success']);
        $this->assertSame('processing', get_job_status::execute(self::JOBID, $courseid)['status']);

        $this->setUser($this->other);
        foreach ($calls as $call) {
            try {
                $call();
                $this->fail('A job of another user must not be found.');
            } catch (\moodle_exception $e) {
                $this->assertSame('error:job_not_found', $e->errorcode);
            }
        }

        $this->setUser($this->student);
        foreach ($calls as $call) {
            try {
                $call();
                $this->fail('A student must not reach a job.');
            } catch (\required_capability_exception $e) {
                $this->assertSame('nopermissions', $e->errorcode);
            }
        }
    }

    /**
     * Store the Dixeo switch at a context.
     *
     * @param \context $context System or course context.
     * @param string $value '1' or '0'.
     */
    private function set_enabled(\context $context, string $value): void {
        service_factory::get_settings_config()->set('local_dixeo', 'enabled', $context, $value, false);
    }
}
