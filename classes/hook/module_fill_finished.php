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

namespace local_dixeo\hook;

/**
 * Hook dispatched when a Dixeo activity fill finished, successfully or not.
 *
 * The plugin that ran the fill dispatches it, so that other plugins can record the outcome
 * without depending on that plugin. Any plugin can dispatch it: listeners must validate the
 * payload before using it. It carries no error text.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('A Dixeo activity fill finished, successfully or not.')]
#[\core\attribute\tags('local_dixeo')]
final class module_fill_finished {
    /**
     * Constructor.
     *
     * @param int $courseid Course the activity was filled in.
     * @param int $userid User who ran the fill. Never 0.
     * @param string $modulename Dixeo module type.
     * @param string $instructions Fill instructions sent to Dixeo.
     * @param int $sectionnumber Number of the target course section.
     * @param int|null $beforemod Course module the activity was inserted before, or null to append.
     * @param string $displaytitle Activity title.
     * @param string $summary Activity summary sent with the fill.
     * @param bool $success Whether the activity was created.
     * @param int $cmid Created course module id, 0 when $success is false.
     * @param string $filljobid Dixeo fill job id, '' when no job was created.
     */
    public function __construct(
        /** @var int Course the activity was filled in. */
        public readonly int $courseid,
        /** @var int User who ran the fill. Never 0. */
        public readonly int $userid,
        /** @var string Dixeo module type. */
        public readonly string $modulename,
        /** @var string Fill instructions sent to Dixeo. */
        public readonly string $instructions,
        /** @var int Number of the target course section. */
        public readonly int $sectionnumber,
        /** @var int|null Course module the activity was inserted before, or null to append. */
        public readonly ?int $beforemod,
        /** @var string Activity title. */
        public readonly string $displaytitle,
        /** @var string Activity summary sent with the fill. */
        public readonly string $summary,
        /** @var bool Whether the activity was created. */
        public readonly bool $success,
        /** @var int Created course module id, 0 when $success is false. */
        public readonly int $cmid,
        /** @var string Dixeo fill job id, '' when no job was created. */
        public readonly string $filljobid,
    ) {
    }
}
