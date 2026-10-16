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
 * Unit tests for generation language preferences.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\service\generation_language_helper;

/**
 * Generation language is a user preference, then the interface language.
 *
 * @covers \local_dixeo\service\generation_language_helper
 */
final class generation_language_helper_test extends \advanced_testcase {
    /**
     * A stored preference is used for quiz and lesson generation. An unknown code is ignored.
     */
    public function test_preference_overrides_interface_language(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['lang' => 'en']);
        $userid = (int) $user->id;

        $this->assertSame('en', generation_language_helper::default_for_user($userid));

        $translations = get_string_manager()->get_list_of_translations();
        $other = null;
        foreach (array_keys($translations) as $code) {
            if ($code !== 'en') {
                $other = $code;
                break;
            }
        }
        if ($other !== null) {
            $this->assertSame($other, generation_language_helper::store_for_user($userid, $other));
            $this->assertSame($other, generation_language_helper::resolve('', $userid));
        }

        set_user_preference(generation_language_helper::PREFERENCE, 'xx', $userid);
        $this->assertSame('en', generation_language_helper::default_for_user($userid));
        $this->assertSame('en', generation_language_helper::resolve('', $userid));
    }

    /**
     * Only an installed language can be stored.
     */
    public function test_store_rejects_unknown_language(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->expectException(\invalid_parameter_exception::class);
        generation_language_helper::store_for_user((int) $user->id, 'xx');
    }
}
