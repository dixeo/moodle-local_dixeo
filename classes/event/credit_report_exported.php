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
 * Event when credit usage report data is exported.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\event;

use local_dixeo\output\credit_report_request;

/**
 * Fired before a credit usage report export download starts.
 *
 * Includes view mode, export format, row counts, and filter counts only.
 */
class credit_report_exported extends \core\event\base {
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
        return get_string('eventcreditreportexported', 'local_dixeo');
    }

    /**
     * Non-localised description for logs.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('eventcreditreportexporteddesc', 'local_dixeo', (object) [
            'userid' => $this->userid,
            'view' => $this->other['view'] ?? '',
            'dataformat' => $this->other['dataformat'] ?? '',
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
     * Create an event for an exported credit report.
     *
     * @param credit_report_request $request Parsed report request.
     * @param int $rowcount Number of rows included in the export.
     * @param string $dataformat Selected dataformat plugin name.
     * @return self
     */
    public static function create_for_request(
        credit_report_request $request,
        int $rowcount,
        string $dataformat
    ): self {
        return self::create([
            'context' => \context_system::instance(),
            'other' => $request->to_event_other($rowcount, $dataformat),
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
        if (empty($this->other['dataformat'])) {
            throw new \coding_exception('The \'dataformat\' value must be set in other.');
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
