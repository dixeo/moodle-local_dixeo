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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_dixeo\api;

/**
 * Tests for the remote job id validator.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\api\job_id
 */
final class job_id_test extends \advanced_testcase {
    /**
     * Candidate ids and whether they are valid.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function job_id_provider(): array {
        return [
            'v4' => ['5f38d9aa-f40c-4992-9727-982f050ff9fd', true],
            'v7' => ['01890a5d-ac96-774b-bcce-b302099a8057', true],
            'upper case' => ['5F38D9AA-F40C-4992-9727-982F050FF9FD', true],
            'braces' => ['{5f38d9aa-f40c-4992-9727-982f050ff9fd}', false],
            'missing hyphen' => ['5f38d9aaf40c-4992-9727-982f050ff9fd', false],
            '35 characters' => ['5f38d9aa-f40c-4992-9727-982f050ff9f', false],
            'empty' => ['', false],
            'trailing newline' => ["5f38d9aa-f40c-4992-9727-982f050ff9fd\n", false],
        ];
    }

    /**
     * is_valid() accepts canonical UUIDs of any version only.
     *
     * @dataProvider job_id_provider
     * @param string $jobid Candidate id.
     * @param bool $expected Expected result.
     */
    public function test_is_valid(string $jobid, bool $expected): void {
        $this->assertSame($expected, job_id::is_valid($jobid));
    }

    /**
     * require_valid() refuses a malformed id with the missing-job error.
     */
    public function test_require_valid(): void {
        job_id::require_valid('5f38d9aa-f40c-4992-9727-982f050ff9fd');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error:job_not_found', 'local_dixeo'));
        job_id::require_valid('not-a-job');
    }
}
