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
 * Tests for tutor conversation loading and message submit payloads.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\api\client;
use local_dixeo\dto\operation_result;
use local_dixeo\dto\tutor_message;
use local_dixeo\external\service_factory;
use local_dixeo\service\file_sync_service;
use local_dixeo\service\job_service;
use local_dixeo\service\tutor_service;

/**
 * Tests for {@see tutor_service} conversation loading and submit payloads.
 *
 * @covers \local_dixeo\service\tutor_service
 */
final class tutor_service_test extends \advanced_testcase {
    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * Stub RAG sync so submit payload tests do not call the real Dixeo API.
     */
    private function stub_file_sync_service(): void {
        $sync = $this->createMock(file_sync_service::class);
        $sync->expects($this->once())
            ->method('ensure_enabled_and_synchronized');
        service_factory::set_test_file_sync_service($sync);
    }

    public function test_submit_sends_the_message_when_file_sync_fails(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $sync = $this->createMock(file_sync_service::class);
        $sync->expects($this->once())
            ->method('ensure_enabled_and_synchronized')
            ->willThrowException(new \moodle_exception('filesync_timeout', 'local_dixeo'));
        service_factory::set_test_file_sync_service($sync);

        $mockjob = $this->createMock(job_service::class);
        $mockjob->expects($this->once())
            ->method('submit_job')
            ->willReturn(operation_result::pending('job-sync', 'pending', 0));

        $message = new tutor_message(tutor_message::ROLE_USER, 'Hello', ['schema' => 'page', 'version' => 1]);
        $result = (new tutor_service($mockjob))->submit((int) $course->id, 2, $message);

        $this->assertSame('job-sync', $result->jobid);
        $this->assertDebuggingCalled();
    }

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

    public function test_submit_user_message_includes_mode_instructions_and_context(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = ['schema' => 'page', 'version' => 1, 'url' => 'https://example.test/course/view.php?id=1'];
        $this->stub_file_sync_service();

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

    public function test_submit_system_message_attaches_course_structure_when_instructions_empty(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = ['schema' => 'proactive', 'version' => 1, 'body' => 'Context line'];
        $this->stub_file_sync_service();

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
                        && str_contains((string) ($payload['instructions'] ?? ''), '## Course Structure')
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

    public function test_submit_practice_quiz_review_appends_course_structure(): void {
        $this->resetAfterTest();
        // The context lists what the current user can see, as for the learner in production.
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'VO2 Max Primer',
            'content' => 'Intro',
        ]);
        $context = [
            'schema' => 'practice_quiz_review',
            'version' => 1,
            'title' => 'Practice check',
            'questions' => [],
        ];
        $this->stub_file_sync_service();

        $mockjob = $this->createMock(job_service::class);
        $mockjob->expects($this->once())
            ->method('submit_job')
            ->with(
                '/v1/tutor/messages',
                $this->callback(function (array $payload): bool {
                    $instructions = (string) ($payload['instructions'] ?? '');
                    $structurepos = strrpos($instructions, '## Course Structure');
                    return ($payload['role'] ?? '') === 'system'
                        && ($payload['mode'] ?? '') === tutor_message::MODE_QUIZ
                        && ($payload['context']['schema'] ?? '') === 'practice_quiz_review'
                        && $structurepos !== false
                        && !str_contains($instructions, '[Practice quiz review]')
                        && str_contains($instructions, 'VO2 Max Primer');
                })
            )
            ->willReturn(operation_result::pending('job-review', 'pending', 0));

        $service = new tutor_service($mockjob);
        $service->submit(
            (int) $course->id,
            2,
            tutor_message::system(
                $context,
                'Practice check',
                null,
                true
            ),
            tutor_message::MODE_QUIZ
        );
    }

    public function test_submit_proactive_system_message_appends_course_structure(): void {
        $this->resetAfterTest();
        // The context lists what the current user can see, as for the learner in production.
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Endurance Basics',
            'content' => 'Intro',
        ]);
        $context = [
            'schema' => 'proactive',
            'version' => 1,
            'events' => [['type' => 'quiz_graded', 'quizname' => 'Unit quiz', 'grade' => '8', 'maxgrade' => '10']],
        ];
        $this->stub_file_sync_service();

        $mockjob = $this->createMock(job_service::class);
        $mockjob->expects($this->once())
            ->method('submit_job')
            ->with(
                '/v1/tutor/messages',
                $this->callback(function (array $payload): bool {
                    $instructions = (string) ($payload['instructions'] ?? '');
                    $structurepos = strrpos($instructions, '## Course Structure');
                    return ($payload['role'] ?? '') === 'system'
                        && ($payload['requireResponse'] ?? null) === true
                        && ($payload['context']['schema'] ?? '') === 'proactive'
                        && $structurepos !== false
                        && !str_contains($instructions, 'The learner completed the quiz')
                        && str_contains($instructions, 'Endurance Basics');
                })
            )
            ->willReturn(operation_result::pending('job-proactive', 'pending', 0));

        $service = new tutor_service($mockjob);
        $service->submit(
            (int) $course->id,
            2,
            tutor_message::system(
                $context,
                '',
                null,
                true
            )
        );
    }

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

    public function test_get_conversation_resolves_only_sources_the_user_can_see(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enableavailability', 1);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $visible = $generator->create_module('resource', ['course' => $course->id, 'name' => 'Cours & PDF']);
        $hidden = $generator->create_module('resource', ['course' => $course->id, 'visible' => 0]);
        $restricted = $generator->create_module('resource', [
            'course' => $course->id,
            'availability' => json_encode(\core_availability\tree::get_root_json([
                \availability_date\condition::get_json('>=', time() + DAYSECS),
            ])),
        ]);
        $label = $generator->create_module('label', ['course' => $course->id]);

        $messages = $this->conversation_with_sources($course->id, $student, [
            ['sourceId' => (string) $visible->cmid, 'file' => 'cours.pdf', 'pages' => [5, 3, 5]],
            ['sourceId' => (string) $hidden->cmid, 'file' => 'hidden.pdf', 'pages' => [1]],
            ['sourceId' => (string) $restricted->cmid, 'file' => 'later.pdf', 'pages' => [2]],
            ['sourceId' => (string) $label->cmid, 'file' => null, 'pages' => []],
            ['sourceId' => '999999', 'file' => 'gone.pdf', 'pages' => [4]],
            ['sourceId' => null, 'file' => 'unknown.pdf', 'pages' => [6]],
            ['sourceId' => (string) $visible->cmid, 'file' => null, 'pages' => []],
        ]);

        $cm = get_fast_modinfo($course->id)->get_cm($visible->cmid);
        $resolved = [
            'cmid' => (int) $visible->cmid,
            'name' => 'Cours & PDF',
            'url' => (new \moodle_url('/mod/resource/view.php', ['id' => $visible->cmid]))->out(false),
            'iconurl' => $cm->get_icon_url()->out(false),
        ];
        $this->assertSame([
            $resolved + ['file' => 'cours.pdf', 'pages' => [3, 5]],
            null,
            null,
            null,
            null,
            null,
            $resolved + ['file' => null, 'pages' => []],
        ], $messages[0]['sources']);
    }

    public function test_get_conversation_omits_sources_when_none_cited(): void {
        $mockclient = $this->createMock(client::class);
        $mockclient->method('get')->willReturn([
            ['id' => 'a1', 'role' => 'assistant', 'content' => 'No citation', 'sources' => []],
            ['id' => 'a2', 'role' => 'assistant', 'content' => 'No citation either'],
        ]);

        $messages = (new tutor_service(null, $mockclient))->get_conversation(1, 2);

        $this->assertArrayNotHasKey('sources', $messages[0]);
        $this->assertArrayNotHasKey('sources', $messages[1]);
    }

    /**
     * Load a one-message conversation carrying the given sources, as the given user.
     *
     * @param int $courseid
     * @param \stdClass $user
     * @param array $sources Raw API source rows.
     * @return array Mapped messages.
     */
    private function conversation_with_sources(int $courseid, \stdClass $user, array $sources): array {
        $mockclient = $this->createMock(client::class);
        $mockclient->method('get')->willReturn([
            ['id' => 'a1', 'role' => 'assistant', 'content' => 'Answer', 'sources' => $sources],
        ]);

        $this->setUser($user);
        return (new tutor_service(null, $mockclient))->get_conversation($courseid, (int) $user->id);
    }

    /**
     * Build a raw API message fixture for conversation tests.
     *
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
