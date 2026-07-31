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
 * Tests for tutor conversation loading.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\api\client;
use local_dixeo\dto\operation_result;
use local_dixeo\external\service_factory;
use local_dixeo\service\file_sync_service;
use local_dixeo\service\job_service;
use local_dixeo\service\tutor_service;
use local_dixeo\dto\tutor_message;


/**
 * Tests for tutor_service_test.
 * @covers \local_dixeo\service\tutor_service
 */
final class tutor_service_test extends \advanced_testcase {
    /**
     * TearDown.
     */
    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * Stub the file sync service so submit() does not hit the real API.
     */
    private function stub_file_sync_service(): void {
        $filesync = $this->createMock(file_sync_service::class);
        $filesync->expects($this->once())->method('ensure_enabled_and_synchronized');
        service_factory::set_test_file_sync_service($filesync);
    }

    /**
     * Test get conversation initial load fetches single page.
     */
    public function test_get_conversation_initial_load_fetches_single_page(): void {
        $page = [];
        for ($i = 1; $i <= 50; $i++) {
            $page[] = $this->raw_message('m' . $i, $i);
        }

        $mockclient = $this->createMock(client::class);
        $mockclient->expects($this->once())
            ->method('get')
            ->with(
                '/v1/tutor/messages',
                $this->callback(function (array $params): bool {
                    return $params['limit'] === 50
                        && !isset($params['sinceId'])
                        && !isset($params['offset']);
                })
            )
            ->willReturn($page);

        $service = new tutor_service(null, $mockclient);
        $messages = $service->get_conversation(1, 2, '', 50, 0);

        $this->assertCount(50, $messages);
        $this->assertSame('m1', $messages[0]['id']);
        $this->assertSame('m50', $messages[49]['id']);
    }

    /**
     * Test get conversation with sinceid fetches paged results.
     */
    public function test_get_conversation_with_sinceid_fetches_paged_results(): void {
        $delta = [
            $this->raw_message('m99', 99),
        ];

        $mockclient = $this->createMock(client::class);
        $mockclient->expects($this->once())
            ->method('get')
            ->with(
                '/v1/tutor/messages',
                $this->callback(function (array $params): bool {
                    return $params['sinceId'] === 'm98' && $params['limit'] === 50;
                })
            )
            ->willReturn($delta);

        $service = new tutor_service(null, $mockclient);
        $messages = $service->get_conversation(1, 2, 'm98', 50, 0);

        $this->assertCount(1, $messages);
        $this->assertSame('m99', $messages[0]['id']);
    }

    /**
     * Test get conversation with offset fetches older page.
     */
    public function test_get_conversation_with_offset_fetches_older_page(): void {
        $older = [
            $this->raw_message('m1', 1),
            $this->raw_message('m2', 2),
        ];

        $mockclient = $this->createMock(client::class);
        $mockclient->expects($this->once())
            ->method('get')
            ->with(
                '/v1/tutor/messages',
                $this->callback(function (array $params): bool {
                    return $params['offset'] === 50 && $params['limit'] === 50;
                })
            )
            ->willReturn($older);

        $service = new tutor_service(null, $mockclient);
        $messages = $service->get_conversation(1, 2, '', 50, 50);

        $this->assertCount(2, $messages);
        $this->assertSame('m1', $messages[0]['id']);
        $this->assertSame('m2', $messages[1]['id']);
    }

    /**
     * Test get conversation short history returns all messages.
     */
    public function test_get_conversation_short_history_returns_all_messages(): void {
        $mockclient = $this->createMock(client::class);
        $mockclient->method('get')->willReturn([
            $this->raw_message('m1', 1),
            $this->raw_message('m2', 2),
        ]);

        $service = new tutor_service(null, $mockclient);
        $messages = $service->get_conversation(3, 4, '', 50, 0);

        $this->assertCount(2, $messages);
        $this->assertSame('m1', $messages[0]['id']);
        $this->assertSame('m2', $messages[1]['id']);
    }

    /**
     * Test submit user message includes mode instructions and context.
     */
    public function test_submit_user_message_includes_mode_instructions_and_context(): void {
        $this->resetAfterTest();
        $this->stub_file_sync_service();
        $course = $this->getDataGenerator()->create_course();
        $context = ['schema' => 'page', 'version' => 1, 'url' => 'https://example.test/course/view.php?id=1'];

        $mockjob = $this->createMock(job_service::class);
        $mockjob->expects($this->once())
            ->method('submit_job')
            ->with(
                '/v1/tutor/messages',
                $this->callback(function (array $payload) use ($context): bool {
                    return ($payload['role'] ?? '') === 'user'
                        && ($payload['message'] ?? '') === 'Hello'
                        && ($payload['mode'] ?? '') === tutor_message::MODE_GUIDE
                        && isset($payload['instructions'])
                        && ($payload['context'] ?? null) === $context
                        && !isset($payload['includeInstructions']);
                })
            )
            ->willReturn(operation_result::pending('job-1', 'pending', 0));

        $service = new tutor_service($mockjob);
        $service->submit(
            (int) $course->id,
            2,
            new tutor_message(tutor_message::ROLE_USER, 'Hello', $context),
            tutor_message::MODE_GUIDE
        );
    }

    /**
     * Test submit system message passes context without instructions.
     */
    public function test_submit_system_message_passes_context_without_instructions(): void {
        $this->resetAfterTest();
        $this->stub_file_sync_service();
        $course = $this->getDataGenerator()->create_course();
        $context = ['schema' => 'proactive', 'version' => 1, 'body' => 'Context line'];

        $mockjob = $this->createMock(job_service::class);
        $mockjob->expects($this->once())
            ->method('submit_job')
            ->with(
                '/v1/tutor/messages',
                $this->callback(function (array $payload) use ($context): bool {
                    return ($payload['role'] ?? '') === 'system'
                        && ($payload['context'] ?? null) === $context
                        && ($payload['requireResponse'] ?? null) === true
                        && ($payload['message'] ?? null) === 'Context line'
                        && ($payload['instructions'] ?? null) === 'Context line'
                        && !isset($payload['includeInstructions']);
                })
            )
            ->willReturn(operation_result::pending('job-2', 'pending', 0));

        $service = new tutor_service($mockjob);
        $service->submit(
            (int) $course->id,
            2,
            tutor_message::system($context, 'Context line')
        );
    }

    /**
     * Test get conversation maps system fields.
     */
    public function test_get_conversation_maps_system_fields(): void {
        $mockclient = $this->createMock(client::class);
        $mockclient->method('get')->willReturn([
            [
                'id' => 'sys1',
                'role' => 'system',
                'context' => [
                    'schema' => 'practice_quiz_review',
                    'version' => 1,
                    'title' => 'Quiz',
                ],
                'instructions' => 'Review these results.',
                'content' => '',
                'createdAt' => gmdate('Y-m-d\TH:i:s\Z', 1_700_000_100),
            ],
        ]);

        $service = new tutor_service(null, $mockclient);
        $messages = $service->get_conversation(1, 2);

        $this->assertSame('system', $messages[0]['role']);
        $this->assertSame('practice_quiz_review', $messages[0]['context']['schema']);
        $this->assertSame('Review these results.', $messages[0]['instructions']);
        $this->assertArrayNotHasKey('requireresponse', $messages[0]);
    }

    /**
     * Test get conversation normalizes legacy string context to object.
     */
    public function test_get_conversation_normalizes_legacy_string_context_to_object(): void {
        $mockclient = $this->createMock(client::class);
        $mockclient->method('get')->willReturn([
            [
                'id' => 'sys2',
                'role' => 'system',
                'context' => 'Legacy proactive line',
                'createdAt' => gmdate('Y-m-d\TH:i:s\Z', 1_700_000_200),
            ],
        ]);

        $service = new tutor_service(null, $mockclient);
        $messages = $service->get_conversation(1, 2);

        $this->assertSame(['body' => 'Legacy proactive line'], $messages[0]['context']);
    }

    /**
     * Test get conversation decodes json string context.
     */
    public function test_get_conversation_decodes_json_string_context(): void {
        $mockclient = $this->createMock(client::class);
        $mockclient->method('get')->willReturn([
            [
                'id' => 'u1',
                'role' => 'user',
                'content' => 'Hi',
                'context' => '{"schema":"page","version":1,"url":"https://example.test/page"}',
                'createdAt' => gmdate('Y-m-d\TH:i:s\Z', 1_700_000_300),
            ],
        ]);

        $service = new tutor_service(null, $mockclient);
        $messages = $service->get_conversation(1, 2);

        $this->assertSame('page', $messages[0]['context']['schema']);
        $this->assertSame('https://example.test/page', $messages[0]['context']['url']);
    }

    /**
     * Raw message.
     * @param string $id
     * @param int $index Used to build distinct timestamps.
     * @return array
     */
    private function raw_message(string $id, int $index): array {
        return [
            'id' => $id,
            'role' => 'user',
            'content' => 'Message ' . $index,
            'context' => ['schema' => 'page', 'version' => 1, 'url' => ''],
            'createdAt' => gmdate('Y-m-d\TH:i:s\Z', 1_700_000_000 + $index),
        ];
    }
}
