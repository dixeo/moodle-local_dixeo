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
use local_dixeo\dto\job_status;

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
     * Known API types return a localised string and omit the remote detail.
     */
    public function test_known_api_types_omit_remote_detail(): void {
        $this->resetAfterTest();
        set_debugging(DEBUG_NONE);

        $crafted = 'crafted-detail /internal/path token=abc';
        $cases = [
            'payment_required' => 'error:payment_required',
            'insufficient_credits' => 'error:payment_required',
            'validation_error' => 'error:validation_failed',
            'job_not_found' => 'error:job_not_found',
            'authentication' => 'error:authentication',
            'upstream_ai' => 'error:unavailable',
            'rate_limit_exceeded' => 'error:busy',
            'connection_error' => 'error:unavailable',
        ];

        foreach ($cases as $type => $stringkey) {
            $exception = api_exception::from_response([
                'type' => $type,
                'detail' => $crafted,
                'title' => $crafted,
            ], 400);
            $response = response_factory::from_api_exception($exception);
            $encoded = json_encode($response);

            $this->assertSame(get_string($stringkey, 'local_dixeo'), $response['errormessage'], $type);
            $this->assertIsString($encoded);
            $this->assertStringNotContainsString($crafted, $encoded, $type);
        }
    }

    /**
     * An unknown API type is logged and replaced by the generic string.
     */
    public function test_unknown_api_type_omits_remote_detail(): void {
        $this->resetAfterTest();
        set_debugging(DEBUG_DEVELOPER);

        $crafted = 'crafted-detail schema dump';
        $exception = api_exception::from_response([
            'type' => 'internal_diagnostics',
            'detail' => $crafted,
        ], 500);
        $response = response_factory::from_api_exception($exception);
        $encoded = json_encode($response);

        $this->assertSame(get_string('error:unexpected', 'local_dixeo'), $response['errormessage']);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString($crafted, $encoded);
        $this->assertDebuggingCalled();
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

    /**
     * Failed job status as returned by the API.
     *
     * @param string|null $type Error type, or null for a job without error.
     * @param string $detail Provider detail.
     * @return job_status
     */
    private function job_status(?string $type, string $detail = ''): job_status {
        $data = [
            'id' => '5f38d9aa-f40c-4992-9727-982f050ff9fd',
            'type' => 'module',
            'status' => $type === null ? 'completed' : 'failed',
            'createdAt' => '2026-01-01T00:00:00Z',
            'namespace' => 'tenant-namespace',
        ];
        if ($type !== null) {
            $data['error'] = ['type' => $type, 'detail' => $detail];
        }
        return job_status::from_array($data);
    }

    /**
     * A known job error code maps to its string, and the provider detail and namespace are dropped.
     */
    public function test_client_job_status_maps_known_code(): void {
        $this->resetAfterTest();
        set_debugging(DEBUG_NONE);
        $crafted = 'provider stack trace /srv/secret';

        $data = response_factory::client_job_status($this->job_status('rate_limited', $crafted));

        $this->assertArrayNotHasKey('namespace', $data);
        $this->assertSame('rate_limited', $data['error']['type']);
        $this->assertSame(get_string('error:busy', 'local_dixeo'), $data['error']['title']);
        $this->assertSame(get_string('error:busy', 'local_dixeo'), $data['error']['detail']);
        $this->assertSame(500, $data['error']['status']);
        $this->assertStringNotContainsString($crafted, json_encode($data));
    }

    /**
     * An unknown job error code maps to the fallback string of the given component.
     */
    public function test_client_job_status_maps_unknown_code_to_fallback(): void {
        $this->resetAfterTest();
        set_debugging(DEBUG_NONE);
        $crafted = 'internal provider message';

        $default = response_factory::client_job_status($this->job_status('internal_diagnostics', $crafted));
        $this->assertSame('api_error', $default['error']['type']);
        $this->assertSame(get_string('error:unexpected', 'local_dixeo'), $default['error']['detail']);
        $this->assertStringNotContainsString($crafted, json_encode($default));

        $custom = response_factory::client_job_status(
            $this->job_status('internal_diagnostics', $crafted),
            'error:job_not_found',
            'local_dixeo'
        );
        $this->assertSame(get_string('error:job_not_found', 'local_dixeo'), $custom['error']['title']);
    }

    /**
     * A job without error keeps its fields and loses only the namespace.
     */
    public function test_client_job_status_without_error(): void {
        $data = response_factory::client_job_status($this->job_status(null));

        $this->assertArrayNotHasKey('error', $data);
        $this->assertArrayNotHasKey('namespace', $data);
        $this->assertSame('completed', $data['status']);
    }

    /**
     * safe_message() uses the fallback component for an unknown API type.
     */
    public function test_safe_message_fallback_component(): void {
        $this->resetAfterTest();
        set_debugging(DEBUG_NONE);
        $exception = api_exception::from_response(['type' => 'internal_diagnostics', 'detail' => 'x'], 500);

        $this->assertSame(
            get_string('error:job_not_found', 'local_dixeo'),
            response_factory::safe_message($exception, 'error:job_not_found', 'local_dixeo')
        );
        $known = api_exception::from_response(['type' => 'rate_limit_exceeded', 'detail' => 'x'], 429);
        $this->assertSame(
            get_string('error:busy', 'local_dixeo'),
            response_factory::safe_message($known, 'nonexistent', 'block_unknown')
        );
    }
}
