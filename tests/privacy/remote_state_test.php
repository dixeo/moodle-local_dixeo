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

namespace local_dixeo\privacy;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/dixeo/db/upgrade.php');

use local_dixeo\api\client;
use local_dixeo\api\exception\api_exception;
use local_dixeo\external\service_factory;

/**
 * Tests for the site-level record of data sent to Dixeo.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\privacy\remote_state
 */
final class remote_state_test extends \advanced_testcase {
    /**
     * Reset state between tests.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Clear injected services.
     */
    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * NEVER without a key or a transfer, UNREACHABLE once data was sent, READY with a key.
     */
    public function test_state_transitions(): void {
        service_factory::set_test_client(new client('https://api.dixeo.test', ''));
        $this->assertSame(remote_state::NEVER, remote_state::current());

        remote_state::record_transfer();
        $this->assertSame(remote_state::UNREACHABLE, remote_state::current());

        service_factory::set_test_client(new client('https://api.dixeo.test', 'test-key'));
        $this->assertSame(remote_state::READY, remote_state::current());
    }

    /**
     * Only the first transfer is written.
     */
    public function test_record_transfer_writes_once(): void {
        $this->assertFalse(remote_state::has_transferred());
        remote_state::record_transfer();
        $first = get_config('local_dixeo', remote_state::CONFIG_TRANSFERRED);
        $this->assertNotEmpty($first);

        set_config(remote_state::CONFIG_TRANSFERRED, 12345, 'local_dixeo');
        remote_state::record_transfer();
        $this->assertSame('12345', get_config('local_dixeo', remote_state::CONFIG_TRANSFERRED));
        $this->assertTrue(remote_state::has_transferred());
    }

    /**
     * The not-configured exception carries its error code.
     */
    public function test_not_configured_exception(): void {
        $e = remote_state::not_configured_exception();
        $this->assertInstanceOf(api_exception::class, $e);
        $this->assertSame(remote_state::ERROR_NOT_CONFIGURED, $e->get_error_code());
    }

    /**
     * A POST through the client records the transfer before the request is sent; a GET does not.
     */
    public function test_client_post_records_transfer_and_get_does_not(): void {
        $client = new client('https://api.dixeo.test', 'test-key');

        \curl::mock_response(json_encode(['ok' => true]));
        try {
            $client->get('/v1/status');
        } catch (api_exception $e) {
            // The mocked response has no HTTP status; only the transfer record matters here.
            $this->assertNotEmpty($e->get_error_code());
        }
        $this->assertFalse(remote_state::has_transferred());

        \curl::mock_response(json_encode(['ok' => true]));
        try {
            $client->post('/v1/things', ['a' => 1]);
        } catch (api_exception $e) {
            $this->assertNotEmpty($e->get_error_code());
        }
        $this->assertTrue(remote_state::has_transferred());
        $this->resetDebugging();
    }

    /**
     * The upgrade copies the tutor block's marker, or derives one from local rows or a key.
     */
    public function test_upgrade_migration(): void {
        global $DB;

        set_config('api_key', '', 'local_dixeo');
        local_dixeo_migrate_transfer_marker();
        $this->assertFalse(remote_state::has_transferred());

        set_config('hastransferred', 4242, 'block_dixeo_tutor');
        local_dixeo_migrate_transfer_marker();
        $this->assertSame('4242', get_config('local_dixeo', remote_state::CONFIG_TRANSFERRED));

        unset_config(remote_state::CONFIG_TRANSFERRED, 'local_dixeo');
        unset_config('hastransferred', 'block_dixeo_tutor');
        $DB->insert_record('local_dixeo_course_ai', (object) [
            'courseid' => 999,
            'enabled' => 1,
            'syncstatus' => 'none',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        local_dixeo_migrate_transfer_marker();
        $this->assertTrue(remote_state::has_transferred());
    }
}
