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
 * Read-only assign context and submission extraction for Dixeo UX flows.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service;

/**
 * Reads assignment metadata, grading criteria, and student submissions.
 */
class assign_submission_reader {
    /** File extensions the AI path can consume (pdf via file_search, txt inline). */
    public const AI_READABLE_EXTENSIONS = ['pdf', 'txt'];

    /** Max size per file for AI upload (20MB). */
    public const UPLOAD_MAX_FILE_SIZE = 20 * 1024 * 1024;

    /** Max total size of files for AI upload (50MB). */
    public const UPLOAD_MAX_TOTAL_SIZE = 50 * 1024 * 1024;

    /**
     * Resolve an assign course module, optionally asserting it belongs to a course.
     *
     * @param int $cmid Course module id.
     * @param int|null $courseid When set, the cm must belong to this course.
     * @return \stdClass Course module record.
     * @throws \dml_missing_record_exception When the cm is missing or not an assign.
     * @throws \moodle_exception When the cm belongs to another course.
     */
    public function require_assign_cm(int $cmid, ?int $courseid = null): \stdClass {
        $cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
        if ($courseid !== null && (int) $cm->course !== (int) $courseid) {
            throw new \moodle_exception('invalidcoursemodule');
        }
        return $cm;
    }

    /**
     * Assignment metadata and grading criteria for AI / UI context.
     *
     * @param int $cmid Course module id.
     * @param int|null $courseid Optional course boundary check.
     * @return array{
     *     name: string,
     *     intro: string,
     *     activity: string,
     *     grading_method: string,
     *     assignmentid: int,
     *     criteria: list<array<string, mixed>>
     * }
     */
    public function get_assignment_context(int $cmid, ?int $courseid = null): array {
        global $DB;

        $cm = $this->require_assign_cm($cmid, $courseid);
        $assign = $DB->get_record(
            'assign',
            ['id' => $cm->instance],
            'name,intro,introformat,activity,activityformat',
            MUST_EXIST
        );

        $context = \context_module::instance($cm->id);
        $area = $DB->get_record('grading_areas', [
            'contextid' => $context->id,
            'component' => 'mod_assign',
            'areaname' => 'submissions',
        ], 'id,activemethod', IGNORE_MISSING);

        $gradingmethod = 'simple';
        if ($area && !empty($area->activemethod)) {
            $gradingmethod = $area->activemethod;
        }

        $result = [
            'name' => $assign->name,
            'intro' => $assign->intro,
            'activity' => $assign->activity ?? '',
            'grading_method' => $gradingmethod,
            'assignmentid' => (int) $cm->instance,
            'criteria' => [],
        ];

        if (!$area || empty($area->id)) {
            return $result;
        }

        if ($gradingmethod === 'guide') {
            $definition = $DB->get_record('grading_definitions', [
                'areaid' => $area->id,
                'method' => 'guide',
            ], 'id', IGNORE_MISSING);
            if ($definition) {
                $rows = $DB->get_records('gradingform_guide_criteria', ['definitionid' => $definition->id], 'sortorder');
                foreach ($rows as $c) {
                    $result['criteria'][] = [
                        'id' => (int) $c->id,
                        'shortname' => $c->shortname,
                        'description' => $c->description ?? '',
                        'maxscore' => (float) $c->maxscore,
                        'sortorder' => (int) $c->sortorder,
                    ];
                }
            }
        } else if ($gradingmethod === 'rubric') {
            $definition = $DB->get_record('grading_definitions', [
                'areaid' => $area->id,
                'method' => 'rubric',
            ], 'id', IGNORE_MISSING);
            if ($definition) {
                $critrows = $DB->get_records('gradingform_rubric_criteria', ['definitionid' => $definition->id], 'sortorder');
                foreach ($critrows as $c) {
                    $levels = $DB->get_records('gradingform_rubric_levels', ['criterionid' => $c->id], 'score');
                    $leveldata = [];
                    foreach ($levels as $l) {
                        $leveldata[] = [
                            'id' => (int) $l->id,
                            'score' => (float) $l->score,
                            'definition' => $l->definition ?? '',
                        ];
                    }
                    $result['criteria'][] = [
                        'id' => (int) $c->id,
                        'description' => $c->description ?? '',
                        'sortorder' => (int) $c->sortorder,
                        'levels' => $leveldata,
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Enabled submission plugins for an assignment.
     *
     * @param int $assignmentid Assign instance id.
     * @return array{onlinetext_enabled: bool, file_enabled: bool, file_filetypeslist: string}
     */
    public function get_submission_plugins(int $assignmentid): array {
        global $DB;

        $rows = $DB->get_records('assign_plugin_config', [
            'assignment' => $assignmentid,
            'subtype' => 'assignsubmission',
        ], 'id ASC');

        $onlinetext = false;
        $file = false;
        $filetypeslist = '';
        foreach ($rows as $row) {
            if ($row->plugin === 'onlinetext' && $row->name === 'enabled') {
                $onlinetext = !empty($row->value);
            }
            if ($row->plugin === 'file') {
                if ($row->name === 'enabled') {
                    $file = !empty($row->value);
                }
                if ($row->name === 'filetypeslist') {
                    $filetypeslist = (string) $row->value;
                }
            }
        }

        return [
            'onlinetext_enabled' => $onlinetext,
            'file_enabled' => $file,
            'file_filetypeslist' => $filetypeslist,
        ];
    }

    /**
     * Ensure the assignment has at least one supported submission type.
     *
     * @param int $assignmentid Assign instance id.
     * @return string|null Error message or null if valid.
     */
    public function validate_submission_plugins_for_analysis(int $assignmentid): ?string {
        $plugins = $this->get_submission_plugins($assignmentid);
        if ($plugins['onlinetext_enabled'] || $plugins['file_enabled']) {
            return null;
        }
        return get_string('assign_submission_unsupported', 'local_dixeo');
    }

    /**
     * Latest submission plus onlinetext, files, and plugin config.
     *
     * @param int $cmid Course module id.
     * @param int $userid Student user id.
     * @param int|null $courseid Optional course boundary check.
     * @return array{
     *     submission: \stdClass|null,
     *     onlinetext: string,
     *     attempts: list<\stdClass>,
     *     submission_plugins: array{onlinetext_enabled: bool, file_enabled: bool, file_filetypeslist: string},
     *     submission_files: list<\stored_file>
     * }
     */
    public function get_submission_data(int $cmid, int $userid, ?int $courseid = null): array {
        global $DB;

        $cm = $this->require_assign_cm($cmid, $courseid);
        $assignmentid = (int) $cm->instance;
        $plugins = $this->get_submission_plugins($assignmentid);

        $submission = $DB->get_record_sql(
            'SELECT * FROM {assign_submission}
             WHERE assignment = :assignmentid AND userid = :userid AND latest = 1
             ORDER BY attemptnumber DESC',
            ['assignmentid' => $assignmentid, 'userid' => $userid],
            IGNORE_MISSING
        );

        $attempts = $DB->get_records('assign_submission', [
            'assignment' => $assignmentid,
            'userid' => $userid,
        ], 'attemptnumber ASC');

        $onlinetext = '';
        $submissionid = $submission ? (int) $submission->id : null;
        if ($submissionid && $plugins['onlinetext_enabled']) {
            $onlinetextrec = $DB->get_record('assignsubmission_onlinetext', [
                'assignment' => $assignmentid,
                'submission' => $submissionid,
            ], 'onlinetext', IGNORE_MISSING);
            if ($onlinetextrec) {
                $onlinetext = $onlinetextrec->onlinetext ?? '';
            }
        }

        $files = [];
        if ($submissionid && $plugins['file_enabled']) {
            $context = \context_module::instance($cm->id);
            $files = $this->get_submission_files($context->id, $submissionid);
        }

        return [
            'submission' => $submission,
            'onlinetext' => $onlinetext,
            'attempts' => array_values($attempts),
            'submission_plugins' => $plugins,
            'submission_files' => $files,
        ];
    }

    /**
     * Stored files for a submission (excluding directories).
     *
     * @param int $contextid Module context id.
     * @param int $submissionid Assign submission id (file area itemid).
     * @return list<\stored_file>
     */
    public function get_submission_files(int $contextid, int $submissionid): array {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $contextid,
            'assignsubmission_file',
            'submission_files',
            $submissionid,
            'itemid, filepath, filename',
            false
        );
        return array_values($files);
    }

    /**
     * Filter submission files by AI-readable extension and size limits.
     *
     * @param \stored_file[] $storedfiles
     * @return array{valid: list<\stored_file>, notreadable: list<string>}
     */
    public function validate_submission_files_for_upload(array $storedfiles): array {
        $valid = [];
        $notreadable = [];
        $totalsize = 0;
        foreach ($storedfiles as $file) {
            if (!($file instanceof \stored_file) || $file->is_directory()) {
                continue;
            }
            $ext = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
            if (!in_array($ext, self::AI_READABLE_EXTENSIONS, true)) {
                $notreadable[] = $file->get_filename();
                continue;
            }
            $size = $file->get_filesize();
            if ($size > self::UPLOAD_MAX_FILE_SIZE) {
                $notreadable[] = $file->get_filename() . ' (exceeds ' . (self::UPLOAD_MAX_FILE_SIZE / (1024 * 1024)) . 'MB)';
                continue;
            }
            if ($totalsize + $size > self::UPLOAD_MAX_TOTAL_SIZE) {
                $notreadable[] = $file->get_filename() . ' (total upload limit exceeded)';
                continue;
            }
            $valid[] = $file;
            $totalsize += $size;
        }
        return ['valid' => $valid, 'notreadable' => $notreadable];
    }

    /**
     * Build plain-text submission content for AI payloads (onlinetext + txt; PDF placeholder).
     *
     * @param int $assignmentid Assign instance id.
     * @param array $submissiondata From {@see get_submission_data()}.
     * @return array{content: string, warning: string|null}|array{error: string}
     */
    public function build_submission_block_for_ai(int $assignmentid, array $submissiondata): array {
        $err = $this->validate_submission_plugins_for_analysis($assignmentid);
        if ($err !== null) {
            return ['error' => $err];
        }

        $plugins = $submissiondata['submission_plugins'] ?? $this->get_submission_plugins($assignmentid);
        $onlinetext = trim($submissiondata['onlinetext'] ?? '');
        $files = $submissiondata['submission_files'] ?? [];

        $parts = [];
        $notreadable = [];
        $readablecontent = '';

        if ($plugins['onlinetext_enabled'] && $onlinetext !== '') {
            $parts[] = strip_tags($onlinetext);
        }

        foreach ($files as $file) {
            if (!$file instanceof \stored_file || $file->is_directory()) {
                continue;
            }
            $ext = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
            if (!in_array($ext, self::AI_READABLE_EXTENSIONS, true)) {
                $notreadable[] = $file->get_filename();
                continue;
            }
            try {
                $content = $file->get_content();
                if ($ext === 'txt') {
                    $readablecontent .= "\n\n--- File: " . $file->get_filename() . " ---\n" . $content;
                } else {
                    $readablecontent .= "\n\n[Attachment: " . $file->get_filename()
                        . " (PDF - content not extracted in this version)]";
                }
            } catch (\Throwable $e) {
                $notreadable[] = $file->get_filename() . ' (' . $e->getMessage() . ')';
            }
        }

        if ($plugins['file_enabled'] && !empty($files) && $readablecontent === '' && empty($parts)) {
            return ['error' => get_string('assign_submission_no_readable_files', 'local_dixeo')];
        }

        if ($readablecontent !== '') {
            $parts[] = trim($readablecontent);
        }

        $content = implode("\n\n", array_filter($parts));
        if ($content === '') {
            $content = '(No submission content yet.)';
        }

        $warning = null;
        if (!empty($notreadable)) {
            $warning = get_string(
                'assign_submission_some_files_not_readable',
                'local_dixeo',
                implode(', ', $notreadable)
            );
        }

        return ['content' => $content, 'warning' => $warning];
    }
}
