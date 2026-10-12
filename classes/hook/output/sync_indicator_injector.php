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
 * Hook callback for injecting the sync indicator into pages.
 *
 * Injects the file sync pill into course pages for users with the appropriate capability.
 * The pill opens the Dixeo settings modal on the Status tab.
 *
 * @package    local_dixeo
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\hook\output;

use core\hook\output\before_footer_html_generation;
use core\hook\output\before_standard_top_of_body_html_generation;
use local_dixeo\external\service_factory;
use local_dixeo\service\file_sync_policy;

/**
 * Callback class for injecting sync indicator.
 */
class sync_indicator_injector {
    /**
     * Inject the pill beside the course title when that is where this course shows it.
     *
     * @param before_standard_top_of_body_html_generation $hook The hook instance.
     * @return void
     */
    public static function callback(before_standard_top_of_body_html_generation $hook): void {
        self::inject($hook, 'coursetitle');
    }

    /**
     * Inject the pill in the page footer when that is where this course shows it.
     *
     * @param before_footer_html_generation $hook The hook instance.
     * @return void
     */
    public static function footer(before_footer_html_generation $hook): void {
        self::inject($hook, 'footer');
    }

    /**
     * Inject the pill for one placement. Hidden courses get no pill. The other placement is skipped.
     *
     * @param before_standard_top_of_body_html_generation|before_footer_html_generation $hook The hook instance.
     * @param string $placement coursetitle or footer.
     * @return void
     */
    private static function inject(
        before_standard_top_of_body_html_generation|before_footer_html_generation $hook,
        string $placement
    ): void {
        global $COURSE, $PAGE, $OUTPUT;

        // Skip iframe/popup layouts (e.g. H5P embed) — would duplicate the indicator inside the frame.
        if (in_array($PAGE->pagelayout, ['embedded', 'popup', 'print', 'frametop', 'secure', 'maintenance'], true)) {
            return;
        }

        // Only show on course pages (not site level).
        if (!isset($COURSE->id) || $COURSE->id <= 1) {
            return;
        }

        $context = \context_course::instance($COURSE->id);
        $config = service_factory::get_settings_config();
        if (!$config->enabled_for($context)) {
            return;
        }

        $configured = $config->get('local_dixeo', 'filesync_pill', $context)->get_value();
        if ($configured === 'hidden' || $configured !== $placement) {
            return;
        }

        // Check capability (view indicator with generate; manage actions need syncfiles).
        if (!has_capability('local/dixeo:generate', $context) && !has_capability('local/dixeo:syncfiles', $context)) {
            return;
        }

        if (!file_sync_policy::should_show_sync_indicator($COURSE->id)) {
            return;
        }

        $canmanagesync = has_capability('local/dixeo:syncfiles', $context);

        // Get sync status.
        $service = service_factory::get_file_sync_service();
        $status = $service->get_status($COURSE->id);

        // Prepare template context.
        $templatecontext = self::prepare_template_context($COURSE->id, $status, $canmanagesync);
        $templatecontext['contextid'] = $context->id;
        $templatecontext['coursename'] = $COURSE->fullname;
        $templatecontext['footer'] = $placement === 'footer';

        // Render the indicator.
        $html = $OUTPUT->render_from_template('local_dixeo/sync_indicator', $templatecontext);

        // Add the HTML to the page.
        $hook->add_html($html);

        // The pill keeps polling. The settings modal opens on the Status tab.
        $PAGE->requires->js_call_amd('local_dixeo/settings_modal', 'init');
        $PAGE->requires->js_call_amd('local_dixeo/sync_indicator', 'init', [
            $COURSE->id,
            $status->status,
            $status->enabled,
            $status->filestotal,
            $status->filescompleted,
            $placement,
        ]);
    }

    /**
     * Prepare the template context from status data.
     *
     * @param int $courseid The course ID.
     * @param \stdClass $status The status object.
     * @param bool $canmanagesync Whether the user may enable/disable/trigger sync.
     * @return array The template context.
     */
    private static function prepare_template_context(int $courseid, \stdClass $status, bool $canmanagesync = false): array {
        $statusclass = $status->enabled ? $status->status : 'disabled';

        $context = [
            'courseid' => $courseid,
            'status' => $status->status,
            'enabled' => $status->enabled,
            'canmanagesync' => $canmanagesync,
            'statusclass' => $statusclass,
            'tooltip' => self::get_tooltip($status),
            'badgelabel' => self::format_badge_label($status->status, $status->filescompleted, $status->filestotal),
        ];

        return $context;
    }

    /**
     * Format the badge label shown on the closed pill.
     *
     * Hidden when synchronized, and when the course has no files to count.
     *
     * @param string|null $statusvalue Current sync status.
     * @param int|null $filescompleted Files synced so far.
     * @param int|null $filestotal Total files expected.
     * @return string|null Label, or null when the badge should be hidden.
     */
    private static function format_badge_label(?string $statusvalue, ?int $filescompleted, ?int $filestotal): ?string {
        if ($statusvalue === 'synchronized') {
            return null;
        }

        if ($filestotal === null || $filestotal <= 0) {
            return null;
        }

        $done = (int) ($filescompleted ?? 0);
        if ($done >= $filestotal) {
            return (string) $filestotal;
        }

        return $done . ' / ' . $filestotal;
    }

    /**
     * Get the tooltip text for the indicator.
     *
     * @param \stdClass $status The status object.
     * @return string The tooltip text.
     */
    private static function get_tooltip(\stdClass $status): string {
        if (!$status->enabled) {
            return get_string('filesync_status_disabled', 'local_dixeo');
        }

        return match ($status->status) {
            'none' => get_string('filesync_status_none', 'local_dixeo'),
            'syncing' => get_string('filesync_status_syncing', 'local_dixeo'),
            'synchronized' => get_string('filesync_status_synchronized', 'local_dixeo'),
            'outdated' => get_string('filesync_status_outdated', 'local_dixeo'),
            'error' => get_string('filesync_status_error', 'local_dixeo'),
            'paused' => get_string('filesync_status_paused', 'local_dixeo'),
            default => get_string('filesync_status_none', 'local_dixeo'),
        };
    }
}
