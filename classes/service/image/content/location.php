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

namespace local_dixeo\service\image\content;


/**
 * Canonical file location identity for content image jobs and version history.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class location {
    /** @var int */
    public int $contextid;
    /** @var string */
    public string $component;
    /** @var string */
    public string $filearea;
    /** @var int */
    public int $itemid;
    /** @var string */
    public string $filepath;
    /** @var string */
    public string $filename;
    /** @var int */
    public int $courseid;

    /**
     *   construct.
     * @param int $contextid
     * @param string $component
     * @param string $filearea
     * @param int $itemid
     * @param string $filepath
     * @param string $filename
     * @param int $courseid
     */
    public function __construct(
        int $contextid,
        string $component,
        string $filearea,
        int $itemid,
        string $filepath,
        string $filename,
        int $courseid
    ) {
        $this->contextid = $contextid;
        $this->component = $component;
        $this->filearea = $filearea;
        $this->itemid = $itemid;
        $this->filepath = $filepath === '' ? '/' : $filepath;
        $this->filename = $filename;
        $this->courseid = $courseid;
    }

    /**
     * From stored file.
     * @param \stored_file $file
     * @return self
     */
    public static function from_stored_file(\stored_file $file): self {
        $courseid = \local_dixeo\service\image\pluginfile_helper::resolve_course_id_for_file($file);
        return new self(
            (int) $file->get_contextid(),
            (string) $file->get_component(),
            (string) $file->get_filearea(),
            (int) $file->get_itemid(),
            (string) $file->get_filepath(),
            (string) $file->get_filename(),
            $courseid
        );
    }

    /**
     * From params.
     * @param array $params
     * @return self
     */
    public static function from_params(array $params): self {
        return new self(
            (int) ($params['contextid'] ?? 0),
            (string) ($params['component'] ?? ''),
            (string) ($params['filearea'] ?? ''),
            (int) ($params['itemid'] ?? 0),
            (string) ($params['filepath'] ?? '/'),
            (string) ($params['filename'] ?? ''),
            (int) ($params['courseid'] ?? 0)
        );
    }

    /**
     * From job record.
     * @param \stdClass $record Job DB row with pluginfile coordinates.
     * @return self
     */
    public static function from_job_record(\stdClass $record): self {
        return new self(
            (int) ($record->contextid ?? 0),
            (string) ($record->component ?? ''),
            (string) ($record->filearea ?? ''),
            (int) ($record->itemid ?? 0),
            (string) ($record->filepath ?? '/'),
            (string) ($record->filename ?? ''),
            (int) ($record->courseid ?? 0)
        );
    }

    /**
     * Stable hash for job rows and version history.
     *
     * @return string
     */
    public function hash(): string {
        return sha1(implode('|', [
            $this->contextid,
            $this->component,
            $this->filearea,
            $this->itemid,
            $this->filepath,
            $this->filename,
        ]));
    }

    /**
     * To record fields.
     * @return array<string, int|string>
     */
    public function to_record_fields(): array {
        return [
            'contextid' => $this->contextid,
            'component' => $this->component,
            'filearea' => $this->filearea,
            'itemid' => $this->itemid,
            'filepath' => $this->filepath,
            'filename' => $this->filename,
            'locationhash' => $this->hash(),
            'courseid' => $this->courseid,
        ];
    }

    /**
     * Get stored file.
     * @return \stored_file|null
     */
    public function get_stored_file(): ?\stored_file {
        $fs = get_file_storage();
        $file = $fs->get_file(
            $this->contextid,
            $this->component,
            $this->filearea,
            $this->itemid,
            $this->filepath,
            $this->filename
        );
        if (!$file || $file->is_directory()) {
            return null;
        }
        return $file;
    }

    /**
     * Itemid segment for pluginfile URLs (may differ from stored file itemid).
     *
     * Intro files are stored with itemid 0 but served via core's intro handler
     * which expects no itemid segment in the URL path.
     *
     * @return int|null
     */
    public function get_pluginfile_url_itemid(): ?int {
        if ($this->itemid !== 0) {
            return $this->itemid;
        }
        if ($this->filearea === 'intro') {
            return null;
        }
        return 0;
    }

    /**
     * Token form for storing image src in HTML fields (rewritten at display time).
     *
     * @return string
     */
    public function get_pluginfile_token_src(): string {
        return '@@PLUGINFILE@@/' . $this->filename;
    }

    /**
     * Get pluginfile url.
     * @return string Pluginfile URL for the html_field_target image.
     */
    public function get_pluginfile_url(): string {
        return \moodle_url::make_pluginfile_url(
            $this->contextid,
            $this->component,
            $this->filearea,
            $this->get_pluginfile_url_itemid(),
            $this->filepath,
            $this->filename
        )->out(false);
    }
}
