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
 * Web service definitions for the Dixeo plugin.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();
$functions = [
    // Generate a new module using AI.
    'local_dixeo_generate_module' => [
        'classname' => 'local_dixeo\external\generate_module',
        'description' => 'Generate a module using AI',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/dixeo:generate',
    ],

    // Get the status of a job.
    'local_dixeo_get_job_status' => [
        'classname' => 'local_dixeo\external\get_job_status',
        'description' => 'Get the status of a job',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/dixeo:generate',
        'readonlysession' => true,
    ],

    // Cancel a running job.
    'local_dixeo_cancel_job' => [
        'classname' => 'local_dixeo\external\cancel_job',
        'description' => 'Cancel a running job',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/dixeo:generate',
    ],

    // Get available module types.
    'local_dixeo_get_module_types' => [
        'classname' => 'local_dixeo\external\get_module_types',
        'description' => 'Get available module types',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => '',
        'readonlysession' => true,
    ],

    // Create a module from a completed job.
    'local_dixeo_create_module_from_job' => [
        'classname' => 'local_dixeo\external\create_module_from_job',
        'description' => 'Create a module from a completed generation job',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/dixeo:generate',
    ],

    // Get file sync status for a course.
    'local_dixeo_get_file_sync_status' => [
        'classname' => 'local_dixeo\external\get_file_sync_status',
        'description' => 'Get the file sync status for a course',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/dixeo:generate',
        'readonlysession' => true,
    ],

    // Enable or disable file sync for a course.
    'local_dixeo_set_file_sync_enabled' => [
        'classname' => 'local_dixeo\external\set_file_sync_enabled',
        'description' => 'Enable or disable AI file sync for a course',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/dixeo:syncfiles',
    ],

    // Trigger immediate file sync for a course.
    'local_dixeo_trigger_file_sync' => [
        'classname' => 'local_dixeo\external\trigger_file_sync',
        'description' => 'Trigger immediate file sync for a course (no debounce)',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/dixeo:syncfiles',
    ],

    // Search credit report users with usage in a period.
    'local_dixeo_search_credit_report_users' => [
        'classname' => 'local_dixeo\external\search_credit_report_users',
        'description' => 'Search users with credit usage in a report period',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/dixeo:viewusage',
    ],

    // Search credit report courses with usage in a period.
    'local_dixeo_search_credit_report_courses' => [
        'classname' => 'local_dixeo\external\search_credit_report_courses',
        'description' => 'Search courses with credit usage in a report period',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/dixeo:viewusage',
    ],
];
