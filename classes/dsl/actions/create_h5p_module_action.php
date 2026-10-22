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
 * DSL action for creating mod_h5pactivity course modules from a content payload.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\dsl\actions;

use local_dixeo\dsl\dsl_exception;
use local_dixeo\dsl\value_resolver;
use local_dixeo\service\h5p_packaging_service;
use local_dixeo\service\job_service;

/**
 * Action handler that delegates H5P activity creation to the packaging service.
 *
 * Expected action format:
 * {
 *   "action": "create_h5p_module",
 *   "main_library": "H5P.QuestionSet 1.20",
 *   "save_as": "module",
 *   "fields": {
 *     "name": {"source": "$.name"},
 *     "intro": {"source": "$.intro"},
 *     "introformat": {"value": 1},
 *     "content": {"source": "$.content"}
 *   }
 * }
 *
 * Required context: courseid, sectionid, sectionnum.
 */
class create_h5p_module_action {
    /** @var h5p_packaging_service The packaging service used to build and provision activities. */
    protected h5p_packaging_service $packagingservice;

    /** @var job_service|null Job status client, created on the first packaged-image download. */
    private ?job_service $jobservice;

    /**
     * Constructor.
     *
     * @param h5p_packaging_service|null $packagingservice Custom service (defaults to a new instance).
     * @param job_service|null $jobservice Custom job service (defaults to a new instance when a download runs).
     */
    public function __construct(
        ?h5p_packaging_service $packagingservice = null,
        ?job_service $jobservice = null
    ) {
        $this->packagingservice = $packagingservice ?? new h5p_packaging_service();
        $this->jobservice = $jobservice;
    }

    /**
     * Execute the create_h5p_module action.
     *
     * @param array $action The action specification.
     * @param value_resolver $resolver The value resolver.
     * @return array Module data ['id', 'cmid', 'cm', 'name', 'modulename'] for variable storage.
     * @throws dsl_exception If the action specification or context is invalid.
     */
    public function execute(array $action, value_resolver $resolver): array {
        global $DB, $USER;

        $context = $resolver->get_context();
        $this->validate_context($context);

        $mainlibrary = $action['main_library'] ?? '';
        if (!is_string($mainlibrary) || $mainlibrary === '') {
            throw new dsl_exception(
                'create_h5p_module action is missing main_library',
                'create_h5p_module',
                ['action' => $action]
            );
        }

        // Fill jobs often omit intro in data; resolve missing paths as null (empty intro).
        $fields = $resolver->resolve_fields($action['fields'] ?? [], true);

        $name = isset($fields['name']) ? (string) $fields['name'] : '';
        $intro = isset($fields['intro']) && $fields['intro'] !== null ? (string) $fields['intro'] : '';
        $language = isset($fields['language']) ? (string) $fields['language'] : '';
        $content = $fields['content'] ?? null;
        if (!is_array($content)) {
            throw new dsl_exception(
                "create_h5p_module action requires 'content' to resolve to an array",
                'create_h5p_module',
                ['type' => gettype($content)]
            );
        }

        [$content, $files] = $this->prepare_packaged_images(
            $content,
            $fields['image_jobs'] ?? null,
            (int) $context['courseid'],
            (int) ($context['userid'] ?? $USER->id)
        );

        try {
            $result = $this->packagingservice->create_activity(
                (int) $context['courseid'],
                (int) $context['sectionid'],
                (int) $context['sectionnum'],
                $name,
                $intro,
                $mainlibrary,
                $content,
                $language,
                $context['beforemod'] ?? null,
                $files
            );
        } catch (\Throwable $e) {
            throw new dsl_exception(
                'H5P activity creation failed: ' . $e->getMessage(),
                'create_h5p_module',
                ['mainlibrary' => $mainlibrary],
                $e
            );
        }

        $shortcodeservice = \local_dixeo\external\service_factory::get_content_image_shortcode_service();
        $cmid = (int) $result['cmid'];
        $instanceid = (int) $result['id'];
        $modulecontext = \context_module::instance($cmid);
        $record = $DB->get_record('h5pactivity', ['id' => $instanceid], '*', MUST_EXIST);
        $shortcodeservice->process_and_persist(
            'h5pactivity',
            $instanceid,
            $modulecontext->id,
            (int) $context['courseid'],
            $cmid,
            $record,
            (int) ($context['userid'] ?? $USER->id)
        );

        return [
            'id' => $result['id'],
            'cmid' => $result['cmid'],
            'cm' => $result['cmid'],
            'name' => $name,
            'modulename' => 'h5pactivity',
        ];
    }

    /**
     * Download packaged flashcard images and drop any card image that has no file.
     *
     * A missing, null, or empty image_jobs list skips downloading. Card images are
     * still removed so content.json cannot point at a file the archive will not contain.
     *
     * @param array $content H5P content structure.
     * @param mixed $imagejobs Resolved image_jobs list, or null when the field is absent.
     * @param int $courseid Course the activity belongs to.
     * @param int $userid User the image jobs are attributed to.
     * @return array Content and a map of content-relative path to file bytes.
     */
    private function prepare_packaged_images(array $content, mixed $imagejobs, int $courseid, int $userid): array {
        $files = [];
        if (is_array($imagejobs) && $imagejobs !== []) {
            foreach ($imagejobs as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $path = isset($entry['path']) && is_string($entry['path']) ? $entry['path'] : '';
                $jobid = isset($entry['jobId']) && is_string($entry['jobId']) ? trim($entry['jobId']) : '';
                if ($path === '' || $jobid === '') {
                    continue;
                }
                $bytes = $this->download_packaged_image($jobid, $courseid, $userid);
                if ($bytes === '') {
                    continue;
                }
                $files[$path] = $bytes;
            }
        }

        return [$this->drop_undownloaded_card_images($content, $files), $files];
    }

    /**
     * Register an API image job locally and return its bytes when the job completed.
     *
     * @param string $jobid Remote image job UUID.
     * @param int $courseid Course the job is bound to.
     * @param int $userid User the job is attributed to.
     * @return string Image bytes, or an empty string when the job has no image.
     */
    private function download_packaged_image(string $jobid, int $courseid, int $userid): string {
        global $CFG;
        require_once($CFG->dirroot . '/local/dixeo/lib.php');

        (new \local_dixeo\repository\job_repository())->register(
            $jobid,
            $courseid,
            $userid,
            \local_dixeo_get_configured_namespace(),
            'image_generate',
            'local_dixeo'
        );

        $status = $this->job_service()->get_job_status($jobid, $courseid, $userid);
        if (!$status->is_completed() || !is_array($status->result)) {
            return '';
        }

        return \local_dixeo\service\image\result_helper::extract_image_binary_from_result($status->result);
    }

    /**
     * Remove card images whose path is not in the downloaded file map.
     *
     * @param array $content H5P content structure.
     * @param array $files Content-relative path to downloaded bytes.
     * @return array
     */
    private function drop_undownloaded_card_images(array $content, array $files): array {
        if (!isset($content['cards']) || !is_array($content['cards'])) {
            return $content;
        }
        foreach ($content['cards'] as $index => $card) {
            if (!is_array($card) || !isset($card['image']) || !is_array($card['image'])) {
                continue;
            }
            $path = $card['image']['path'] ?? '';
            if (!is_string($path) || !array_key_exists($path, $files)) {
                unset($card['image']);
                $content['cards'][$index] = $card;
            }
        }
        return $content;
    }

    /**
     * Job status client, created when the caller did not supply one.
     *
     * @return job_service
     */
    private function job_service(): job_service {
        if ($this->jobservice === null) {
            $this->jobservice = new job_service();
        }
        return $this->jobservice;
    }

    /**
     * Validate that the runtime context contains the keys required to create a module.
     *
     * @param array $context The runtime context.
     * @throws dsl_exception If a required key is missing.
     */
    protected function validate_context(array $context): void {
        foreach (['courseid', 'sectionid', 'sectionnum'] as $key) {
            if (!isset($context[$key])) {
                throw dsl_exception::missing_context($key);
            }
        }
    }
}
