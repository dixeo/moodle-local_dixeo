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

namespace local_dixeo\external\traits;

/**
 * Trait for credit report access checks in external APIs.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait credit_report_access {
    /**
     * Validate system context and credit report capabilities.
     *
     * @return \context_system
     */
    protected static function validate_credit_report_access(): \context_system {
        $context = \context_system::instance();
        self::validate_context($context);
        if (
            !has_capability('local/dixeo:manage', $context)
            && !has_capability('local/dixeo:viewusage', $context)
        ) {
            require_capability('local/dixeo:manage', $context);
        }
        return $context;
    }
}
