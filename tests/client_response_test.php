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
 * Tests for API response parsing and error mapping.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\api\client;
use local_dixeo\api\exception\api_exception;
use local_dixeo\api\exception\rate_limit_exception;

/**
 * API response parsing tests.
 *
 * @covers \local_dixeo\api\client
 * @covers \local_dixeo\api\exception\api_exception
 */
final class client_response_test extends \advanced_testcase {
    /**
     * Parse a raw response through the client.
     *
     * @param string $body Raw response body.
     * @param int $httpcode HTTP status code.
     * @return array
     */
    private function parse(string $body, int $httpcode): array {
        $client = new class ('https://api.dixeo.com', 'test-key') extends client {
            /**
             * Expose protected parsing for unit tests.
             *
             * @param string $body Raw response body.
             * @param array $info Curl info.
             * @return array
             */
            public function expose_parse_response(string $body, array $info): array {
                return $this->parse_response($body, $info);
            }
        };
        return $client->expose_parse_response($body, ['http_code' => $httpcode]);
    }

    /**
     * Assert that parsing a response throws the expected exception.
     *
     * @param string $body Raw response body.
     * @param int $httpcode HTTP status code.
     * @return api_exception
     */
    private function parse_failure(string $body, int $httpcode): api_exception {
        try {
            $this->parse($body, $httpcode);
        } catch (api_exception $e) {
            $this->resetDebugging();
            return $e;
        }
        $this->fail('Expected an api_exception');
    }

    public function test_gateway_rate_limit_without_json_body_is_a_rate_limit(): void {
        $e = $this->parse_failure('Too Many Requests', 429);

        $this->assertInstanceOf(rate_limit_exception::class, $e);
        $this->assertSame(429, $e->get_http_status());
        $this->assertStringContainsString(get_string('error:rate_limit', 'local_dixeo'), $e->getMessage());
    }

    public function test_api_rate_limit_problem_is_a_rate_limit(): void {
        $body = json_encode([
            'type' => 'rate_limit_exceeded',
            'title' => 'Too Many Requests',
            'status' => 429,
            'detail' => 'Rate limit exceeded.',
            'retryAfter' => 1790000000,
        ]);

        $e = $this->parse_failure($body, 429);

        $this->assertInstanceOf(rate_limit_exception::class, $e);
        $this->assertSame(1790000000, $e->get_retry_after());
    }

    public function test_error_without_json_body_reports_http_status(): void {
        $e = $this->parse_failure('Bad Gateway', 502);

        $this->assertSame('unknown_error', $e->get_error_type());
        $this->assertStringContainsString('HTTP 502', $e->getMessage());
    }

    public function test_success_without_json_body_is_an_invalid_response(): void {
        $e = $this->parse_failure('<html></html>', 200);

        $this->assertSame('invalid_response', $e->get_error_type());
    }

    public function test_success_unwraps_data(): void {
        $this->assertSame(['credits' => 5], $this->parse('{"data":{"credits":5}}', 200));
        $this->resetDebugging();
    }
}
