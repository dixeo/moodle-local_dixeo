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

namespace local_dixeo\privacy;

use local_dixeo\api\exception\api_exception;
use local_dixeo\event\privacy_request_failed;

/**
 * Makes a privacy request that could not be completed at Dixeo visible to site administrators.
 *
 * {@see \core_privacy\manager} logs whatever a provider throws as debugging and still marks the
 * request complete, and the adhoc runner stops picking up a task once its attempts are spent.
 * The event is the durable trace; the message tells the administrators who can replay the request.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class failure_reporter {
    /** @var string The Dixeo API could not be reached or refused the call. */
    public const REASON_UNREACHABLE = 'unreachable';

    /** @var string Data was sent from this site, but the API key is missing. */
    public const REASON_NOKEY = 'nokey';

    /** @var string A queued erasure failed on its last attempt. */
    public const REASON_ABANDONED = 'abandoned';

    /** @var string[] Accepted reasons, each selecting a privacyfailure_body_<reason> string. */
    private const REASONS = [self::REASON_UNREACHABLE, self::REASON_NOKEY, self::REASON_ABANDONED];

    /**
     * Trigger the privacy_request_failed event and message every site administrator.
     *
     * @param string $component Frankenstyle name of the plugin whose data is concerned, shown in the message.
     * @param int $userid Subject of the request, or 0 when it targets a whole context.
     * @param string $reason One of the REASON_* constants.
     * @param \Throwable|null $e The failure, when there is one.
     * @return void
     * @throws \coding_exception When the reason is unknown.
     */
    public static function report(string $component, int $userid, string $reason, ?\Throwable $e = null): void {
        if (!in_array($reason, self::REASONS, true)) {
            throw new \coding_exception('Unknown privacy failure reason: ' . $reason);
        }

        privacy_request_failed::create_for_user($userid, $component, self::error_code($reason, $e))->trigger();

        $user = $userid > 0 ? \core_user::get_user($userid) : false;
        $a = (object) [
            'component' => $component,
            'user' => $user ? fullname($user) : get_string('privacyfailure_everyone', 'local_dixeo'),
        ];
        $subject = get_string('privacyfailure_subject', 'local_dixeo');
        $body = get_string('privacyfailure_body_' . $reason, 'local_dixeo', $a);

        foreach (get_admins() as $admin) {
            $message = new \core\message\message();
            $message->component = 'local_dixeo';
            $message->name = 'privacyfailure';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $admin;
            $message->subject = $subject;
            $message->fullmessage = $body;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = text_to_html($body);
            $message->smallmessage = $subject;
            $message->notification = 1;
            message_send($message);
        }
    }

    /**
     * Error code recorded on the event: the API error code when known, else the reason.
     *
     * @param string $reason Failure reason.
     * @param \Throwable|null $e The failure, when there is one.
     * @return string
     */
    private static function error_code(string $reason, ?\Throwable $e): string {
        if ($e instanceof api_exception) {
            return $e->get_error_code();
        }
        if ($e instanceof \moodle_exception) {
            return $e->errorcode;
        }
        return $reason === self::REASON_NOKEY ? remote_state::ERROR_NOT_CONFIGURED : $reason;
    }
}
