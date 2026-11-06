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
 * Tests for the request memo of {@see \local_dixeo\settings\catalogue::load()}.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\settings;

/**
 * The catalogue is scanned once per request and language, and a write scans it again.
 *
 * @covers \local_dixeo\settings\catalogue
 */
final class catalogue_memo_test extends \advanced_testcase {
    /** @var string Key added to the memoised definitions to tell a memo hit from a fresh scan. */
    private const MARKER = 'zz_memo_marker';

    /**
     * Request cache that holds the memoised definitions.
     *
     * @return \cache
     */
    private function memo(): \cache {
        return \cache::make_from_params(\cache_store::MODE_REQUEST, 'local_dixeo', 'settingscatalogue');
    }

    /**
     * Load the catalogue, then add a marker entry to the memo for the current language.
     *
     * @return int Clock time stored with the memo.
     */
    private function plant_marker(): int {
        $definitions = catalogue::load();
        $cached = $this->memo()->get(current_language());
        $this->assertIsArray($cached);
        $definitions[self::MARKER] = $definitions['enabled'];
        $this->memo()->set(current_language(), ['t' => $cached['t'], 'd' => $definitions]);
        return (int) $cached['t'];
    }

    /**
     * A second load in the same request returns the memo.
     */
    public function test_second_load_returns_the_memo(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen();
        $this->plant_marker();

        $this->assertArrayHasKey(self::MARKER, catalogue::load());
    }

    /**
     * Reset forgets the memo.
     */
    public function test_reset_loads_again(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen();
        $this->plant_marker();

        catalogue::reset();

        $definitions = catalogue::load();
        $this->assertArrayNotHasKey(self::MARKER, $definitions);
        $this->assertArrayHasKey('enabled', $definitions);
    }

    /**
     * The memo is kept per language.
     */
    public function test_language_switch_loads_again(): void {
        global $SESSION;

        $this->resetAfterTest();
        $this->mock_clock_with_frozen();
        $this->plant_marker();

        // A language code with no installed pack: strings fall back to English, the memo key changes.
        $SESSION->forcelang = 'xx_test';
        try {
            $this->assertSame('xx_test', current_language());
            $this->assertArrayNotHasKey(self::MARKER, catalogue::load());
            $this->assertIsArray($this->memo()->get('xx_test'));
        } finally {
            unset($SESSION->forcelang);
        }
        $this->assertArrayHasKey(self::MARKER, catalogue::load());
    }

    /**
     * The memo expires after 30 seconds.
     */
    public function test_memo_expires(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen();
        $this->plant_marker();

        $clock->bump(29);
        $this->assertArrayHasKey(self::MARKER, catalogue::load());

        $clock->bump(2);
        $this->assertArrayNotHasKey(self::MARKER, catalogue::load());
    }

    /**
     * A stored setting write forgets the memo.
     */
    public function test_config_set_invalidates_the_memo(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->mock_clock_with_frozen();
        $this->plant_marker();

        (new config())->set('local_dixeo', 'learning_intent', \context_course::instance(
            (int) $this->getDataGenerator()->create_course()->id
        ), 'Know the basics');

        $this->assertArrayNotHasKey(self::MARKER, catalogue::load());
    }
}
