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
 * Poll pending/failed content-image placeholders on course pages.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\external;

use context;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_dixeo\repository\image\job_repository;
use local_dixeo\service\image\content\file_service;
use local_dixeo\service\image\content\url_helper;
use local_dixeo\service\image\content_target;
use local_dixeo\service\image\target_factory;

/**
 * External API to poll content image placeholder status for live page updates.
 */
class get_content_image_status extends external_api {
    /**
     * Describe parameters for the web service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'placeholderids' => new external_multiple_structure(
                new external_value(PARAM_RAW, 'Placeholder UUID'),
                'Placeholder ids to poll'
            ),
        ]);
    }

    /**
     * Build an idle payload so the client can stop polling a missing job.
     *
     * @param string $placeholderid
     * @return array
     */
    private static function idle_item(string $placeholderid): array {
        return [
            'placeholderid' => $placeholderid,
            'status' => 'idle',
            'imageurl' => '',
            'imgclass' => 'img-fluid',
            'contenthash' => '',
            'errormessage' => '',
            'filename' => file_service::stub_filename_for_placeholder($placeholderid),
        ];
    }

    /**
     * Return status details for the given content image placeholders.
     *
     * Always returns one item per requested id. Missing/inaccessible jobs are
     * reported as status "idle" so the page poller can clear pending UI.
     *
     * @param array $placeholderids Placeholder UUIDs to poll.
     * @return array
     */
    public static function execute(array $placeholderids): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'placeholderids' => $placeholderids,
        ]);

        $items = [];
        $seencontexts = [];

        foreach ($params['placeholderids'] as $placeholderid) {
            $placeholderid = trim((string) $placeholderid);
            if ($placeholderid === '') {
                continue;
            }

            $job = job_repository::get_by_placeholderid($placeholderid);
            if (!$job || empty($job->contextid)) {
                $items[] = self::idle_item($placeholderid);
                continue;
            }

            $contextid = (int) $job->contextid;
            if (!isset($seencontexts[$contextid])) {
                $context = context::instance_by_id($contextid, IGNORE_MISSING);
                if (!$context) {
                    $items[] = self::idle_item($placeholderid);
                    continue;
                }
                self::validate_context($context);
                $seencontexts[$contextid] = true;
            }

            $target = target_factory::from_job_record($job);
            $statuspayload = job_repository::get_status_for_target($target, false);
            $status = (string) ($statuspayload['status'] ?? 'idle');

            $imgclass = 'img-fluid';
            if (
                $status === job_repository::STATUS_PENDING
                || $status === job_repository::STATUS_PROCESSING
            ) {
                $imgclass .= ' dixeo-img-gen-pending';
            } else if ($status === job_repository::STATUS_FAILED) {
                $imgclass .= ' dixeo-img-gen-failed';
            }

            $location = null;
            if ($target instanceof content_target) {
                $location = $target->get_location();
            }
            $imageurl = (string) ($statuspayload['imageurl'] ?? '');
            $contenthash = (string) ($statuspayload['current_contenthash'] ?? '');
            if ($location) {
                if ($imageurl === '') {
                    $imageurl = url_helper::get_current_image_url($location);
                }
                if ($contenthash === '') {
                    $stored = $location->get_stored_file();
                    $contenthash = $stored ? $stored->get_contenthash() : '';
                }
            }
            if ($contenthash !== '' && $imageurl !== '') {
                $imageurl = url_helper::append_image_rev($imageurl, $contenthash);
            }

            $items[] = [
                'placeholderid' => $placeholderid,
                'status' => $status,
                'imageurl' => $imageurl,
                'imgclass' => $imgclass,
                'contenthash' => $contenthash,
                'errormessage' => (string) ($statuspayload['errormessage'] ?? ''),
                'filename' => file_service::stub_filename_for_placeholder($placeholderid),
            ];
        }

        return [
            'items' => $items,
        ];
    }

    /**
     * Describe return values for the web service.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(
                new external_single_structure([
                    'placeholderid' => new external_value(PARAM_RAW, 'Placeholder UUID'),
                    'status' => new external_value(PARAM_ALPHANUMEXT, 'Job status'),
                    'imageurl' => new external_value(PARAM_RAW, 'Current image URL'),
                    'imgclass' => new external_value(PARAM_RAW, 'Suggested img class attribute'),
                    'contenthash' => new external_value(PARAM_ALPHANUM, 'File contenthash for cache busting'),
                    'errormessage' => new external_value(PARAM_RAW, 'Error message when failed'),
                    'filename' => new external_value(PARAM_FILE, 'Stub filename'),
                ])
            ),
        ]);
    }
}
