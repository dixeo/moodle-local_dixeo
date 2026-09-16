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

namespace local_dixeo\external;

use local_dixeo\api\exception\api_exception;
use local_dixeo\dsl\dsl_exception;

/**
 * Unit tests for the client-facing error messages built by response_factory.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\external\response_factory
 */
final class response_factory_test extends \advanced_testcase {
    /**
     * Business messages raised by the API reach the user unchanged.
     */
    public function test_api_exception_message_is_kept(): void {
        $this->resetAfterTest();
        set_debugging(DEBUG_NONE);

        $exception = new api_exception('payment_required', 'Insufficient credits. Please add credits to continue.', 402);

        $this->assertSame(
            get_string('api_error', 'local_dixeo', 'Insufficient credits. Please add credits to continue.'),
            response_factory::safe_message($exception)
        );
        $this->assertStringContainsString('Insufficient credits', response_factory::safe_message($exception));
    }

    /**
     * Internal failures are replaced by a localised message outside developer debugging.
     */
    public function test_internal_failure_is_replaced_by_the_generic_message(): void {
        $this->resetAfterTest();
        set_debugging(DEBUG_NONE);

        $message = response_factory::safe_message(
            new \RuntimeException('SELECT * FROM mdl_local_dixeo_job failed on db-01')
        );

        $this->assertSame(get_string('error:unexpected', 'local_dixeo'), $message);
        $this->assertStringNotContainsString('mdl_local_dixeo_job', $message);
        $this->assertStringNotContainsString('db-01', $message);
    }

    /**
     * DSL failures describe course and instance ids, which stay on the server.
     */
    public function test_dsl_exception_detail_is_withheld(): void {
        $this->resetAfterTest();
        set_debugging(DEBUG_NONE);

        $exception = new dsl_exception(
            'glossary instance 42 does not belong to course 7',
            'action_validation'
        );

        $message = response_factory::safe_message($exception);

        $this->assertSame(get_string('error:unexpected', 'local_dixeo'), $message);
        $this->assertArrayHasKey(
            'errormessage',
            response_factory::from_exception($exception)
        );
        $this->assertSame(
            get_string('error:unexpected', 'local_dixeo'),
            response_factory::from_exception($exception)['errormessage']
        );
    }

    /**
     * Developers still get the detail, and it is always logged.
     */
    public function test_developer_debugging_returns_the_detail(): void {
        $this->resetAfterTest();
        set_debugging(DEBUG_DEVELOPER);

        $message = response_factory::safe_message(new \RuntimeException('connection refused'));

        $this->assertStringContainsString('connection refused', $message);
        $this->assertDebuggingCalled();
    }
}
