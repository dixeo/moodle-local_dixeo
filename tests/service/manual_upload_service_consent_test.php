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

namespace local_dixeo\service;

use local_dixeo\exception\consent_required_exception;

/**
 * Terms of Use gate of manual uploads, which end with a course file sync to Dixeo.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_dixeo\service\manual_upload_service
 */
final class manual_upload_service_consent_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    private \stdClass $course;

    /**
     * Create a course.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * Upload entry that passes the access check but has no uploaded file.
     *
     * @return array
     */
    private static function empty_upload(): array {
        return ['error' => UPLOAD_ERR_OK, 'tmp_name' => '', 'name' => 'a.pdf'];
    }

    /**
     * A capable teacher without the current Terms of Use is refused before the file is looked at.
     */
    public function test_upload_refused_without_accepted_terms(): void {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($teacher);

        try {
            (new manual_upload_service())->create_from_upload('resource', (int) $this->course->id, 0, null, self::empty_upload());
            $this->fail('A teacher without the current Terms of Use must not upload.');
        } catch (consent_required_exception $e) {
            $this->assertSame('error:consentrequired', $e->errorcode);
            $this->assertSame('local_dixeo', $e->module);
        }
    }

    /**
     * After acceptance the gate passes and the next check reports the missing file.
     */
    public function test_upload_passes_the_gate_after_acceptance(): void {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->getDataGenerator()->get_plugin_generator('local_dixeo')->create_terms_acceptance(['userid' => $teacher->id]);
        $this->setUser($teacher);

        try {
            (new manual_upload_service())->create_from_upload('resource', (int) $this->course->id, 0, null, self::empty_upload());
            $this->fail('An upload without a file must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertNotInstanceOf(consent_required_exception::class, $e);
            $this->assertSame('manual_upload_error_missing', $e->errorcode);
            $this->assertSame('block_dixeo_modulegen', $e->module);
        }
        // The message string belongs to block_dixeo_modulegen, which may be absent from the test site.
        $this->resetDebugging();
    }

    /**
     * A user without the capabilities is refused for the capability, before the Terms of Use.
     */
    public function test_capability_is_checked_before_the_terms(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        (new manual_upload_service())->create_from_upload('resource', (int) $this->course->id, 0, null, self::empty_upload());
    }
}
