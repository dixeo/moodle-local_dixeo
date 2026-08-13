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

/**
 * Library functions for the Dixeo plugin.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add navigation nodes to the admin tree.
 *
 * This function extends the settings navigation tree with Dixeo-specific items.
 *
 * @param settings_navigation $settingsnav The settings navigation object.
 * @param context $context The context of the current page.
 */
function local_dixeo_extend_settings_navigation(settings_navigation $settingsnav, context $context): void {
    // No additional navigation items needed at this time.
    // The plugin uses admin settings and a dedicated report page.
}

/**
 * Add the tutor usage report to course navigation for users with access.
 *
 * @param navigation_node $navigation Course navigation node.
 * @param stdClass $course Course record.
 * @param context_course $context Course context.
 */
function local_dixeo_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context): void {
    if (!\local_dixeo\service\tutor_usage_report_service::can_view_course((int) $course->id)) {
        return;
    }

    $url = new moodle_url('/local/dixeo/tutor_usage_report.php', [
        'level' => \local_dixeo\service\tutor_usage_report_service::LEVEL_COURSE,
        'courseid' => (int) $course->id,
    ]);

    $navigation->add(
        get_string('tutor_usage_report_nav', 'local_dixeo'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'local_dixeo_tutor_usage',
        new pix_icon('i/report', '')
    );
}

/**
 * Get the default namespace for this Moodle site.
 *
 * The namespace is only needed when multiple Moodle sites share the same API key.
 * In that case, each site should use a different namespace to keep their data separate.
 *
 * @return string The default namespace.
 */
function local_dixeo_get_default_namespace(): string {
    return 'default';
}

/**
 * Get the configured namespace for API requests.
 *
 * Returns the namespace from plugin settings, falling back to the default.
 * This is the single source of truth for namespace resolution across all services.
 *
 * @return string The configured namespace.
 */
function local_dixeo_get_configured_namespace(): string {
    $namespace = get_config('local_dixeo', 'namespace');

    if (!empty($namespace)) {
        return $namespace;
    }

    return local_dixeo_get_default_namespace();
}

/**
 * Whether a job id looks like a UUID (canonical 8-4-4-4-12 hex form).
 *
 * Used by hub externals before course-shared job operations.
 *
 * @param string $jobid Candidate job UUID.
 * @return bool True when the string matches a UUID pattern.
 */
function local_dixeo_is_valid_job_uuid(string $jobid): bool {
    return (bool) preg_match(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
        $jobid
    );
}
