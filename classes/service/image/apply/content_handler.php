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

namespace local_dixeo\service\image\apply;


use local_dixeo\repository\image\job_repository;
use local_dixeo\service\image\content\apply_handler;
use local_dixeo\service\image\content\file_service;
use local_dixeo\service\image\content\html_helper as content_html_helper;
use local_dixeo\service\image\content\location;
use local_dixeo\service\image\content\target_registry;
use local_dixeo\service\image\content_target;

/**
 * Apply content image job results (shortcode + modal paths).
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class content_handler {
    /**
     * Apply.
     * @param content_target $target
     * @param array $result
     * @param int $userid
     * @param string $source
     * @param \stdClass|null $jobrow
     * @param apply_handler|null $modalhandler
     * @return void
     */
    public static function apply(
        content_target $target,
        array $result,
        int $userid,
        string $source,
        ?\stdClass $jobrow,
        ?apply_handler $modalhandler
    ): void {
        $location = $target->get_location();

        if ($jobrow && $jobrow->origin === job_repository::ORIGIN_MODAL && $modalhandler !== null) {
            $modalhandler->apply_job_result($location, $result, $userid, $source);
            self::clear_generation_status_after_success($location, $jobrow);
            return;
        }

        file_service::apply_job_result($location, $result, $userid);

        // Editor drafts only replace the draft file; the editor polls and rewrites the live img.
        if (job_repository::is_editor_draft_job($jobrow)) {
            return;
        }

        self::clear_generation_status_after_success($location, $jobrow);
    }

    /**
     * Clear pending/failed gen classes and write the new contenthash into stored HTML.
     *
     * Modal jobs often lack targettable/placeholderid; resolve from the file location.
     *
     * @param location $location
     * @param \stdClass|null $jobrow
     * @return void
     */
    private static function clear_generation_status_after_success(location $location, ?\stdClass $jobrow): void {
        $contenthash = '';
        $stored = $location->get_stored_file();
        if ($stored) {
            $contenthash = $stored->get_contenthash();
        }

        $placeholderid = trim((string) ($jobrow->placeholderid ?? ''));
        if ($placeholderid === '') {
            $placeholderid = file_service::placeholderid_from_stub_filename($location->filename) ?? '';
        }

        $jobmeta = $jobrow ? clone $jobrow : (object) [];
        if (empty($jobmeta->targettable) || empty($jobmeta->targetfield) || empty($jobmeta->targetid)) {
            $htmltarget = target_registry::resolve_from_location($location);
            if ($htmltarget) {
                $jobmeta->targettable = $htmltarget->targettable;
                $jobmeta->targetfield = $htmltarget->targetfield;
                $jobmeta->targetid = $htmltarget->targetid;
            }
        }

        self::bump_url_revision($jobmeta);

        if ($placeholderid === '' || empty($jobmeta->targettable) || empty($jobmeta->targetfield) || empty($jobmeta->targetid)) {
            return;
        }

        content_html_helper::update_target_html_class(
            $jobmeta,
            $placeholderid,
            'dixeo-img-gen-pending',
            '',
            $contenthash
        );
    }

    /**
     * Bump the target's URL revision so browsers refetch the swapped image file.
     * @param \stdClass|null $jobrow
     * @return void
     */
    private static function bump_url_revision(?\stdClass $jobrow): void {
        if ($jobrow && !empty($jobrow->targettable) && !empty($jobrow->targetid)) {
            target_registry::bump_url_revision((string) $jobrow->targettable, (int) $jobrow->targetid);
        }
    }

    /**
     * Apply failure.
     * @param content_target $target
     * @param int $userid
     * @param \stdClass|null $jobrow
     * @return void
     */
    public static function apply_failure(content_target $target, int $userid, ?\stdClass $jobrow): void {
        $isdraft = job_repository::is_editor_draft_job($jobrow);
        // Mirror apply(): replace stub for shortcode + editor_draft; never overwrite modal images.
        $shouldreplacefile = $jobrow !== null
            && ($jobrow->origin ?? '') !== job_repository::ORIGIN_MODAL;
        $location = $target->get_location();
        if ($shouldreplacefile) {
            file_service::apply_failed_placeholder($location, $userid);
        }

        // Editor drafts only replace the draft file; the editor polls and rewrites the live img.
        if ($isdraft) {
            return;
        }

        if ($shouldreplacefile) {
            self::bump_url_revision($jobrow);
        }

        if ($jobrow && !empty($jobrow->placeholderid)) {
            $contenthash = '';
            if ($shouldreplacefile) {
                $stored = $location->get_stored_file();
                if ($stored) {
                    $contenthash = $stored->get_contenthash();
                }
            }
            content_html_helper::update_target_html_class(
                $jobrow,
                (string) $jobrow->placeholderid,
                'dixeo-img-gen-pending',
                'dixeo-img-gen-failed',
                $contenthash
            );
        }
    }
}
