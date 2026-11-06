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
 * Records one Terms of Use decision per user action.
 *
 * The notice text is stored once per hash. Each decision points at that row.
 * The latest decision is the current choice. A new version, or edited text in the
 * same language, asks again. Switching language does not.
 *
 * Accepting records tutor usage analytics. Withdrawing deletes analytics already stored.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class consent_service {
    /** @var string One acceptance or withdrawal per user action. */
    public const TABLE = 'local_dixeo_consent';

    /** @var string Notice text, one row per hash. */
    public const TABLE_DOCUMENT = 'local_dixeo_consent_document';

    /**
     * Document version shown to the user.
     *
     * Bump this when the Terms of Use, Privacy Notice, or analytics statement changes
     * so people who already accepted are asked again.
     */
    public const VERSION = '2026-10-15.1';

    /** @var string The user accepted. */
    public const ACTION_GRANTED = 'granted';

    /** @var string The user withdrew acceptance. */
    public const ACTION_WITHDRAWN = 'withdrawn';

    /** @var int Seconds a memoised terms answer stays valid within one process. */
    private const TERMS_MEMO_TTL = 30;

    /** @var consent_notice|null Notice text for the current request. */
    private ?consent_notice $notice = null;

    /**
     * Whether the course page must lock until the current terms are accepted.
     *
     * Guests are skipped: consent has to be stored against a real account.
     * Dixeo off for the course skips the lock. Acceptance is per user and per
     * document, not per course.
     *
     * @param int $userid User id.
     * @param \context $context Page context.
     * @return bool
     */
    public function gate_required(int $userid, \context $context): bool {
        if ($userid < 1 || isguestuser($userid)) {
            return false;
        }
        $coursecontext = $context->get_course_context(false);
        if (!$coursecontext instanceof \context_course || (int) $coursecontext->instanceid <= 1) {
            return false;
        }
        if (!(new \local_dixeo\settings\config())->enabled_for($coursecontext)) {
            return false;
        }
        return !$this->terms_current($userid);
    }

    /**
     * Stop a request that would send this user's data while the current Terms of Use are not accepted.
     *
     * Uses {@see self::gate_required()}: guests, contexts outside a course and courses with Dixeo
     * off never stop.
     *
     * @param int $userid Acting user.
     * @param \context $context Request context.
     * @param string $errorkey Language string of the error.
     * @param string $component Component owning the error string.
     * @return void
     * @throws \local_dixeo\exception\consent_required_exception $errorkey in $component when the terms are not accepted.
     */
    public function require_accepted(
        int $userid,
        \context $context,
        string $errorkey = 'error:consentrequired',
        string $component = 'local_dixeo'
    ): void {
        if ($this->gate_required($userid, $context)) {
            throw new \local_dixeo\exception\consent_required_exception($errorkey, $component);
        }
    }

    /**
     * Whether this user must accept the current terms before Dixeo works for a course, or for the site.
     *
     * Guests and ids below 1 never need to. Dixeo off ({@see \local_dixeo\settings\config::enabled_for_course()})
     * never needs to. Courses at or below SITEID, and missing courses, resolve to the site
     * ({@see \local_dixeo\settings\config::context_for_course()}).
     *
     * @param int $userid User id.
     * @param int $courseid Course id, or SITEID (or 0) for site-level Dixeo work.
     * @return bool
     */
    public function gate_required_for_course(int $userid, int $courseid): bool {
        if ($userid < 1 || isguestuser($userid)) {
            return false;
        }
        if (!(new \local_dixeo\settings\config())->enabled_for_course($courseid)) {
            return false;
        }
        return !$this->terms_current($userid);
    }

    /**
     * Stop a request for a course, or for the site, while the current Terms of Use are not accepted.
     *
     * Uses {@see self::gate_required_for_course()}.
     *
     * @param int $userid Acting user.
     * @param int $courseid Course id, or SITEID (or 0) for site-level Dixeo work.
     * @param string $errorkey Language string of the error.
     * @param string $component Component owning the error string.
     * @return void
     * @throws \local_dixeo\exception\consent_required_exception $errorkey in $component when the terms are not accepted.
     */
    public function require_accepted_for_course(
        int $userid,
        int $courseid,
        string $errorkey = 'error:consentrequired',
        string $component = 'local_dixeo'
    ): void {
        if ($this->gate_required_for_course($userid, $courseid)) {
            throw new \local_dixeo\exception\consent_required_exception($errorkey, $component);
        }
    }

    /**
     * Whether a background path (observer, task) may send data about this user for this course.
     *
     * Requires a real, non-guest user, an existing course above SITEID with Dixeo enabled, and
     * acceptance of the current Terms of Use. No acceptance can be stored for a guest.
     *
     * @param int $userid User the data is about.
     * @param int $courseid Course.
     * @return bool
     */
    public function may_process_for(int $userid, int $courseid): bool {
        if ($userid <= 0 || $courseid <= SITEID || isguestuser($userid)) {
            return false;
        }
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context instanceof \context_course) {
            return false;
        }
        if (!(new \local_dixeo\settings\config())->enabled_for($context)) {
            return false;
        }
        return $this->terms_current($userid);
    }

    /**
     * Whether My preferences should offer the terms and privacy row.
     *
     * @param int $userid User id.
     * @return bool
     */
    public function show_manage_link(int $userid): bool {
        global $DB;

        if ($userid < 1 || isguestuser($userid)) {
            return false;
        }
        if ((new \local_dixeo\settings\config())->enabled_for(\context_system::instance())) {
            return true;
        }
        return $DB->record_exists(self::TABLE, ['userid' => $userid]);
    }

    /**
     * Whether the user has accepted the current Terms of Use.
     *
     * The answer is kept for up to 30 seconds per user and language, because entry points and
     * queue loops run this check once per module, row or image. Recording a decision in this
     * process clears it at once. A decision recorded by another process is seen within 30 seconds.
     *
     * @param int $userid User id.
     * @return bool
     */
    public function terms_current(int $userid): bool {
        $cache = self::terms_cache();
        $key = $userid . '_' . current_language();
        $now = \core\di::get(\core\clock::class)->time();
        $cached = $cache->get($key);
        if (is_array($cached) && ($now - (int) $cached['t']) < self::TERMS_MEMO_TTL) {
            return $cached['v'] === 1;
        }
        $current = $this->decision_current($userid, self::ACTION_GRANTED);
        $cache->set($key, ['v' => $current ? 1 : 0, 't' => $now]);
        return $current;
    }

    /**
     * Request cache of time-stamped {@see self::terms_current()} answers.
     *
     * @return \cache
     */
    private static function terms_cache(): \cache {
        return \cache::make_from_params(\cache_store::MODE_REQUEST, 'local_dixeo', 'termscurrent');
    }

    /**
     * Whether tutor usage analytics may be recorded for this user.
     *
     * Acceptance is a single decision, so this matches the current terms.
     *
     * @param int $userid User id.
     * @return bool
     */
    public function processing_current(int $userid): bool {
        return $this->terms_current($userid);
    }

    /**
     * Record acceptance of the current notice.
     *
     * @param int $userid User id.
     * @throws \moodle_exception error:consent_loggedinas while an administrator is logged in as the user.
     */
    public function accept(int $userid): void {
        $this->require_own_session();
        if ($this->terms_current($userid)) {
            return;
        }
        $this->append($userid, self::ACTION_GRANTED, $this->document_id());
    }

    /**
     * Grant or withdraw the current acceptance.
     *
     * Withdrawing deletes tutor usage analytics stored for this user.
     *
     * @param int $userid User id.
     * @param bool $granted True to accept, false to withdraw.
     * @throws \moodle_exception error:consent_loggedinas while an administrator is logged in as the user.
     */
    public function set_processing(int $userid, bool $granted): void {
        if ($granted) {
            $this->accept($userid);
            return;
        }
        $this->withdraw_terms($userid);
    }

    /**
     * Withdraw Terms of Use acceptance.
     *
     * The next Dixeo course asks again. Tutor usage analytics already stored
     * for this user are deleted.
     *
     * @param int $userid User id.
     * @throws \moodle_exception error:consent_loggedinas while an administrator is logged in as the user.
     */
    public function withdraw_terms(int $userid): void {
        $this->require_own_session();
        if ($this->terms_current($userid)) {
            $latest = $this->latest($userid);
            $documentid = $latest ? (int) $latest->documentid : $this->document_id();
            $this->append($userid, self::ACTION_WITHDRAWN, $documentid);
        }
        if ($this->has_analytics($userid)) {
            $this->delete_analytics($userid);
        }
    }

    /**
     * Refuse to record a decision while an administrator is logged in as the user.
     *
     * Only the user can accept or withdraw the Dixeo Terms of Use.
     *
     * @throws \moodle_exception error:consent_loggedinas
     */
    private function require_own_session(): void {
        if (\core\session\manager::is_loggedinas()) {
            throw new \moodle_exception('error:consent_loggedinas', 'local_dixeo');
        }
    }

    /**
     * Template context for the review modal and the manage page.
     *
     * @param int $userid User id.
     * @return array
     */
    public function manage_context(int $userid): array {
        $notice = new consent_notice();
        $template = $notice->template_context();
        $template['intro'] = get_string('consent_manage_intro', 'local_dixeo');
        $template['termsstatus'] = $this->terms_status_text($userid);
        $template['processingstatus'] = $this->processing_status_text($userid);
        $template['acceptanceinfo'] = $this->acceptance_notice($userid);
        $template['termschecked'] = $this->terms_current($userid);
        $template['processingchecked'] = $this->processing_current($userid);
        $template['saveprocessing'] = get_string('consent_save_processing', 'local_dixeo');
        $template['withdrawterms'] = get_string('consent_withdraw_terms', 'local_dixeo');
        $template['withdrawconfirmtitle'] = get_string('consent_withdraw_confirm_title', 'local_dixeo');
        $template['withdrawconfirm'] = get_string('consent_withdraw_confirm', 'local_dixeo');
        $template['closelabel'] = get_string('consent_close', 'local_dixeo');
        $template['closeurl'] = (new \moodle_url('/'))->out(false);
        $template['actionurl'] = (new \moodle_url('/local/dixeo/consent.php'))->out(false);
        $template['sesskey'] = sesskey();
        $template['canwithdrawterms'] = $this->terms_current($userid);
        $template['locked'] = $template['termschecked'] && $template['processingchecked'];
        $template['loggedinas'] = \core\session\manager::is_loggedinas();
        $template['loggedinasnotice'] = get_string('error:consent_loggedinas', 'local_dixeo');
        return $template;
    }

    /**
     * Rendered review modal for one user.
     *
     * @param int $userid User id.
     * @return string
     */
    public function manage_html(int $userid): string {
        global $PAGE;

        return $PAGE->get_renderer('core')->render_from_template(
            'local_dixeo/consent_manage',
            $this->manage_context($userid)
        );
    }

    /**
     * One notice when both the Terms of Use and tutor usage analytics are current.
     *
     * @param int $userid User id.
     * @return string Empty when either choice is missing.
     */
    public function acceptance_notice(int $userid): string {
        $latest = $this->latest($userid);
        if (!$latest || !$this->terms_current($userid)) {
            return '';
        }
        $time = userdate((int) $latest->timecreated);
        return get_string('consent_status_accepted', 'local_dixeo', (object) [
            'version' => $latest->version,
            'time' => $time,
            'processingversion' => $latest->version,
            'processingtime' => $time,
        ]);
    }

    /**
     * Status line for the current terms decision.
     *
     * @param int $userid User id.
     * @return string
     */
    public function terms_status_text(int $userid): string {
        $latest = $this->latest($userid);
        if ($latest && $this->terms_current($userid)) {
            return get_string('consent_status_terms_accepted', 'local_dixeo', (object) [
                'version' => $latest->version,
                'time' => userdate((int) $latest->timecreated),
            ]);
        }
        return get_string('consent_status_terms_none', 'local_dixeo');
    }

    /**
     * Status line for the current analytics decision.
     *
     * @param int $userid User id.
     * @return string
     */
    public function processing_status_text(int $userid): string {
        $latest = $this->latest($userid);
        if ($latest && $this->processing_current($userid)) {
            return get_string('consent_status_processing_on', 'local_dixeo', (object) [
                'version' => $latest->version,
                'time' => userdate((int) $latest->timecreated),
            ]);
        }
        return get_string('consent_status_processing_off', 'local_dixeo');
    }

    /**
     * Latest decision for one user, with the notice it points at, or null.
     *
     * @param int $userid User id.
     * @return \stdClass|null
     */
    public function latest(int $userid): ?\stdClass {
        global $DB;

        $rows = $DB->get_records_sql(
            "SELECT c.id, c.userid, c.documentid, c.action, c.timecreated,
                    d.lang, d.version, d.documenthash, d.documenttext
               FROM {" . self::TABLE . "} c
               JOIN {" . self::TABLE_DOCUMENT . "} d ON d.id = c.documentid
              WHERE c.userid = :userid
           ORDER BY c.timecreated DESC, c.id DESC",
            ['userid' => $userid],
            0,
            1
        );
        if (!$rows) {
            return null;
        }
        $row = reset($rows);
        return $row instanceof \stdClass ? $row : null;
    }

    /**
     * Whether the latest decision is this action on the current notice.
     *
     * A different language still counts for the same version. The same language
     * must match the hash of the text now shown, so an edited translation asks again.
     *
     * @param int $userid User id.
     * @param string $action Action constant.
     * @return bool
     */
    private function decision_current(int $userid, string $action): bool {
        $latest = $this->latest($userid);
        if ($latest === null || (string) $latest->action !== $action) {
            return false;
        }
        if ((string) $latest->version !== self::VERSION) {
            return false;
        }
        if (
            (string) $latest->lang === current_language()
            && !hash_equals((string) $latest->documenthash, $this->notice()->hash())
        ) {
            return false;
        }
        return true;
    }

    /**
     * Id of the stored notice for the text shown now.
     *
     * @return int
     */
    private function document_id(): int {
        global $DB;

        $notice = $this->notice();
        $hash = $notice->hash();
        $existing = $DB->get_field(self::TABLE_DOCUMENT, 'id', ['documenthash' => $hash]);
        if ($existing) {
            return (int) $existing;
        }

        return (int) $DB->insert_record(self::TABLE_DOCUMENT, (object) [
            'lang' => current_language(),
            'version' => self::VERSION,
            'documenthash' => $hash,
            'documenttext' => $notice->document_text(),
            'timecreated' => time(),
        ]);
    }

    /**
     * Append one decision pointing at a stored notice.
     *
     * @param int $userid User id.
     * @param string $action Action constant.
     * @param int $documentid Notice id.
     */
    private function append(int $userid, string $action, int $documentid): void {
        global $DB;

        $DB->insert_record(self::TABLE, (object) [
            'userid' => $userid,
            'documentid' => $documentid,
            'action' => $action,
            'timecreated' => time(),
        ]);
        self::terms_cache()->purge();
    }

    /**
     * Whether any tutor usage analytics row exists for the user.
     *
     * @param int $userid User id.
     * @return bool
     */
    private function has_analytics(int $userid): bool {
        global $DB;

        if ($userid < 1) {
            return false;
        }
        return $DB->record_exists(tutor_usage_recorder::TABLE_EVENT, ['userid' => $userid])
            || $DB->record_exists(tutor_usage_aggregator::TABLE_DAILY, ['userid' => $userid])
            || $DB->record_exists(tutor_usage_aggregator::TABLE_SESSION, ['userid' => $userid])
            || $DB->record_exists(tutor_usage_aggregator::TABLE_OPEN, ['userid' => $userid]);
    }

    /**
     * Delete tutor usage analytics stored for one user.
     *
     * @param int $userid User id.
     */
    public function delete_analytics(int $userid): void {
        global $DB;

        if ($userid < 1) {
            return;
        }
        $DB->delete_records(tutor_usage_recorder::TABLE_EVENT, ['userid' => $userid]);
        $DB->delete_records(tutor_usage_aggregator::TABLE_DAILY, ['userid' => $userid]);
        $DB->delete_records(tutor_usage_aggregator::TABLE_SESSION, ['userid' => $userid]);
        $DB->delete_records(tutor_usage_aggregator::TABLE_OPEN, ['userid' => $userid]);
    }

    /**
     * Notice used for the text stored on new rows.
     *
     * @return consent_notice
     */
    private function notice(): consent_notice {
        if ($this->notice === null) {
            $this->notice = new consent_notice();
        }
        return $this->notice;
    }
}
