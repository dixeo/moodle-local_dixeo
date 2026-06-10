<?php
/**
 * Unit tests for practice_quiz_service.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\dto\job_status;
use local_dixeo\external\service_factory;
use local_dixeo\service\job_service;
use local_dixeo\service\practice_quiz_service;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_dixeo\service\practice_quiz_service
 */
final class practice_quiz_service_test extends \advanced_testcase {

    /**
     * build_instructions states question count and difficulty explicitly.
     */
    public function test_build_instructions_includes_count_and_difficulty(): void {
        $this->resetAfterTest();

        $service = new practice_quiz_service();
        $instructions = $service->build_instructions(7, 'hard', practice_quiz_service::SCOPE_SECTION, 'Unit 2');

        $this->assertStringContainsString('exactly 7', $instructions);
        $this->assertStringContainsString('QUESTION COUNT', $instructions);
        $this->assertStringContainsString('DIFFICULTY LEVEL', $instructions);
        $this->assertStringContainsString('hard', $instructions);
        $this->assertStringContainsString('section', $instructions);
        $this->assertStringContainsString('Unit 2', $instructions);
        $this->assertStringNotContainsString('ephemeral', $instructions);
    }

    /**
     * submit_from_setup rejects invalid scope values.
     */
    public function test_submit_from_setup_rejects_invalid_scope(): void {
        $this->resetAfterTest();

        $service = new practice_quiz_service();

        $this->expectException(\invalid_parameter_exception::class);
        $service->submit_from_setup(1, 'invalid');
    }

    /**
     * finalize_from_job trims excess questions when expected count is set.
     */
    public function test_finalize_from_job_trims_to_expected_count(): void {
        $this->resetAfterTest(true);

        $mockjob = $this->getMockBuilder(job_service::class)
            ->onlyMethods(['get_job_status'])
            ->getMock();

        $mockjob->method('get_job_status')->willReturn(new job_status(
            jobid: 'test-job-id',
            type: 'generate_module',
            status: job_status::STATUS_COMPLETED,
            progress: 100,
            createdat: time(),
            result: [
                'moduleType' => 'simplequiz2',
                'data' => [
                    'name' => 'Quiz',
                    'questions' => [
                        ['text' => 'Q1', 'options' => ['A', 'B'], 'answer' => 0],
                        ['text' => 'Q2', 'options' => ['A', 'B'], 'answer' => 0],
                        ['text' => 'Q3', 'options' => ['A', 'B'], 'answer' => 0],
                        ['text' => 'Q4', 'options' => ['A', 'B'], 'answer' => 0],
                        ['text' => 'Q5', 'options' => ['A', 'B'], 'answer' => 0],
                    ],
                ],
            ]
        ));

        service_factory::set_test_job_service($mockjob);

        $service = new practice_quiz_service(null, $mockjob);
        $result = $service->finalize_from_job('test-job-id', '', 3);

        $questions = json_decode($result['questions'], true);
        $this->assertCount(3, $questions);
        $this->assertEquals('Q1', $questions[0]['text']);

        service_factory::reset();
    }

    /**
     * finalize_from_job transforms completed simplequiz2 job data.
     */
    public function test_finalize_from_job_success(): void {
        $this->resetAfterTest(true);

        $mockjob = $this->getMockBuilder(job_service::class)
            ->onlyMethods(['get_job_status'])
            ->getMock();

        $mockjob->method('get_job_status')->willReturn(new job_status(
            jobid: 'test-job-id',
            type: 'generate_module',
            status: job_status::STATUS_COMPLETED,
            progress: 100,
            createdat: time(),
            result: [
                'moduleType' => 'simplequiz2',
                'data' => [
                    'name' => 'Cell biology quiz',
                    'questions' => [
                        [
                            'text' => 'What is a cell?',
                            'options' => ['Unit of life', 'Organ'],
                            'answer' => 0,
                        ],
                    ],
                ],
            ]
        ));

        service_factory::set_test_job_service($mockjob);

        $service = new practice_quiz_service(null, $mockjob);
        $result = $service->finalize_from_job('test-job-id');

        $this->assertTrue($result['success']);
        $this->assertEquals('Cell biology quiz', $result['title']);
        $questions = json_decode($result['questions'], true);
        $this->assertCount(1, $questions);
        $this->assertEquals('What is a cell?', $questions[0]['text']);

        service_factory::reset();
    }
}
