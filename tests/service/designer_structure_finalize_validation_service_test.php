<?php
// This file is part of Moodle - http://moodle.org/
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
 * Tests for designer finalize structure validation.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\service\designer_structure_finalize_validation_service;
use local_dixeo\service\module_types_service;
use local_dixeo\util\input_limits;

/**
 * Unit tests for designer structure finalize validation service.
 *
 * @covers \local_dixeo\service\designer_structure_finalize_validation_service
 */
final class designer_structure_finalize_validation_service_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Build a minimal module-type catalogue containing only page (and quiz).
     *
     * @return array<int, array<string, mixed>>
     */
    private function catalogue_page_only(): array {
        return [
            [
                'type' => 'page',
                'label' => 'Page',
                'description' => '',
                'category' => 'x',
                'component' => 'mod_page',
                'requirements' => [],
            ],
            [
                'type' => 'quiz',
                'label' => 'Quiz',
                'description' => '',
                'category' => 'x',
                'component' => 'mod_quiz',
                'requirements' => [],
            ],
        ];
    }

    public function test_valid_minimal_structure_returns_no_errors(): void {
        $mock = $this->createMock(module_types_service::class);
        $mock->method('get_module_types_cached')->willReturn($this->catalogue_page_only());

        $svc = new designer_structure_finalize_validation_service($mock);
        $structure = [
            'course_structure' => [
                'title' => 'My course',
                'summary' => '',
                'sections' => [
                    [
                        'title' => 'Chapter 1',
                        'summary' => '',
                        'modules' => [
                            [
                                'type' => 'page',
                                'title' => 'Intro activity',
                                'summary' => 'A real summary for the learner-facing description.',
                                'instructions' => '1234567890 Explain the goals in depth.',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame([], $svc->validate($structure));
    }

    public function test_empty_course_title_returns_error(): void {
        $mock = $this->createMock(module_types_service::class);
        $mock->method('get_module_types_cached')->willReturn($this->catalogue_page_only());
        $svc = new designer_structure_finalize_validation_service($mock);

        $errors = $svc->validate([
            'course_structure' => [
                'title' => '   ',
                'sections' => [],
            ],
        ]);

        $this->assertCount(1, $errors);
    }

    public function test_unknown_module_type_returns_error(): void {
        $mock = $this->createMock(module_types_service::class);
        $mock->method('get_module_types_cached')->willReturn($this->catalogue_page_only());
        $svc = new designer_structure_finalize_validation_service($mock);

        $errors = $svc->validate([
            'course_structure' => [
                'title' => 'T',
                'sections' => [
                    [
                        'title' => 'S',
                        'modules' => [
                            [
                                'type' => 'not_a_real_mod_type_xx',
                                'title' => 'M',
                                'summary' => 'Real summary text here.',
                                'instructions' => '1234567890 enough chars',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertNotEmpty($errors);
        $joined = implode(' ', $errors);
        $this->assertStringContainsString('not_a_real_mod_type_xx', $joined);
    }

    public function test_instructions_shorter_than_ten_fail(): void {
        $mock = $this->createMock(module_types_service::class);
        $mock->method('get_module_types_cached')->willReturn($this->catalogue_page_only());
        $svc = new designer_structure_finalize_validation_service($mock);

        $errors = $svc->validate([
            'course_structure' => [
                'title' => 'T',
                'sections' => [
                    [
                        'title' => 'S',
                        'modules' => [
                            [
                                'type' => 'page',
                                'title' => 'Title',
                                'summary' => 'Summary text long enough.',
                                'instructions' => 'short',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertNotEmpty($errors);
        $joined = implode(' ', $errors);
        $this->assertStringContainsString('Instructions must be at least', $joined);
    }

    public function test_empty_module_title_returns_error(): void {
        $mock = $this->createMock(module_types_service::class);
        $mock->method('get_module_types_cached')->willReturn($this->catalogue_page_only());
        $svc = new designer_structure_finalize_validation_service($mock);

        $errors = $svc->validate([
            'course_structure' => [
                'title' => 'T',
                'sections' => [
                    [
                        'title' => 'S',
                        'modules' => [
                            [
                                'type' => 'page',
                                'title' => '',
                                'summary' => 'Summary text long enough.',
                                'instructions' => '1234567890 enough',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertNotEmpty($errors);
    }

    public function test_empty_module_title_field_message_has_no_section_prefix(): void {
        $mock = $this->createMock(module_types_service::class);
        $mock->method('get_module_types_cached')->willReturn($this->catalogue_page_only());
        $svc = new designer_structure_finalize_validation_service($mock);

        $issues = $svc->validate_with_field_issues([
            'course_structure' => [
                'title' => 'T',
                'sections' => [
                    [
                        'title' => 'S',
                        'modules' => [
                            [
                                'type' => 'page',
                                'title' => '',
                                'summary' => 'Summary text long enough.',
                                'instructions' => '1234567890 enough',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertNotEmpty($issues);
        $titleissue = null;
        foreach ($issues as $row) {
            if (($row['path'] ?? '') === 'sections[0].modules[0].title') {
                $titleissue = $row;
                break;
            }
        }
        $this->assertNotNull($titleissue);
        $this->assertStringContainsString('activity title', $titleissue['message']);
        $this->assertStringNotContainsString('Section 1', $titleissue['message']);
    }

    public function test_empty_module_summary_is_allowed(): void {
        $mock = $this->createMock(module_types_service::class);
        $mock->method('get_module_types_cached')->willReturn($this->catalogue_page_only());
        $svc = new designer_structure_finalize_validation_service($mock);

        $issues = $svc->validate_issues_for_path([
            'course_structure' => [
                'title' => 'T',
                'sections' => [
                    [
                        'title' => 'S',
                        'modules' => [
                            [
                                'type' => 'page',
                                'title' => 'Real title',
                                'summary' => '',
                                'instructions' => '1234567890 enough',
                            ],
                        ],
                    ],
                ],
            ],
        ], 'sections[0].modules[0].summary');

        $this->assertSame([], $issues);
    }

    public function test_validate_issues_for_path_empty_path_returns_empty(): void {
        $mock = $this->createMock(module_types_service::class);
        $mock->method('get_module_types_cached')->willReturn($this->catalogue_page_only());
        $svc = new designer_structure_finalize_validation_service($mock);

        $root = [
            'course_structure' => [
                'title' => '',
                'sections' => [],
            ],
        ];

        $this->assertSame([], $svc->validate_issues_for_path($root, ''));
        $this->assertNotEmpty($svc->validate_with_field_issues($root));
    }

    public function test_validate_issues_for_path_returns_only_matching_field(): void {
        $mock = $this->createMock(module_types_service::class);
        $mock->method('get_module_types_cached')->willReturn($this->catalogue_page_only());
        $svc = new designer_structure_finalize_validation_service($mock);

        $root = [
            'course_structure' => [
                'title' => 'T',
                'sections' => [
                    [
                        'title' => 'S',
                        'modules' => [
                            [
                                'type' => 'page',
                                'title' => '',
                                'summary' => '',
                                'instructions' => '',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $all = $svc->validate_with_field_issues($root);
        $this->assertGreaterThan(1, count($all));

        $titleonly = $svc->validate_issues_for_path($root, 'sections[0].modules[0].title');
        $this->assertCount(1, $titleonly);
        $this->assertSame('sections[0].modules[0].title', $titleonly[0]['path']);
    }

    public function test_course_title_exceeds_limit_returns_error(): void {
        $mock = $this->createMock(module_types_service::class);
        $mock->method('get_module_types_cached')->willReturn($this->catalogue_page_only());
        $svc = new designer_structure_finalize_validation_service($mock);

        $errors = $svc->validate([
            'course_structure' => [
                'title' => str_repeat('a', 300),
                'sections' => [],
            ],
        ]);

        $this->assertCount(1, $errors);
    }

    /**
     * Build a one-module structure whose module carries the given instructions.
     *
     * @param string $instructions Module instructions.
     * @return array
     */
    private function structure_with_instructions(string $instructions): array {
        return [
            'course_structure' => [
                'title' => 'T',
                'sections' => [
                    [
                        'title' => 'S',
                        'modules' => [
                            [
                                'type' => 'page',
                                'title' => 'Title',
                                'summary' => 'Summary text long enough.',
                                'instructions' => $instructions,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_instructions_at_shared_maximum_pass(): void {
        $mock = $this->createMock(module_types_service::class);
        $mock->method('get_module_types_cached')->willReturn($this->catalogue_page_only());
        $svc = new designer_structure_finalize_validation_service($mock);

        $instructions = str_repeat('é', input_limits::MAX_INSTRUCTIONS_CHARS);

        $this->assertSame([], $svc->validate_with_field_issues($this->structure_with_instructions($instructions)));
    }

    public function test_instructions_over_shared_maximum_report_one_issue(): void {
        $mock = $this->createMock(module_types_service::class);
        $mock->method('get_module_types_cached')->willReturn($this->catalogue_page_only());
        $svc = new designer_structure_finalize_validation_service($mock);

        $instructions = str_repeat('é', input_limits::MAX_INSTRUCTIONS_CHARS + 1);

        $issues = $svc->validate_with_field_issues($this->structure_with_instructions($instructions));

        $this->assertCount(1, $issues);
        $this->assertSame('sections[0].modules[0].instructions', $issues[0]['path']);
        $this->assertSame(
            get_string('designerstructurevalidate_fill_instructions_too_long', 'local_dixeo',
                ['max' => input_limits::MAX_INSTRUCTIONS_CHARS]),
            $issues[0]['message']
        );
    }
}
