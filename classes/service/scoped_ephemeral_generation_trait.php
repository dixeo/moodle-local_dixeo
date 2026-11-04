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
 * Shared scope/context helpers for ephemeral tutor generation services.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service;

use local_dixeo\context\context_builder_factory;
use local_dixeo\context\course_context_builder;


/**
 * Trait for practice quiz and teach lesson scoped generation services.
 */
trait scoped_ephemeral_generation_trait {
    /**
     * Course context builder mode for full-course scope.
     *
     * @return string course_context_builder::MODE_*
     */
    abstract protected function get_course_context_mode(): string;

    /**
     * Build markdown context for the selected scope.
     *
     * @param int $courseid
     * @param string $scope course|section|activity
     * @param int|null $sectionnum Section number when scope is section.
     * @param int|null $cmid Course module id when scope is activity.
     * @return string
     */
    public function build_context(int $courseid, string $scope, ?int $sectionnum, ?int $cmid): string {
        switch ($scope) {
            case self::SCOPE_SECTION:
                if ($sectionnum !== null && $sectionnum > 0) {
                    return context_builder_factory::buildsectioncontextfornumber($courseid, $sectionnum);
                }
                // Fall through to course if section number missing.
                // No break.
            case self::SCOPE_ACTIVITY:
                if ($cmid !== null && $cmid > 0) {
                    get_coursemodule_from_id('', $cmid, $courseid, false, MUST_EXIST);
                    return context_builder_factory::buildmodulepracticecontext($cmid);
                }
                // Fall through to course if cmid missing.
                // No break.
            case self::SCOPE_COURSE:
            default:
                return context_builder_factory::buildCourseContext(
                    $courseid,
                    null,
                    $this->get_course_context_mode()
                );
        }
    }

    /**
     * Human-readable scope line for generation instructions.
     *
     * @param string $scope course|section|activity
     * @param string $scopename Scope display name.
     * @param string $language Moodle language code for localized scope text.
     * @return string
     */
    protected function build_scope_description(string $scope, string $scopename, string $language): string {
        $name = trim($scopename);

        return match ($scope) {
            self::SCOPE_SECTION => generation_language_helper::get_string(
                'practice_quiz_scope_section_description',
                (object) ['name' => $name],
                $language
            ),
            self::SCOPE_ACTIVITY => generation_language_helper::get_string(
                'practice_quiz_scope_activity_description',
                (object) ['name' => $name],
                $language
            ),
            default => generation_language_helper::get_string(
                'practice_quiz_scope_course_description',
                (object) ['name' => $name],
                $language
            ),
        };
    }

    /**
     * Normalise a course/section/activity scope and require it to be visible and available to the current user.
     *
     * Section scope: section number above 0 and uservisible. Activity scope: course module
     * uservisible, not in section 0, and its module name not in $excludedmodnames. The ids that do
     * not belong to the scope are returned as 0, so a missing id never falls back to another scope.
     *
     * @param int $courseid Course id.
     * @param string $scope course, section or activity.
     * @param int $sectionnum Section number for the section scope.
     * @param int $cmid Course module id for the activity scope.
     * @param string[] $excludedmodnames Module names that cannot be a topic.
     * @return array{0: string, 1: int, 2: int} Scope, section number and cmid.
     * @throws \invalid_parameter_exception When the scope is unknown.
     * @throws \moodle_exception error:scope_unavailable, without saying whether the item exists.
     */
    public function require_visible_scope(
        int $courseid,
        string $scope,
        int $sectionnum,
        int $cmid,
        array $excludedmodnames = []
    ): array {
        $scope = $this->normalize_scope($scope);
        if ($scope === self::SCOPE_COURSE) {
            return [$scope, 0, 0];
        }

        $modinfo = get_fast_modinfo($courseid);

        if ($scope === self::SCOPE_SECTION) {
            $section = $sectionnum > 0 ? $modinfo->get_section_info($sectionnum, IGNORE_MISSING) : null;
            if (!$section || !$section->uservisible) {
                throw new \moodle_exception('error:scope_unavailable', 'local_dixeo');
            }
            return [$scope, (int) $section->section, 0];
        }

        $cm = null;
        if ($cmid > 0) {
            try {
                $cm = $modinfo->get_cm($cmid);
            } catch (\moodle_exception $e) {
                $cm = null;
            }
        }
        if (
            !$cm
            || !$cm->uservisible
            || (int) $cm->sectionnum === 0
            || in_array($cm->modname, $excludedmodnames, true)
        ) {
            throw new \moodle_exception('error:scope_unavailable', 'local_dixeo');
        }
        return [$scope, 0, (int) $cm->id];
    }

    /**
     * Normalize and validate a scope value.
     *
     * @param string $scope
     * @return string
     * @throws \invalid_parameter_exception When the scope is unknown.
     */
    private function normalize_scope(string $scope): string {
        $scope = strtolower(trim($scope));
        if (
            !in_array($scope, [
            self::SCOPE_COURSE,
            self::SCOPE_SECTION,
            self::SCOPE_ACTIVITY,
            ], true)
        ) {
            throw new \invalid_parameter_exception('Invalid scope');
        }

        return $scope;
    }

    /**
     * Parse completed job result payload from job status.
     *
     * @param mixed $result
     * @return array|null
     */
    protected function parse_completed_job_result($result): ?array {
        if (is_string($result)) {
            $result = json_decode($result, true);
        }
        if (!is_array($result)) {
            return null;
        }

        return $result;
    }
}
