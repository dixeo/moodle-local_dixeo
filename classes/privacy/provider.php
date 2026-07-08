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
 * Privacy API implementation for local_dixeo.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for course AI sync records and Dixeo API transfers.
 *
 * Local personal data is limited to operational user IDs on sync configuration rows.
 * Content, messages and files sent to Dixeo are declared as external locations; retention
 * and deletion of remote copies are controlled by the Dixeo processor, not by this plugin.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var string Course AI sync configuration table. */
    public const TABLE_COURSE_AI = 'local_dixeo_course_ai';

    /** @var string Local remote-job ownership table. */
    public const TABLE_JOBS = 'local_dixeo_jobs';

    /** @var string Async image generation jobs. */
    public const TABLE_IMAGE_JOB = 'local_dixeo_image_job';

    /**
     * Describe metadata stored or transmitted by this plugin.
     *
     * @param collection $collection The privacy metadata collection.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            self::TABLE_COURSE_AI,
            [
                'courseid' => 'privacy:metadata:course_ai:courseid',
                'enabled' => 'privacy:metadata:course_ai:enabled',
                'syncstatus' => 'privacy:metadata:course_ai:syncstatus',
                'errormessage' => 'privacy:metadata:course_ai:errormessage',
                'enabledby' => 'privacy:metadata:course_ai:enabledby',
                'enabledat' => 'privacy:metadata:course_ai:enabledat',
                'disabledby' => 'privacy:metadata:course_ai:disabledby',
                'disabledat' => 'privacy:metadata:course_ai:disabledat',
                'timecreated' => 'privacy:metadata:course_ai:timecreated',
                'timemodified' => 'privacy:metadata:course_ai:timemodified',
            ],
            'privacy:metadata:course_ai'
        );

        $collection->add_database_table(
            self::TABLE_JOBS,
            [
                'jobid' => 'privacy:metadata:jobs:jobid',
                'courseid' => 'privacy:metadata:jobs:courseid',
                'userid' => 'privacy:metadata:jobs:userid',
                'namespace' => 'privacy:metadata:jobs:namespace',
                'operation' => 'privacy:metadata:jobs:operation',
                'timecreated' => 'privacy:metadata:jobs:timecreated',
            ],
            'privacy:metadata:jobs'
        );

        $collection->add_database_table(
            self::TABLE_IMAGE_JOB,
            [
                'userid' => 'privacy:metadata:image_job:userid',
                'courseid' => 'privacy:metadata:image_job:courseid',
                'jobid' => 'privacy:metadata:image_job:jobid',
                'prompt' => 'privacy:metadata:image_job:prompt',
                'status' => 'privacy:metadata:image_job:status',
                'errormessage' => 'privacy:metadata:image_job:errormessage',
                'timecreated' => 'privacy:metadata:image_job:timecreated',
                'timemodified' => 'privacy:metadata:image_job:timemodified',
            ],
            'privacy:metadata:image_job'
        );

        $collection->add_external_location_link(
            'dixeo_api',
            [
                'courseId' => 'privacy:metadata:external:courseid',
                'userId' => 'privacy:metadata:external:userid',
                'message' => 'privacy:metadata:external:message',
                'instructions' => 'privacy:metadata:external:instructions',
                'context' => 'privacy:metadata:external:context',
                'pageContext' => 'privacy:metadata:external:pagecontext',
                'moduleType' => 'privacy:metadata:external:moduletype',
                'templateId' => 'privacy:metadata:external:templateid',
                'name' => 'privacy:metadata:external:name',
                'description' => 'privacy:metadata:external:description',
                'templateDefinition' => 'privacy:metadata:external:templatedefinition',
                'title' => 'privacy:metadata:external:title',
                'summary' => 'privacy:metadata:external:summary',
                'images' => 'privacy:metadata:external:images',
                'files' => 'privacy:metadata:external:files',
                'namespace' => 'privacy:metadata:external:namespace',
            ],
            'privacy:metadata:externalpurpose'
        );

        return $collection;
    }

    /**
     * Get course contexts that contain sync or job records linked to the user.
     *
     * @param int $userid The user ID.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        if ($DB->record_exists(self::TABLE_JOBS, ['userid' => $userid, 'courseid' => 0])) {
            \context_system::instance(0, MUST_EXIST, false);
            $contextlist->add_from_sql(
                'SELECT id FROM {context} WHERE contextlevel = :contextlevel AND instanceid = 0',
                ['contextlevel' => CONTEXT_SYSTEM]
            );
        }

        $sql = "SELECT ctx.id
                  FROM {" . self::TABLE_COURSE_AI . "} cai
                  JOIN {context} ctx ON ctx.instanceid = cai.courseid AND ctx.contextlevel = :contextlevel
                 WHERE cai.enabledby = :enabledby OR cai.disabledby = :disabledby";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_COURSE,
            'enabledby' => $userid,
            'disabledby' => $userid,
        ]);

        $sql = "SELECT ctx.id
                  FROM {" . self::TABLE_JOBS . "} j
                  JOIN {context} ctx ON ctx.instanceid = j.courseid AND ctx.contextlevel = :contextlevel
                 WHERE j.userid = :userid AND j.courseid > 0";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_COURSE,
            'userid' => $userid,
        ]);

        $sql = "SELECT ctx.id
                  FROM {" . self::TABLE_IMAGE_JOB . "} ij
                  JOIN {context} ctx ON ctx.instanceid = ij.courseid AND ctx.contextlevel = :contextlevel
                 WHERE ij.userid = :userid AND ij.courseid > 0";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_COURSE,
            'userid' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Export sync configuration rows where the user is recorded as enabling or disabling.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = (int) $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_SYSTEM) {
                self::export_user_jobs($context, $userid, 0);
                continue;
            }

            if ($context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }

            $courseid = (int) $context->instanceid;
            $record = $DB->get_record(self::TABLE_COURSE_AI, ['courseid' => $courseid]);
            if ($record) {
                $linked = ((int) ($record->enabledby ?? 0) === $userid)
                    || ((int) ($record->disabledby ?? 0) === $userid);
                if ($linked) {
                    $data = (object) [
                        'courseid' => (int) $record->courseid,
                        'enabled' => transform::yesno((bool) $record->enabled),
                        'syncstatus' => (string) $record->syncstatus,
                        'errormessage' => $record->errormessage,
                        'enabledby' => (int) ($record->enabledby ?? 0) === $userid
                            ? transform::yesno(true)
                            : transform::yesno(false),
                        'enabledat' => !empty($record->enabledat) ? transform::datetime((int) $record->enabledat) : null,
                        'disabledby' => (int) ($record->disabledby ?? 0) === $userid
                            ? transform::yesno(true)
                            : transform::yesno(false),
                        'disabledat' => !empty($record->disabledat) ? transform::datetime((int) $record->disabledat) : null,
                        'timecreated' => transform::datetime((int) $record->timecreated),
                        'timemodified' => transform::datetime((int) $record->timemodified),
                    ];

                    writer::with_context($context)->export_data(
                        [get_string('privacy:path:course_ai', 'local_dixeo')],
                        $data
                    );
                }
            }

            self::export_user_jobs($context, $userid, $courseid);
            self::export_user_image_jobs($context, $userid, $courseid);
        }
    }

    /**
     * Export job ownership rows for a user within a course or pre-course scope.
     *
     * @param \context $context Export context (course or system for courseid 0).
     * @param int $userid User id.
     * @param int $courseid Course id, or 0 before a draft course exists.
     */
    private static function export_user_jobs(\context $context, int $userid, int $courseid): void {
        global $DB;

        $jobs = $DB->get_records(self::TABLE_JOBS, [
            'courseid' => $courseid,
            'userid' => $userid,
        ]);
        if (!$jobs) {
            return;
        }

        $exportedjobs = [];
        foreach ($jobs as $job) {
            $exportedjobs[] = (object) [
                'jobid' => (string) $job->jobid,
                'courseid' => (int) $job->courseid,
                'namespace' => (string) $job->namespace,
                'operation' => (string) $job->operation,
                'timecreated' => transform::datetime((int) $job->timecreated),
            ];
        }

        writer::with_context($context)->export_data(
            [get_string('privacy:path:jobs', 'local_dixeo')],
            (object) ['jobs' => $exportedjobs]
        );
    }

    /**
     * Export image generation job rows for a user within a course.
     *
     * @param \context $context Export context.
     * @param int $userid User id.
     * @param int $courseid Course id.
     */
    private static function export_user_image_jobs(\context $context, int $userid, int $courseid): void {
        global $DB;

        $imagejobs = $DB->get_records(self::TABLE_IMAGE_JOB, [
            'courseid' => $courseid,
            'userid' => $userid,
        ]);
        if (!$imagejobs) {
            return;
        }

        $exportedimagejobs = [];
        foreach ($imagejobs as $imagejob) {
            $exportedimagejobs[] = (object) [
                'jobid' => (string) $imagejob->jobid,
                'courseid' => (int) $imagejob->courseid,
                'prompt' => (string) ($imagejob->prompt ?? ''),
                'status' => (string) $imagejob->status,
                'errormessage' => (string) ($imagejob->errormessage ?? ''),
                'timecreated' => transform::datetime((int) $imagejob->timecreated),
                'timemodified' => transform::datetime((int) $imagejob->timemodified),
            ];
        }

        writer::with_context($context)->export_data(
            [get_string('privacy:path:image_jobs', 'local_dixeo')],
            (object) ['image_jobs' => $exportedimagejobs]
        );
    }

    /**
     * Delete all plugin data for a course context (the entire sync configuration row).
     *
     * @param \context $context The context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel === CONTEXT_SYSTEM) {
            $DB->delete_records(self::TABLE_JOBS, ['courseid' => 0]);
            return;
        }

        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }

        $DB->delete_records(self::TABLE_COURSE_AI, ['courseid' => (int) $context->instanceid]);
        $DB->delete_records(self::TABLE_JOBS, ['courseid' => (int) $context->instanceid]);
        $DB->delete_records(self::TABLE_IMAGE_JOB, ['courseid' => (int) $context->instanceid]);
    }

    /**
     * Remove user identifiers from sync rows; keep course operational configuration.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = (int) $contextlist->get_user()->id;
        $courseids = [];

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_SYSTEM) {
                $DB->delete_records(self::TABLE_JOBS, [
                    'userid' => $userid,
                    'courseid' => 0,
                ]);
                continue;
            }
            if ($context->contextlevel === CONTEXT_COURSE) {
                $courseids[] = (int) $context->instanceid;
            }
        }

        if ($courseids === []) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $params['userid'] = $userid;

        $DB->execute(
            "UPDATE {" . self::TABLE_COURSE_AI . "}
                SET enabledby = NULL, enabledat = NULL, timemodified = :timemodified
              WHERE enabledby = :userid AND courseid {$insql}",
            $params + ['timemodified' => time()]
        );

        // Re-bind named params for the second statement (course id placeholders reused).
        [$insql2, $params2] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $params2['userid'] = $userid;
        $params2['timemodified'] = time();

        $DB->execute(
            "UPDATE {" . self::TABLE_COURSE_AI . "}
                SET disabledby = NULL, disabledat = NULL, timemodified = :timemodified
              WHERE disabledby = :userid AND courseid {$insql2}",
            $params2
        );

        [$insql3, $params3] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $params3['userid'] = $userid;
        $DB->delete_records_select(
            self::TABLE_JOBS,
            "userid = :userid AND courseid {$insql3}",
            $params3
        );

        [$insql4, $params4] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $params4['userid'] = $userid;
        $DB->delete_records_select(
            self::TABLE_IMAGE_JOB,
            "userid = :userid AND courseid {$insql4}",
            $params4
        );
    }

    /**
     * List users with personal identifiers in a course context.
     *
     * @param userlist $userlist The userlist for the context.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if ($context->contextlevel === CONTEXT_SYSTEM) {
            $userlist->add_from_sql(
                'userid',
                "SELECT j.userid AS userid
                   FROM {" . self::TABLE_JOBS . "} j
                  WHERE j.courseid = 0 AND j.userid > 0",
                []
            );
            return;
        }

        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }

        $params = ['courseid' => (int) $context->instanceid];

        $userlist->add_from_sql(
            'userid',
            "SELECT cai.enabledby AS userid
               FROM {" . self::TABLE_COURSE_AI . "} cai
              WHERE cai.courseid = :courseid AND cai.enabledby IS NOT NULL AND cai.enabledby > 0",
            $params
        );

        $userlist->add_from_sql(
            'userid',
            "SELECT cai.disabledby AS userid
               FROM {" . self::TABLE_COURSE_AI . "} cai
              WHERE cai.courseid = :courseid AND cai.disabledby IS NOT NULL AND cai.disabledby > 0",
            $params
        );

        $userlist->add_from_sql(
            'userid',
            "SELECT j.userid AS userid
               FROM {" . self::TABLE_JOBS . "} j
              WHERE j.courseid = :courseid AND j.userid > 0",
            $params
        );

        $userlist->add_from_sql(
            'userid',
            "SELECT ij.userid AS userid
               FROM {" . self::TABLE_IMAGE_JOB . "} ij
              WHERE ij.courseid = :courseid AND ij.userid > 0",
            $params
        );
    }

    /**
     * Delete personal identifiers for the listed users in a course context.
     *
     * @param approved_userlist $userlist The approved user list.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();

        if ($context->contextlevel === CONTEXT_SYSTEM) {
            $userids = $userlist->get_userids();
            if ($userids === []) {
                return;
            }

            [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $DB->delete_records_select(
                self::TABLE_JOBS,
                "courseid = 0 AND userid {$insql}",
                $params
            );
            return;
        }

        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }

        $userids = $userlist->get_userids();
        if ($userids === []) {
            return;
        }

        $courseid = (int) $context->instanceid;
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['courseid'] = $courseid;
        $params['timemodified'] = time();

        $DB->execute(
            "UPDATE {" . self::TABLE_COURSE_AI . "}
                SET enabledby = NULL, enabledat = NULL, timemodified = :timemodified
              WHERE courseid = :courseid AND enabledby {$insql}",
            $params
        );

        [$insql2, $params2] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params2['courseid'] = $courseid;
        $params2['timemodified'] = time();

        $DB->execute(
            "UPDATE {" . self::TABLE_COURSE_AI . "}
                SET disabledby = NULL, disabledat = NULL, timemodified = :timemodified
              WHERE courseid = :courseid AND disabledby {$insql2}",
            $params2
        );

        [$insql3, $params3] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params3['courseid'] = $courseid;
        $DB->delete_records_select(
            self::TABLE_JOBS,
            "courseid = :courseid AND userid {$insql3}",
            $params3
        );

        [$insql4, $params4] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params4['courseid'] = $courseid;
        $DB->delete_records_select(
            self::TABLE_IMAGE_JOB,
            "courseid = :courseid AND userid {$insql4}",
            $params4
        );
    }
}
