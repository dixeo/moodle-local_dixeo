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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_dixeo\service;

use local_dixeo\dto\tutor_message;

/**
 * Records tutor usage analytics events (no message content).
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tutor_usage_recorder {
    /** @var string Event table. */
    public const TABLE_EVENT = 'local_dixeo_tutor_usage_event';

    /** @var string Message event type. */
    public const EVENT_MESSAGE = 'message';

    /** @var string Practice quiz created event type. */
    public const EVENT_QUIZ_CREATED = 'quiz_created';

    /** @var string Teach lesson created event type. */
    public const EVENT_LESSON_CREATED = 'lesson_created';

    /**
     * Record a tutor message usage event.
     *
     * @param int $userid User id.
     * @param int $courseid Course id.
     * @param string $mode Tutor mode.
     * @param int $cmid Course module id (0 when unknown).
     * @param int|null $timecreated Optional timestamp override.
     * @return int Inserted record id.
     */
    public function record_message(
        int $userid,
        int $courseid,
        string $mode = tutor_message::MODE_NORMAL,
        int $cmid = 0,
        ?int $timecreated = null
    ): int {
        return $this->record(
            $userid,
            $courseid,
            self::EVENT_MESSAGE,
            $mode,
            $cmid,
            $timecreated
        );
    }

    /**
     * Record a practice quiz creation event.
     *
     * @param int $userid User id.
     * @param int $courseid Course id.
     * @param int $cmid Course module id when known.
     * @param int|null $timecreated Optional timestamp override.
     * @return int Inserted record id.
     */
    public function record_quiz_created(
        int $userid,
        int $courseid,
        int $cmid = 0,
        ?int $timecreated = null
    ): int {
        return $this->record(
            $userid,
            $courseid,
            self::EVENT_QUIZ_CREATED,
            tutor_message::MODE_QUIZ,
            $cmid,
            $timecreated
        );
    }

    /**
     * Record a teach lesson creation event.
     *
     * @param int $userid User id.
     * @param int $courseid Course id.
     * @param int $cmid Course module id when known.
     * @param int|null $timecreated Optional timestamp override.
     * @return int Inserted record id.
     */
    public function record_lesson_created(
        int $userid,
        int $courseid,
        int $cmid = 0,
        ?int $timecreated = null
    ): int {
        return $this->record(
            $userid,
            $courseid,
            self::EVENT_LESSON_CREATED,
            tutor_message::MODE_TEACH,
            $cmid,
            $timecreated
        );
    }

    /**
     * Validate and persist a usage event.
     *
     * @param int $userid User id.
     * @param int $courseid Course id.
     * @param string $eventtype Event type constant.
     * @param string $mode Tutor mode.
     * @param int $cmid Course module id.
     * @param int|null $timecreated Optional timestamp.
     * @return int Inserted record id.
     */
    public function record(
        int $userid,
        int $courseid,
        string $eventtype,
        string $mode = tutor_message::MODE_NORMAL,
        int $cmid = 0,
        ?int $timecreated = null
    ): int {
        global $DB;

        if ($userid < 1 || $courseid < 1) {
            return 0;
        }

        $eventtype = $this->normalize_eventtype($eventtype);
        $mode = tutor_message::normalize_mode($mode);
        $cmid = max(0, $cmid);
        $timecreated = $timecreated ?? time();

        $id = (int) $DB->insert_record(self::TABLE_EVENT, (object) [
            'userid' => $userid,
            'courseid' => $courseid,
            'cmid' => $cmid,
            'mode' => $mode,
            'eventtype' => $eventtype,
            'timecreated' => $timecreated,
        ]);

        if ($id > 0 && $eventtype === self::EVENT_MESSAGE) {
            (new tutor_usage_aggregator())->touch_open_session($userid, $courseid, $timecreated);
        }

        return $id;
    }

    /**
     * Normalize an event type to a known value.
     *
     * @param string $eventtype Raw event type.
     * @return string
     */
    public function normalize_eventtype(string $eventtype): string {
        $allowed = [
            self::EVENT_MESSAGE,
            self::EVENT_QUIZ_CREATED,
            self::EVENT_LESSON_CREATED,
        ];
        return in_array($eventtype, $allowed, true) ? $eventtype : self::EVENT_MESSAGE;
    }

    /**
     * Validate that a course module belongs to the given course.
     *
     * @param int $courseid Course id.
     * @param int $cmid Course module id.
     * @return int Sanitized cmid (0 when invalid).
     */
    public static function sanitize_cmid(int $courseid, int $cmid): int {
        global $DB;

        if ($courseid < 1 || $cmid < 1) {
            return 0;
        }

        $exists = $DB->record_exists('course_modules', [
            'id' => $cmid,
            'course' => $courseid,
        ]);

        return $exists ? $cmid : 0;
    }
}
