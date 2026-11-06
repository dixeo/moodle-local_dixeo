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

// phpcs:disable moodle.Commenting.InlineComment.DocBlock -- Older moodle-cs omits T_ENUM from allowed docblock targets.
/**
 * Effect of an entry point, which decides the Dixeo switch and Terms of Use gates it needs.
 *
 * An entry point is classified by what it actually does, not by its name.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum entry_effect: string {
    // phpcs:enable moodle.Commenting.InlineComment.DocBlock

    // Starts, queues or advances processing, or stores input that will be sent.
    // Needs Dixeo enabled and the current Terms of Use.
    case SEND = 'send';

    // Reads feature data, or may start work that is skipped without the current terms.
    // Needs Dixeo enabled only.
    case FEATURE_READ = 'featureread';

    // Cancels, stops, turns off, erases, or reads the status of existing work, with no path
    // that starts, queues or advances a send. Needs neither the switch nor the terms.
    case STOP_OR_STATUS = 'stoporstatus';

    /**
     * Whether Dixeo must be enabled for the course.
     *
     * @return bool
     */
    public function requires_enabled(): bool {
        return $this !== self::STOP_OR_STATUS;
    }

    /**
     * Whether the acting user must have accepted the current Terms of Use.
     *
     * @return bool
     */
    public function requires_terms(): bool {
        return $this === self::SEND;
    }
}
