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

namespace local_dixeo\service;

/**
 * Adds an enrol_lti tool instance for designer-created courses.
 *
 * Callers (e.g. block_dixeo_designer) supply field values from their own settings.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class designer_lti_enrol_service {
    /**
     * Create one LTI 1.3 enrol instance for the course.
     *
     * @param int $courseid
     * @param array $fields Optional keys: maxenrolled (int), membersync (0|1), membersyncmode (1|2|3).
     * @return string|false Tool UUID for LTI Advantage, or false if enrol_lti disabled or failure.
     */
    public function add_lti_enrol_instance(int $courseid, array $fields): string|false {
        global $DB;

        if (!enrol_is_enabled('lti')) {
            return false;
        }

        $course = get_course($courseid);
        $maxenrolled = (int) ($fields['maxenrolled'] ?? 0);
        if ($maxenrolled < 0) {
            $maxenrolled = 0;
        }

        $membersync = (int) ($fields['membersync'] ?? 0);
        if (!in_array($membersync, [0, 1], true)) {
            $membersync = 0;
        }
        $membersyncmode = (int) ($fields['membersyncmode'] ?? \enrol_lti\helper::MEMBER_SYNC_ENROL_AND_UNENROL);
        if (
            !in_array($membersyncmode, [
            \enrol_lti\helper::MEMBER_SYNC_ENROL_AND_UNENROL,
            \enrol_lti\helper::MEMBER_SYNC_ENROL_NEW,
            \enrol_lti\helper::MEMBER_SYNC_UNENROL_MISSING,
            ], true)
        ) {
            $membersyncmode = \enrol_lti\helper::MEMBER_SYNC_ENROL_AND_UNENROL;
        }

        $coursecontext = \context_course::instance($course->id);
        $settings = \local_dixeo\external\service_factory::get_settings_config();
        $maildisplay = (int) $settings->get_moodle('enrol_lti', 'emaildisplay', $coursecontext);
        if (!in_array($maildisplay, [0, 1, 2], true)) {
            $fallback = (int) $settings->get_moodle('', 'defaultpreference_maildisplay', $coursecontext);
            $maildisplay = $fallback;
        }

        // Language follows the course when available; otherwise fallback to enrol_lti default.
        $lang = !empty($course->lang) ? clean_param((string) $course->lang, PARAM_LANG) : '';
        if ($lang === '') {
            $lang = clean_param($settings->get_moodle('enrol_lti', 'lang', $coursecontext), PARAM_LANG);
        }
        if ($lang === '') {
            $lang = $settings->get_moodle('', 'lang', $coursecontext);
        }

        $timezone = $settings->get_moodle('enrol_lti', 'timezone', $coursecontext);
        $timezone = $timezone === '' ? 99 : $timezone;
        $institution = clean_param($settings->get_moodle('enrol_lti', 'institution', $coursecontext), PARAM_TEXT);
        $city = clean_param($settings->get_moodle('enrol_lti', 'city', $coursecontext), PARAM_TEXT);
        if ($city === '') {
            $city = clean_param($settings->get_moodle('', 'defaultcity', $coursecontext), PARAM_TEXT);
        }
        $country = clean_param($settings->get_moodle('enrol_lti', 'country', $coursecontext), PARAM_ALPHA);
        if ($country === '') {
            $country = clean_param($settings->get_moodle('', 'country', $coursecontext), PARAM_ALPHA);
        }

        $ltitool = (object) [
            'contextid' => \context_course::instance($course->id)->id,
            'ltiversion' => 'LTI-1p3',
            'institution' => $institution,
            'lang' => $lang,
            'timezone' => $timezone,
            'maxenrolled' => $maxenrolled,
            'maildisplay' => $maildisplay,
            'city' => $city,
            'country' => $country,
            'gradesync' => 1,
            'gradesynccompletion' => 0,
            'membersync' => $membersync,
            'membersyncmode' => $membersyncmode,
            'roleinstructor' => 3,
            'rolelearner' => 5,
            'secret' => random_string(32),
            'provisioningmodelearner' => 1,
            'provisioningmodeinstructor' => 2,
        ];

        $enrol = enrol_get_plugin('lti');
        if (!$enrol) {
            return false;
        }

        $instanceid = $enrol->add_instance($course, (array) $ltitool);
        if (!$instanceid) {
            return false;
        }

        return $DB->get_field('enrol_lti_tools', 'uuid', ['enrolid' => $instanceid], IGNORE_MISSING) ?: false;
    }
}
