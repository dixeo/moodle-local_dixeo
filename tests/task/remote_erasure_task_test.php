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

namespace local_dixeo\task;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/dixeo/tests/fixtures/remote_erasure_task_fixture.php');

use local_dixeo\api\client;
use local_dixeo\api\exception\api_exception;
use local_dixeo\event\privacy_request_failed;
use local_dixeo\external\service_factory;
use local_dixeo\privacy\remote_state;
use local_dixeo\remote_erasure_task_fixture;

/**
 * Tests for the shared remote erasure task: retry budget, missing key and last-attempt alert.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\task\remote_erasure_task
 * @covers     \local_dixeo\privacy\failure_reporter
 */
final class remote_erasure_task_test extends \advanced_testcase {
    /**
     * Reset state between tests.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        remote_erasure_task_fixture::$runs = 0;
    }

    /**
     * Clear injected services.
     */
    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * Configure or remove the API key through an injected client.
     *
     * @param bool $configured Whether the client has a key.
     */
    private function set_client(bool $configured): void {
        service_factory::set_test_client(new client('https://api.dixeo.test', $configured ? 'test-key' : ''));
    }

    /**
     * Load the single queued fixture task.
     *
     * @return remote_erasure_task_fixture
     */
    private function queued_task(): remote_erasure_task_fixture {
        $tasks = \core\task\manager::get_adhoc_tasks(remote_erasure_task_fixture::class);
        $this->assertCount(1, $tasks);
        return reset($tasks);
    }

    /**
     * Queueing raises the retry budget, and nothing is queued on a site that never sent data.
     */
    public function test_queue_sets_attempts_and_skips_never(): void {
        $this->set_client(false);
        remote_erasure_task_fixture::queue(0, false);
        $this->assertCount(0, \core\task\manager::get_adhoc_tasks(remote_erasure_task_fixture::class));

        $this->set_client(true);
        remote_erasure_task_fixture::queue(0, false);
        remote_erasure_task_fixture::queue(0, false);
        $this->assertSame(remote_erasure_task::RETRY_ATTEMPTS, $this->queued_task()->get_attempts_available());
    }

    /**
     * A missing key throws, so the erasure stays queued until the key is back.
     */
    public function test_missing_key_throws(): void {
        set_config(remote_state::CONFIG_TRANSFERRED, time(), 'local_dixeo');
        $this->set_client(false);
        $task = new remote_erasure_task_fixture();
        $task->set_custom_data((object) ['userid' => 0, 'fail' => false]);

        try {
            $task->execute();
            $this->fail('A missing key must throw.');
        } catch (api_exception $e) {
            $this->assertSame(remote_state::ERROR_NOT_CONFIGURED, $e->get_error_code());
        }
        $this->assertSame(0, remote_erasure_task_fixture::$runs);
    }

    /**
     * Earlier attempts rethrow without any message or event.
     */
    public function test_earlier_attempt_sends_nothing(): void {
        $this->set_client(true);
        $task = new remote_erasure_task_fixture();
        $task->set_custom_data((object) ['userid' => 0, 'fail' => true]);
        $task->set_attempts_available(5);

        $sink = $this->redirectMessages();
        $events = $this->redirectEvents();
        try {
            $task->execute();
            $this->fail('A failed erasure must throw.');
        } catch (api_exception $e) {
            $this->assertSame('service_unavailable', $e->get_error_code());
        }
        $this->assertSame(0, $sink->count());
        $this->assertCount(0, array_filter(
            $events->get_events(),
            static fn($event): bool => $event instanceof privacy_request_failed
        ));
    }

    /**
     * The last attempt messages every admin once and triggers one event, then rethrows.
     */
    public function test_last_attempt_reports_failure(): void {
        $this->set_client(true);
        $user = $this->getDataGenerator()->create_user();
        $secondadmin = $this->getDataGenerator()->create_user();
        set_config('siteadmins', get_config('core', 'siteadmins') . ',' . $secondadmin->id);

        $task = new remote_erasure_task_fixture();
        $task->set_custom_data((object) ['userid' => (int) $user->id, 'fail' => true]);
        $task->set_attempts_available(1);

        $sink = $this->redirectMessages();
        $events = $this->redirectEvents();
        try {
            $task->execute();
            $this->fail('A failed erasure must throw.');
        } catch (api_exception $e) {
            $this->assertSame('service_unavailable', $e->get_error_code());
        }

        $messages = $sink->get_messages();
        $this->assertCount(2, $messages);
        foreach ($messages as $message) {
            $this->assertSame('local_dixeo', $message->component);
            $this->assertSame('privacyfailure', $message->eventtype);
            $this->assertStringContainsString(fullname($user), $message->fullmessage);
        }

        $failures = array_values(array_filter(
            $events->get_events(),
            static fn($event): bool => $event instanceof privacy_request_failed
        ));
        $this->assertCount(1, $failures);
        $this->assertSame((int) $user->id, (int) $failures[0]->relateduserid);
        $this->assertSame('local_dixeo', $failures[0]->other['component']);
        $this->assertSame('service_unavailable', $failures[0]->other['errorcode']);
    }

    /**
     * Erasure runs whatever the Dixeo enable switch says.
     */
    public function test_runs_while_dixeo_disabled(): void {
        $this->setAdminUser();
        service_factory::get_settings_config()->set('local_dixeo', 'enabled', \context_system::instance(), '0');
        $this->assertFalse(service_factory::get_settings_config()->enabled_for(\context_system::instance()));
        $this->set_client(true);
        $task = new remote_erasure_task_fixture();
        $task->set_custom_data((object) ['userid' => 0, 'fail' => false]);

        $task->execute();

        $this->assertSame(1, remote_erasure_task_fixture::$runs);
    }
}
