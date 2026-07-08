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
            return;
        }

        file_service::apply_job_result($location, $result, $userid);

        if (job_repository::is_editor_draft_job($jobrow)) {
            return;
        }

        if ($jobrow && $jobrow->origin === job_repository::ORIGIN_SHORTCODE && !empty($jobrow->placeholderid)) {
            content_html_helper::update_target_html_class(
                $jobrow,
                (string) $jobrow->placeholderid,
                'dixeo-img-gen-pending',
                ''
            );
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
        $location = $target->get_location();
        file_service::apply_failed_placeholder($location, $userid);

        if (job_repository::is_editor_draft_job($jobrow)) {
            return;
        }

        if ($jobrow && !empty($jobrow->placeholderid)) {
            content_html_helper::update_target_html_class(
                $jobrow,
                (string) $jobrow->placeholderid,
                'dixeo-img-gen-pending',
                'dixeo-img-gen-failed'
            );
        }
    }
}
