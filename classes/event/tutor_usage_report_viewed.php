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
 * Event when the tutor usage report page is viewed.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\event;

use local_dixeo\output\tutor_usage_report_request;

/**
 * Fired after a successful tutor usage report page render.
 *
 * Includes level, view mode, row counts, and filter counts only — no user or course names.
 */
class tutor_usage_report_viewed extends \core\event\base {
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
        return get_string('eventtutorusagereportviewed', 'local_dixeo');
    }

    /**
     * Non-localised description for logs.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('eventtutorusagereportvieweddesc', 'local_dixeo', (object) [
            'userid' => $this->userid,
            'level' => $this->other['level'] ?? '',
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
        return new \moodle_url('/local/dixeo/tutor_usage_report.php');
    }

    /**
     * Create an event for a viewed tutor usage report.
     *
     * @param tutor_usage_report_request $request Parsed report request.
     * @param int $rowcount Total matching rows for the current filters.
     * @return self
     */
    public static function create_for_request(tutor_usage_report_request $request, int $rowcount): self {
        $context = $request->courseid > 0
            ? \context_course::instance($request->courseid)
            : \context_system::instance();

        return self::create([
            'context' => $context,
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
        if (empty($this->other['level'])) {
            throw new \coding_exception('The \'level\' value must be set in other.');
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
