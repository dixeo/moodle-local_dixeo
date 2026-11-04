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
 * Tests for {@see \local_dixeo\service\course_certificate_service} availability payload.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\service\course_certificate_service;
use local_dixeo\service\plugin_installation_service;

/**
 * Unit tests for course certificate service.
 *
 * @covers \local_dixeo\service\course_certificate_service
 */
final class course_certificate_service_test extends \advanced_testcase {
    public function test_is_course_completed_availability_plugin_enabled_returns_bool(): void {
        $result = course_certificate_service::is_course_completed_availability_plugin_enabled();
        $this->assertIsBool($result);
    }

    public function test_course_completed_availability_json_shape(): void {
        $service = new course_certificate_service();
        $method = new \ReflectionMethod(course_certificate_service::class, 'build_course_completed_availability_json');
        $method->setAccessible(true);
        $json = $method->invoke($service);
        $data = json_decode($json, true);
        $this->assertIsArray($data);
        $this->assertSame('&', $data['op']);
        $this->assertSame([true], $data['showc']);
        $this->assertCount(1, $data['c']);
        $this->assertSame('coursecompleted', $data['c'][0]['type']);
        $this->assertSame('1', $data['c'][0]['id']);
        $this->assertSame(0, $data['c'][0]['courseid']);
    }

    /**
     * Availability requires both certificate plugins and the template table.
     */
    public function test_is_available_requires_both_plugins(): void {
        global $DB;

        $expected = \core_plugin_manager::instance()->get_plugin_info('mod_coursecertificate') !== null
            && \core_plugin_manager::instance()->get_plugin_info('tool_certificate') !== null
            && $DB->get_manager()->table_exists('tool_certificate_templates');

        $this->assertSame($expected, course_certificate_service::is_available());
        if (!plugin_installation_service::is_plugin_installed('tool_certificate')) {
            $this->assertFalse(course_certificate_service::is_available());
        }
        if (!plugin_installation_service::is_plugin_installed('mod_coursecertificate')) {
            $this->assertFalse(course_certificate_service::is_available());
        }
    }
}
