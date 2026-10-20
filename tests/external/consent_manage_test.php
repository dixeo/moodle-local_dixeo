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
 * The My preferences review reads and stores the current user's choice.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_dixeo\external\get_consent_manage
 * @covers \local_dixeo\external\save_consent
 */
final class consent_manage_test extends \advanced_testcase {
    /**
     * The status line and the review markup follow the stored choice.
     */
    public function test_review_and_withdraw(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->setUser($user);

        $service = new consent_service();
        $service->accept((int) $user->id);

        $read = get_consent_manage::execute();
        $this->assertTrue($read['show']);
        $this->assertSame($service->terms_status_text((int) $user->id), $read['termsstatus']);
        $this->assertStringContainsString(get_string('consent_title', 'local_dixeo'), $read['html']);
        $this->assertStringContainsString('alert-info', $read['html']);
        $this->assertStringContainsString($service->acceptance_notice((int) $user->id), $read['html']);
        $this->assertStringNotContainsString('local-dixeo-consent-status', $read['html']);
        $this->assertStringContainsString(get_string('consent_withdraw_terms', 'local_dixeo'), $read['html']);
        $this->assertStringContainsString(get_string('consent_withdraw_confirm', 'local_dixeo'), $read['html']);
        $this->assertStringContainsString(get_string('consent_close', 'local_dixeo'), $read['html']);
        $this->assertMatchesRegularExpression(
            '/id="local-dixeo-consent-manage-terms"[\s\S]*?disabled/',
            $read['html']
        );
        $this->assertMatchesRegularExpression(
            '/id="local-dixeo-consent-manage-processing"[\s\S]*?disabled/',
            $read['html']
        );
        $this->assertStringNotContainsString(get_string('consent_save_processing', 'local_dixeo'), $read['html']);

        $service->set_processing((int) $user->id, false);
        $open = get_consent_manage::execute();
        $this->assertStringContainsString(get_string('consent_save_processing', 'local_dixeo'), $open['html']);

        $saved = save_consent::execute('saveprocessing', true);
        $this->assertTrue($service->processing_current((int) $user->id));
        $this->assertFalse($saved['reload']);
        $this->assertMatchesRegularExpression(
            '/id="local-dixeo-consent-manage-processing"[\s\S]*?disabled/',
            $saved['html']
        );

        $withdrawn = save_consent::execute('withdrawterms', false);
        $this->assertTrue($withdrawn['reload']);
        $this->assertFalse($service->terms_current((int) $user->id));
        $this->assertStringNotContainsString(get_string('consent_withdraw_terms', 'local_dixeo'), $withdrawn['html']);
    }
}
