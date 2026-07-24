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
 * Event observers for the Dixeo plugin.
 *
 * Defines event handlers for file sync triggers based on course module events.
 * Sync activation itself is a manual action by an admin or teacher.
 *
 * This plugin also emits custom events (not observed here) for audit logging:
 * file_sync_enabled, file_sync_disabled, file_sync_triggered, job_cancelled,
 * credit_report_viewed, and credit_report_exported.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();
$observers = [
    // Trigger sync when a course module is created.
    [
        'eventname' => '\core\event\course_module_created',
        'callback' => '\local_dixeo\observer\file_sync_observer::course_module_created',
        'internal' => false,
        'priority' => 0,
    ],

    // Trigger sync when a course module is updated.
    [
        'eventname' => '\core\event\course_module_updated',
        'callback' => '\local_dixeo\observer\file_sync_observer::course_module_updated',
        'internal' => false,
        'priority' => 0,
    ],

    // Trigger sync when a course module is deleted.
    [
        'eventname' => '\core\event\course_module_deleted',
        'callback' => '\local_dixeo\observer\file_sync_observer::course_module_deleted',
        'internal' => false,
        'priority' => 0,
    ],
];
