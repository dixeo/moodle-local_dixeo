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

namespace local_dixeo\service;

/**
 * Reads HTML draft text from core tiny_autosave (TinyMCE autosave plugin).
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tiny_autosave_draft_service {
    /**
     * Load draft text for the current editing session keys.
     *
     * Returns null if no row, empty text, or stale autosave (time-based, same default window as core).
     *
     * @param int $userid Moodle user id.
     * @param int $contextid Context id (must match the module context used by the editor).
     * @param string $pagehash Page hash from Tiny autosave options.
     * @param string $elementid Target textarea element id (e.g. id_modulecontent).
     * @return string|null Trimmed HTML or null.
     */
    public function get_draft_text(int $userid, int $contextid, string $pagehash, string $elementid): ?string {
        global $DB;

        $pagehash = trim($pagehash);
        $elementid = trim($elementid);
        if ($pagehash === '' || $elementid === '') {
            return null;
        }

        $record = $DB->get_record('tiny_autosave', [
            'userid' => $userid,
            'contextid' => $contextid,
            'pagehash' => $pagehash,
            'elementid' => $elementid,
        ], '*', IGNORE_MISSING);

        if (!$record) {
            return null;
        }

        if ($this->is_stale($record)) {
            return null;
        }

        $text = trim($record->drafttext ?? '');
        return $text === '' ? null : $text;
    }

    /**
     * Time-based staleness aligned with tiny_autosave defaults (file-based staleness not duplicated here).
     *
     * @param \stdClass $record tiny_autosave row.
     * @return bool
     */
    private function is_stale(\stdClass $record): bool {
        $context = \context::instance_by_id((int) $record->contextid, IGNORE_MISSING);
        $coursecontext = $context instanceof \context ? $context->get_course_context(false) : false;
        $readcontext = $coursecontext instanceof \context ? $coursecontext : \context_system::instance();
        $staleperiod = \local_dixeo\external\service_factory::get_settings_config()
            ->get_moodle('tiny_autosave', 'staleperiod', $readcontext);
        if (empty($staleperiod)) {
            $staleperiod = 4 * DAYSECS;
        }
        $staleperiod = (int) $staleperiod;

        return $record->timemodified < (time() - $staleperiod);
    }
}
