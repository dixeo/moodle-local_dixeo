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

/**
 * API client that returns queued responses and records calls.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class synthesis_fake_client extends client {
    /** @var array Recorded calls. */
    public array $calls = [];

    /** @var array Responses consumed in order. */
    private array $responses;

    /**
     * Queue the responses that post and get will return.
     *
     * @param array $responses Responses for post and get, in call order.
     */
    public function __construct(array $responses) {
        parent::__construct('https://api.dixeo.test', 'test-key');
        $this->responses = $responses;
    }

    /**
     * Record a post and return the next queued response.
     *
     * @param string $endpoint Endpoint.
     * @param array $data Payload.
     * @param string|null $idempotencykey Idempotency key.
     * @return array
     */
    public function post(string $endpoint, array $data, ?string $idempotencykey = null): array {
        $this->calls[] = ['method' => 'post', 'endpoint' => $endpoint, 'data' => $data];
        return $this->shift();
    }

    /**
     * Record a get and return the next queued response.
     *
     * @param string $endpoint Endpoint.
     * @param array $queryparams Query.
     * @return array
     */
    public function get(string $endpoint, array $queryparams = []): array {
        $this->calls[] = ['method' => 'get', 'endpoint' => $endpoint, 'data' => $queryparams];
        return $this->shift();
    }

    /**
     * Next queued response.
     *
     * @return array
     */
    private function shift(): array {
        $response = array_shift($this->responses);
        if (!is_array($response)) {
            throw new \runtime_exception('synthesis_fake_client has no response left');
        }
        return $response;
    }
}
