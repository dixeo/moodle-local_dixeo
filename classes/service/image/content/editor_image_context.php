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
 * Module + editor session coordinates for img-gen round-trip in the content editor.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class editor_image_context {
    /** @var int */
    public int $contextid;
    /** @var int */
    public int $courseid;
    /** @var int */
    public int $cmid;
    /** @var string */
    public string $modname;
    /** @var string */
    public string $component;
    /** @var string */
    public string $filearea;
    /** @var int */
    public int $fileitemid;
    /** @var string */
    public string $contentfield;
    /** @var string */
    public string $shortcodeentity;
    /** @var int */
    public int $recordid;
    /** @var int */
    public int $sessionid;
    /** @var string */
    public string $draftfilearea;

    /**
     *   construct.
     * @param int $contextid
     * @param int $courseid
     * @param int $cmid
     * @param string $modname
     * @param string $component
     * @param string $filearea
     * @param int $fileitemid
     * @param string $contentfield
     * @param string $shortcodeentity
     * @param int $recordid
     * @param int $sessionid
     */
    public function __construct(
        int $contextid,
        int $courseid,
        int $cmid,
        string $modname,
        string $component,
        string $filearea,
        int $fileitemid,
        string $contentfield,
        string $shortcodeentity,
        int $recordid,
        int $sessionid
    ) {
        $this->contextid = $contextid;
        $this->courseid = $courseid;
        $this->cmid = $cmid;
        $this->modname = $modname;
        $this->component = $component;
        $this->filearea = $filearea;
        $this->fileitemid = $fileitemid;
        $this->contentfield = $contentfield;
        $this->shortcodeentity = $shortcodeentity;
        $this->recordid = $recordid;
        $this->sessionid = $sessionid;
        $this->draftfilearea = editor_draft_fileareas::for_modname($modname);
    }

    /**
     * Build a location in the module file area.
     *
     * @param string $filename Stored file name.
     * @return location
     */
    public function module_location(string $filename): location {
        return new location(
            $this->contextid,
            $this->component,
            $this->filearea,
            $this->fileitemid,
            '/',
            $filename,
            $this->courseid
        );
    }

    /**
     * Build a location in the editor draft file area.
     *
     * @param string $filename Stored file name.
     * @return location
     */
    public function draft_location(string $filename): location {
        return new location(
            $this->contextid,
            editor_draft_fileareas::COMPONENT,
            $this->draftfilearea,
            $this->sessionid,
            '/',
            $filename,
            $this->courseid
        );
    }

    /**
     * Html field target.
     * @return html_field_target
     */
    public function html_field_target(): html_field_target {
        $target = target_registry::resolve(
            $this->shortcodeentity,
            $this->contentfield,
            $this->recordid,
            $this->contextid,
            $this->courseid,
            $this->cmid
        );
        if ($target === null) {
            throw new \coding_exception('No html_field_target for editor context');
        }
        return $target;
    }
}
