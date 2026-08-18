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
 * Event when course file synchronisation is disabled.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\event;

/**
 * Fired when Dixeo course file sync is disabled.
 *
 * Payload is limited to identifiers and whether remote files were cleared.
 */
class file_sync_disabled extends \core\event\base {
    /**
     * Init method.
     */
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_dixeo_course_ai';
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventfilesyncdisabled', 'local_dixeo');
    }

    /**
     * Non-localised description for logs.
     *
     * @return string
     */
    public function get_description(): string {
        $removefiles = !empty($this->other['removefiles']) ? 1 : 0;
        $remotestatus = (string) ($this->other['remotedeletestatus'] ?? 'not_requested');
        return get_string('eventfilesyncdisableddesc', 'local_dixeo', (object) [
            'userid' => $this->userid,
            'courseid' => $this->courseid,
            'removefiles' => $removefiles,
            'remotedeletestatus' => $remotestatus,
        ]);
    }

    /**
     * Relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/course/view.php', ['id' => $this->courseid]);
    }

    /**
     * Create an event for a course sync disable action.
     *
     * @param int $courseid Course ID.
     * @param int $userid User who disabled sync.
     * @param int $objectid local_dixeo_course_ai row id.
     * @param bool $removefiles Whether remote files were requested for deletion.
     * @param string $remotedeletestatus completed|pending|not_requested
     * @return self
     */
    public static function create_for_course(
        int $courseid,
        int $userid,
        int $objectid,
        bool $removefiles,
        string $remotedeletestatus = 'not_requested'
    ): self {
        return self::create([
            'context' => \context_course::instance($courseid),
            'objectid' => $objectid,
            'userid' => $userid,
            'courseid' => $courseid,
            'other' => [
                'removefiles' => $removefiles ? 1 : 0,
                'remotedeletestatus' => $remotedeletestatus,
            ],
        ]);
    }

    /**
     * Custom validation.
     */
    protected function validate_data(): void {
        parent::validate_data();
        if (!isset($this->other['removefiles'])) {
            throw new \coding_exception('The \'removefiles\' value must be set in other.');
        }
        if (!isset($this->other['remotedeletestatus'])) {
            throw new \coding_exception('The \'remotedeletestatus\' value must be set in other.');
        }
    }

    /**
     * Object id mapping for backup/restore.
     *
     * @return array
     */
    public static function get_objectid_mapping(): array {
        return ['db' => 'local_dixeo_course_ai', 'restore' => \core\event\base::NOT_MAPPED];
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
