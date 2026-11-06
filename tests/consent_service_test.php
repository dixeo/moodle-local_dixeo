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
use local_dixeo\exception\consent_required_exception;
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
        $this->assertSame((new consent_notice())->terms_hash(), $decision->documenthash);
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
     * A changed document version or a changed terms hash in the same language asks again.
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
        $this->start_new_request();
        $this->assertTrue($service->gate_required((int) $user->id, $context));

        $DB->set_field(consent_service::TABLE_DOCUMENT, 'version', consent_service::VERSION, ['id' => $decision->documentid]);
        $DB->set_field(consent_service::TABLE_DOCUMENT, 'documenthash', str_repeat('a', 64), ['id' => $decision->documentid]);
        $this->start_new_request();
        $this->assertTrue($service->gate_required((int) $user->id, $context));
    }

    /**
     * Site name, support email, Dixeo API URL and site URL are not part of the terms.
     */
    public function test_site_values_do_not_reprompt(): void {
        $user = $this->getDataGenerator()->create_user();
        $service = new consent_service();
        $service->accept((int) $user->id);

        $notice = new consent_notice();
        $termshash = $notice->terms_hash();

        $this->change_site_values();
        $this->start_new_request();

        $this->assertTrue((new consent_service())->terms_current((int) $user->id));
        $this->assertSame($termshash, $notice->terms_hash());
    }

    /**
     * An acceptance after a site change points at the notice stored first for the same terms.
     */
    public function test_acceptance_after_site_change_reuses_the_stored_notice(): void {
        global $DB, $SITE;

        $first = $this->getDataGenerator()->create_user();
        $second = $this->getDataGenerator()->create_user();
        $oldname = $SITE->fullname;
        (new consent_service())->accept((int) $first->id);
        $firstdecision = (new consent_service())->latest((int) $first->id);

        $this->change_site_values();
        $this->start_new_request();
        (new consent_service())->accept((int) $second->id);
        $seconddecision = (new consent_service())->latest((int) $second->id);

        $this->assertSame(1, $DB->count_records(consent_service::TABLE_DOCUMENT, [
            'documenthash' => (new consent_notice())->terms_hash(),
        ]));
        $this->assertEquals($firstdecision->documentid, $seconddecision->documentid);
        $text = $DB->get_field(consent_service::TABLE_DOCUMENT, 'documenttext', ['id' => $firstdecision->documentid]);
        $this->assertStringContainsString($oldname, $text);
        $this->assertStringNotContainsString('Renamed Dixeo Site', $text);
    }

    /**
     * A local customisation of a consent string in the same language asks again.
     */
    public function test_string_edit_reprompts(): void {
        global $CFG;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $service = new consent_service();
        $service->accept((int) $user->id);
        $this->assertFalse($service->gate_required((int) $user->id, $context));

        $dir = $CFG->langlocalroot . '/en_local';
        make_writable_directory($dir);
        $file = $dir . '/local_dixeo.php';
        file_put_contents($file, "<?php\n\$string['consent_terms_body'] = 'Edited terms for {\$a->sitename}.';\n");
        get_string_manager()->reset_caches(true);
        $this->start_new_request();

        try {
            $this->assertSame('Edited terms for {$a->sitename}.', get_string('consent_terms_body', 'local_dixeo'));
            $this->assertTrue((new consent_service())->gate_required((int) $user->id, $context));
        } finally {
            unlink($file);
            get_string_manager()->reset_caches(true);
        }
    }

    /**
     * An acceptance stored with the earlier rendered-text hash is asked again.
     */
    public function test_old_format_acceptance_is_asked_again(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $service = new consent_service();
        $service->accept((int) $user->id);
        $this->assertFalse($service->gate_required((int) $user->id, $context));

        $oldhash = hash(
            'sha256',
            consent_service::VERSION . "\n" . current_language() . "\n" . (new consent_notice())->document_text()
        );
        $DB->set_field(consent_service::TABLE_DOCUMENT, 'documenthash', $oldhash, [
            'id' => $service->latest((int) $user->id)->documentid,
        ]);
        $this->start_new_request();

        $this->assertTrue($service->gate_required((int) $user->id, $context));
    }

    /**
     * An acceptance in another language counts for the same version, whatever its hash.
     */
    public function test_other_language_counts_for_same_version(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $service = new consent_service();
        $service->accept((int) $user->id);
        $documentid = $service->latest((int) $user->id)->documentid;
        $DB->update_record(consent_service::TABLE_DOCUMENT, (object) [
            'id' => $documentid,
            'lang' => 'xx',
            'documenthash' => str_repeat('a', 64),
        ]);
        $this->start_new_request();
        $this->assertTrue($service->terms_current((int) $user->id));

        $DB->set_field(consent_service::TABLE_DOCUMENT, 'version', '1999-01-01.1', ['id' => $documentid]);
        $this->start_new_request();
        $this->assertFalse($service->terms_current((int) $user->id));
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
     * require_accepted() throws the given error until the terms are accepted, and never for guests or Dixeo off.
     */
    public function test_require_accepted(): void {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $service = new consent_service();

        try {
            $service->require_accepted((int) $user->id, $context);
            $this->fail('Unaccepted terms must stop the request.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:consentrequired', $e->errorcode);
            $this->assertSame('local_dixeo', $e->module);
        }

        try {
            $service->require_accepted((int) $user->id, $context, 'consentrequired', 'block_dixeo_tutor');
            $this->fail('Unaccepted terms must stop the request.');
        } catch (\moodle_exception $e) {
            $this->assertSame('consentrequired', $e->errorcode);
            $this->assertSame('block_dixeo_tutor', $e->module);
        }

        $service->require_accepted((int) guest_user()->id, $context);
        $service->require_accepted((int) $user->id, \context_system::instance());

        $this->setAdminUser();
        (new config())->set('local_dixeo', 'enabled', $context, '0');
        $service->require_accepted((int) $user->id, $context);

        (new config())->set('local_dixeo', 'enabled', $context, '1');
        $service->accept((int) $user->id);
        $service->require_accepted((int) $user->id, $context);
        $this->assertTrue($service->terms_current((int) $user->id));
    }

    /**
     * Both require methods throw consent_required_exception with the default or the caller-chosen string.
     */
    public function test_require_methods_throw_consent_required_exception(): void {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $service = new consent_service();
        $userid = (int) $user->id;

        $calls = [
            'course default' => fn() => $service->require_accepted($userid, $context),
            'course custom' => fn() => $service->require_accepted($userid, $context, 'consentrequired', 'block_dixeo_tutor'),
            'site default' => fn() => $service->require_accepted_for_course($userid, SITEID),
            'site custom' => fn() => $service->require_accepted_for_course(
                $userid,
                (int) $course->id,
                'consentrequired',
                'block_dixeo_tutor'
            ),
        ];
        foreach ($calls as $label => $call) {
            try {
                $call();
                $this->fail($label . ': unaccepted terms must stop the request.');
            } catch (consent_required_exception $e) {
                $custom = str_ends_with($label, 'custom');
                $this->assertSame($custom ? 'consentrequired' : 'error:consentrequired', $e->errorcode, $label);
                $this->assertSame($custom ? 'block_dixeo_tutor' : 'local_dixeo', $e->module, $label);
            }
        }
    }

    /**
     * The course-or-site gate follows Dixeo at the site layer for SITEID and the course switch for a course.
     */
    public function test_gate_required_for_course(): void {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $offcourse = $this->getDataGenerator()->create_course();
        $service = new consent_service();
        $userid = (int) $user->id;

        $this->assertTrue($service->gate_required_for_course($userid, SITEID));
        $this->assertTrue($service->gate_required_for_course($userid, 0));
        $this->assertTrue($service->gate_required_for_course($userid, (int) $course->id));
        $this->assertTrue($service->gate_required_for_course($userid, (int) $course->id + 1000));
        $this->assertFalse($service->gate_required_for_course((int) guest_user()->id, SITEID));
        $this->assertFalse($service->gate_required_for_course(0, (int) $course->id));

        $this->setAdminUser();
        (new config())->set('local_dixeo', 'enabled', \context_course::instance($offcourse->id), '0');
        $this->assertFalse($service->gate_required_for_course($userid, (int) $offcourse->id));
        $this->assertTrue($service->gate_required_for_course($userid, SITEID));

        (new config())->set('local_dixeo', 'enabled', \context_system::instance(), '0');
        $this->assertFalse($service->gate_required_for_course($userid, SITEID));
        $this->assertFalse($service->gate_required_for_course($userid, (int) $course->id));

        (new config())->set('local_dixeo', 'enabled', \context_system::instance(), '1');
        $service->accept($userid);
        $this->assertFalse($service->gate_required_for_course($userid, SITEID));
        $this->assertFalse($service->gate_required_for_course($userid, (int) $course->id));
    }

    /**
     * require_accepted_for_course() throws the given error for the site and for a course until acceptance.
     */
    public function test_require_accepted_for_course(): void {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $service = new consent_service();
        $userid = (int) $user->id;

        foreach ([SITEID, (int) $course->id] as $courseid) {
            try {
                $service->require_accepted_for_course($userid, $courseid);
                $this->fail('Unaccepted terms must stop the request.');
            } catch (\moodle_exception $e) {
                $this->assertSame('error:consentrequired', $e->errorcode);
                $this->assertSame('local_dixeo', $e->module);
            }
        }

        try {
            $service->require_accepted_for_course($userid, SITEID, 'consentrequired', 'block_dixeo_tutor');
            $this->fail('Unaccepted terms must stop the request.');
        } catch (\moodle_exception $e) {
            $this->assertSame('consentrequired', $e->errorcode);
            $this->assertSame('block_dixeo_tutor', $e->module);
        }

        $service->require_accepted_for_course((int) guest_user()->id, SITEID);
        $service->require_accepted_for_course(0, (int) $course->id);

        $this->setAdminUser();
        (new config())->set('local_dixeo', 'enabled', \context_course::instance($course->id), '0');
        $service->require_accepted_for_course($userid, (int) $course->id);

        (new config())->set('local_dixeo', 'enabled', \context_system::instance(), '0');
        $service->require_accepted_for_course($userid, SITEID);

        (new config())->set('local_dixeo', 'enabled', \context_system::instance(), '1');
        $this->getDataGenerator()->get_plugin_generator('local_dixeo')->create_terms_acceptance(['userid' => $userid]);
        $service->require_accepted_for_course($userid, SITEID);
        $this->assertTrue($service->terms_current($userid));
    }

    /**
     * The answer kept for the request follows accept() and withdraw_terms() in the same request.
     */
    public function test_terms_memo_follows_decisions_in_the_same_request(): void {
        $user = $this->getDataGenerator()->create_user();
        $userid = (int) $user->id;

        $this->assertFalse((new consent_service())->terms_current($userid));
        (new consent_service())->accept($userid);
        $this->assertTrue((new consent_service())->terms_current($userid));
        (new consent_service())->withdraw_terms($userid);
        $this->assertFalse((new consent_service())->terms_current($userid));
        (new consent_service())->accept($userid);
        $this->assertTrue((new consent_service())->terms_current($userid));
    }

    /**
     * A withdrawal recorded by another process is seen once the memoised answer expires.
     */
    public function test_terms_memo_expires_after_withdrawal_by_another_process(): void {
        global $DB;

        $clock = $this->mock_clock_with_frozen();
        $user = $this->getDataGenerator()->create_user();
        $userid = (int) $user->id;
        $service = new consent_service();
        $service->accept($userid);
        $this->assertTrue($service->terms_current($userid));

        $latest = $service->latest($userid);
        $DB->insert_record(consent_service::TABLE, (object) [
            'userid' => $userid,
            'documentid' => (int) $latest->documentid,
            'action' => consent_service::ACTION_WITHDRAWN,
            'timecreated' => (int) $latest->timecreated + 1,
        ]);
        $this->assertTrue($service->terms_current($userid));

        $clock->bump(31);
        $this->assertFalse($service->terms_current($userid));
    }

    /**
     * An acceptance recorded by another process is seen once the memoised refusal expires.
     */
    public function test_terms_memo_expires_after_acceptance_by_another_process(): void {
        global $DB;

        $clock = $this->mock_clock_with_frozen();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $userid = (int) $user->id;
        $service = new consent_service();
        $this->assertFalse($service->terms_current($userid));

        $service->accept((int) $other->id);
        $documentid = (int) $service->latest((int) $other->id)->documentid;
        $this->assertFalse($service->terms_current($userid));

        $DB->insert_record(consent_service::TABLE, (object) [
            'userid' => $userid,
            'documentid' => $documentid,
            'action' => consent_service::ACTION_GRANTED,
            'timecreated' => time(),
        ]);
        $clock->bump(29);
        $this->assertFalse($service->terms_current($userid));

        $clock->bump(2);
        $this->assertTrue($service->terms_current($userid));
    }

    /**
     * No decision is recorded or withdrawn while an administrator is logged in as the user.
     */
    public function test_decisions_refused_while_logged_in_as(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $accepted = $this->getDataGenerator()->create_user();
        $userid = (int) $user->id;
        $service = new consent_service();
        $service->accept((int) $accepted->id);
        $this->assertFalse($service->manage_context($userid)['loggedinas']);

        $this->setAdminUser();
        \core\session\manager::loginas($userid, \context_system::instance());
        $rows = $DB->count_records(consent_service::TABLE);

        $calls = [
            'accept' => fn() => $service->accept($userid),
            'set_processing on' => fn() => $service->set_processing($userid, true),
            'set_processing off' => fn() => $service->set_processing($userid, false),
            'withdraw_terms' => fn() => $service->withdraw_terms($userid),
            'withdraw accepted user' => fn() => $service->withdraw_terms((int) $accepted->id),
        ];
        foreach ($calls as $label => $call) {
            try {
                $call();
                $this->fail($label . ' must be refused while logged in as.');
            } catch (\moodle_exception $e) {
                $this->assertSame('error:consent_loggedinas', $e->errorcode, $label);
                $this->assertSame('local_dixeo', $e->module, $label);
            }
        }
        $this->assertSame($rows, $DB->count_records(consent_service::TABLE));
        $this->assertFalse($service->terms_current($userid));
        $this->assertTrue($service->terms_current((int) $accepted->id));

        $context = $service->manage_context($userid);
        $this->assertTrue($context['loggedinas']);
        $this->assertSame(get_string('error:consent_loggedinas', 'local_dixeo'), $context['loggedinasnotice']);
        $html = $service->manage_html($userid);
        $this->assertStringContainsString(s(get_string('error:consent_loggedinas', 'local_dixeo')), $html);
        $this->assertStringContainsString(get_string('consent_close', 'local_dixeo'), $html);
        $this->assertStringNotContainsString('local-dixeo-consent-save-form', $html);
        $this->assertStringNotContainsString('data-action="revoke-consent"', $html);
        $this->assertStringNotContainsString('local-dixeo-consent-revoke', $html);
        $this->assertStringNotContainsString(
            'local-dixeo-consent-revoke',
            $service->manage_html((int) $accepted->id)
        );

        $this->setUser($user);
        $service->accept($userid);
        $this->assertTrue($service->terms_current($userid));
    }

    /**
     * may_process_for() needs a real user, an existing course with Dixeo on and current terms.
     */
    public function test_may_process_for(): void {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $offcourse = $this->getDataGenerator()->create_course();
        $service = new consent_service();
        $service->accept((int) $user->id);

        $this->assertTrue($service->may_process_for((int) $user->id, (int) $course->id));
        $this->assertFalse($service->may_process_for((int) guest_user()->id, (int) $course->id));
        $this->assertFalse($service->may_process_for(0, (int) $course->id));
        $this->assertFalse($service->may_process_for((int) $user->id, SITEID));
        $this->assertFalse($service->may_process_for((int) $user->id, (int) $course->id + 1000));

        $this->setAdminUser();
        (new config())->set('local_dixeo', 'enabled', \context_course::instance($offcourse->id), '0');
        $this->assertFalse($service->may_process_for((int) $user->id, (int) $offcourse->id));

        $service->withdraw_terms((int) $user->id);
        $this->assertFalse($service->may_process_for((int) $user->id, (int) $course->id));
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

    /**
     * Change the site name, support email, Dixeo API URL and site URL.
     */
    private function change_site_values(): void {
        global $CFG;

        $this->set_site_name('Renamed Dixeo Site');
        $CFG->supportemail = 'privacy@other.example';
        set_config('api_url', 'https://other.example', 'local_dixeo');
        $CFG->wwwroot = 'https://moved.example';
    }

    /**
     * Rename the site in the database and in the global.
     *
     * @param string $name New site full name.
     */
    private function set_site_name(string $name): void {
        global $DB, $SITE;

        $DB->set_field('course', 'fullname', $name, ['id' => SITEID]);
        $SITE->fullname = $name;
    }

    /**
     * Forget answers kept for the current request, as a new page load would.
     */
    private function start_new_request(): void {
        \cache::make_from_params(\cache_store::MODE_REQUEST, 'local_dixeo', 'termscurrent')->purge();
    }
}
