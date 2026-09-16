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
 * Trait for common DSL action validation.
 *
 * Provides the field validation and course boundary checks shared by the
 * DSL action classes.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\dsl\actions;

use local_dixeo\dsl\dsl_exception;
use local_dixeo\dsl\value_resolver;

/**
 * Trait providing common action field validation.
 */
trait action_validation {
    /**
     * Validate that required fields are present in the action specification.
     *
     * @param array $action The action to validate.
     * @param array $requiredfields List of required field names.
     * @param string $actionname The action name for error messages.
     * @throws dsl_exception If any required field is missing.
     */
    protected function require_action_fields(array $action, array $requiredfields, string $actionname): void {
        foreach ($requiredfields as $field) {
            if (!isset($action[$field])) {
                throw new dsl_exception(
                    "{$actionname} action requires '{$field}' field",
                    $actionname,
                    ['action' => $action]
                );
            }
        }
    }

    /**
     * Resolve the parent course module a child action writes into.
     *
     * The parent must have been created by the running execution and must live in
     * the course the interpreter was given, so a crafted action specification
     * cannot reach a module of another course.
     *
     * @param value_resolver $resolver The value resolver holding the variables and the context.
     * @param string $modulename The expected module plugin name.
     * @param int $instanceid The module instance id resolved from the action specification.
     * @param int|null $cmid The course module id resolved from the action specification, when known.
     * @return \stdClass The parent course module record.
     * @throws dsl_exception If the module was not created by this execution or is outside the course.
     */
    protected function require_module_created_in_course(
        value_resolver $resolver,
        string $modulename,
        int $instanceid,
        ?int $cmid = null
    ): \stdClass {
        $courseid = (int) ($resolver->get_context()['courseid'] ?? 0);
        if ($courseid <= 0) {
            throw dsl_exception::missing_context('courseid');
        }

        if ($instanceid <= 0 || !self::was_created_in_run($resolver->get_variables(), $modulename, $instanceid, $cmid)) {
            throw new dsl_exception(
                "$modulename instance $instanceid was not created by this execution",
                'action_validation',
                ['modulename' => $modulename, 'instanceid' => $instanceid, 'cmid' => $cmid]
            );
        }

        $cm = get_coursemodule_from_instance($modulename, $instanceid, $courseid, false, IGNORE_MISSING);
        if (!$cm || ($cmid !== null && (int) $cm->id !== $cmid)) {
            throw new dsl_exception(
                "$modulename instance $instanceid does not belong to course $courseid",
                'action_validation',
                ['modulename' => $modulename, 'instanceid' => $instanceid, 'courseid' => $courseid]
            );
        }

        return $cm;
    }

    /**
     * Whether a module created by this execution matches the one being written into.
     *
     * @param array $variables The variables saved by the previous actions.
     * @param string $modulename The expected module plugin name.
     * @param int $instanceid The module instance id.
     * @param int|null $cmid The course module id, when known.
     * @return bool True when a create_module result matches.
     */
    private static function was_created_in_run(
        array $variables,
        string $modulename,
        int $instanceid,
        ?int $cmid
    ): bool {
        foreach ($variables as $value) {
            if (!is_array($value) || !isset($value['id'], $value['cmid'], $value['modulename'])) {
                continue;
            }
            if ((int) $value['id'] !== $instanceid || $value['modulename'] !== $modulename) {
                continue;
            }
            if ($cmid !== null && (int) $value['cmid'] !== $cmid) {
                continue;
            }

            return true;
        }

        return false;
    }
}
