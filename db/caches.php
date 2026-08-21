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
 * Cache definitions for local_dixeo.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();
$definitions = [
    'coursetemplates' => [
        'mode' => \cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'ttl' => 60 * 60 * 24, // 1 day
    ],
    'moduletypes' => [
        'mode' => \cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'ttl' => 60 * 60 * 24, // 1 day
    ],
    'installedplugintypes' => [
        'mode' => \cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'ttl' => 60 * 60 * 24, // 24 hours
    ],
    // Marks courses whose files were recently confirmed in sync, so RAG-backed jobs
    // (tutor messages, module generation) can skip the blocking trigger-and-poll round trips.
    'filesyncverified' => [
        'mode' => \cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'ttl' => 5 * 60, // 5 minutes
    ],
];
