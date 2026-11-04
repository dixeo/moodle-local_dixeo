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
 * Tests for course template list normalisation and caching.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\api\client;
use local_dixeo\service\course_template_service;
use ReflectionMethod;

/**
 * Unit tests for course template service.
 *
 * @covers \local_dixeo\service\course_template_service
 */
final class course_template_service_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        \cache_helper::purge_all(true);
    }

    public function test_normalise_templates_skips_invalid_rows_and_preserves_description(): void {
        $service = new course_template_service();
        $method = new ReflectionMethod($service, 'normalise_templates');
        $method->setAccessible(true);

        $result = $method->invoke($service, [
            ['id' => 'tpl-1', 'name' => 'ABC Learning', 'description' => 'Section-based design.'],
            ['id' => '', 'name' => 'Missing id'],
            ['id' => 'tpl-2', 'name' => ''],
            'not-an-array',
            ['id' => 'tpl-3', 'name' => 'No description'],
            ['id' => 'tpl-4', 'name' => 'Escaped', 'description' => '<b>Bold</b> text'],
        ]);

        $this->assertCount(3, $result);
        $this->assertSame('tpl-1', $result[0]['id']);
        $this->assertSame('ABC Learning', $result[0]['name']);
        $this->assertSame('Section-based design.', $result[0]['description']);
        $this->assertSame('', $result[1]['description']);
        $this->assertSame(s('<b>Bold</b> text'), $result[2]['description']);
    }

    public function test_get_cached_templates_and_choices_use_api_data(): void {
        $mockclient = $this->createMock(client::class);
        $mockclient->method('is_configured')->willReturn(true);
        $mockclient->method('get')->willReturn([
            ['id' => 'tpl-a', 'name' => 'Template A', 'description' => 'First template.'],
            ['id' => 'tpl-b', 'name' => 'Template B'],
        ]);

        $service = new course_template_service($mockclient);

        $templates = $service->get_cached_templates();
        $this->assertCount(2, $templates);
        $this->assertSame('First template.', $templates[0]['description']);
        $this->assertSame('', $templates[1]['description']);

        $choices = $service->get_cached_choices();
        $this->assertSame([
            'tpl-a' => 'Template A',
            'tpl-b' => 'Template B',
        ], $choices);

        // Second call should hit cache (client get still only once).
        $this->assertSame($templates, $service->get_cached_templates());
    }

    public function test_get_cached_templates_returns_empty_when_not_configured(): void {
        $mockclient = $this->createMock(client::class);
        $mockclient->method('is_configured')->willReturn(false);

        $service = new course_template_service($mockclient);

        $this->assertSame([], $service->get_cached_templates());
        $this->assertSame([], $service->get_cached_choices());
    }

    /**
     * Names are stored as plain text, and a name that is empty once cleaned is skipped.
     */
    public function test_template_names_are_stored_as_plain_text(): void {
        $mockclient = $this->createMock(client::class);
        $mockclient->method('is_configured')->willReturn(true);
        $mockclient->method('get')->willReturn([
            ['id' => 'tpl-a', 'name' => '<b>Bold</b> <script>alert(1)</script>plan'],
            ['id' => 'tpl-b', 'name' => '<img src=x onerror=alert(1)>'],
        ]);

        $choices = (new course_template_service($mockclient))->get_cached_choices();

        $this->assertCount(1, $choices);
        $this->assertStringNotContainsString('<', $choices['tpl-a']);
        $this->assertStringContainsString('Bold', $choices['tpl-a']);
        $this->assertStringContainsString('plan', $choices['tpl-a']);
    }

    /**
     * peek_cached_choices() never calls the API: empty on a cold cache, the cached choices once warm.
     */
    public function test_peek_cached_choices_reads_the_cache_only(): void {
        $mockclient = $this->createMock(client::class);
        $mockclient->method('is_configured')->willReturn(true);
        $mockclient->expects($this->once())->method('get')->willReturn([
            ['id' => 'tpl-a', 'name' => 'Template A'],
        ]);
        $service = new course_template_service($mockclient);

        $this->assertSame([], $service->peek_cached_choices());

        $service->get_cached_choices();
        $this->assertSame(['tpl-a' => 'Template A'], $service->peek_cached_choices());
    }
}
