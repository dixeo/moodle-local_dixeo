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
use local_dixeo\dto\job_binding_metadata;
use local_dixeo\dto\operation_result;
use local_dixeo\external\service_factory;

/**
 * Service for tutor message operations.
 */
class tutor_service {
    /** @var int Page size requested when walking the conversation and message endpoints. */
    private const PAGE_SIZE = 100;

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

        return $this->jobservice->submit_job(
            '/v1/tutor/messages',
            $payload,
            'block_dixeo_tutor',
            job_binding_metadata::for_course($courseid)
        );
    }

    /**
     * Get conversation history from the API.
     *
     * @param int $courseid The course ID.
     * @param int $userid The user ID.
     * @param string $sinceid Optional message ID to fetch messages after.
     * @param int $limit Maximum number of messages to return.
     * @param int $offset Skip that many messages, counted from the newest when no cursor is supplied.
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
        $params = [
            'courseId' => (string) $courseid,
            'userId' => (string) $userid,
            'namespace' => $this->namespace,
            'limit' => $limit,
        ];

        if (!empty($sinceid)) {
            $params['sinceId'] = $sinceid;
        }

        if ($offset > 0) {
            $params['offset'] = $offset;
        }

        $response = $this->client->get('/v1/tutor/messages', $params);

        // The endpoint answers with a bare JSON list; map it to the Moodle format.
        $messages = [];

        foreach ($response as $msg) {
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
     * List the conversations the API holds, restricted to the configured namespace.
     *
     * Backs the Moodle privacy API: enumerating conversations tells a privacy provider
     * which courses hold data for a user, and which users hold data in a course.
     * At least one filter must be supplied.
     *
     * @param int|null $courseid Restrict to one course, or null for every course.
     * @param int|null $userid Restrict to one user, or null for every user.
     * @return array List of ['courseid' => int, 'userid' => int].
     * @throws api_exception If the API request fails.
     * @throws \coding_exception If no filter is supplied.
     */
    public function list_conversations(?int $courseid = null, ?int $userid = null): array {
        $filters = $this->conversation_filters($courseid, $userid);
        $conversations = [];
        $seen = [];
        $offset = 0;

        // The API is free to serve fewer rows than asked, so advance by what it actually
        // returned. Conversations are unique per course and user within a namespace, so a
        // page holding none we have not seen means the walk stopped progressing (a server
        // ignoring offset, say) and no later page would progress either.
        do {
            $page = $this->client->get('/v1/tutor/conversations', $filters + [
                'limit' => self::PAGE_SIZE,
                'offset' => $offset,
            ]);

            $before = count($conversations);
            foreach ($page as $conversation) {
                $entry = [
                    'courseid' => (int) ($conversation['courseId'] ?? 0),
                    'userid' => (int) ($conversation['userId'] ?? 0),
                ];

                $key = $entry['courseid'] . '|' . $entry['userid'];
                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $conversations[] = $entry;
            }

            $offset += count($page);
        } while (count($conversations) > $before);

        return $conversations;
    }

    /**
     * Fetch every message of a conversation, following the API pagination.
     *
     * {@see self::get_conversation()} returns a single page; privacy exports must be
     * complete, so this walks the whole history.
     *
     * Without a cursor the API returns the newest page first, so the walk climbs back
     * through the history by offset and prepends each page: a growing offset reaches
     * older messages, and every page is already chronological internally.
     *
     * @param int $courseid The course ID.
     * @param int $userid The user ID.
     * @return array Messages with id, role, content and time keys, oldest first.
     * @throws api_exception If the API request fails.
     */
    public function export_conversation(int $courseid, int $userid): array {
        $messages = [];
        $seen = [];
        $offset = 0;

        do {
            $page = $this->get_conversation($courseid, $userid, '', self::PAGE_SIZE, $offset);
            $offset += count($page);

            // Writes landing mid-export shift the offsets and can repeat a message across
            // two pages; drop the repeats rather than risk dropping a message.
            $older = [];
            foreach ($page as $message) {
                $id = (string) $message['id'];
                if (isset($seen[$id])) {
                    continue;
                }

                $seen[$id] = true;
                $older[] = $message;
            }

            $messages = array_merge($older, $messages);

            // A page holding nothing new means the walk stopped progressing (a server
            // ignoring offset, say); no later page would progress either.
        } while ($older !== []);

        return $messages;
    }

    /**
     * Erase conversations, both the local API records and the remote AI provider copy.
     *
     * Backs the Moodle privacy API erasure path. At least one filter must be supplied.
     *
     * @param int|null $courseid Restrict to one course, or null for every course.
     * @param int|null $userid Restrict to one user, or null for every user.
     * @return int Number of conversations deleted.
     * @throws api_exception If the API request fails.
     * @throws \coding_exception If no filter is supplied.
     */
    public function delete_conversations(?int $courseid = null, ?int $userid = null): int {
        $response = $this->client->delete(
            '/v1/tutor/conversations',
            $this->conversation_filters($courseid, $userid)
        );

        return (int) ($response['deleted'] ?? 0);
    }

    /**
     * Build the query filters shared by the conversation list and delete endpoints.
     *
     * The namespace is always sent and always cast to a string: http_build_query drops
     * null values, and an absent namespace makes the API match every namespace, so a
     * deletion issued by one site would reach conversations belonging to another site
     * sharing the same tenant.
     *
     * @param int|null $courseid Restrict to one course, or null for every course.
     * @param int|null $userid Restrict to one user, or null for every user.
     * @return array Query parameters for the API request.
     * @throws \coding_exception If no filter is supplied.
     */
    private function conversation_filters(?int $courseid, ?int $userid): array {
        if ($courseid === null && $userid === null) {
            throw new \coding_exception('At least one of courseid or userid must be supplied.');
        }

        $filters = ['namespace' => (string) $this->namespace];

        if ($courseid !== null) {
            $filters['courseId'] = (string) $courseid;
        }
        if ($userid !== null) {
            $filters['userId'] = (string) $userid;
        }

        return $filters;
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
