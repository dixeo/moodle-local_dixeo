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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

use local_dixeo\external\service_factory;

/**
 * Checkbox for the Dixeo master switch on the plugin admin page.
 *
 * Reads and writes through the config helper. Saving On deletes the stored override.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_enabled extends \admin_setting_configcheckbox {
    /**
     * Build the Enable Dixeo checkbox.
     */
    public function __construct() {
        parent::__construct(
            'local_dixeo/enabled',
            get_string('enabled', 'local_dixeo'),
            get_string('enabled_help', 'local_dixeo'),
            '1'
        );
    }

    /**
     * Effective Dixeo-layer value. Unset is On, so the admin page does not write a default row.
     *
     * @return string
     */
    public function get_setting() {
        return service_factory::get_settings_config()->get(
            'local_dixeo',
            'enabled',
            \context_system::instance()
        )->get_value();
    }

    /**
     * Store Off, or delete the override when the checkbox is On.
     *
     * @param mixed $data Submitted yes or no value. Null leaves the stored value unchanged.
     * @return string Empty string on success, otherwise an error string.
     */
    public function write_setting($data) {
        if ($data === null) {
            return '';
        }
        $value = ((string) $data === $this->yes) ? $this->yes : $this->no;
        service_factory::get_settings_config()->set(
            'local_dixeo',
            'enabled',
            \context_system::instance(),
            $value
        );
        return '';
    }
}
