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

namespace local_dixeo\output;

use plugin_renderer_base;

/**
 * Renderer for the Dixeo plugin.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {
    /**
     * Render the credit report page.
     *
     * @param credit_report_page $page The credit report page renderable.
     * @return string The rendered HTML.
     */
    public function render_credit_report_page(credit_report_page $page): string {
        $data = $page->export_for_template($this);
        return $this->render_from_template('local_dixeo/credit_report', $data);
    }

    /**
     * Render the tutor usage report page.
     *
     * @param tutor_usage_report_page $page The tutor usage report page renderable.
     * @return string The rendered HTML.
     */
    public function render_tutor_usage_report_page(tutor_usage_report_page $page): string {
        $data = $page->export_for_template($this);
        return $this->render_from_template('local_dixeo/tutor_usage_report', $data);
    }
}
