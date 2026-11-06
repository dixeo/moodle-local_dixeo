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
 * Orchestrates manual (non-AI) module uploads from multipart form data.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service;

use coding_exception;
use context_course;
use context_user;
use local_dixeo\dsl\interpreter;
use local_dixeo\external\service_factory;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');

/**
 * Generic manual upload orchestration for SCORM and File activities.
 */
class manual_upload_service {
    /** @var string[] Supported modtype values. */
    private const SUPPORTED_MODTYPES = ['scorm', 'resource'];

    /**
     * Maximum resource file size (20 MB), matching block_dixeo_designer submission uploads.
     */
    public const MAX_RESOURCE_FILE_SIZE = 20971520;

    /** @var scorm_creation_service */
    private scorm_creation_service $scormservice;

    /** @var resource_upload_service */
    private resource_upload_service $resourceservice;

    /** @var scorm_vector_extract_service */
    private scorm_vector_extract_service $scormextractservice;

    /**
     * Constructor.
     *
     * @param scorm_creation_service|null $scormservice Optional SCORM service.
     * @param resource_upload_service|null $resourceservice Optional resource service.
     * @param scorm_vector_extract_service|null $scormextractservice Optional SCORM extract service.
     */
    public function __construct(
        ?scorm_creation_service $scormservice = null,
        ?resource_upload_service $resourceservice = null,
        ?scorm_vector_extract_service $scormextractservice = null
    ) {
        $this->scormservice = $scormservice ?? new scorm_creation_service();
        $this->resourceservice = $resourceservice ?? new resource_upload_service();
        $this->scormextractservice = $scormextractservice ?? new scorm_vector_extract_service();
    }

    /**
     * Create a module from an uploaded file.
     *
     * @param string $modtype Module type: scorm or resource.
     * @param int $courseid Course ID.
     * @param int $sectionnum Section number.
     * @param int|null $beforemod Optional cmid to insert before.
     * @param array $uploadedfile $_FILES entry shape.
     * @return array{cmid: int, id: int, name: string}
     * @throws coding_exception|moodle_exception
     * @throws \local_dixeo\exception\consent_required_exception error:consentrequired when the user has not accepted
     *         the current Dixeo Terms of Use
     */
    public function create_from_upload(
        string $modtype,
        int $courseid,
        int $sectionnum,
        ?int $beforemod,
        array $uploadedfile
    ): array {
        if (!in_array($modtype, self::SUPPORTED_MODTYPES, true)) {
            throw new coding_exception('Unsupported manual upload modtype: ' . $modtype);
        }

        $this->validate_course_access($courseid);
        $this->validate_uploaded_file($modtype, $uploadedfile);
        $name = $this->derive_activity_name($modtype, $uploadedfile);
        $draftitemid = $this->stage_upload_to_draft($courseid, $uploadedfile);

        $context = interpreter::build_context($courseid, $sectionnum, $modtype, $beforemod ?: null);
        $sectionid = (int) $context['sectionid'];
        $resolvedsectionnum = (int) $context['sectionnum'];
        $resolvedbeforemod = !empty($context['beforemod']) ? (int) $context['beforemod'] : null;

        if ($modtype === 'scorm') {
            $result = $this->scormservice->create_from_draft(
                $courseid,
                $sectionid,
                $resolvedsectionnum,
                $resolvedbeforemod,
                $name,
                $draftitemid
            );
        } else {
            $result = $this->resourceservice->create_from_draft(
                $courseid,
                $sectionid,
                $resolvedsectionnum,
                $resolvedbeforemod,
                $name,
                $draftitemid
            );
        }

        service_factory::get_file_sync_service()
            ->enable_and_trigger_sync_after_module_creation($courseid);

        $result['name'] = $name;

        return $result;
    }

    /**
     * Derive the Moodle activity name from the uploaded file.
     *
     * SCORM: manifest title with filename fallback. Resource: filename without extension.
     *
     * @param string $modtype Module type: scorm or resource.
     * @param array $uploadedfile $_FILES entry shape.
     * @return string
     * @throws moodle_exception
     */
    private function derive_activity_name(string $modtype, array $uploadedfile): string {
        $filename = clean_param($uploadedfile['name'] ?? '', PARAM_FILE);
        if ($filename === '') {
            throw new moodle_exception('manual_upload_error_missing', 'block_dixeo_modulegen');
        }

        if ($modtype === 'scorm') {
            $name = $this->scormextractservice->get_package_title_from_zip_path(
                $uploadedfile['tmp_name'],
                $filename
            );
        } else {
            $name = $this->activity_name_from_filename($filename);
        }

        $name = trim($name);
        if ($name === '') {
            throw new moodle_exception('manual_upload_error_missing', 'block_dixeo_modulegen');
        }

        return $name;
    }

    /**
     * Derive an activity name from an uploaded filename.
     *
     * @param string $filename Original upload filename.
     * @return string Basename without extension.
     */
    private function activity_name_from_filename(string $filename): string {
        $filename = clean_param($filename, PARAM_FILE);
        $base = pathinfo($filename, PATHINFO_FILENAME);
        return ($base !== '') ? $base : $filename;
    }

    /**
     * Validate uploaded file type before staging to draft.
     *
     * @param string $modtype Module type: scorm or resource.
     * @param array $uploadedfile $_FILES entry shape.
     * @throws moodle_exception
     */
    private function validate_uploaded_file(string $modtype, array $uploadedfile): void {
        if (empty($uploadedfile['tmp_name']) || !is_uploaded_file($uploadedfile['tmp_name'])) {
            throw new moodle_exception('manual_upload_error_missing', 'block_dixeo_modulegen');
        }

        $filename = clean_param($uploadedfile['name'] ?? '', PARAM_FILE);
        if ($filename === '') {
            throw new moodle_exception('manual_upload_error_missing', 'block_dixeo_modulegen');
        }

        if ($modtype === 'scorm') {
            if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'zip') {
                throw new moodle_exception('manual_upload_error_invalid_scorm', 'block_dixeo_modulegen');
            }
            if (!$this->scormextractservice->is_storyline_extractable($uploadedfile['tmp_name'])) {
                throw new moodle_exception('manual_upload_error_invalid_scorm', 'block_dixeo_modulegen');
            }
            return;
        }

        if ($modtype === 'resource') {
            $filesize = (int) ($uploadedfile['size'] ?? 0);
            if ($filesize > self::MAX_RESOURCE_FILE_SIZE) {
                throw new moodle_exception(
                    'manual_upload_error_file_too_large',
                    'block_dixeo_modulegen',
                    '',
                    (object) ['maxsize' => display_size(self::MAX_RESOURCE_FILE_SIZE)]
                );
            }
            if (!file_sync_service::is_rag_indexed_filename($filename)) {
                throw new moodle_exception(
                    'manual_upload_error_invalid_resource',
                    'block_dixeo_modulegen',
                    '',
                    (object) ['ragformats' => file_sync_service::format_rag_indexed_extensions_label()]
                );
            }
        }
    }

    /**
     * Validate course login, generate and manageactivities capabilities, and the current Terms of Use.
     *
     * The upload ends with a course file sync that sends the file to Dixeo, so the acting user
     * must have accepted the current Dixeo Terms of Use before anything is staged.
     *
     * @param int $courseid Course ID.
     * @throws \local_dixeo\exception\consent_required_exception error:consentrequired when the user has not accepted
     *         the current Dixeo Terms of Use
     */
    private function validate_course_access(int $courseid): void {
        global $USER;

        require_course_login($courseid);
        $context = context_course::instance($courseid);
        require_capability('local/dixeo:generate', $context);
        require_capability('moodle/course:manageactivities', $context);
        (new consent_service())->require_accepted((int) $USER->id, $context);
    }

    /**
     * Store an uploaded file in the current user's draft area.
     *
     * @param int $courseid Course ID for maxbytes check.
     * @param array $uploadedfile $_FILES entry.
     * @return int Draft item ID.
     * @throws moodle_exception
     */
    private function stage_upload_to_draft(int $courseid, array $uploadedfile): int {
        global $CFG, $USER;

        if (empty($uploadedfile) || !isset($uploadedfile['error'])) {
            throw new moodle_exception('manual_upload_error_missing', 'block_dixeo_modulegen');
        }
        if ($uploadedfile['error'] !== UPLOAD_ERR_OK) {
            throw new moodle_exception('manual_upload_error_failed', 'block_dixeo_modulegen');
        }
        if (empty($uploadedfile['tmp_name']) || !is_uploaded_file($uploadedfile['tmp_name'])) {
            throw new moodle_exception('manual_upload_error_missing', 'block_dixeo_modulegen');
        }

        $filename = clean_param($uploadedfile['name'] ?? '', PARAM_FILE);
        if ($filename === '') {
            throw new moodle_exception('manual_upload_error_missing', 'block_dixeo_modulegen');
        }

        $course = get_course($courseid);
        $context = context_course::instance($courseid);
        $maxbytes = get_user_max_upload_file_size($context, $CFG->maxbytes, $course->maxbytes);
        if (
            $maxbytes != USER_CAN_IGNORE_FILE_SIZE_LIMITS
                && !empty($uploadedfile['size']) && $uploadedfile['size'] > $maxbytes
        ) {
            throw new moodle_exception('uploadfilelimitexceeded', 'error');
        }

        $draftitemid = file_get_unused_draft_itemid();
        $usercontext = context_user::instance($USER->id);
        $filerecord = [
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => $filename,
            'userid' => $USER->id,
        ];

        $fs = get_file_storage();
        $stored = $fs->create_file_from_pathname($filerecord, $uploadedfile['tmp_name']);
        if (!$stored) {
            throw new moodle_exception('manual_upload_error_failed', 'block_dixeo_modulegen');
        }

        return $draftitemid;
    }
}
