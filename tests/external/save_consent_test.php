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

namespace local_dixeo\external;

use local_dixeo\service\consent_service;

/**
 * The review web service records choices only for the user themself.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_dixeo\external\save_consent
 */
final class save_consent_test extends \advanced_testcase {
    /**
     * Saving and withdrawing are refused while an administrator is logged in as the user.
     */
    public function test_choices_refused_while_logged_in_as(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $accepted = $this->getDataGenerator()->create_user();
        (new consent_service())->accept((int) $accepted->id);

        foreach ([[$user, 'saveprocessing', true], [$accepted, 'withdrawterms', false]] as [$target, $action, $processing]) {
            $this->setAdminUser();
            \core\session\manager::loginas((int) $target->id, \context_system::instance());
            $rows = $DB->count_records(consent_service::TABLE);
            try {
                save_consent::execute($action, $processing);
                $this->fail($action . ' must be refused while logged in as.');
            } catch (\moodle_exception $e) {
                $this->assertSame('error:consent_loggedinas', $e->errorcode, $action);
            }
            $this->assertSame($rows, $DB->count_records(consent_service::TABLE), $action);
        }
        $this->assertFalse((new consent_service())->terms_current((int) $user->id));
        $this->assertTrue((new consent_service())->terms_current((int) $accepted->id));
    }

    /**
     * The user themself can save and withdraw.
     */
    public function test_choices_recorded_for_the_user(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        save_consent::execute('saveprocessing', true);
        $this->assertTrue((new consent_service())->terms_current((int) $user->id));

        save_consent::execute('withdrawterms', false);
        $this->assertFalse((new consent_service())->terms_current((int) $user->id));
    }
}
