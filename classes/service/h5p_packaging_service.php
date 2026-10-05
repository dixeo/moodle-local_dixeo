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

/**
 * Service for packaging H5P content into .h5p files and creating mod_h5pactivity instances.
 *
 * Exposes two library-level entry points reusable by any plugin:
 * - build_package(): assemble a self-contained .h5p file from a content array
 *   and a main library identifier, returning the absolute path to a temp file.
 * - create_activity(): build the package and create a mod_h5pactivity course
 *   module that uses it.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service;

use coding_exception;
use stdClass;
use ZipArchive;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Builds .h5p packages and provisions mod_h5pactivity course modules.
 */
class h5p_packaging_service {
    /** @var string Module name for the H5P activity in Moodle's modules table. */
    private const MODULE_NAME = 'h5pactivity';

    /** @var int Default grade-to-pass for the activity. */
    private const DEFAULT_GRADEPASS = 50;

    /** @var int Default maximum grade for the activity. */
    private const DEFAULT_GRADE = 100;

    /**
     * Build a self-contained .h5p package file from a content structure and a main library identifier.
     *
     * The returned path points to a temporary file. Callers are responsible for moving or deleting it.
     *
     * The version embedded in `h5p.json` is the site's actually-installed minor (resolved
     * via {@see h5p_library_service::resolve_installed_version()}) — not the API's pinned
     * version — because Moodle's H5P import rejects any package referencing a library
     * version it does not have. H5P's library convention guarantees backward compatibility
     * within a major, so content authored against the pinned minor runs fine on any
     * installed minor `>=` it.
     *
     * @param string $mainlibrary Minimum-version requirement, e.g. 'H5P.QuestionSet 1.20'.
     * @param array $content Content structure to serialize as content.json (already library-shaped).
     * @param string $title Human-readable activity title (stored in h5p.json metadata).
     * @param string $language Two-letter language code stored as h5p.json defaultLanguage.
     * @return string Absolute path to a temporary .h5p file.
     * @throws coding_exception If no compatible library is installed or the package cannot be written.
     */
    public function build_package(string $mainlibrary, array $content, string $title = '', string $language = 'en'): string {
        $resolved = h5p_library_service::resolve_installed_version($mainlibrary);
        if ($resolved === null) {
            throw new coding_exception("No installed H5P library satisfies '{$mainlibrary}'");
        }

        $h5pjson = [
            'title' => $title !== '' ? $title : $resolved['machinename'],
            'language' => $language,
            'mainLibrary' => $resolved['machinename'],
            'embedTypes' => ['iframe'],
            'license' => 'U',
            'defaultLanguage' => $language,
            'preloadedDependencies' => [
                [
                    'machineName' => $resolved['machinename'],
                    'majorVersion' => $resolved['major'],
                    'minorVersion' => $resolved['minor'],
                ],
            ],
        ];

        $tempdir = make_request_directory();
        $packagepath = $tempdir . DIRECTORY_SEPARATOR . 'package.h5p';

        $zip = new ZipArchive();
        if ($zip->open($packagepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new coding_exception("Failed to open .h5p archive for writing: {$packagepath}");
        }

        $zip->addFromString('h5p.json', json_encode($h5pjson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $zip->addFromString('content/content.json', json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $zip->close();

        return $packagepath;
    }

    /**
     * Create a mod_h5pactivity instance in the given course section, using a freshly-built package.
     *
     * Wraps Moodle's standard module creation APIs (add_course_module + h5pactivity_add_instance)
     * with the H5P-specific steps: writing the package file into the module's file area,
     * setting the activity reference, and registering the content with core_h5p.
     *
     * @param int $courseid The target course ID.
     * @param int $sectionid The target section ID.
     * @param int $sectionnum The target section number (0-based).
     * @param string $name Activity name displayed in the course.
     * @param string $intro Activity intro shown above the H5P content.
     * @param string $mainlibrary Full library identifier including version.
     * @param array $content Content structure already shaped for the target H5P library.
     * @param string $language ISO 639-1 code for the .h5p defaultLanguage; falls back to current_language() when empty.
     * @param int|null $beforemod Optional cmid to insert before; null appends to the section.
     * @return array Two-key array: ['cmid' => int, 'id' => int (instance id)].
     * @throws coding_exception If mod_h5pactivity is missing or any step of the creation pipeline fails.
     */
    public function create_activity(
        int $courseid,
        int $sectionid,
        int $sectionnum,
        string $name,
        string $intro,
        string $mainlibrary,
        array $content,
        string $language = '',
        ?int $beforemod = null
    ): array {
        global $CFG, $DB;

        $moduleid = module_addinstance_service::require_for_course($courseid, self::MODULE_NAME);

        $resolvedlanguage = $language !== '' ? $language : current_language();
        $packagepath = $this->build_package($mainlibrary, $content, $name, $resolvedlanguage);

        require_once($CFG->dirroot . '/mod/h5pactivity/lib.php');

        $transaction = $DB->start_delegated_transaction();
        try {
            $cm = new stdClass();
            $cm->course = $courseid;
            $cm->module = $moduleid;
            $cm->section = $sectionid;
            foreach (module_activity_defaults_registry::get_course_module_defaults(self::MODULE_NAME) as $key => $value) {
                $cm->{$key} = $value;
            }

            $cmid = add_course_module($cm);
            if (!$cmid) {
                throw new coding_exception('add_course_module returned a falsy value');
            }

            course_add_cm_to_section($courseid, $cmid, $sectionnum, $beforemod);

            $moduledata = $this->prepare_instance_data($courseid, $cmid, $name, $intro);
            $instanceid = h5pactivity_add_instance($moduledata, null);
            if (!$instanceid) {
                throw new coding_exception('h5pactivity_add_instance returned a falsy value');
            }

            $DB->set_field('course_modules', 'instance', $instanceid, ['id' => $cmid]);

            $this->store_package_file($cmid, $packagepath);

            $this->register_h5p_content($cmid);

            $this->apply_default_grade_pass((int) $instanceid);

            rebuild_course_cache($courseid);

            $transaction->allow_commit();

            return ['cmid' => (int) $cmid, 'id' => (int) $instanceid];
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            throw $e;
        } finally {
            @unlink($packagepath);
        }
    }

    /**
     * Build the stdClass passed to h5pactivity_add_instance with sensible defaults.
     *
     * Defaults are merged from the module's admin-configured platform defaults so any
     * site-level configuration is respected.
     *
     * @param int $courseid The course ID.
     * @param int $cmid The course module ID just created.
     * @param string $name Activity name.
     * @param string $intro HTML intro.
     * @return stdClass The instance data.
     */
    private function prepare_instance_data(int $courseid, int $cmid, string $name, string $intro): stdClass {
        $platformdefaults = (array) (get_config(self::MODULE_NAME) ?: []);
        $activitydefaults = module_activity_defaults_registry::get_instance_completion_defaults(self::MODULE_NAME);

        $factory = new \core_h5p\factory();
        $core = $factory->get_core();
        $h5pdisplayconfig = \core_h5p\helper::decode_display_options($core);
        $displayoptions = \core_h5p\helper::get_display_options($core, $h5pdisplayconfig);

        $data = (object) array_merge($platformdefaults, $activitydefaults, [
            'course' => $courseid,
            'coursemodule' => $cmid,
            'cmidnumber' => $cmid,
            'name' => $name,
            'intro' => $intro,
            'introformat' => FORMAT_HTML,
            'grade' => self::DEFAULT_GRADE,
            'gradepass' => self::DEFAULT_GRADEPASS,
            'displayoptions' => $displayoptions,
        ]);

        // H5P activity add_instance reads these only when set; let it pick its own defaults otherwise.
        if (!isset($data->enabletracking)) {
            $data->enabletracking = 1;
        }
        if (!isset($data->grademethod)) {
            $data->grademethod = 1;
        }
        if (!isset($data->reviewmode)) {
            $data->reviewmode = 1;
        }
        if (!isset($data->attempts)) {
            $data->attempts = 0;
        }

        return $data;
    }

    /**
     * Copy a .h5p file from a temp path into the module's package file area.
     *
     * @param int $cmid The course module ID.
     * @param string $sourcepath Absolute path to the source .h5p file.
     * @throws coding_exception If the file cannot be stored.
     */
    private function store_package_file(int $cmid, string $sourcepath): void {
        $context = \context_module::instance($cmid);
        $fs = get_file_storage();

        $fs->delete_area_files($context->id, 'mod_h5pactivity', 'package');

        $filerecord = (object) [
            'contextid' => $context->id,
            'component' => 'mod_h5pactivity',
            'filearea' => 'package',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => basename($sourcepath),
        ];

        $stored = $fs->create_file_from_pathname($filerecord, $sourcepath);
        if (!$stored) {
            throw new coding_exception('Failed to store .h5p file in module package area');
        }
    }

    /**
     * Register the package file with the H5P core so the activity can render.
     *
     * @param int $cmid The course module ID.
     * @throws coding_exception If the package file is missing.
     */
    private function register_h5p_content(int $cmid): void {
        $context = \context_module::instance($cmid);
        $fs = get_file_storage();

        $files = $fs->get_area_files($context->id, 'mod_h5pactivity', 'package', 0, 'id', false);
        $file = reset($files);
        if (!$file) {
            throw new coding_exception('Expected .h5p package file is missing from the module file area');
        }

        $url = \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename(),
            false
        );
        $url->param('contextid', $context->id);

        $factory = new \core_h5p\factory();
        $config = new stdClass();
        $messages = new stdClass();

        \core_h5p\api::create_content_from_pluginfile_url(
            $url->out(),
            $config,
            $factory,
            $messages,
            true,
            true
        );
    }

    /**
     * Set the default grade pass for the activity.
     *
     * @param int $instanceid h5pactivity.id
     */
    private function apply_default_grade_pass(int $instanceid): void {
        global $DB;

        $gradeitem = $DB->get_record('grade_items', [
            'itemtype' => 'mod',
            'itemmodule' => self::MODULE_NAME,
            'iteminstance' => $instanceid,
            'itemnumber' => 0,
        ]);
        if (!$gradeitem) {
            return;
        }
        $gradeitem->gradepass = (float) self::DEFAULT_GRADEPASS;
        $DB->update_record('grade_items', $gradeitem);
    }
}
