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

namespace local_dixeo\hook;

/**
 * Hook to declare courses whose content belongs to the context of another course.
 *
 * Dispatched while building the course context, so plugins that link courses together
 * can have those contents described alongside the course being processed. Callbacks
 * decide which linked courses the current user is entitled to; the sections are then
 * filtered with the same user visibility rules as the main course.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to add linked courses to the context Dixeo builds for a course.')]
#[\core\attribute\tags('local_dixeo')]
class extend_course_context {
    /** @var array Linked course ids mapped to section numbers, or to null for the whole course. */
    private array $courses = [];

    /**
     * Constructor.
     *
     * @param int $courseid The course the context is being built for.
     * @param string $mode Context mode: 'teaching' or 'assessment'.
     */
    public function __construct(
        /** @var int The course the context is being built for. */
        public readonly int $courseid,
        /** @var string Context mode: 'teaching' or 'assessment'. */
        public readonly string $mode,
    ) {
    }

    /**
     * Add a course to include in the context.
     *
     * @param int $courseid The linked course id.
     * @param array|null $sectionnums Section numbers to include, null for the whole course.
     * @return void
     */
    public function add_course(int $courseid, ?array $sectionnums = null): void {
        if ($courseid <= 0 || $courseid === $this->courseid) {
            return;
        }

        $sections = $sectionnums === null ? null : array_map('intval', $sectionnums);

        if (!array_key_exists($courseid, $this->courses)) {
            $this->courses[$courseid] = $sections;
            return;
        }

        // A course claimed by several callbacks keeps the widest scope requested.
        $known = $this->courses[$courseid];
        if ($known === null || $sections === null) {
            $this->courses[$courseid] = null;
            return;
        }

        $this->courses[$courseid] = array_values(array_unique(array_merge($known, $sections)));
    }

    /**
     * Get the requested courses.
     *
     * @return array Course id => section numbers to include, or null for the whole course.
     */
    public function get_courses(): array {
        return $this->courses;
    }
}
