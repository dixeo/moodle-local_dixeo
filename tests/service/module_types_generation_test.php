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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests for resolving a generation type to a Moodle activity.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service;

use local_dixeo\api\client;
use local_dixeo\api\exception\api_exception;

/**
 * Catalogue rows decide which activity a generation type may create.
 *
 * @covers \local_dixeo\service\module_types_service
 */
final class module_types_generation_test extends \advanced_testcase {
    public function test_catalogue_maps_an_installed_supported_type(): void {
        $this->resetAfterTest();
        $service = $this->service_with_rows([
            ['type' => 'page', 'component' => 'mod_page', 'supported' => true],
            ['type' => 'h5p_quiz', 'component' => 'mod_h5pactivity', 'supported' => true],
            ['type' => 'quiz', 'component' => 'mod_quiz', 'supported' => false],
        ]);

        $this->assertSame('page', $service->resolve_generation_module('page'));
        if (plugin_installation_service::is_component_installed('mod_h5pactivity')) {
            $this->assertSame('h5pactivity', $service->resolve_generation_module('h5p_quiz'));
        } else {
            $this->assertNull($service->resolve_generation_module('h5p_quiz'));
        }
        $this->assertNull($service->resolve_generation_module('quiz'));
        $this->assertNull($service->resolve_generation_module('forum'));
        $this->assertNull($service->resolve_generation_module('quiz;drop'));
    }

    public function test_installed_activity_is_used_when_the_catalogue_cannot_be_loaded(): void {
        $this->resetAfterTest();
        $client = $this->createMock(client::class);
        $client->method('is_configured')->willReturn(false);
        $client->method('get')->willThrowException(new api_exception('unknown_error', 'catalogue down'));
        $service = new module_types_service($client);

        $this->assertSame('page', $service->resolve_generation_module('page'));
        $this->assertNull($service->resolve_generation_module('notamodule'));
    }

    public function test_result_type_must_be_the_request_or_its_activity(): void {
        $this->assertTrue(module_types_service::result_matches_requested_type('page', 'page', 'page'));
        $this->assertTrue(module_types_service::result_matches_requested_type('h5p_quiz', 'h5pactivity', 'h5pactivity'));
        $this->assertFalse(module_types_service::result_matches_requested_type('page', 'quiz', 'page'));
        $this->assertFalse(module_types_service::result_matches_requested_type('page', '', 'page'));
    }

    /**
     * Service whose catalogue client returns the given rows.
     *
     * @param array $rows Catalogue rows returned by the API.
     * @return module_types_service
     */
    private function service_with_rows(array $rows): module_types_service {
        $client = $this->createMock(client::class);
        $client->method('is_configured')->willReturn(true);
        $client->method('get')->willReturn($rows);
        return new module_types_service($client);
    }
}
