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
 * Unit tests for the Design a course entry in the admin tree.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use local_dixeo\service\plugin_installation_service;
use local_dixeo\settings\config;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

/**
 * Design a course is listed under Courses for every user with the designer capability.
 *
 * The entry is registered by local/dixeo/settings.php, which core loads for every user who
 * builds the admin tree.
 *
 * @coversNothing
 */
final class admin_tree_designer_test extends \advanced_testcase {
    /** @var string Admin page name of the designer entry. */
    private const PAGE = 'block_dixeo_designer_designacourse';

    /**
     * Without block_dixeo_designer the entry is not registered.
     */
    public function test_absent_without_the_designer_block(): void {
        $this->resetAfterTest();
        if (plugin_installation_service::is_plugin_installed('block_dixeo_designer')) {
            $this->markTestSkipped('block_dixeo_designer is installed.');
        }
        $this->setAdminUser();

        $this->assertNull(admin_get_root(true, false)->locate(self::PAGE));
    }

    /**
     * The entry follows the designer capability, not the site configuration capability.
     */
    public function test_access_follows_the_designer_capability(): void {
        $this->resetAfterTest();
        $this->require_designer_block();
        $generator = $this->getDataGenerator();
        $system = \context_system::instance();

        $this->setAdminUser();
        $page = admin_get_root(true, false)->locate(self::PAGE);
        $this->assertInstanceOf(\admin_externalpage::class, $page);
        $this->assertTrue($page->check_access());

        $manager = $this->create_manager();
        $this->setUser($manager);
        $this->assertFalse(has_capability('moodle/site:config', $system));
        $page = admin_get_root(true, false)->locate(self::PAGE);
        $this->assertInstanceOf(\admin_externalpage::class, $page);
        $this->assertTrue($page->check_access());

        $viewer = $generator->create_user();
        $viewerrole = $generator->create_role();
        assign_capability('moodle/site:configview', CAP_ALLOW, $viewerrole, $system->id);
        role_assign($viewerrole, $viewer->id, $system->id);
        $this->setUser($viewer);
        $page = admin_get_root(true, false)->locate(self::PAGE);
        $this->assertInstanceOf(\admin_externalpage::class, $page);
        $this->assertFalse($page->check_access());

        $prohibited = $this->create_manager();
        $prohibitrole = $generator->create_role();
        assign_capability('local/dixeo:create', CAP_PROHIBIT, $prohibitrole, $system->id);
        role_assign($prohibitrole, $prohibited->id, $system->id);
        $this->setUser($prohibited);
        $page = admin_get_root(true, false)->locate(self::PAGE);
        $this->assertInstanceOf(\admin_externalpage::class, $page);
        $this->assertFalse($page->check_access());
    }

    /**
     * While Dixeo is off site-wide the entry is not registered for anyone.
     */
    public function test_absent_while_dixeo_is_off(): void {
        $this->resetAfterTest();
        $this->require_designer_block();
        $manager = $this->create_manager();

        $this->setAdminUser();
        (new config())->set('local_dixeo', 'enabled', \context_system::instance(), '0');
        $this->assertNull(admin_get_root(true, false)->locate(self::PAGE));

        $this->setUser($manager);
        $this->assertNull(admin_get_root(true, false)->locate(self::PAGE));
    }

    /**
     * Skip when block_dixeo_designer is not installed on this site.
     */
    private function require_designer_block(): void {
        if (!plugin_installation_service::is_plugin_installed('block_dixeo_designer')) {
            $this->markTestSkipped('block_dixeo_designer is not installed.');
        }
    }

    /**
     * Create a user holding the default manager role at system level.
     *
     * @return \stdClass
     */
    private function create_manager(): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($roleid, $user->id, \context_system::instance()->id);
        return $user;
    }
}
