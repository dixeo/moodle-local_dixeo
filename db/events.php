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
 * Defines event handlers for file sync. A new course stores the course file sync
 * setting from the Dixeo default. Saving that setting updates the operational flag.
 * Module changes queue a sync when that course already has sync on.
 *
 * This plugin also emits custom events (not observed here) for audit logging:
 * file_sync_enabled, file_sync_disabled, file_sync_triggered, job_cancelled,
 * credit_report_viewed, credit_report_exported, tutor_usage_report_viewed,
 * and tutor_usage_report_exported.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();
$observers = [
    // Stores the new-course file sync default as part of course creation.
    // Internal so the stored setting is visible before the surrounding transaction commits.
    [
        'eventname' => '\core\event\course_created',
        'callback' => '\local_dixeo\observer\file_sync_observer::course_created',
        'internal' => true,
        'priority' => 0,
    ],

    // Keep the operational file sync flag aligned with the course setting.
    [
        'eventname' => '\local_dixeo\event\setting_updated',
        'callback' => '\local_dixeo\observer\file_sync_observer::setting_updated',
        'internal' => true,
        'priority' => 0,
    ],

    // Queues a sync as part of the module change when that course already has sync on.
    // Internal so the outdated status is visible before the surrounding transaction commits.
    [
        'eventname' => '\core\event\course_module_created',
        'callback' => '\local_dixeo\observer\file_sync_observer::course_module_created',
        'internal' => true,
        'priority' => 0,
    ],

    // Queues a sync as part of the module change when that course already has sync on.
    [
        'eventname' => '\core\event\course_module_updated',
        'callback' => '\local_dixeo\observer\file_sync_observer::course_module_updated',
        'internal' => true,
        'priority' => 0,
    ],

    // Queues a sync as part of the module change when that course already has sync on.
    [
        'eventname' => '\core\event\course_module_deleted',
        'callback' => '\local_dixeo\observer\file_sync_observer::course_module_deleted',
        'internal' => true,
        'priority' => 0,
    ],
];
