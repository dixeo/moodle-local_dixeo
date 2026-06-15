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
use local_dixeo\dto\tutor_message;
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
     * Submit a tutor message (user, assistant, or system).
     *
     * @param int $courseid The course ID.
     * @param int $userid The user ID.
     * @param tutor_message $message Unified message DTO.
     * @param string $mode Tutor interaction mode.
     * @return operation_result Pending operation result with jobid.
     * @throws api_exception If the API request fails.
     */
    public function submit(
        int $courseid,
        int $userid,
        tutor_message $message,
        string $mode = tutor_message::MODE_NORMAL
    ): operation_result {
        service_factory::get_file_sync_service()->ensure_enabled_and_synchronized($courseid, $userid);
        $message->validate();

        $payload = $this->build_submit_payload($courseid, $userid, $message, $mode);

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
     * Request messages.
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
     * Map raw messages.
     * @param array $rawmessages
     * @return array
     */
    private function map_raw_messages(array $rawmessages): array {
        $messages = [];

        foreach ($rawmessages as $msg) {
            if (!is_array($msg)) {
                continue;
            }

            $role = tutor_message::normalize_role((string) ($msg['role'] ?? 'user'));
            $content = (string) ($msg['content'] ?? '');
            $instructions = isset($msg['instructions']) ? (string) $msg['instructions'] : null;

            $normalized = [
                'id' => $msg['id'] ?? '',
                'role' => $role,
                'content' => $content,
                'time' => isset($msg['createdAt']) ? self::parse_iso_timestamp($msg['createdAt']) : 0,
            ];

            $context = self::passthrough_context($msg['context'] ?? null, $role);
            if ($context !== null) {
                $normalized['context'] = $context;
            }
            if ($instructions !== null && $instructions !== '') {
                $normalized['instructions'] = $instructions;
            }

            $messages[] = $normalized;
        }

        return $messages;
    }

    /**
     * Build submit payload.
     * @param int $courseid
     * @param int $userid
     * @param tutor_message $message
     * @param string $mode
     * @return array
     */
    private function build_submit_payload(
        int $courseid,
        int $userid,
        tutor_message $message,
        string $mode
    ): array {
        $payload = [
            'courseId' => (string) $courseid,
            'userId' => (string) $userid,
            'namespace' => $this->namespace,
            'mode' => tutor_message::normalize_mode($mode),
            'role' => $message->role,
            'context' => $message->context,
            'message' => $this->resolve_wire_message($message),
        ];

        $instructions = $this->resolve_instructions($courseid, $message);
        if (
            ($instructions === null || trim($instructions) === '')
            && $message->role === tutor_message::ROLE_SYSTEM
            && trim($message->message) !== ''
        ) {
            $instructions = $message->message;
        }
        $payload['instructions'] = $instructions ?? '';

        if ($message->role === tutor_message::ROLE_SYSTEM) {
            $payload['requireResponse'] = $message->requireresponse;
        }

        return $payload;
    }

    /**
     * Resolve instructions.
     * @param int $courseid
     * @param tutor_message $message
     * @return string|null
     */
    private function resolve_instructions(int $courseid, tutor_message $message): ?string {
        if ($message->instructions !== null && trim($message->instructions) !== '') {
            return $message->instructions;
        }

        if (
            $message->role === tutor_message::ROLE_USER
            || $message->role === tutor_message::ROLE_ASSISTANT
        ) {
            return $this->build_instructions($courseid);
        }

        return null;
    }

    /**
     * Message text sent on the wire. Proactive system rows stay empty in the DTO but the
     * remote API requires a non-empty string; those rows are hidden in the tutor UI anyway.
     *
     * @param tutor_message $message
     * @return string
     */
    private function resolve_wire_message(tutor_message $message): string {
        $text = $message->message;
        if ($message->role === tutor_message::ROLE_SYSTEM && trim($text) === '') {
            $instructions = $message->instructions ?? '';
        }

        return $text;
    }

    /**
     * Normalize API context to an object (JSON string or legacy plain string).
     *
     * @param mixed $context
     * @param string $role Message role for legacy plain-string wrapping.
     * @return array|null
     */
    private static function passthrough_context(mixed $context, string $role): ?array {
        if (is_array($context) && $context !== []) {
            return $context;
        }

        if (!is_string($context) || trim($context) === '') {
            return null;
        }

        $decoded = json_decode($context, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $trimmed = trim($context);
        $role = tutor_message::normalize_role($role);
        if ($role === tutor_message::ROLE_SYSTEM) {
            return ['body' => $trimmed];
        }

        return ['url' => $trimmed];
    }

    /**
     * Build course context markdown for tutor instructions.
     *
     * @param int $courseid The course ID.
     * @return string The complete instruction string.
     */
    private function build_instructions(int $courseid): string {
        return context_builder_factory::buildCourseContext($courseid, null, 'assessment');
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
