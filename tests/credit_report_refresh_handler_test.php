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

namespace local_dixeo;

use core\output\notification;
use local_dixeo\dto\credit_sync_result;
use local_dixeo\event\credit_usage_refreshed;
use local_dixeo\output\credit_report_refresh_handler;
use local_dixeo\repository\credit_usage_repository;
use local_dixeo\service\credit_usage_sync_service;

/**
 * Tests for the "Refresh now" request of the credit report.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_dixeo\output\credit_report_refresh_handler
 * @covers \local_dixeo\event\credit_usage_refreshed
 */
final class credit_report_refresh_handler_test extends \advanced_testcase {
    /**
     * Reset the simulated request.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $_GET = [];
        $_POST = [];
    }

    /**
     * Clear the simulated request.
     */
    public function tearDown(): void {
        $_GET = [];
        $_POST = [];
        unset($_SERVER['REQUEST_METHOD']);
        parent::tearDown();
    }

    /**
     * Simulate a POST request.
     *
     * @param array $fields Posted fields.
     */
    private function post(array $fields): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $fields;
    }

    /**
     * Sync service mock returning a status, expecting a number of refresh calls.
     *
     * @param int $calls Expected refresh_now() calls.
     * @param string $status Returned status.
     * @param int $lastsuccess Returned last success time.
     * @return credit_usage_sync_service
     */
    private function sync_mock(int $calls, string $status = credit_sync_result::STATUS_SYNCED, int $lastsuccess = 0) {
        $sync = $this->createMock(credit_usage_sync_service::class);
        $sync->expects($this->exactly($calls))
            ->method('refresh_now')
            ->willReturn(new credit_sync_result($status, 0, $lastsuccess));
        return $sync;
    }

    /**
     * A user without the report capability is refused before any sync.
     */
    public function test_user_without_capability_is_refused(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $this->post(['sesskey' => sesskey()]);

        $this->expectException(\required_capability_exception::class);
        (new credit_report_refresh_handler($this->sync_mock(0)))->handle();
    }

    /**
     * A POST without a valid session key is refused before any sync.
     */
    public function test_post_without_valid_sesskey_is_refused(): void {
        $this->setAdminUser();
        $this->post(['sesskey' => 'not-the-key']);

        try {
            (new credit_report_refresh_handler($this->sync_mock(0)))->handle();
            $this->fail('A POST without a valid session key must be refused.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('invalidsesskey', $exception->errorcode);
        }
    }

    /**
     * A GET request never syncs, even with a valid session key.
     */
    public function test_get_request_is_refused(): void {
        $this->setAdminUser();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['sesskey' => sesskey()];

        try {
            (new credit_report_refresh_handler($this->sync_mock(0)))->handle();
            $this->fail('A GET request must be refused.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('invalidrequest', $exception->errorcode);
        }
    }

    /**
     * A valid POST syncs, fires the event and returns to the credit report with its validated parameters and filters.
     */
    public function test_valid_post_syncs_and_redirects_to_the_report(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/dixeo:viewusage', CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, $user->id, \context_system::instance()->id);
        $this->setUser($user);
        $course = $this->getDataGenerator()->create_course();
        $DB->insert_record(credit_usage_repository::TABLE, (object) [
            'transactionid' => 'tx-refresh-filter',
            'type' => 'deduction',
            'amount' => -1,
            'credits' => 1,
            'courseid' => (int) $course->id,
            'timecreated' => time(),
            'timesynced' => time(),
        ]);
        $this->post([
            'sesskey' => sesskey(),
            'view' => 'month',
            'perpage' => '100000',
            'component' => ['block_dixeo_tutor'],
            'courseid' => [(string) $course->id],
            'returnurl' => 'https://attacker.example/phish',
        ]);

        $sink = $this->redirectEvents();
        [$url, $message, $type] = (new credit_report_refresh_handler($this->sync_mock(1)))->handle();
        $events = $sink->get_events();
        $sink->close();

        $this->assertInstanceOf(\moodle_url::class, $url);
        $this->assertTrue($url->compare(new \moodle_url('/local/dixeo/credit_report.php'), URL_MATCH_BASE));
        $this->assertSame([
            'view' => 'month',
            'perpage' => '100',
            'component[0]' => 'block_dixeo_tutor',
            'courseid[0]' => (string) $course->id,
        ], $url->params());
        $this->assertStringNotContainsString('attacker', $url->out(false));
        $this->assertSame(get_string('credit_report_sync_done', 'local_dixeo'), $message);
        $this->assertSame(notification::NOTIFY_SUCCESS, $type);

        $refreshed = array_values(array_filter($events, static fn($event) => $event instanceof credit_usage_refreshed));
        $this->assertCount(1, $refreshed);
        $this->assertSame(['status' => credit_sync_result::STATUS_SYNCED], $refreshed[0]->other);
        $this->assertSame('u', $refreshed[0]->crud);
    }

    /**
     * Each sync outcome maps to a fixed notice.
     */
    public function test_notices_match_the_sync_status(): void {
        $this->setAdminUser();
        $date = userdate(1800000000, get_string('strftimedatetime', 'langconfig'));
        $cases = [
            [credit_sync_result::STATUS_THROTTLED, 0, get_string('credit_report_sync_throttled', 'local_dixeo'),
                notification::NOTIFY_INFO],
            [credit_sync_result::STATUS_BUSY, 0, get_string('credit_report_sync_busy', 'local_dixeo'),
                notification::NOTIFY_INFO],
            [credit_sync_result::STATUS_FAILED, 1800000000, get_string('credit_report_sync_failed', 'local_dixeo', $date),
                notification::NOTIFY_WARNING],
            [credit_sync_result::STATUS_FAILED, 0, get_string('credit_report_sync_failed_never', 'local_dixeo'),
                notification::NOTIFY_WARNING],
            [credit_sync_result::STATUS_NOT_CONFIGURED, 0, '', notification::NOTIFY_INFO],
        ];

        foreach ($cases as [$status, $lastsuccess, $expectedmessage, $expectedtype]) {
            $this->post(['sesskey' => sesskey()]);
            $sink = $this->redirectEvents();
            [, $message, $type] = (new credit_report_refresh_handler($this->sync_mock(1, $status, $lastsuccess)))->handle();
            $this->assertSame([], $sink->get_events(), $status);
            $sink->close();
            $this->assertSame($expectedmessage, $message, $status);
            $this->assertSame($expectedtype, $type, $status);
        }
    }

    /**
     * The credit report strings exist and are translated in every language pack.
     */
    public function test_credit_strings_exist_in_every_language(): void {
        $keys = [
            'credit_report_last_synced',
            'credit_report_never_synced',
            'credit_report_refresh',
            'credit_report_sync_busy',
            'credit_report_sync_done',
            'credit_report_sync_failed',
            'credit_report_sync_failed_never',
            'credit_report_sync_throttled',
            'event_credit_usage_refreshed',
            'task_sync_credit_usage',
        ];
        $english = $this->plugin_strings('en');
        foreach (['de', 'es', 'fr', 'it', 'pt'] as $lang) {
            $strings = $this->plugin_strings($lang);
            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $strings, "{$lang}: {$key}");
                $this->assertNotSame($english[$key], $strings[$key], "{$lang}: {$key} is not translated");
            }
        }
    }

    /**
     * Load one language file without the English fallback.
     *
     * @param string $lang Language code.
     * @return array
     */
    private function plugin_strings(string $lang): array {
        $string = [];
        include(__DIR__ . "/../lang/{$lang}/local_dixeo.php");
        return $string;
    }
}
