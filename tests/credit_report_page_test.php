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

use local_dixeo\dto\credit_transaction;
use local_dixeo\output\credit_report_filters;
use local_dixeo\output\credit_report_page;
use local_dixeo\repository\credit_usage_repository;
use local_dixeo\service\credit_usage_report_service;

/**
 * Tests for credit report page filter rendering and navigation URLs.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_dixeo\output\credit_report_page
 */
final class credit_report_page_test extends \advanced_testcase {
    /**
     * Applied filter values must appear in option lists even when absent from period data.
     */
    public function test_build_filter_options_includes_selected_values(): void {
        $page = $this->create_page([
            'moduletypes' => ['h5pactivity'],
        ]);

        $options = $this->invoke($page, 'build_filter_options', [['page'], ['h5pactivity'], 'credit_moduletype_']);
        $selected = array_values(array_filter($options, static fn(array $option): bool => !empty($option['selected'])));

        $this->assertCount(1, $selected);
        $this->assertSame('h5pactivity', $selected[0]['value']);
    }

    /**
     * Action aliases selected in the URL are normalized to canonical option values.
     */
    public function test_build_filter_options_normalizes_action_aliases(): void {
        $page = $this->create_page([
            'jobtypes' => ['module_generate'],
        ]);

        $options = $this->invoke($page, 'build_filter_options', [[], ['module_generate'], 'credit_action_']);
        $selected = array_values(array_filter($options, static fn(array $option): bool => !empty($option['selected'])));

        $this->assertCount(1, $selected);
        $this->assertSame('generate_module', $selected[0]['value']);
    }

    /**
     * View links preserve active filters via the shared filter state serializer.
     */
    public function test_view_urls_preserve_active_filters(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $repo = new credit_usage_repository();
        $repo->upsert_from_transaction(
            credit_transaction::from_array([
                'id' => 'tx-url-user',
                'type' => credit_transaction::TYPE_DEDUCTION,
                'amount' => -1,
                'balanceAfter' => 99,
                'createdAt' => gmdate('c'),
            ]),
            (object) [
                'userid' => (int) $user->id,
                'courseid' => (int) $course->id,
                'operation' => 'module_generate',
                'component' => 'block_dixeo_modulegen',
            ]
        );

        $filters = credit_report_filters::from_raw([
            'components' => ['block_dixeo_tutor'],
            'jobtypes' => ['tutor_message'],
            'moduletypes' => ['h5pactivity'],
            'userids' => [(int) $user->id],
            'courseids' => [(int) $course->id],
        ]);

        $weekurl = credit_usage_report_service::build_report_url(array_merge(
            $filters->to_query_params(),
            ['view' => credit_usage_report_service::VIEW_WEEK]
        ));
        $monthurl = credit_usage_report_service::build_report_url(array_merge(
            $filters->to_query_params(),
            ['view' => credit_usage_report_service::VIEW_MONTH]
        ));

        $this->assertStringContainsString('component%5B0%5D=block_dixeo_tutor', $weekurl);
        $this->assertStringContainsString('jobtype%5B0%5D=tutor_message', $weekurl);
        $this->assertStringContainsString('moduletype%5B0%5D=h5pactivity', $weekurl);
        $this->assertStringContainsString('userid%5B0%5D=' . $user->id, $weekurl);
        $this->assertStringContainsString('courseid%5B0%5D=' . $course->id, $weekurl);
        $this->assertStringContainsString('view=week', $weekurl);
        $this->assertStringContainsString('view=month', $monthurl);
        $this->assertStringContainsString('component%5B0%5D=block_dixeo_tutor', $monthurl);
    }

    /**
     * Create a credit report page instance for protected method tests.
     *
     * @param array $params Renderable parameters.
     * @return credit_report_page
     */
    private function create_page(array $params): credit_report_page {
        return new credit_report_page($params);
    }

    /**
     * Invoke a protected method on the credit report page.
     *
     * @param credit_report_page $page Page instance.
     * @param string $method Method name.
     * @param array $args Method arguments.
     * @return mixed
     */
    private function invoke(credit_report_page $page, string $method, array $args = []) {
        $reflection = new \ReflectionClass($page);
        $callable = $reflection->getMethod($method);
        $callable->setAccessible(true);
        return $callable->invoke($page, ...$args);
    }
}
