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
 * DSL action for creating Moodle activity modules.
 *
 * Creates a Moodle activity module using the standard Moodle APIs:
 * add_course_module() and {modulename}_add_instance().
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\dsl\actions;

use local_dixeo\dsl\dsl_exception;
use local_dixeo\dsl\value_resolver;
use local_dixeo\service\module_activity_defaults_registry;



defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/gradelib.php');

/**
 * Action handler for creating Moodle course modules.
 *
 * Expected action format:
 * {
 *   "action": "create_module",
 *   "save_as": "module",
 *   "fields": {
 *     "name": {"source": "$.name"},
 *     "intro": {"source": "$.intro"},
 *     "content": {"source": "$.content"}
 *   }
 * }
 *
 * Required context:
 * - courseid: The course ID
 * - sectionid: The section ID
 * - sectionnum: The section number
 * - modulename: The module type (page, quiz, glossary, etc.)
 * - beforemod: Optional course module ID to insert before
 */
class create_module_action {
    /**
     * Module-specific field quirks that cannot be handled by platform defaults.
     *
     * Some Moodle modules require specific field name remappings or special
     * handling that differs from the standard form field names.
     *
     * @var array<string, array<string, mixed>>
     */
    protected const MODULE_FIELD_QUIRKS = [
        'quiz' => [
            // Quiz should create a grade item.
            'sumgrades' => 100,
            'grade' => 100,
            'introformat' => FORMAT_HTML,
            'questionsperpage' => 1,
            'timeopen' => 0,
            'timeclose' => 0,
            'overduehandling' => 'autosubmit',
            'preferredbehaviour' => 'deferredfeedback',
            'shuffleanswers' => 1,
            'decimalpoints' => 0,
            'questiondecimalpoints' => -1,
            // Moodle expects 'quizpassword' which gets renamed to 'password' in quiz_process_options().
            'quizpassword' => '',
            // Review options: quiz_review_option_form_to_db() expects {option}{when} fields.
            // When: during, immediately, open (while open), closed (after close).
            'attemptduring' => 1,
            'attemptimmediately' => 1,
            'attemptopen' => 1,
            'attemptclosed' => 1,
            'correctnessimmediately' => 1,
            'correctnessopen' => 1,
            'correctnessclosed' => 1,
            // Maximum marks (aligned with local_edai quiz module).
            'maxmarksimmediately' => 1,
            'maxmarksopen' => 1,
            'marksimmediately' => 1,
            'marksopen' => 1,
            'marksclosed' => 1,
            'specificfeedbackimmediately' => 1,
            'specificfeedbackopen' => 1,
            'specificfeedbackclosed' => 1,
            'generalfeedbackimmediately' => 1,
            'generalfeedbackopen' => 1,
            'generalfeedbackclosed' => 1,
            'rightanswerimmediately' => 1,
            'rightansweropen' => 1,
            'rightanswerclosed' => 1,
            'overallfeedbackclosed' => 1,
        ],
    ];

    /**
     * Execute the create_module action.
     *
     * @param array $action The action specification.
     * @param value_resolver $resolver The value resolver for field resolution.
     * @return array The created module data including 'id' (instance), 'cmid', and 'cm'.
     * @throws dsl_exception If module creation fails.
     */
    public function execute(array $action, value_resolver $resolver): array {
        global $CFG, $DB, $USER;

        $context = $resolver->get_context();
        $this->validate_context($context);

        // Resolve field values from the action specification.
        $fields = $resolver->resolve_fields($action['fields'] ?? []);

        $courseid = (int) $context['courseid'];
        $sectionid = (int) $context['sectionid'];
        $sectionnum = (int) $context['sectionnum'];
        $modulename = $context['modulename'];
        $beforemod = $context['beforemod'] ?? null;

        // Get the module ID from the modules table.
        $moduleid = $DB->get_field('modules', 'id', ['name' => $modulename]);
        if (!$moduleid) {
            throw dsl_exception::module_creation_failed($modulename, 'Module type not found in database');
        }

        // Start a transaction for atomic creation.
        $transaction = $DB->start_delegated_transaction();

        try {
            // Create the course module record.
            $cmid = $this->create_course_module($courseid, $moduleid, $sectionid, $modulename);

            // Add the course module to the section.
            \course_add_cm_to_section($courseid, $cmid, $sectionnum, $beforemod);

            // Prepare module data for the add_instance function.
            $moduledata = $this->prepare_module_data($fields, $courseid, $cmid, $modulename);

            // Run module-specific pre-creation hooks if available.
            $this->run_before_hook($modulename, $cmid, $moduledata);

            // Create the module instance.
            $instanceid = $this->create_module_instance($modulename, $moduledata);
            if (!$instanceid) {
                throw dsl_exception::module_creation_failed($modulename, 'add_instance returned false');
            }

            // Link the course module to its instance.
            $DB->set_field('course_modules', 'instance', $instanceid, ['id' => $cmid]);

            // Store instance ID for after hook.
            $moduledata->id = $instanceid;

            $shortcodeservice = \local_dixeo\external\service_factory::get_content_image_shortcode_service();
            $modulecontext = \context_module::instance($cmid);
            $moduledata = $shortcodeservice->process_and_persist(
                $modulename,
                $instanceid,
                $modulecontext->id,
                $courseid,
                $cmid,
                $moduledata,
                (int) ($context['userid'] ?? $USER->id)
            );

            if ($modulename === 'quiz') {
                $this->set_quiz_pass_grade($courseid, (int) $instanceid);
            }

            // Run module-specific post-creation hooks if available.
            $this->run_after_hook($modulename, $cmid, $moduledata);

            // Rebuild course cache to reflect changes.
            \rebuild_course_cache($courseid);

            $transaction->allow_commit();

            // Return module data for variable storage.
            return [
                'id' => $instanceid,
                'cmid' => $cmid,
                'cm' => $cmid,
                'name' => $moduledata->name ?? '',
                'modulename' => $modulename,
            ];
        } catch (\Exception $e) {
            $transaction->rollback($e);

            if ($e instanceof dsl_exception) {
                throw $e;
            }

            throw new dsl_exception(
                "Module creation failed: " . $e->getMessage(),
                'create_module',
                ['modulename' => $modulename],
                $e
            );
        }
    }

    /**
     * Validate that required context values are present.
     *
     * @param array $context The context array.
     * @throws dsl_exception If required values are missing.
     */
    protected function validate_context(array $context): void {
        $required = ['courseid', 'sectionid', 'sectionnum', 'modulename'];

        foreach ($required as $key) {
            if (!isset($context[$key])) {
                throw dsl_exception::missing_context($key);
            }
        }
    }

    /**
     * Create the course module record.
     *
     * @param int $courseid The course ID.
     * @param int $moduleid The module type ID.
     * @param int $sectionid The section ID.
     * @param string $modulename Module plugin name.
     * @return int The new course module ID (cmid).
     * @throws dsl_exception If creation fails.
     */
    protected function create_course_module(int $courseid, int $moduleid, int $sectionid, string $modulename): int {
        $cm = new \stdClass();
        $cm->course = $courseid;
        $cm->module = $moduleid;
        $cm->section = $sectionid;
        foreach (module_activity_defaults_registry::get_course_module_defaults($modulename) as $key => $value) {
            $cm->{$key} = $value;
        }

        $cmid = \add_course_module($cm);

        if (!$cmid) {
            throw new dsl_exception(
                'add_course_module returned false',
                'create_module',
                ['courseid' => $courseid, 'moduleid' => $moduleid]
            );
        }

        return $cmid;
    }

    /**
     * Prepare module data for the add_instance function.
     *
     * Merges data in priority order (later overrides earlier):
     * 1. Platform defaults from get_config()
     * 2. Module-specific quirks (field name remappings)
     * 3. Activity instance completion defaults for the module type
     * 4. DSL-provided fields
     *
     * @param array $fields The resolved field values.
     * @param int $courseid The course ID.
     * @param int $cmid The course module ID.
     * @param string $modulename The module type name.
     * @return \stdClass The module data object.
     */
    protected function prepare_module_data(array $fields, int $courseid, int $cmid, string $modulename): \stdClass {
        // Get platform defaults for this module type.
        $platformdefaults = $this->get_platform_defaults($modulename);

        // Get module-specific quirks (field name remappings, etc.).
        $quirks = $this->get_module_field_quirks($modulename);

        $activitydefaults = module_activity_defaults_registry::get_instance_completion_defaults($modulename);

        // Merge in priority order: platform defaults -> quirks -> activity defaults -> DSL fields.
        $mergedfields = array_merge($platformdefaults, $quirks, $activitydefaults, $fields);

        $moduledata = (object) $mergedfields;
        $moduledata->course = $courseid;
        $moduledata->coursemodule = $cmid;
        $moduledata->cmidnumber = $cmid;

        return $moduledata;
    }

    /**
     * Get platform default values for a module type.
     *
     * Retrieves admin-configured defaults from Moodle's config table.
     *
     * @param string $modulename The module type name.
     * @return array The platform default values as an associative array.
     */
    protected function get_platform_defaults(string $modulename): array {
        $config = get_config($modulename);

        if (empty($config) || !is_object($config)) {
            return [];
        }

        return (array) $config;
    }

    /**
     * Get module-specific data quirks merged before activity defaults and DSL fields.
     *
     * @param string $modulename The module type name.
     * @return array<string, mixed>
     */
    protected function get_module_field_quirks(string $modulename): array {
        global $CFG;

        $quirks = self::MODULE_FIELD_QUIRKS[$modulename] ?? [];

        if ($modulename === 'glossary') {
            $edunaoformatfile = $CFG->dirroot . '/mod/glossary/formats/edunao123/edunao123_format.php';
            $quirks['displayformat'] = file_exists($edunaoformatfile) ? 'edunao123' : 'dictionary';
        }

        return $quirks;
    }

    /**
     * Create the module instance using the module's add_instance function.
     *
     * @param string $modulename The module type name.
     * @param \stdClass $moduledata The module data.
     * @return int|false The instance ID or false on failure.
     */
    protected function create_module_instance(string $modulename, \stdClass $moduledata): int|false {
        global $CFG;

        $libfile = $CFG->dirroot . '/mod/' . $modulename . '/lib.php';
        if (!file_exists($libfile)) {
            throw dsl_exception::module_creation_failed($modulename, "Module lib.php not found: $libfile");
        }

        require_once($libfile);

        $addfunction = $modulename . '_add_instance';
        if (!function_exists($addfunction)) {
            throw dsl_exception::module_creation_failed($modulename, "Function $addfunction does not exist");
        }

        return $addfunction($moduledata, null);
    }

    /**
     * Run module-specific before_module_created hook.
     *
     * Looks for a hook class in block_dixeo_modulegen for backward compatibility.
     *
     * @param string $modulename The module type name.
     * @param int $cmid The course module ID.
     * @param \stdClass $moduledata The module data (passed by reference).
     */
    protected function run_before_hook(string $modulename, int $cmid, \stdClass &$moduledata): void {
        $classname = '\\block_dixeo_modulegen\\modules\\' . $modulename;

        if (class_exists($classname) && method_exists($classname, 'before_module_created')) {
            $classname::before_module_created($cmid, $moduledata);
        }
    }

    /**
     * Run module-specific after_module_created hook.
     *
     * Looks for a hook class in block_dixeo_modulegen for backward compatibility.
     *
     * @param string $modulename The module type name.
     * @param int $cmid The course module ID.
     * @param \stdClass $moduledata The module data (passed by reference).
     */
    protected function run_after_hook(string $modulename, int $cmid, \stdClass &$moduledata): void {
        $classname = '\\block_dixeo_modulegen\\modules\\' . $modulename;

        if (class_exists($classname) && method_exists($classname, 'after_module_created')) {
            $classname::after_module_created($cmid, $moduledata);
        }
    }

    /**
     * Set the gradebook passing grade for a quiz instance (aligned with local_edai).
     *
     * @param int $courseid Course id.
     * @param int $quizid Quiz instance id.
     */
    protected function set_quiz_pass_grade(int $courseid, int $quizid): void {
        $gradeitem = \grade_item::fetch([
            'courseid' => $courseid,
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'iteminstance' => $quizid,
            'itemnumber' => 0,
        ]);
        if (!$gradeitem) {
            throw new dsl_exception(
                'Grade item not found for quiz after creation',
                'create_module',
                ['quizid' => $quizid, 'courseid' => $courseid]
            );
        }
        $gradeitem->gradepass = 50;
        $gradeitem->update('local_dixeo');
    }
}
