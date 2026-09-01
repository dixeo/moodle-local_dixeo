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

namespace local_dixeo\service;

/**
 * Tests for module_generation_service payload building.
 *
 * @covers \local_dixeo\service\module_generation_service
 * @package local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class module_generation_service_test extends \advanced_testcase {
    /**
     * SetUp.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('image_generation_enabled', 1, 'local_dixeo');
        set_config('image_generation_content_mode', 'generate', 'local_dixeo');
    }

    /**
     * Content images enabled: the API is asked for images through the flag, not through instructions.
     */
    public function test_payload_carries_generate_images_flag(): void {
        $payload = (new module_generation_service())
            ->build_edit_payload('page', 'Rewrite the intro', 'Course context');

        $this->assertTrue($payload['generateImages']);
        $this->assertStringNotContainsString('[img-gen', $payload['instructions']);
        $this->assertSame('Rewrite the intro', $payload['instructions']);
    }

    /**
     * Content image generation disabled by policy: the flag is omitted.
     */
    public function test_payload_omits_flag_when_policy_disabled(): void {
        set_config('image_generation_content_mode', 'disabled', 'local_dixeo');

        $payload = (new module_generation_service())
            ->build_edit_payload('page', 'Rewrite the intro', 'Course context');

        $this->assertArrayNotHasKey('generateImages', $payload);
    }

    /**
     * Ephemeral callers opting out: the flag is omitted even when the policy allows images.
     */
    public function test_payload_omits_flag_when_generate_images_off(): void {
        $service = new module_generation_service();
        $service->set_generate_images(false);

        $payload = $service->build_edit_payload('page', 'Rewrite the intro', 'Course context');

        $this->assertArrayNotHasKey('generateImages', $payload);
    }
}
