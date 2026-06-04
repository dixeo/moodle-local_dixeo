<?php
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
use local_dixeo\service\tutor_service;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_dixeo\service\tutor_service
 */
final class tutor_service_test extends \advanced_testcase {

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
                $this->callback(function(array $params): bool {
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

    public function test_get_conversation_with_sinceid_fetches_paged_results(): void {
        $delta = [
            $this->raw_message('m99', 99),
        ];

        $mockclient = $this->createMock(client::class);
        $mockclient->expects($this->once())
            ->method('get')
            ->with(
                '/v1/tutor/messages',
                $this->callback(function(array $params): bool {
                    return $params['sinceId'] === 'm98' && $params['limit'] === 50;
                })
            )
            ->willReturn($delta);

        $service = new tutor_service(null, $mockclient);
        $messages = $service->get_conversation(1, 2, 'm98', 50, 0);

        $this->assertCount(1, $messages);
        $this->assertSame('m99', $messages[0]['id']);
    }

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
                $this->callback(function(array $params): bool {
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
     * @param string $id
     * @param int $index Used to build distinct timestamps.
     * @return array
     */
    private function raw_message(string $id, int $index): array {
        return [
            'id' => $id,
            'role' => 'user',
            'content' => 'Message ' . $index,
            'createdAt' => gmdate('Y-m-d\TH:i:s\Z', 1_700_000_000 + $index),
        ];
    }
}
