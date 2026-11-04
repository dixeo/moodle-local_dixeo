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

namespace local_dixeo\event;

/**
 * Fired when a privacy request or a queued erasure could not be completed at the Dixeo API.
 *
 * Site scoped rather than course scoped: the request may fail before any course is known, and
 * the audience is the site administrator. Core marks a privacy request complete even when a
 * provider fails, so this event is the durable trace that the answer was incomplete. The payload
 * is limited to the subject, the plugin concerned and the error code: no content is logged.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class privacy_request_failed extends \core\event\base {
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
        return get_string('eventprivacyrequestfailed', 'local_dixeo');
    }

    /**
     * Non-localised description for logs.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('eventprivacyrequestfaileddesc', 'local_dixeo', (object) [
            'userid' => (int) $this->relateduserid,
            'component' => clean_param((string) ($this->other['component'] ?? ''), PARAM_COMPONENT),
            'errorcode' => clean_param((string) ($this->other['errorcode'] ?? ''), PARAM_ALPHANUMEXT),
        ]);
    }

    /**
     * Create an event for a privacy request the API could not serve.
     *
     * @param int $userid The user the request is about, or 0 when it targets a whole context.
     * @param string $component Frankenstyle name of the plugin whose data is concerned.
     * @param string $errorcode The Dixeo API error code, or the failure reason.
     * @return self
     */
    public static function create_for_user(int $userid, string $component, string $errorcode): self {
        return self::create([
            'context' => \context_system::instance(),
            'relateduserid' => $userid > 0 ? $userid : null,
            'other' => ['component' => $component, 'errorcode' => $errorcode],
        ]);
    }

    /**
     * Custom validation.
     */
    protected function validate_data(): void {
        parent::validate_data();
        if (!array_key_exists('component', $this->other)) {
            throw new \coding_exception('The \'component\' value must be set in other.');
        }
        if (!array_key_exists('errorcode', $this->other)) {
            throw new \coding_exception('The \'errorcode\' value must be set in other.');
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
