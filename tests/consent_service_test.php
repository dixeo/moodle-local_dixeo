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

namespace local_dixeo;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;
use local_dixeo\privacy\provider;
use local_dixeo\service\consent_notice;
use local_dixeo\service\consent_service;
use local_dixeo\service\tutor_usage_recorder;
use local_dixeo\settings\config;

/**
 * Terms acceptance and tutor usage analytics.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_dixeo\service\consent_service
 * @covers \local_dixeo\service\consent_notice
 */
final class consent_service_test extends \core_privacy\tests\provider_testcase {
    /**
     * Keep the analytics consent check on.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        tutor_usage_recorder::allow_without_consent_for_fixtures(false);
    }

    /**
     * The course gate stays up until the current notice is accepted.
     */
    public function test_acceptance_clears_the_gate_and_stores_one_decision(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $service = new consent_service();

        $this->assertTrue($service->gate_required((int) $user->id, $context));
        $this->assertFalse($service->processing_current((int) $user->id));

        $service->accept((int) $user->id);

        $this->assertFalse($service->gate_required((int) $user->id, $context));
        $this->assertTrue($service->terms_current((int) $user->id));
        $this->assertTrue($service->processing_current((int) $user->id));
        $this->assertGreaterThan(0, (new tutor_usage_recorder())->record_message((int) $user->id, (int) $course->id));

        $decision = $service->latest((int) $user->id);
        $this->assertNotNull($decision);
        $this->assertSame(consent_service::ACTION_GRANTED, $decision->action);
        $this->assertSame(consent_service::VERSION, $decision->version);
        $this->assertSame(current_language(), $decision->lang);
        $this->assertSame((new consent_notice())->hash(), $decision->documenthash);
        $this->assertStringContainsString((new consent_notice())->terms_statement(), $decision->documenttext);
        $this->assertSame(1, $DB->count_records(consent_service::TABLE, ['userid' => $user->id]));
        $this->assertSame(1, $DB->count_records(consent_service::TABLE_DOCUMENT, [
            'documenthash' => $decision->documenthash,
        ]));
    }

    /**
     * Withdrawing acceptance stops analytics and deletes what was stored.
     */
    public function test_withdrawing_acceptance_deletes_analytics(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $service = new consent_service();
        $service->accept((int) $user->id);

        $recorder = new tutor_usage_recorder();
        $id = $recorder->record_message((int) $user->id, (int) $course->id);
        $this->assertGreaterThan(0, $id);

        $service->set_processing((int) $user->id, false);

        $this->assertFalse($service->processing_current((int) $user->id));
        $this->assertFalse($service->terms_current((int) $user->id));
        $this->assertSame(0, $recorder->record_message((int) $user->id, (int) $course->id));
        $this->assertFalse($DB->record_exists(tutor_usage_recorder::TABLE_EVENT, ['userid' => $user->id]));

        $latest = $service->latest((int) $user->id);
        $this->assertSame(consent_service::ACTION_WITHDRAWN, $latest->action);
        $this->assertStringContainsString((new consent_notice())->processing_statement(), $latest->documenttext);
    }

    /**
     * Withdrawing the terms asks again and leaves analytics consent in place until it is unticked.
     */
    public function test_withdrawing_terms_reopens_the_gate(): void {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $service = new consent_service();
        $service->accept((int) $user->id);

        $service->withdraw_terms((int) $user->id);

        $this->assertTrue($service->gate_required((int) $user->id, $context));
        $this->assertFalse($service->processing_current((int) $user->id));
    }

    /**
     * A changed document version or a changed text in the same language asks again.
     */
    public function test_version_or_hash_change_reprompts(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $service = new consent_service();
        $service->accept((int) $user->id);
        $this->assertFalse($service->gate_required((int) $user->id, $context));

        $decision = $service->latest((int) $user->id);
        $DB->set_field(consent_service::TABLE_DOCUMENT, 'version', '1999-01-01.1', ['id' => $decision->documentid]);
        $this->assertTrue($service->gate_required((int) $user->id, $context));

        $DB->set_field(consent_service::TABLE_DOCUMENT, 'version', consent_service::VERSION, ['id' => $decision->documentid]);
        $DB->set_field(consent_service::TABLE_DOCUMENT, 'documenthash', str_repeat('a', 64), ['id' => $decision->documentid]);
        $this->assertTrue($service->gate_required((int) $user->id, $context));
    }

    /**
     * Guests and courses with Dixeo off are not locked.
     */
    public function test_guest_and_disabled_course_skip_the_gate(): void {
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $service = new consent_service();

        $this->setGuestUser();
        $this->assertFalse($service->gate_required((int) guest_user()->id, $context));

        $this->setAdminUser();
        (new config())->set('local_dixeo', 'enabled', $context, '0');
        $user = $this->getDataGenerator()->create_user();
        $this->assertFalse($service->gate_required((int) $user->id, $context));
    }

    /**
     * Consent rows are exported and deleted with the system context.
     */
    public function test_privacy_export_and_delete(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $service = new consent_service();
        $service->accept((int) $user->id);

        $contextlist = provider::get_contexts_for_userid((int) $user->id);
        $ids = array_map('intval', $contextlist->get_contextids());
        $this->assertContains((int) \context_system::instance()->id, $ids);

        writer::reset();
        $approved = new approved_contextlist($user, 'local_dixeo', [\context_system::instance()->id]);
        provider::export_user_data($approved);
        $this->assertTrue(writer::with_context(\context_system::instance())->has_any_data());

        provider::delete_data_for_user($approved);
        $this->assertFalse($DB->record_exists(consent_service::TABLE, ['userid' => $user->id]));
    }

    /**
     * Every language pack has the same consent strings, and none copies the English sentence.
     */
    public function test_consent_strings_exist_in_every_language(): void {
        global $CFG;

        $english = $this->plugin_strings('en');
        $keys = array_values(array_filter(array_keys($english), static function (string $key): bool {
            return str_starts_with($key, 'consent_')
                || str_starts_with($key, 'privacy:metadata:consent')
                || $key === 'privacy:path:consent';
        }));
        $sorted = $keys;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $keys);
        $this->assertNotEmpty($keys);

        foreach (['de', 'es', 'fr', 'it', 'pt'] as $lang) {
            $strings = $this->plugin_strings($lang);
            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $strings, $lang . ' ' . $key);
                $this->assertNotSame($english[$key], $strings[$key], $lang . ' ' . $key);
            }
        }

        $this->assertSame(
            'I have read and agree to the Terms of Use and acknowledge the Privacy Notice.',
            $english['consent_terms_checkbox']
        );
        $this->assertSame('Terms of Use and Privacy Notice', $english['consent_title']);
        $this->assertStringContainsString('{$a->sitename}', $english['consent_terms_body']);
        $this->assertStringContainsString('{$a->version}', $english['consent_terms_body']);
        $this->assertStringContainsString('{$a->sitename}', $english['consent_privacy_body']);
        $this->assertStringContainsString('{$a->contact}', $english['consent_privacy_body']);
        $this->assertStringContainsString('{$a->apiurl}', $english['consent_privacy_body']);
        $this->assertStringContainsString("\n- ", $english['consent_privacy_body']);
        unset($CFG);
    }

    /**
     * Load one language file without the English fallback.
     *
     * @param string $lang Language code.
     * @return array
     */
    private function plugin_strings(string $lang): array {
        $string = [];
        include(__DIR__ . "/../lang/{$lang}/local_dixeo.php");
        return $string;
    }
}
