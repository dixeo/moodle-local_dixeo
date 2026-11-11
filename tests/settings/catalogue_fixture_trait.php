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

namespace local_dixeo\settings;

/**
 * Adds test-only settings to the catalogue for the current request.
 *
 * The trait primes the request-cache memo that {@see catalogue::load()} reads, using the same
 * cache parameters as catalogue::memo() (MODE_REQUEST, local_dixeo, settingscatalogue, keyed by
 * language, holding the clock time and the definitions). Any stored setting write purges that
 * memo, so a test calls {@see self::prime_catalogue_fixtures()} again after each write before it
 * reads a fixture setting.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait catalogue_fixture_trait {
    /**
     * Fixture catalogue rows, owned by local_dixeo.
     *
     * test_course_text is a course-only textarea.
     *
     * @return array[]
     */
    protected function catalogue_fixture_rows(): array {
        return [
            [
                'key' => 'test_course_text',
                'shortname' => 'test_course_text',
                'component' => 'local_dixeo',
                'type' => 'textarea',
                'default' => '',
                'levels' => ['course'],
                'feature' => 'courses',
                'plugingroup' => 'dixeo_core',
                'help' => 'enabled_help',
                'sensitive' => false,
            ],
        ];
    }

    /**
     * Make catalogue::load() return the installed definitions plus the fixture rows.
     */
    protected function prime_catalogue_fixtures(): void {
        $installed = catalogue::load();
        $fixtures = catalogue::from_definitions($this->catalogue_fixture_rows());
        $definitions = array_merge($installed, $fixtures);
        \cache::make_from_params(\cache_store::MODE_REQUEST, 'local_dixeo', 'settingscatalogue')
            ->set(current_language(), [
                't' => \core\di::get(\core\clock::class)->time(),
                'd' => $definitions,
            ]);
    }
}
