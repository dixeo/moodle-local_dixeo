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

    // Credit usage table and component column on job bindings.
    if ($oldversion < 2026072801) {
        $jobstable = new xmldb_table('local_dixeo_jobs');
        $componentfield = new xmldb_field(
            'component',
            XMLDB_TYPE_CHAR,
            '100',
            null,
            null,
            null,
            null,
            'operation'
        );
        if (!$dbman->field_exists($jobstable, $componentfield)) {
            $dbman->add_field($jobstable, $componentfield);
        }

        $table = new xmldb_table('local_dixeo_credit_usage');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('transactionid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('jobid', XMLDB_TYPE_CHAR, '64', null, null, null, null);
        $table->add_field('type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('amount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('credits', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('jobtype', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $table->add_field('moduletype', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $table->add_field('component', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('operation', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timesynced', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('idx_transactionid', XMLDB_INDEX_UNIQUE, ['transactionid']);
        $table->add_index('idx_jobid', XMLDB_INDEX_NOTUNIQUE, ['jobid']);
        $table->add_index('idx_report', XMLDB_INDEX_NOTUNIQUE, ['timecreated', 'type', 'courseid', 'userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026072801, 'local', 'dixeo');
    }

    // Job binding metadata and credit usage module context for reporting.
    if ($oldversion < 2026072803) {
        $jobstable = new xmldb_table('local_dixeo_jobs');

        $moduletypefield = new xmldb_field(
            'moduletype',
            XMLDB_TYPE_CHAR,
            '50',
            null,
            null,
            null,
            null,
            'component'
        );
        if (!$dbman->field_exists($jobstable, $moduletypefield)) {
            $dbman->add_field($jobstable, $moduletypefield);
        }

        $contextidfield = new xmldb_field(
            'contextid',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'moduletype'
        );
        if (!$dbman->field_exists($jobstable, $contextidfield)) {
            $dbman->add_field($jobstable, $contextidfield);
        }

        $cmidfield = new xmldb_field(
            'cmid',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'contextid'
        );
        if (!$dbman->field_exists($jobstable, $cmidfield)) {
            $dbman->add_field($jobstable, $cmidfield);
        }

        $usagetable = new xmldb_table('local_dixeo_credit_usage');
        $usagecmidfield = new xmldb_field(
            'cmid',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'contextid'
        );
        if (!$dbman->field_exists($usagetable, $usagecmidfield)) {
            $dbman->add_field($usagetable, $usagecmidfield);
        }

        upgrade_plugin_savepoint(true, 2026072803, 'local', 'dixeo');
    }

    // Persist job access mode (default initiator_scoped; opt-in course_shared).
    if ($oldversion < 2026081100) {
        $jobstable = new xmldb_table('local_dixeo_jobs');
        $accessmodefield = new xmldb_field(
            'accessmode',
            XMLDB_TYPE_CHAR,
            '20',
            null,
            XMLDB_NOTNULL,
            null,
            'initiator_scoped',
            'operation'
        );
        if (!$dbman->field_exists($jobstable, $accessmodefield)) {
            $dbman->add_field($jobstable, $accessmodefield);
        }

        // Existing collaborative generate/fill jobs remain course-shared.
        $DB->set_field_select(
            'local_dixeo_jobs',
            'accessmode',
            'course_shared',
            "operation IN ('module_generate', 'module_fill')"
        );

        upgrade_plugin_savepoint(true, 2026081100, 'local', 'dixeo');
    }

    return true;
}
