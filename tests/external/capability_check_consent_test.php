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

use local_dixeo\external\cancel_job;
use local_dixeo\external\generate_module;
use local_dixeo\external\get_file_sync_status;
use local_dixeo\external\get_job_status;
use local_dixeo\external\get_module_types;
use local_dixeo\external\service_factory;
use local_dixeo\external\set_file_sync_enabled;
use local_dixeo\external\trigger_file_sync;
use local_dixeo\service\file_sync_service;
use local_dixeo\service\job_service;
use local_dixeo\service\module_types_service;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/capability_check_fixture.php');

/**
 * Terms of Use gate in the capability_check trait and in the web services that use it.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\external\traits\capability_check
 * @covers     \local_dixeo\external\generate_module
 * @covers     \local_dixeo\external\trigger_file_sync
 * @covers     \local_dixeo\external\set_file_sync_enabled
 * @covers     \local_dixeo\external\cancel_job
 * @covers     \local_dixeo\external\get_job_status
 * @covers     \local_dixeo\external\get_file_sync_status
 * @covers     \local_dixeo\external\get_module_types
 */
final class capability_check_consent_test extends \advanced_testcase {
    /** @var string Job id used by the job calls. */
    private const JOBID = '0b0f8a3c-1d2e-4f5a-9b6c-7d8e9f0a1b2c';

    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass Editing teacher of the course, without accepted terms. */
    private \stdClass $teacher;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
    }

    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * Both trait methods refuse a capable teacher and the admin until the terms are accepted.
     */
    public function test_trait_refuses_without_accepted_terms(): void {
        $courseid = (int) $this->course->id;

        foreach ([$this->teacher, get_admin()] as $user) {
            $this->setUser($user);
            $this->assert_consent_required(fn() => capability_check_fixture::course($courseid));
            $this->assert_consent_required(fn() => capability_check_fixture::course($courseid, true));
            $this->assert_consent_required(fn() => capability_check_fixture::sync($courseid));
        }
    }

    /**
     * Both trait methods pass after acceptance.
     */
    public function test_trait_passes_after_acceptance(): void {
        $courseid = (int) $this->course->id;
        $this->accept_terms($this->teacher);
        $this->setUser($this->teacher);

        $this->assertSame($courseid, (int) capability_check_fixture::course($courseid, true)->instanceid);
        $this->assertSame($courseid, (int) capability_check_fixture::sync($courseid)->instanceid);
    }

    /**
     * Feature reads and stop or status actions do not need the current terms.
     */
    public function test_trait_passes_when_consent_is_not_required(): void {
        $courseid = (int) $this->course->id;
        $this->setUser($this->teacher);

        foreach ([entry_effect::FEATURE_READ, entry_effect::STOP_OR_STATUS] as $effect) {
            $this->assertSame($courseid, (int) capability_check_fixture::course($courseid, true, $effect)->instanceid);
            $this->assertSame($courseid, (int) capability_check_fixture::sync($courseid, $effect)->instanceid);
        }
    }

    /**
     * Dixeo off for the course reports error:dixeo_disabled, not the terms.
     */
    public function test_dixeo_off_is_reported_before_consent(): void {
        $courseid = (int) $this->course->id;
        $this->setAdminUser();
        service_factory::get_settings_config()->set('local_dixeo', 'enabled', \context_course::instance($courseid), '0');
        $this->setUser($this->teacher);

        $calls = [
            fn() => capability_check_fixture::course($courseid),
            fn() => capability_check_fixture::sync($courseid),
        ];
        foreach ($calls as $call) {
            try {
                $call();
                $this->fail('Dixeo off must stop the request.');
            } catch (\moodle_exception $e) {
                $this->assertSame('error:dixeo_disabled', $e->errorcode);
            }
        }
    }

    /**
     * Sending web services refuse without accepted terms.
     */
    public function test_sending_services_refuse_without_accepted_terms(): void {
        $courseid = (int) $this->course->id;
        $this->setUser($this->teacher);

        $this->assert_consent_required(fn() => generate_module::execute('page', 'Photosynthesis', '', $courseid));
        $this->assert_consent_required(fn() => trigger_file_sync::execute($courseid));
        $this->assert_consent_required(fn() => set_file_sync_enabled::execute($courseid, true));
    }

    /**
     * Turning file sync off works without accepted terms.
     */
    public function test_disabling_file_sync_does_not_require_terms(): void {
        $courseid = (int) $this->course->id;
        $service = $this->createMock(file_sync_service::class);
        $service->expects($this->once())->method('disable_sync')->with($courseid, (int) $this->teacher->id, false);
        $service->method('get_status')->willReturn((object) ['status' => 'none']);
        service_factory::set_test_file_sync_service($service);
        $this->setUser($this->teacher);

        $result = set_file_sync_enabled::execute($courseid, false);

        $this->assertTrue($result['success']);
    }

    /**
     * Cancelling and reading job status work without accepted terms.
     */
    public function test_job_cancel_and_status_do_not_require_terms(): void {
        $courseid = (int) $this->course->id;
        $service = $this->createMock(job_service::class);
        $service->expects($this->once())->method('cancel_job')->willReturn(['status' => 'cancelled']);
        $service->expects($this->once())->method('get_job_status')
            ->willThrowException(new \RuntimeException('status reached'));
        service_factory::set_test_job_service($service);
        $this->setUser($this->teacher);

        $this->assertTrue(cancel_job::execute(self::JOBID, $courseid)['success']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('status reached');
        get_job_status::execute(self::JOBID, $courseid);
    }

    /**
     * Reading file sync status works without accepted terms.
     */
    public function test_file_sync_status_does_not_require_terms(): void {
        $courseid = (int) $this->course->id;
        $service = $this->createMock(file_sync_service::class);
        $service->method('get_live_status')->willReturn((object) ['enabled' => false, 'status' => 'none']);
        $service->method('list_status_files')->willReturn([]);
        service_factory::set_test_file_sync_service($service);
        $this->setUser($this->teacher);

        $this->assertSame('none', get_file_sync_status::execute($courseid)['status']);
    }

    /**
     * Listing module types for a course works without accepted terms.
     */
    public function test_module_types_do_not_require_terms(): void {
        $stub = $this->getMockBuilder(module_types_service::class)
            ->onlyMethods(['get_module_types_cached'])
            ->getMock();
        $stub->method('get_module_types_cached')->willReturn([
            ['type' => 'page', 'label' => 'Page', 'description' => '', 'category' => 'content', 'component' => 'mod_page'],
        ]);
        service_factory::set_test_module_types_service($stub);
        $this->setUser($this->teacher);

        $response = get_module_types::execute((int) $this->course->id);

        $this->assertNotEmpty($response['types']);
    }

    /**
     * Record acceptance of the current terms for one user.
     *
     * @param \stdClass $user User.
     */
    private function accept_terms(\stdClass $user): void {
        $this->getDataGenerator()->get_plugin_generator('local_dixeo')->create_terms_acceptance(['userid' => $user->id]);
    }

    /**
     * Assert that a call stops with error:consentrequired from local_dixeo.
     *
     * @param callable $call Call to run.
     */
    private function assert_consent_required(callable $call): void {
        try {
            $call();
            $this->fail('Unaccepted terms must stop the request.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:consentrequired', $e->errorcode);
            $this->assertSame('local_dixeo', $e->module);
        }
    }
}
