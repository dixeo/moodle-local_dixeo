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
 * Tests for the plugin installation lookup helpers.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\service\h5p_library_service;
use local_dixeo\service\plugin_installation_service;

/**
 * Unit tests for plugin installation service.
 *
 * @covers \local_dixeo\service\plugin_installation_service
 */
final class plugin_installation_service_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        h5p_library_service::reset_cache();
    }

    /**
     * An installed plugin has code and a version row. A missing plugin has neither.
     */
    public function test_is_plugin_installed_requires_code_and_a_version_row(): void {
        $this->assertTrue(plugin_installation_service::is_plugin_installed('local_dixeo'));
        $this->assertFalse(plugin_installation_service::is_plugin_installed('block_not_a_real_plugin'));
        $this->assertFalse(plugin_installation_service::is_plugin_installed('core'));

        unset_config('version', 'block_dixeo_tutor');

        $this->assertFalse(plugin_installation_service::is_plugin_installed('block_dixeo_tutor'));
    }

    public function test_resolve_module_type_plugin_name_uses_component_when_available(): void {
        $name = plugin_installation_service::resolve_module_type_plugin_name([
            'type' => 'h5p_quiz',
            'component' => 'mod_h5pactivity',
        ]);

        $this->assertSame('h5pactivity', $name);
    }

    public function test_resolve_module_type_plugin_name_falls_back_to_type_when_component_missing(): void {
        $name = plugin_installation_service::resolve_module_type_plugin_name([
            'type' => 'page',
        ]);

        $this->assertSame('page', $name);
    }

    public function test_resolve_module_type_plugin_name_ignores_non_mod_components(): void {
        $name = plugin_installation_service::resolve_module_type_plugin_name([
            'type' => 'foo',
            'component' => 'block_foo',
        ]);

        $this->assertSame('foo', $name);
    }

    public function test_resolve_module_type_plugin_name_returns_empty_when_unresolvable(): void {
        $name = plugin_installation_service::resolve_module_type_plugin_name([]);

        $this->assertSame('', $name);
    }

    public function test_is_module_type_installed_true_for_classic_type(): void {
        $installed = plugin_installation_service::is_module_type_installed([
            'type' => 'page',
            'component' => 'mod_page',
        ]);

        $this->assertTrue($installed);
    }

    public function test_is_module_type_installed_false_when_plugin_missing(): void {
        $installed = plugin_installation_service::is_module_type_installed([
            'type' => 'inexistent_module_xyz',
            'component' => 'mod_inexistent_module_xyz',
        ]);

        $this->assertFalse($installed);
    }

    public function test_is_module_type_installed_routes_h5p_variants_to_h5pactivity(): void {
        $installed = plugin_installation_service::is_module_type_installed([
            'type' => 'h5p_quiz',
            'component' => 'mod_h5pactivity',
        ]);

        $this->assertTrue($installed);
    }

    public function test_is_module_type_installed_false_when_h5p_library_requirement_missing(): void {
        $installed = plugin_installation_service::is_module_type_installed([
            'type' => 'h5p_crossword',
            'component' => 'mod_h5pactivity',
            'requirements' => ['H5P.Crossword 0.5'],
        ]);

        $this->assertFalse($installed);
    }

    public function test_is_module_type_installed_true_when_h5p_library_requirement_present(): void {
        global $DB;

        $DB->insert_record('h5p_libraries', (object) [
            'machinename' => 'H5P.Crossword',
            'majorversion' => 0,
            'minorversion' => 5,
            'patchversion' => 0,
            'runnable' => 1,
            'enabled' => 1,
            'fullscreen' => 0,
            'embedtypes' => '',
            'preloadedjs' => '',
            'preloadedcss' => '',
            'droplibrarycss' => '',
            'semantics' => '[]',
            'addto' => '',
            'coremajor' => null,
            'coreminor' => null,
            'metadatasettings' => '',
            'tutorial' => '',
            'example' => '',
            'title' => 'H5P Crossword',
        ]);

        $installed = plugin_installation_service::is_module_type_installed([
            'type' => 'h5p_crossword',
            'component' => 'mod_h5pactivity',
            'requirements' => ['H5P.Crossword 0.5'],
        ]);

        $this->assertTrue($installed);
    }
}
