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

namespace local_dixeo;

use local_dixeo\external\search_credit_report_courses;
use local_dixeo\external\search_credit_report_users;

/**
 * Tests for credit report filter search webservices.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\external\search_credit_report_users
 * @covers     \local_dixeo\external\search_credit_report_courses
 */
final class credit_report_search_external_test extends \advanced_testcase {
    /**
     * User search returns only users with usage in the requested period.
     */
    public function test_search_credit_report_users_is_period_scoped(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user(['firstname' => 'Searchable', 'lastname' => 'User']);
        $this->create_usage((int) $user->id, 0, 'tx-search-user', time());

        $results = search_credit_report_users::execute('Searchable', time() - DAYSECS, time() + DAYSECS);
        $this->assertCount(1, $results['list']);
        $this->assertSame((int) $user->id, $results['list'][0]['id']);

        $empty = search_credit_report_users::execute('Searchable', time() + YEARSECS, time() + YEARSECS + DAYSECS);
        $this->assertSame([], $empty['list']);
    }

    /**
     * Course search returns only courses with usage in the requested period.
     */
    public function test_search_credit_report_courses_is_period_scoped(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['fullname' => 'Searchable Course']);
        $this->create_usage(0, (int) $course->id, 'tx-search-course', time());

        $results = search_credit_report_courses::execute('Searchable', time() - DAYSECS, time() + DAYSECS);
        $this->assertCount(1, $results['list']);
        $this->assertSame((int) $course->id, $results['list'][0]['id']);

        $empty = search_credit_report_courses::execute('Searchable', time() + YEARSECS, time() + YEARSECS + DAYSECS);
        $this->assertSame([], $empty['list']);
    }

    /**
     * Insert a minimal usage row.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param string $transactionid Transaction ID.
     * @param int $when Timestamp.
     */
    private function create_usage(int $userid, int $courseid, string $transactionid, int $when): void {
        $repo = new \local_dixeo\repository\credit_usage_repository();
        $repo->upsert_from_transaction(
            \local_dixeo\dto\credit_transaction::from_array([
                'id' => $transactionid,
                'type' => \local_dixeo\dto\credit_transaction::TYPE_DEDUCTION,
                'amount' => -1,
                'balanceAfter' => 99,
                'createdAt' => gmdate('c', $when),
            ]),
            (object) [
                'userid' => $userid,
                'courseid' => $courseid,
                'operation' => 'module_generate',
                'component' => 'block_dixeo_modulegen',
            ]
        );
    }
}
