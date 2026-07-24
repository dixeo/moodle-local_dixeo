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
 * Event when the credit usage report page is viewed.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\event;

use local_dixeo\local\credit_report_request;

/**
 * Fired after a successful credit usage report page render.
 *
 * Includes view mode, row counts, and filter counts only — no user or course names.
 */
class credit_report_viewed extends \core\event\base {
    /**
     * Init method.
     */
    protected function init(): void {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventcreditreportviewed', 'local_dixeo');
    }

    /**
     * Non-localised description for logs.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('eventcreditreportvieweddesc', 'local_dixeo', (object) [
            'userid' => $this->userid,
            'view' => $this->other['view'] ?? '',
            'rowcount' => (int) ($this->other['rowcount'] ?? 0),
        ]);
    }

    /**
     * Relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/dixeo/credit_report.php');
    }

    /**
     * Create an event for a viewed credit report.
     *
     * @param credit_report_request $request Parsed report request.
     * @param int $rowcount Total matching rows for the current filters.
     * @return self
     */
    public static function create_for_request(credit_report_request $request, int $rowcount): self {
        return self::create([
            'context' => \context_system::instance(),
            'other' => $request->to_event_other($rowcount),
        ]);
    }

    /**
     * Custom validation.
     */
    protected function validate_data(): void {
        parent::validate_data();
        if (empty($this->other['view'])) {
            throw new \coding_exception('The \'view\' value must be set in other.');
        }
    }

    /**
     * Object id mapping for backup/restore.
     *
     * @return false
     */
    public static function get_objectid_mapping() {
        return false;
    }

    /**
     * Other mapping for backup/restore.
     *
     * @return false
     */
    public static function get_other_mapping() {
        return false;
    }
}
