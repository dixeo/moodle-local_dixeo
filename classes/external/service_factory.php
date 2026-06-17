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
 * Factory for creating service instances in external API classes.
 *
 * Centralizes service instantiation to allow for easier testing and
 * consistent service configuration across all API endpoints.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\external;

use local_dixeo\api\client;
use local_dixeo\service\course_structure_service;
use local_dixeo\service\course_template_service;
use local_dixeo\service\job_service;
use local_dixeo\service\module_generation_service;
use local_dixeo\service\module_types_service;
use local_dixeo\service\file_sync_service;
use local_dixeo\service\manual_upload_service;
use local_dixeo\service\practice_quiz_service;
use local_dixeo\service\teach_lesson_service;
use local_dixeo\service\tutor_service;

/**
 * Factory class for creating service instances.
 *
 * Provides focused service instances for external API endpoints.
 * Supports test mock injection for each service type independently.
 */
class service_factory {
    /** @var job_service|null Mock job service instance for unit testing. */
    private static ?job_service $testjobservice = null;

    /** @var module_generation_service|null Mock module generation service for unit testing. */
    private static ?module_generation_service $testmodulegenerationservice = null;

    /** @var tutor_service|null Mock tutor service instance for unit testing. */
    private static ?tutor_service $testtutorservice = null;

    /** @var course_structure_service|null Mock course structure service for unit testing. */
    private static ?course_structure_service $testcoursestructureservice = null;

    /** @var course_template_service|null Mock course template service for unit testing. */
    private static ?course_template_service $testcoursetemplateservice = null;

    /** @var module_types_service|null Mock module types service for unit testing. */
    private static ?module_types_service $testmoduletypesservice = null;

    /** @var file_sync_service|null Mock file sync service for unit testing. */
    private static ?file_sync_service $testfilesyncservice = null;

    /** @var manual_upload_service|null Mock manual upload service for unit testing. */
    private static ?manual_upload_service $testmanualuploadservice = null;

    /** @var practice_quiz_service|null Mock practice quiz service for unit testing. */
    private static ?practice_quiz_service $testpracticequizservice = null;

    /** @var teach_lesson_service|null Mock teach lesson service for unit testing. */
    private static ?teach_lesson_service $testteachlessonservice = null;

    /** @var client|null Mock client instance for unit testing. */
    private static ?client $testclient = null;

    /**
     * Get a job_service instance.
     *
     * Returns a fresh instance unless a test instance has been set.
     *
     * @return job_service The service instance.
     */
    public static function get_job_service(): job_service {
        if (self::$testjobservice !== null) {
            return self::$testjobservice;
        }

        return new job_service();
    }

    /**
     * Get a module_generation_service instance.
     *
     * Returns a fresh instance unless a test instance has been set.
     *
     * @return module_generation_service The service instance.
     */
    public static function get_module_generation_service(): module_generation_service {
        if (self::$testmodulegenerationservice !== null) {
            return self::$testmodulegenerationservice;
        }

        return new module_generation_service();
    }

    /**
     * Get a tutor_service instance.
     *
     * Returns a fresh instance unless a test instance has been set.
     *
     * @return tutor_service The service instance.
     */
    public static function get_tutor_service(): tutor_service {
        if (self::$testtutorservice !== null) {
            return self::$testtutorservice;
        }

        return new tutor_service();
    }

    /**
     * Get a course_structure_service instance.
     *
     * Returns a fresh instance unless a test instance has been set.
     *
     * @return course_structure_service The service instance.
     */
    public static function get_course_structure_service(): course_structure_service {
        if (self::$testcoursestructureservice !== null) {
            return self::$testcoursestructureservice;
        }

        return new course_structure_service();
    }

    /**
     * Get a course_template_service instance.
     *
     * Returns a fresh instance unless a test instance has been set.
     *
     * @return course_template_service The service instance.
     */
    public static function get_course_template_service(): course_template_service {
        if (self::$testcoursetemplateservice !== null) {
            return self::$testcoursetemplateservice;
        }

        return new course_template_service();
    }

    /**
     * Get a module_types_service instance.
     *
     * @return module_types_service The service instance.
     */
    public static function get_module_types_service(): module_types_service {
        if (self::$testmoduletypesservice !== null) {
            return self::$testmoduletypesservice;
        }

        return new module_types_service(self::get_client());
    }

    /**
     * Get a file_sync_service instance.
     *
     * Returns a fresh instance unless a test instance has been set.
     *
     * @return file_sync_service The service instance.
     */
    public static function get_file_sync_service(): file_sync_service {
        if (self::$testfilesyncservice !== null) {
            return self::$testfilesyncservice;
        }

        return new file_sync_service();
    }

    /**
     * Get a manual_upload_service instance.
     *
     * @return manual_upload_service The service instance.
     */
    public static function get_manual_upload_service(): manual_upload_service {
        if (self::$testmanualuploadservice !== null) {
            return self::$testmanualuploadservice;
        }

        return new manual_upload_service();
    }

    /**
     * Get a practice_quiz_service instance.
     *
     * @return practice_quiz_service
     */
    public static function get_practice_quiz_service(): practice_quiz_service {
        if (self::$testpracticequizservice !== null) {
            return self::$testpracticequizservice;
        }

        return new practice_quiz_service();
    }

    /**
     * Get a teach_lesson_service instance.
     *
     * @return teach_lesson_service
     */
    public static function get_teach_lesson_service(): teach_lesson_service {
        if (self::$testteachlessonservice !== null) {
            return self::$testteachlessonservice;
        }

        return new teach_lesson_service();
    }

    /**
     * Get a client instance.
     *
     * Returns a fresh instance unless a test instance has been set.
     * Use for direct API calls not covered by a dedicated service.
     *
     * @return client The client instance.
     */
    public static function get_client(): client {
        if (self::$testclient !== null) {
            return self::$testclient;
        }

        return new client();
    }

    /**
     * Set a test job service instance.
     *
     * Use this in unit tests to inject mock services.
     *
     * @param job_service|null $service The test service, or null to clear.
     */
    public static function set_test_job_service(?job_service $service): void {
        self::$testjobservice = $service;
    }

    /**
     * Set a test module generation service instance.
     *
     * Use this in unit tests to inject mock services.
     *
     * @param module_generation_service|null $service The test service, or null to clear.
     */
    public static function set_test_module_generation_service(?module_generation_service $service): void {
        self::$testmodulegenerationservice = $service;
    }

    /**
     * Set a test tutor service instance.
     *
     * Use this in unit tests to inject mock services.
     *
     * @param tutor_service|null $service The test service, or null to clear.
     */
    public static function set_test_tutor_service(?tutor_service $service): void {
        self::$testtutorservice = $service;
    }

    /**
     * Set a test course structure service instance.
     *
     * Use this in unit tests to inject mock services.
     *
     * @param course_structure_service|null $service The test service, or null to clear.
     */
    public static function set_test_course_structure_service(?course_structure_service $service): void {
        self::$testcoursestructureservice = $service;
    }

    /**
     * Set a test course template service instance.
     *
     * Use this in unit tests to inject mock services.
     *
     * @param course_template_service|null $service The test service, or null to clear.
     */
    public static function set_test_course_template_service(?course_template_service $service): void {
        self::$testcoursetemplateservice = $service;
    }

    /**
     * Set a test module types service instance.
     *
     * @param module_types_service|null $service The test service, or null to clear.
     */
    public static function set_test_module_types_service(?module_types_service $service): void {
        self::$testmoduletypesservice = $service;
    }

    /**
     * Set a test file sync service instance.
     *
     * Use this in unit tests to inject mock services.
     *
     * @param file_sync_service|null $service The test service, or null to clear.
     */
    public static function set_test_file_sync_service(?file_sync_service $service): void {
        self::$testfilesyncservice = $service;
    }

    /**
     * Set a test manual upload service instance.
     *
     * @param manual_upload_service|null $service The test service, or null to clear.
     */
    public static function set_test_manual_upload_service(?manual_upload_service $service): void {
        self::$testmanualuploadservice = $service;
    }

    /**
     * Set a test practice quiz service instance.
     *
     * @param practice_quiz_service|null $service
     */
    public static function set_test_practice_quiz_service(?practice_quiz_service $service): void {
        self::$testpracticequizservice = $service;
    }

    /**
     * Set a test teach lesson service instance.
     *
     * @param teach_lesson_service|null $service
     */
    public static function set_test_teach_lesson_service(?teach_lesson_service $service): void {
        self::$testteachlessonservice = $service;
    }

    /**
     * Set a test client instance.
     *
     * Use this in unit tests to inject mock clients.
     *
     * @param client|null $client The test client, or null to clear.
     */
    public static function set_test_client(?client $client): void {
        self::$testclient = $client;
    }

    /**
     * Reset the factory to default state.
     *
     * Should be called in test tearDown methods.
     */
    public static function reset(): void {
        self::$testjobservice = null;
        self::$testmodulegenerationservice = null;
        self::$testtutorservice = null;
        self::$testcoursestructureservice = null;
        self::$testcoursetemplateservice = null;
        self::$testmoduletypesservice = null;
        self::$testfilesyncservice = null;
        self::$testmanualuploadservice = null;
        self::$testpracticequizservice = null;
        self::$testteachlessonservice = null;
        self::$testclient = null;
    }
}
