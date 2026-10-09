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

namespace local_dixeo\settings;

/**
 * Finds a Moodle admin setting by its plugin/name (or legacy $CFG name).
 *
 * Plugin settings are omitted from the admin tree unless the current user has
 * moodle/site:config. The lookup therefore reads a full tree as the site admin
 * and restores the previous user. Headings (`nosave`) are not settings.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class core_lookup {
    /** @var array<string, \admin_setting>|null Setting full name => setting, for this request. */
    private static ?array $index = null;

    /**
     * Require that the target is a stored admin setting.
     *
     * @param string $settingkey Catalogue key that declared the target.
     * @param core_target $target Core target to resolve.
     */
    public static function assert_resolvable(string $settingkey, core_target $target): void {
        $name = $target->admin_setting_name();
        if (self::find($name) === null) {
            throw new unresolved_core_target_exception($settingkey, $name);
        }
    }

    /**
     * @param string $settingname Admin setting name, such as `assign/duedate` or `glossary_entbypage`.
     * @return \admin_setting|null
     */
    public static function find(string $settingname): ?\admin_setting {
        $index = self::index();
        return $index[$settingname] ?? null;
    }

    /**
     * @return array<string, \admin_setting>
     */
    private static function index(): array {
        if (self::$index !== null) {
            return self::$index;
        }
        self::$index = [];
        self::collect(self::full_tree(), self::$index);
        return self::$index;
    }

    /**
     * Admin tree that includes plugin settings.
     *
     * @return \admin_root
     */
    private static function full_tree(): \admin_root {
        global $CFG, $USER, $ADMIN;
        require_once($CFG->libdir . '/adminlib.php');

        $systemcontext = \context_system::instance();
        if (has_capability('moodle/site:config', $systemcontext)) {
            return admin_get_root(false, true);
        }

        $admin = get_admin();
        if (!$admin) {
            return admin_get_root(false, true);
        }

        $previoususer = $USER;
        $previousadmin = $ADMIN;
        \core\session\manager::set_user($admin);
        $ADMIN = null;
        try {
            return admin_get_root(true, true);
        } finally {
            $ADMIN = $previousadmin;
            \core\session\manager::set_user($previoususer);
        }
    }

    /**
     * @param object $node Admin-tree node.
     * @param array<string, \admin_setting> $index Collected settings, keyed by plugin/name.
     */
    private static function collect(object $node, array &$index): void {
        if ($node instanceof \admin_settingpage && is_object($node->settings)) {
            foreach ($node->settings as $setting) {
                if (!$setting instanceof \admin_setting || $setting->nosave) {
                    continue;
                }
                $fullname = ($setting->plugin === null || $setting->plugin === '')
                    ? $setting->name
                    : $setting->plugin . '/' . $setting->name;
                $index[$fullname] = $setting;
            }
            return;
        }

        if ($node instanceof \admin_category) {
            foreach ($node->get_children() as $child) {
                self::collect($child, $index);
            }
        }
    }
}
