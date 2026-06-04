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
 * Service for AI tutor operations.
 *
 * Handles message submission and conversation retrieval for the tutor block.
 * All API communication goes through job_service and client from local_dixeo.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service;

use local_dixeo\api\client;
use local_dixeo\api\exception\api_exception;
use local_dixeo\context\context_builder_factory;
use local_dixeo\dto\operation_result;
use local_dixeo\external\service_factory;

/**
 * Service for tutor message operations.
 */
class tutor_service {
    /** @var job_service The job service for submitting messages. */
    private job_service $jobservice;

    /** @var client The API client for direct GET requests. */
    private client $client;

    /** @var string|null The namespace for API requests. */
    private ?string $namespace;

    /**
     * Constructor.
     *
     * @param job_service|null $jobservice Optional job service instance.
     * @param client|null $client Optional API client instance.
     */
    public function __construct(?job_service $jobservice = null, ?client $client = null) {
        $this->jobservice = $jobservice ?? new job_service();
        $this->client = $client ?? $this->jobservice->get_client();
        global $CFG;
        require_once($CFG->dirroot . '/local/dixeo/lib.php');
        $this->namespace = \local_dixeo_get_configured_namespace();
    }

    /**
     * Submit a tutor message.
     *
     * Builds instructions, constructs payload, and submits a job via job_service.
     *
     * @param int $courseid The course ID.
     * @param int $userid The user ID.
     * @param string $message The user message.
     * @param string $pagecontext Optional page context information.
     * @return operation_result Pending operation result with jobid.
     * @throws api_exception If the API request fails.
     */
    public function submit_message(int $courseid, int $userid, string $message, string $pagecontext = ''): operation_result {
        service_factory::get_file_sync_service()->ensure_enabled_and_synchronized($courseid, $userid);

        $instructions = $this->build_instructions($courseid);

        $payload = [
            'courseId' => (string) $courseid,
            'userId' => (string) $userid,
            'message' => $message,
            'instructions' => $instructions,
            'namespace' => $this->namespace,
        ];

        if (!empty($pagecontext)) {
            $payload['pageContext'] = $pagecontext;
        }

        return $this->jobservice->submit_job('/v1/tutor/messages', $payload);
    }

    /**
     * Get conversation history from the API.
     *
     * @param int $courseid The course ID.
     * @param int $userid The user ID.
     * @param string $sinceid Optional message ID cursor for newer messages (delta).
     * @param int $limit Maximum number of messages to return per request.
     * @param int $offset Optional offset for loading older message pages.
     * @return array Array of message objects with id, role, content, time keys.
     * @throws api_exception If the API request fails.
     */
    public function get_conversation(
        int $courseid,
        int $userid,
        string $sinceid = '',
        int $limit = 50,
        int $offset = 0
    ): array {
        $limit = max(1, $limit);
        $offset = max(0, $offset);

        return $this->map_raw_messages(
            $this->request_messages($courseid, $userid, $sinceid, $limit, $offset)
        );
    }

    /**
     * @param int $courseid
     * @param int $userid
     * @param string $sinceid
     * @param int $limit
     * @param int $offset
     * @return array
     * @throws api_exception
     */
    private function request_messages(
        int $courseid,
        int $userid,
        string $sinceid,
        int $limit,
        int $offset = 0
    ): array {
        $params = [
            'courseId' => (string) $courseid,
            'userId' => (string) $userid,
            'namespace' => $this->namespace,
            'limit' => $limit,
        ];

        if ($sinceid !== '') {
            $params['sinceId'] = $sinceid;
        }

        if ($offset > 0) {
            $params['offset'] = $offset;
        }

        $response = $this->client->get('/v1/tutor/messages', $params);
        $rawmessages = $response['messages'] ?? $response;

        return is_array($rawmessages) ? $rawmessages : [];
    }

    /**
     * @param array $rawmessages
     * @return array
     */
    private function map_raw_messages(array $rawmessages): array {
        $messages = [];

        foreach ($rawmessages as $msg) {
            if (!is_array($msg)) {
                continue;
            }

            $messages[] = [
                'id' => $msg['id'] ?? '',
                'role' => strtolower((string) ($msg['role'] ?? 'user')),
                'content' => $msg['content'] ?? '',
                'time' => isset($msg['createdAt']) ? self::parse_iso_timestamp($msg['createdAt']) : 0,
            ];
        }

        return $messages;
    }

    /**
     * Build system instructions for the tutor.
     *
     * Combines the instruction template lang string with course context.
     *
     * @param int $courseid The course ID.
     * @return string The complete instruction string.
     */
    private function build_instructions(int $courseid): string {
        return context_builder_factory::buildcoursecontext($courseid, null, 'assessment');
    }

    /**
     * Parse an ISO-8601 timestamp to a Unix timestamp.
     *
     * @param string|int $timestamp The timestamp value.
     * @return int Unix timestamp.
     */
    private static function parse_iso_timestamp(string|int $timestamp): int {
        if (is_int($timestamp)) {
            // Values above ~year 2286 in seconds are likely milliseconds.
            if ($timestamp > 9999999999) {
                return (int) floor($timestamp / 1000);
            }
            return $timestamp;
        }

        $parsed = strtotime($timestamp);
        return $parsed !== false ? $parsed : 0;
    }
}
