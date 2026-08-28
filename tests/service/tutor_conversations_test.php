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
 * Tests for the tutor conversation endpoints backing the privacy API.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\api\client;
use local_dixeo\service\job_service;
use local_dixeo\service\tutor_service;

/**
 * The conversation list, export and delete calls carry the protocol invariants the
 * privacy provider depends on: namespace scoping, pagination, export completeness
 * and mandatory filters.
 *
 * The mocked client mirrors the real endpoints rather than a convenient fiction: the
 * message endpoint serves the newest page first, which is what makes a naive cursor
 * walk truncate an export.
 *
 * @covers \local_dixeo\service\tutor_service
 */
final class tutor_conversations_test extends \advanced_testcase {
    /** @var string Namespace configured for the site under test. */
    private const NAMESPACE = 'moodle-under-test';

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('namespace', self::NAMESPACE, 'local_dixeo');
    }

    /**
     * Build a tutor service backed by a mocked API client.
     *
     * @param client $client The mocked client.
     * @return tutor_service The service under test.
     */
    private function service_with(client $client): tutor_service {
        return new tutor_service($this->createMock(job_service::class), $client);
    }

    /**
     * Build one API conversation row.
     *
     * @param int $courseid Course identifier.
     * @param int $userid User identifier.
     * @return array Conversation row as returned by the API.
     */
    private function conversation(int $courseid, int $userid): array {
        return [
            'id' => '00000000-0000-4000-8000-000000000000',
            'courseId' => (string) $courseid,
            'userId' => (string) $userid,
            'namespace' => self::NAMESPACE,
        ];
    }

    /**
     * Serve GET /v1/tutor/conversations: a plain offset window over the rows.
     *
     * The server may return fewer rows than requested, hence the cap.
     *
     * @param array $all Every conversation matching the filters.
     * @param array $params Query parameters received by the client.
     * @param int $servercap Most rows the server is willing to return at once.
     * @return array The page.
     */
    private function conversations_page(array $all, array $params, int $servercap = 100): array {
        return array_slice($all, (int) $params['offset'], min((int) $params['limit'], $servercap));
    }

    /**
     * Build a conversation history of the given length, oldest first.
     *
     * @param int $count Number of messages.
     * @return array Message rows as returned by the API.
     */
    private function history(int $count): array {
        return array_map(fn(int $i) => [
            'id' => 'msg-' . $i,
            'role' => $i % 2 === 0 ? 'assistant' : 'user',
            'content' => 'text ' . $i,
            'createdAt' => 0,
        ], range(1, $count));
    }

    /**
     * Serve GET /v1/tutor/messages exactly as the API does.
     *
     * Without a cursor the rows are taken newest first (ORDER BY id DESC, then offset
     * and limit) and reversed before being sent, so offset 0 is the tail of the history
     * and a growing offset walks back towards its start. With a cursor the rows after
     * it are returned ascending. The payload is a bare JSON list, not an envelope.
     *
     * @param array $history The whole conversation, oldest first.
     * @param array $params Query parameters received by the client.
     * @param int $servercap Most rows the server is willing to return at once.
     * @return array The page.
     */
    private function messages_page(array $history, array $params, int $servercap = 100): array {
        $limit = min((int) $params['limit'], $servercap);
        $offset = (int) ($params['offset'] ?? 0);
        $sinceid = (string) ($params['sinceId'] ?? '');

        if ($sinceid !== '') {
            $position = array_search($sinceid, array_column($history, 'id'), true);
            $newer = $position === false ? [] : array_slice($history, $position + 1);
            return array_slice($newer, $offset, $limit);
        }

        $newestfirst = array_reverse($history);
        return array_reverse(array_slice($newestfirst, $offset, $limit));
    }

    public function test_list_conversations_scopes_to_the_configured_namespace(): void {
        $client = $this->createMock(client::class);
        $client->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(function (string $endpoint, array $params): array {
                $this->assertSame('/v1/tutor/conversations', $endpoint);
                $this->assertSame(self::NAMESPACE, $params['namespace']);
                $this->assertSame('42', $params['userId']);
                $this->assertArrayNotHasKey('courseId', $params);
                return $this->conversations_page([$this->conversation(7, 42)], $params);
            });

        $this->assertSame(
            [['courseid' => 7, 'userid' => 42]],
            $this->service_with($client)->list_conversations(null, 42)
        );
    }

    public function test_list_conversations_follows_pagination(): void {
        $all = array_map(fn(int $i) => $this->conversation($i, 42), range(1, 101));

        $client = $this->createMock(client::class);
        $client->expects($this->exactly(3))
            ->method('get')
            ->willReturnCallback(fn(string $endpoint, array $params): array => $this->conversations_page($all, $params));

        $this->assertCount(101, $this->service_with($client)->list_conversations(null, 42));
    }

    public function test_list_conversations_does_not_assume_the_requested_page_size(): void {
        $all = array_map(fn(int $i) => $this->conversation($i, 42), range(1, 70));

        // A server capping pages below the requested size must not cut the walk short.
        $client = $this->createMock(client::class);
        $client->expects($this->exactly(4))
            ->method('get')
            ->willReturnCallback(
                fn(string $endpoint, array $params): array => $this->conversations_page($all, $params, 30)
            );

        $this->assertCount(70, $this->service_with($client)->list_conversations(null, 42));
    }

    public function test_list_conversations_stops_when_the_server_ignores_the_offset(): void {
        $all = array_map(fn(int $i) => $this->conversation($i, 42), range(1, 101));

        // A proxy stripping the offset would otherwise serve the same page for ever.
        $client = $this->createMock(client::class);
        $client->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(function (string $endpoint, array $params) use ($all): array {
                $params['offset'] = 0;
                return $this->conversations_page($all, $params);
            });

        $this->assertCount(100, $this->service_with($client)->list_conversations(null, 42));
    }

    public function test_delete_conversations_sends_both_filters_and_returns_the_count(): void {
        $client = $this->createMock(client::class);
        $client->expects($this->once())
            ->method('delete')
            ->with('/v1/tutor/conversations', [
                'namespace' => self::NAMESPACE,
                'courseId' => '7',
                'userId' => '42',
            ])
            ->willReturn(['deleted' => 3]);

        $this->assertSame(3, $this->service_with($client)->delete_conversations(7, 42));
    }

    public function test_delete_conversations_without_filter_is_rejected(): void {
        $client = $this->createMock(client::class);
        $client->expects($this->never())->method('delete');

        $this->expectException(\coding_exception::class);
        $this->service_with($client)->delete_conversations();
    }

    public function test_get_conversation_without_a_cursor_returns_the_tail_of_the_history(): void {
        $history = $this->history(10);

        $client = $this->createMock(client::class);
        $client->expects($this->once())
            ->method('get')
            ->willReturnCallback(fn(string $endpoint, array $params): array => $this->messages_page($history, $params));

        $messages = $this->service_with($client)->get_conversation(7, 42, '', 3);

        $this->assertSame(['msg-8', 'msg-9', 'msg-10'], array_column($messages, 'id'));
    }

    public function test_get_conversation_with_a_cursor_returns_only_newer_messages(): void {
        $history = $this->history(10);

        $client = $this->createMock(client::class);
        $client->expects($this->once())
            ->method('get')
            ->willReturnCallback(fn(string $endpoint, array $params): array => $this->messages_page($history, $params));

        $messages = $this->service_with($client)->get_conversation(7, 42, 'msg-8');

        $this->assertSame(['msg-9', 'msg-10'], array_column($messages, 'id'));
    }

    public function test_export_conversation_walks_a_history_longer_than_two_pages(): void {
        $history = $this->history(250);

        $client = $this->createMock(client::class);
        $client->expects($this->exactly(4))
            ->method('get')
            ->willReturnCallback(function (string $endpoint, array $params) use ($history): array {
                $this->assertSame('/v1/tutor/messages', $endpoint);
                // A cursor taken from the newest page pins the walk to the end of the
                // history and hides everything before it; the walk must use offsets.
                $this->assertArrayNotHasKey('sinceId', $params);
                return $this->messages_page($history, $params);
            });

        $messages = $this->service_with($client)->export_conversation(7, 42);

        $this->assertSame(array_column($history, 'id'), array_column($messages, 'id'));
    }

    public function test_export_conversation_does_not_assume_the_requested_page_size(): void {
        $history = $this->history(250);

        // A server capping pages below the requested size must not cut the walk short.
        $client = $this->createMock(client::class);
        $client->method('get')
            ->willReturnCallback(
                fn(string $endpoint, array $params): array => $this->messages_page($history, $params, 40)
            );

        $messages = $this->service_with($client)->export_conversation(7, 42);

        $this->assertSame(array_column($history, 'id'), array_column($messages, 'id'));
    }

    public function test_export_conversation_stops_when_the_server_ignores_the_offset(): void {
        $history = $this->history(250);

        // A proxy stripping the offset would otherwise serve the same tail for ever.
        $client = $this->createMock(client::class);
        $client->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(function (string $endpoint, array $params) use ($history): array {
                unset($params['offset']);
                return $this->messages_page($history, $params);
            });

        $messages = $this->service_with($client)->export_conversation(7, 42);

        $this->assertSame(array_column(array_slice($history, -100), 'id'), array_column($messages, 'id'));
    }

    public function test_export_conversation_drops_messages_repeated_by_a_concurrent_write(): void {
        $history = $this->history(250);
        $calls = 0;

        $client = $this->createMock(client::class);
        $client->method('get')
            ->willReturnCallback(function (string $endpoint, array $params) use (&$history, &$calls): array {
                $page = $this->messages_page($history, $params);
                if (++$calls === 1) {
                    // A reply written mid-export shifts every later offset by one, so the
                    // next page repeats a message already collected.
                    $history[] = ['id' => 'msg-251', 'role' => 'assistant', 'content' => 'late', 'createdAt' => 0];
                }
                return $page;
            });

        $messages = $this->service_with($client)->export_conversation(7, 42);

        $this->assertSame(array_column($this->history(250), 'id'), array_column($messages, 'id'));
    }
}
