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

namespace local_dixeo;

use local_dixeo\dto\job_quota;

/**
 * Bounds and lock key of the job quota value object.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\dto\job_quota
 */
final class job_quota_test extends \basic_testcase {
    /**
     * Valid values are kept, with one job and a five-second wait by default.
     */
    public function test_valid_values_are_kept(): void {
        $quota = new job_quota(10, 3600);

        $this->assertSame(10, $quota->limit);
        $this->assertSame(3600, $quota->windowseconds);
        $this->assertSame(1, $quota->jobsneeded);
        $this->assertSame(5, $quota->locktimeout);

        $this->assertSame(0, (new job_quota(1, 1, 1, 0))->locktimeout);
    }

    /**
     * Out-of-range values are refused.
     *
     * @dataProvider invalid_values_provider
     * @param int $limit Limit.
     * @param int $window Window in seconds.
     * @param int $needed Jobs needed.
     * @param int $timeout Lock timeout.
     */
    public function test_invalid_values_are_refused(int $limit, int $window, int $needed, int $timeout): void {
        $this->expectException(\coding_exception::class);

        new job_quota($limit, $window, $needed, $timeout);
    }

    /**
     * Out-of-range constructor values.
     *
     * @return array<string, int[]>
     */
    public static function invalid_values_provider(): array {
        return [
            'zero limit' => [0, 3600, 1, 5],
            'zero window' => [10, 0, 1, 5],
            'zero jobs needed' => [10, 3600, 0, 5],
            'negative timeout' => [10, 3600, 1, -1],
        ];
    }

    /**
     * The lock key names the component, the user and the course module.
     */
    public function test_lock_key(): void {
        $this->assertSame('plagiarism_dixeo_42_7', job_quota::lock_key('plagiarism_dixeo', 42, 7));
    }
}
