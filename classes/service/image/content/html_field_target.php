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
 * Target HTML field and filearea mapping for shortcode processing.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class html_field_target {
    /** @var string */
    public string $modname;
    /** @var string */
    public string $fieldname;
    /** @var string */
    public string $component;
    /** @var string */
    public string $filearea;
    /** @var int */
    public int $itemid;
    /** @var string */
    public string $targettable;
    /** @var string */
    public string $targetfield;
    /** @var int */
    public int $targetid;
    /** @var int */
    public int $contextid;
    /** @var int */
    public int $courseid;
    /** @var int|null */
    public ?int $cmid;
    /** @var string */
    public string $formatfield;

    /**
     *   construct.
     * @param string $modname
     * @param string $fieldname
     * @param string $component
     * @param string $filearea
     * @param int $itemid
     * @param string $targettable
     * @param string $targetfield
     * @param int $targetid
     * @param int $contextid
     * @param int $courseid
     * @param int|null $cmid
     * @param string $formatfield
     */
    public function __construct(
        string $modname,
        string $fieldname,
        string $component,
        string $filearea,
        int $itemid,
        string $targettable,
        string $targetfield,
        int $targetid,
        int $contextid,
        int $courseid,
        ?int $cmid,
        string $formatfield
    ) {
        $this->modname = $modname;
        $this->fieldname = $fieldname;
        $this->component = $component;
        $this->filearea = $filearea;
        $this->itemid = $itemid;
        $this->targettable = $targettable;
        $this->targetfield = $targetfield;
        $this->targetid = $targetid;
        $this->contextid = $contextid;
        $this->courseid = $courseid;
        $this->cmid = $cmid;
        $this->formatfield = $formatfield;
    }
}
