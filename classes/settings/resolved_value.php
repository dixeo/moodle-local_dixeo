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
 * Effective value of a Dixeo setting and the layer that supplied it.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class resolved_value {
    /**
     * Record the effective value and the layer that supplied it.
     *
     * @param string $value Effective text.
     * @param string $source Layer id from {@see resolver}.
     */
    public function __construct(
        /** @var string Effective text. */
        private string $value,
        /** @var string Layer id from {@see resolver}. */
        private string $source,
    ) {
    }

    /**
     * Effective text.
     *
     * @return string
     */
    public function get_value(): string {
        return $this->value;
    }

    /**
     * Layer that supplied the effective value.
     *
     * @return string
     */
    public function get_source(): string {
        return $this->source;
    }
}
