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
 * Creates mod_resource (File) instances from a user draft file.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service;

use coding_exception;
use context_user;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/resourcelib.php');

/**
 * File (mod_resource) manual upload creation.
 */
class resource_upload_service {
    /** @var string Module plugin name. */
    private const MODULE_NAME = 'resource';

    /**
     * Create a mod_resource instance from a staged user draft file.
     *
     * @param int $courseid Target course ID.
     * @param int $sectionid Target section record ID.
     * @param int $sectionnum Target section number.
     * @param int|null $beforemod Optional cmid to insert before.
     * @param string $name Activity name.
     * @param int $draftitemid User draft area item ID containing the file.
     * @return array{cmid: int, id: int}
     * @throws coding_exception|moodle_exception
     */
    public function create_from_draft(
        int $courseid,
        int $sectionid,
        int $sectionnum,
        ?int $beforemod,
        string $name,
        int $draftitemid
    ): array {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/mod/resource/lib.php');

        $moduleid = module_addinstance_service::require_for_course($courseid, self::MODULE_NAME);

        $usercontext = context_user::instance($USER->id);
        $fs = get_file_storage();
        $draftfiles = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id', false);
        if (empty($draftfiles)) {
            throw new moodle_exception('manual_upload_error_missing', 'block_dixeo_modulegen');
        }

        $config = get_config('resource');
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

            $instance = $this->prepare_instance_data($courseid, $cmid, $name, $draftitemid, $config);
            $instanceid = resource_add_instance($instance, null);
            if (!$instanceid) {
                throw new coding_exception('resource_add_instance returned a falsy value');
            }

            rebuild_course_cache($courseid);
            $transaction->allow_commit();

            return ['cmid' => (int) $cmid, 'id' => (int) $instanceid];
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            throw $e;
        }
    }

    /**
     * Build instance data for resource_add_instance().
     *
     * @param int $courseid Course ID.
     * @param int $cmid Course module ID.
     * @param string $name Activity name.
     * @param int $draftitemid Draft file area item ID.
     * @param stdClass $config Site resource config.
     * @return stdClass
     */
    private function prepare_instance_data(
        int $courseid,
        int $cmid,
        string $name,
        int $draftitemid,
        stdClass $config
    ): stdClass {
        return (object) [
            'course' => $courseid,
            'coursemodule' => $cmid,
            'name' => $name,
            'intro' => '',
            'introformat' => FORMAT_HTML,
            'files' => $draftitemid,
            'display' => $config->display ?? RESOURCELIB_DISPLAY_AUTO,
            'popupheight' => $config->popupheight ?? 600,
            'popupwidth' => $config->popupwidth ?? 800,
            'printintro' => $config->printintro ?? 0,
            'showsize' => $config->showsize ?? 0,
            'showtype' => $config->showtype ?? 0,
            'showdate' => $config->showdate ?? 0,
            'filterfiles' => $config->filterfiles ?? 0,
        ];
    }
}
