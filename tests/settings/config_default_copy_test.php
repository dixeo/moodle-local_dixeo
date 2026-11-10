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
 * Tests for the default-copy marker on setting_updated.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\settings;

use local_dixeo\event\setting_updated;

/**
 * Only copies of the Dixeo values onto a new activity are marked as default copies.
 *
 * @covers \local_dixeo\settings\config::copy_activity_defaults
 * @covers \local_dixeo\settings\config::set
 */
final class config_default_copy_test extends \advanced_testcase {
    /**
     * An installed bool setting that can be stored on an activity.
     *
     * @return definition
     */
    private function activity_bool_setting(): definition {
        foreach (catalogue::load() as $definition) {
            if ($definition->get_type() === 'bool' && in_array(resolver::LAYER_ACTIVITY, $definition->get_levels(), true)) {
                return $definition;
            }
        }
        $this->markTestSkipped('No installed plugin declares an activity-level bool setting.');
    }

    public function test_only_default_copies_carry_the_marker(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $definition = $this->activity_bool_setting();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $context = \context_module::instance($page->cmid);
        $DB->delete_records(config::TABLE, ['contextid' => $context->id]);
        $config = new config();

        $sink = $this->redirectEvents();
        $config->copy_activity_defaults($context);
        $copies = array_filter($sink->get_events(), static fn($event): bool => $event instanceof setting_updated);
        $sink->close();

        $this->assertNotEmpty($copies);
        foreach ($copies as $event) {
            $this->assertTrue($event->other['defaultcopy'] ?? false);
        }

        $current = $config->get($definition->get_component(), $definition->get_key(), $context)->get_value();
        $sink = $this->redirectEvents();
        $config->set($definition->get_component(), $definition->get_key(), $context, $current === '1' ? '0' : '1', false);
        $writes = array_filter($sink->get_events(), static fn($event): bool => $event instanceof setting_updated);
        $sink->close();

        $this->assertCount(1, $writes);
        $this->assertArrayNotHasKey('defaultcopy', reset($writes)->other);
    }
}
