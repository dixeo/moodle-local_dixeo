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
 * Database upgrade steps for the Dixeo plugin.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the local_dixeo plugin.
 *
 * @param int $oldversion The old version of the plugin.
 * @return bool True on success.
 */
function xmldb_local_dixeo_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // Upgrade to add the course_ai table for file sync tracking.
    if ($oldversion < 2026022300) {
        $table = new xmldb_table('local_dixeo_course_ai');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('sync_status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'none');
        $table->add_field('files_total', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('files_completed', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('progress_percent', XMLDB_TYPE_INTEGER, '3', null, null, null, null);
        $table->add_field('error_message', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('error_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('last_error_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('last_sync_started', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('last_sync_completed', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('enabled_by', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('enabled_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('disabled_by', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('disabled_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_courseid', XMLDB_KEY_FOREIGN_UNIQUE, ['courseid'], 'course', ['id']);

        $table->add_index('idx_sync_status', XMLDB_INDEX_NOTUNIQUE, ['sync_status']);
        $table->add_index('idx_enabled', XMLDB_INDEX_NOTUNIQUE, ['enabled']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026022300, 'local', 'dixeo');
    }

    // Rename snake_case columns to concatenated lowercase per Moodle naming convention.
    if ($oldversion < 2026022301) {
        $table = new xmldb_table('local_dixeo_course_ai');

        // Only rename if the old columns still exist (idempotent upgrade).
        if ($dbman->field_exists($table, 'sync_status')) {
            $field = new xmldb_field('sync_status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'none', 'enabled');
            $dbman->rename_field($table, $field, 'syncstatus');
        }

        if ($dbman->field_exists($table, 'files_total')) {
            $field = new xmldb_field('files_total', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'syncstatus');
            $dbman->rename_field($table, $field, 'filestotal');
        }

        if ($dbman->field_exists($table, 'files_completed')) {
            $field = new xmldb_field('files_completed', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'filestotal');
            $dbman->rename_field($table, $field, 'filescompleted');
        }

        if ($dbman->field_exists($table, 'progress_percent')) {
            $field = new xmldb_field('progress_percent', XMLDB_TYPE_INTEGER, '3', null, null, null, null, 'filescompleted');
            $dbman->rename_field($table, $field, 'progresspercent');
        }

        if ($dbman->field_exists($table, 'error_message')) {
            $field = new xmldb_field('error_message', XMLDB_TYPE_TEXT, null, null, null, null, null, 'progresspercent');
            $dbman->rename_field($table, $field, 'errormessage');
        }

        if ($dbman->field_exists($table, 'error_count')) {
            $field = new xmldb_field('error_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'errormessage');
            $dbman->rename_field($table, $field, 'errorcount');
        }

        if ($dbman->field_exists($table, 'last_error_at')) {
            $field = new xmldb_field('last_error_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'errorcount');
            $dbman->rename_field($table, $field, 'lasterrorat');
        }

        if ($dbman->field_exists($table, 'last_sync_started')) {
            $field = new xmldb_field('last_sync_started', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'lasterrorat');
            $dbman->rename_field($table, $field, 'lastsyncstarted');
        }

        if ($dbman->field_exists($table, 'last_sync_completed')) {
            $field = new xmldb_field('last_sync_completed', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'lastsyncstarted');
            $dbman->rename_field($table, $field, 'lastsynccompleted');
        }

        if ($dbman->field_exists($table, 'enabled_by')) {
            $field = new xmldb_field('enabled_by', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'lastsynccompleted');
            $dbman->rename_field($table, $field, 'enabledby');
        }

        if ($dbman->field_exists($table, 'enabled_at')) {
            $field = new xmldb_field('enabled_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'enabledby');
            $dbman->rename_field($table, $field, 'enabledat');
        }

        if ($dbman->field_exists($table, 'disabled_by')) {
            $field = new xmldb_field('disabled_by', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'enabledat');
            $dbman->rename_field($table, $field, 'disabledby');
        }

        if ($dbman->field_exists($table, 'disabled_at')) {
            $field = new xmldb_field('disabled_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'disabledby');
            $dbman->rename_field($table, $field, 'disabledat');
        }

        // Replace the old snake_case index with the new concatenated name.
        $oldindex = new xmldb_index('idx_sync_status', XMLDB_INDEX_NOTUNIQUE, ['syncstatus']);
        if ($dbman->index_exists($table, $oldindex)) {
            $dbman->drop_index($table, $oldindex);
        }

        $newindex = new xmldb_index('idx_syncstatus', XMLDB_INDEX_NOTUNIQUE, ['syncstatus']);
        if (!$dbman->index_exists($table, $newindex)) {
            $dbman->add_index($table, $newindex);
        }

        upgrade_plugin_savepoint(true, 2026022301, 'local', 'dixeo');
    }

    // Add filehash column for manifest-based sync skip.
    if ($oldversion < 2026031600) {
        $table = new xmldb_table('local_dixeo_course_ai');
        $field = new xmldb_field('filehash', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'lasterrorat');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026031600, 'local', 'dixeo');
    }

    if ($oldversion < 2026032100) {
        $table = new xmldb_table('local_dixeo_course_ai');

        $field = new xmldb_field('uploadbytes', XMLDB_TYPE_INTEGER, '20', null, null, null, null, 'progresspercent');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('uploadbytestotal', XMLDB_TYPE_INTEGER, '20', null, null, null, null, 'uploadbytes');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026032100, 'local', 'dixeo');
    }

    if ($oldversion < 2026061200) {
        update_capabilities('local_dixeo');

        $oldcap = 'block/dixeo_designer:create';
        $newcap = 'local/dixeo:create';
        foreach ($DB->get_records('role_capabilities', ['capability' => $oldcap]) as $rc) {
            if (
                !$DB->record_exists('role_capabilities', [
                'roleid' => $rc->roleid,
                'capability' => $newcap,
                'contextid' => $rc->contextid,
                ])
            ) {
                $rc->capability = $newcap;
                unset($rc->id);
                $DB->insert_record('role_capabilities', $rc);
            }
        }

        upgrade_plugin_savepoint(true, 2026061200, 'local', 'dixeo');
    }

    if ($oldversion < 2026070601) {
        $DB->execute('UPDATE {local_dixeo_course_ai} SET enabled = 0 WHERE enabled = 1');

        $admin = get_admin();
        $filesync = \local_dixeo\external\service_factory::get_file_sync_service();
        foreach (\local_dixeo\service\file_sync_policy::get_courseids_with_sync_blocks() as $courseid) {
            $filesync->enable_sync((int) $courseid, (int) $admin->id);
        }

        upgrade_plugin_savepoint(true, 2026070601, 'local', 'dixeo');
    }

    // Local binding between remote Dixeo jobs and Moodle course/user.
    if ($oldversion < 2026071400) {
        $table = new xmldb_table('local_dixeo_jobs');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('jobid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('namespace', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, 'default');
        $table->add_field('operation', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, 'unknown');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('idx_jobid', XMLDB_INDEX_UNIQUE, ['jobid']);
        $table->add_index('idx_courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        $table->add_index('idx_userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026071400, 'local', 'dixeo');
    }

    if ($oldversion < 2026072202) {
        $table = new xmldb_table('local_dixeo_image_job');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('placeholderid', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('component', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('filearea', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('filepath', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('filename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('locationhash', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('targettable', XMLDB_TYPE_CHAR, '64', null, null, null, null);
        $table->add_field('targetfield', XMLDB_TYPE_CHAR, '64', null, null, null, null);
        $table->add_field('targetid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('origin', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'shortcode');
        $table->add_field('prompt', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('quality', XMLDB_TYPE_CHAR, '10', null, null, null, null);
        $table->add_field('mode', XMLDB_TYPE_CHAR, '20', null, null, null, null);
        $table->add_field('jobid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('errormessage', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('placeholderid_idx', XMLDB_INDEX_NOTUNIQUE, ['placeholderid']);
        $table->add_index('locationhash_uix', XMLDB_INDEX_UNIQUE, ['locationhash']);
        $table->add_index('status_idx', XMLDB_INDEX_NOTUNIQUE, ['status']);
        $table->add_index('courseid_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $field = new xmldb_field('target_kind', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'content', 'id');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('objectid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'target_kind');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('target_kind_idx', XMLDB_INDEX_NOTUNIQUE, ['target_kind']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026072202, 'local', 'dixeo');
    }

    // Repair local_dixeo_jobs schema drift from early dev deploys (orphan timemodified, missing columns).
    if ($oldversion < 2026072203) {
        $table = new xmldb_table('local_dixeo_jobs');

        if ($dbman->table_exists($table)) {
            $field = new xmldb_field('timemodified');
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }

            $field = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            $field = new xmldb_field('namespace', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, 'default');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            $field = new xmldb_field('operation', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, 'unknown');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_plugin_savepoint(true, 2026072203, 'local', 'dixeo');
    }

    return true;
}
