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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Adds mod_coursecertificate to a course from designer-style configuration.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_certificate_service {
    /**
     * Whether the course-completed availability restriction may be stored on new certificate CMs.
     *
     * Requires {@see plugin_installation_service::is_component_installed()} for availability_coursecompleted,
     * then {@see \core\plugininfo\availability::get_enabled_plugins()} so admin-disabled plugins are excluded.
     *
     * @return bool
     */
    public static function is_course_completed_availability_plugin_enabled(): bool {
        if (!plugin_installation_service::is_component_installed('availability_coursecompleted')) {
            return false;
        }
        $enabled = \core\plugininfo\availability::get_enabled_plugins();
        return isset($enabled['coursecompleted']);
    }

    /**
     * Whether certificates can be generated on this site.
     *
     * @return bool True when mod_coursecertificate and tool_certificate are installed and the template table exists.
     */
    public static function is_available(): bool {
        global $DB;

        $pluginmanager = \core_plugin_manager::instance();
        if (
            $pluginmanager->get_plugin_info('mod_coursecertificate') === null
                || $pluginmanager->get_plugin_info('tool_certificate') === null
        ) {
            return false;
        }
        return $DB->get_manager()->table_exists('tool_certificate_templates');
    }

    /**
     * Try to add a course certificate activity.
     *
     * @param int $courseid
     * @param bool $generationenabled Admin setting: certificate generation on.
     * @param int $templateid Template id from tool_certificate (must exist).
     * @param string $location 'summary' or 'last' (after content sections, excluding resources).
     * @param string $activityname Translated activity name.
     * @param string $sectiontitle Translated section title when $location is last.
     * @param string $sectionintro Section summary HTML when $location is last.
     * @return string|false 'summary'|'last' if a certificate was added; false if skipped.
     */
    public function try_add_coursecertificate_activity(
        int $courseid,
        bool $generationenabled,
        int $templateid,
        string $location,
        string $activityname,
        string $sectiontitle,
        string $sectionintro
    ) {
        global $DB;

        if (!$generationenabled || $templateid <= 0) {
            return false;
        }

        if (!self::is_available()) {
            return false;
        }

        if (!$DB->record_exists('tool_certificate_templates', ['id' => $templateid])) {
            return false;
        }

        $hascert = $DB->record_exists_sql(
            "SELECT 1 FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
             WHERE cm.course = ? AND m.name = 'coursecertificate'
               AND cm.deletioninprogress = 0",
            [$courseid]
        );
        if ($hascert) {
            return false;
        }

        $course = get_course($courseid);
        $translations = get_string_manager()->get_list_of_translations();
        $fixlang = isset($translations[$course->lang]) && $course->lang !== '';
        if ($fixlang) {
            $defaultlang = current_language();
            \fix_current_language($course->lang);
        }

        try {
            if ($location === 'summary') {
                $section = $DB->get_record('course_sections', ['course' => $courseid, 'section' => 0], '*', MUST_EXIST);
                $trailing = 'summary';
            } else {
                $section = $this->append_course_section($courseid, $sectiontitle, $sectionintro);
                $trailing = 'last';
            }

            $availability = self::is_course_completed_availability_plugin_enabled()
                ? $this->build_course_completed_availability_json()
                : null;

            $instance = (object) [
                'course' => $courseid,
                'name' => $activityname,
                'template' => $templateid,
                'automaticsend' => 0,
                'expirydatetype' => 0,
                'expirydateoffset' => 0,
                'intro' => '',
                'introformat' => FORMAT_HTML,
                'timecreated' => time(),
                'timemodified' => time(),
            ];
            $instance->id = $DB->insert_record('coursecertificate', $instance);

            $moduleid = $DB->get_field('modules', 'id', ['name' => 'coursecertificate'], MUST_EXIST);
            $cm = (object) [
                'course' => $courseid,
                'module' => $moduleid,
                'instance' => $instance->id,
                'section' => $section->id,
                'visible' => 1,
                'visibleoncoursepage' => 1,
                'downloadcontent' => 1,
                'completion' => 0,
                'availability' => $availability,
            ];
            $cm->id = $DB->insert_record('course_modules', $cm);
            \course_add_cm_to_section($course, $cm->id, $section->section);
            \rebuild_course_cache($courseid, true);

            return $trailing;
        } finally {
            if ($fixlang) {
                \fix_current_language($defaultlang);
            }
        }
    }

    /**
     * Availability JSON: show when the user has completed the current course.
     *
     * Only valid when {@see self::is_course_completed_availability_plugin_enabled()} is true
     * (requires availability_coursecompleted).
     *
     * @return string Encoded JSON for course_modules.availability.
     */
    protected function build_course_completed_availability_json(): string {
        $condition = (object) [
            'type' => 'coursecompleted',
            'id' => '1',
            'courseid' => 0,
        ];
        return json_encode([
            'op' => '&',
            'showc' => [true],
            'c' => [$condition],
        ], JSON_UNESCAPED_SLASHES);
    }

    /**
     * Append a new section at max(section)+1.
     *
     * @param int $courseid
     * @param string $name
     * @param string $summary
     * @return \stdClass Section record with id and section number.
     */
    private function append_course_section(int $courseid, string $name, string $summary): \stdClass {
        global $DB;

        $next = (int) $DB->get_field_sql(
            'SELECT COALESCE(MAX(section), 0) + 1 FROM {course_sections} WHERE course = ?',
            [$courseid]
        );
        $section = (object) [
            'course' => $courseid,
            'section' => $next,
            'name' => $name,
            'summary' => $summary,
            'summaryformat' => FORMAT_HTML,
            'visible' => 1,
            'timemodified' => time(),
        ];
        $section->id = $DB->insert_record('course_sections', $section);
        \rebuild_course_cache($courseid, true);

        return $section;
    }
}
