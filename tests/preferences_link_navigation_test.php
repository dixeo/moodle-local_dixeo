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
 * Unit tests for the Dixeo preferences link in user settings navigation.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\settings\config;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/dixeo/lib.php');

/**
 * The Dixeo preferences link appears only on the user's own Preferences page.
 *
 * @covers ::local_dixeo_extend_navigation_user_settings
 */
final class preferences_link_navigation_test extends \advanced_testcase {
    /**
     * Reset the page and the tree for each case.
     */
    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        $PAGE = new \moodle_page();
    }

    /**
     * The user's own Preferences page gets a plain setting link and the opener module.
     */
    public function test_own_preferences_page_gets_the_link(): void {
        global $PAGE;
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->set_page('/user/preferences.php', \context_user::instance($user->id));

        $navigation = $this->run_callback($user);

        $node = $navigation->find('local_dixeo_preferences', \navigation_node::TYPE_SETTING);
        $this->assertInstanceOf(\navigation_node::class, $node);
        $this->assertSame('useraccount', $node->parent->key);
        $this->assertSame(\navigation_node::TYPE_SETTING, $node->type);
        $this->assertSame('Dixeo preferences', $node->get_content());
        $this->assertSame(
            (new \moodle_url('/user/preferences.php', null, 'local-dixeo-preferences'))->out(false),
            $node->action->out(false)
        );
        $this->assertStringEndsWith('#local-dixeo-preferences', $node->action->out(false));
        $this->assertSame([], $node->classes);
        $this->assertStringContainsString('local_dixeo/preferences_link', $PAGE->requires->get_end_code());
    }

    /**
     * An administrator viewing another user's settings gets no link for that user.
     */
    public function test_other_user_settings_get_no_link(): void {
        global $PAGE;
        $this->setAdminUser();
        $other = $this->getDataGenerator()->create_user();
        $this->set_page(new \moodle_url('/user/preferences.php', ['userid' => $other->id]), \context_user::instance($other->id));

        $navigation = $this->run_callback($other);

        $this->assertFalse($navigation->find('local_dixeo_preferences', \navigation_node::TYPE_SETTING));
        $this->assertStringNotContainsString('local_dixeo/preferences_link', $PAGE->requires->get_end_code());
    }

    /**
     * On another user's Preferences page, core also builds the viewer's own settings: that run adds nothing.
     */
    public function test_viewer_settings_on_other_user_page_get_no_link(): void {
        global $PAGE, $USER;
        $this->setAdminUser();
        $other = $this->getDataGenerator()->create_user();
        $this->set_page(new \moodle_url('/user/preferences.php', ['userid' => $other->id]), \context_user::instance($other->id));

        $navigation = $this->run_callback($USER);

        $this->assertFalse($navigation->find('local_dixeo_preferences', \navigation_node::TYPE_SETTING));
        $this->assertStringNotContainsString('local_dixeo/preferences_link', $PAGE->requires->get_end_code());
    }

    /**
     * With Dixeo off and no consent record, My preferences has no row, so there is no link.
     */
    public function test_no_link_without_any_row(): void {
        global $PAGE;
        $this->setAdminUser();
        (new config())->set('local_dixeo', 'enabled', \context_system::instance(), '0');
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->set_page('/user/preferences.php', \context_user::instance($user->id));

        $navigation = $this->run_callback($user);

        $this->assertFalse($navigation->find('local_dixeo_preferences', \navigation_node::TYPE_SETTING));
        $this->assertStringNotContainsString('local_dixeo/preferences_link', $PAGE->requires->get_end_code());
    }

    /**
     * Another page in the user's own context gets no link.
     */
    public function test_other_page_gets_no_link(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->set_page('/user/profile.php', \context_user::instance($user->id));

        $navigation = $this->run_callback($user);

        $this->assertFalse($navigation->find('local_dixeo_preferences', \navigation_node::TYPE_SETTING));
    }

    /**
     * Set the URL and context of the current page.
     *
     * @param string|\moodle_url $url Page URL.
     * @param \context $context Page context.
     */
    private function set_page($url, \context $context): void {
        global $PAGE;
        $PAGE->set_url($url);
        $PAGE->set_context($context);
    }

    /**
     * Build a user settings tree with a User account container and run the callback for one user.
     *
     * @param \stdClass $user User whose settings are built.
     * @return \navigation_node Root of the tree.
     */
    private function run_callback(\stdClass $user): \navigation_node {
        global $SITE;
        $root = \navigation_node::create('root');
        $root->add('User account', null, \navigation_node::TYPE_CONTAINER, null, 'useraccount');
        local_dixeo_extend_navigation_user_settings(
            $root,
            $user,
            \context_user::instance($user->id),
            $SITE,
            \context_course::instance($SITE->id)
        );
        return $root;
    }
}
