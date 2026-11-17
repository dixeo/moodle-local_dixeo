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

namespace local_dixeo\output;

use core\output\notification;
use local_dixeo\dto\credit_sync_result;
use local_dixeo\event\credit_usage_refreshed;
use local_dixeo\service\credit_usage_sync_service;

/**
 * "Refresh now" request of the credit report: checks, sync and redirect target.
 *
 * Checks run in a fixed order: login, report access, POST only, session key. Only then is
 * the sync started. The redirect always goes to the credit report with the validated
 * report parameters and filters, and notices are fixed language strings.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credit_report_refresh_handler {
    /** @var credit_usage_sync_service|null Sync service, created on demand. */
    private ?credit_usage_sync_service $sync;

    /**
     * Constructor.
     *
     * @param credit_usage_sync_service|null $sync Sync service.
     */
    public function __construct(?credit_usage_sync_service $sync = null) {
        $this->sync = $sync;
    }

    /**
     * Handle the request.
     *
     * @return array{0: \moodle_url, 1: string, 2: string} Redirect URL, notice text ('' for none), notification type.
     * @throws \moodle_exception When the request is not a POST or the session key is invalid.
     */
    public function handle(): array {
        require_login();
        credit_report_request::require_access();
        if (!data_submitted()) {
            throw new \moodle_exception('invalidrequest');
        }
        require_sesskey();

        $result = ($this->sync ?? new credit_usage_sync_service())->refresh_now();
        if ($result->status === credit_sync_result::STATUS_SYNCED) {
            credit_usage_refreshed::create_for_status($result->status)->trigger();
        }

        // Only validated report parameters, multi-value filters included, are carried back.
        $request = credit_report_request::from_globals();
        $params = $request->to_page_url_params();
        foreach ($request->filters->to_query_params() as $name => $values) {
            foreach (array_values($values) as $index => $value) {
                $params[$name . '[' . $index . ']'] = $value;
            }
        }
        $url = new \moodle_url('/local/dixeo/credit_report.php', $params);
        [$message, $type] = $this->notice($result);
        return [$url, $message, $type];
    }

    /**
     * Notice text and type of a sync outcome.
     *
     * @param credit_sync_result $result Sync outcome.
     * @return array{0: string, 1: string}
     */
    private function notice(credit_sync_result $result): array {
        switch ($result->status) {
            case credit_sync_result::STATUS_SYNCED:
                return [get_string('credit_report_sync_done', 'local_dixeo'), notification::NOTIFY_SUCCESS];
            case credit_sync_result::STATUS_THROTTLED:
                return [get_string('credit_report_sync_throttled', 'local_dixeo'), notification::NOTIFY_INFO];
            case credit_sync_result::STATUS_BUSY:
                return [get_string('credit_report_sync_busy', 'local_dixeo'), notification::NOTIFY_INFO];
            case credit_sync_result::STATUS_FAILED:
                if ($result->lastsuccess > 0) {
                    $date = userdate($result->lastsuccess, get_string('strftimedatetime', 'langconfig'));
                    return [get_string('credit_report_sync_failed', 'local_dixeo', $date), notification::NOTIFY_WARNING];
                }
                return [get_string('credit_report_sync_failed_never', 'local_dixeo'), notification::NOTIFY_WARNING];
            default:
                return ['', notification::NOTIFY_INFO];
        }
    }
}
