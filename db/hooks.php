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
 * Hook callbacks for the Dixeo plugin.
 *
 * Injects course-page indicators and adds Learning intent to the course edit form.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();
$callbacks = [
    [
        'hook' => \core\hook\output\before_standard_top_of_body_html_generation::class,
        'callback' => [\local_dixeo\hook\output\sync_indicator_injector::class, 'callback'],
        'priority' => 500,
    ],
    [
        'hook' => \core\hook\output\before_footer_html_generation::class,
        'callback' => [\local_dixeo\hook\output\sync_indicator_injector::class, 'footer'],
        'priority' => 500,
    ],
    [
        'hook' => \core\hook\output\before_standard_top_of_body_html_generation::class,
        'callback' => [\local_dixeo\hook\output\content_image_pending_injector::class, 'callback'],
        'priority' => 400,
    ],
    [
        'hook' => \core_course\hook\after_form_definition::class,
        'callback' => [\local_dixeo\hook\course_edit_form::class, 'definition'],
    ],
    [
        'hook' => \core_course\hook\after_form_definition_after_data::class,
        'callback' => [\local_dixeo\hook\course_edit_form::class, 'definition_after_data'],
    ],
    [
        'hook' => \core_course\hook\after_form_submission::class,
        'callback' => [\local_dixeo\hook\course_edit_form::class, 'submission'],
    ],
];
