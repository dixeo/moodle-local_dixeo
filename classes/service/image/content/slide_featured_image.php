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

namespace local_dixeo\service\image\content;

use local_dixeo\dto\job_binding_metadata;
use local_dixeo\repository\image\job_repository;
use local_dixeo\service\image\content_target;
use local_dixeo\service\image\generation_request;
use local_dixeo\service\image\job_orchestrator;
use local_dixeo\service\image\policy;

/**
 * Resolves a slideshow featured-image shortcode into the slide image file area.
 *
 * The shortcode uses the same grammar as an inline content image. The file is
 * not written into the slide HTML.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class slide_featured_image {
    /**
     * Whether the value is one new [img-gen] shortcode.
     *
     * @param string $value Featured image field from the API.
     * @return bool
     */
    public static function has_generatable_shortcode(string $value): bool {
        $parsed = self::parse($value);
        return $parsed !== null;
    }

    /**
     * Queue generation of the featured image.
     *
     * Returns false when no file will be stored. The caller then keeps the
     * slide on the background layout.
     *
     * @param int $slideid Slide id, used as the file itemid.
     * @param \context $context Module context.
     * @param int $courseid Course id.
     * @param int $cmid Course module id.
     * @param string $featuredimage Featured image field from the API.
     * @param string $title Slide title, used as the image job title.
     * @param int $userid User the job is attributed to.
     * @return bool True when a job was queued.
     */
    public function queue(
        int $slideid,
        \context $context,
        int $courseid,
        int $cmid,
        string $featuredimage,
        string $title,
        int $userid
    ): bool {
        $spec = self::parse($featuredimage);
        if ($spec === null) {
            return false;
        }
        if (
            !policy::is_enabled(
                policy::ENTITY_CONTENT,
                policy::ACTION_GENERATE,
                $courseid > 0 ? \context_course::instance($courseid) : null
            )
        ) {
            return false;
        }

        $placeholderid = \core\uuid::generate();
        $location = new location(
            $context->id,
            'mod_slideshow',
            'image',
            $slideid,
            '/',
            file_service::stub_filename_for_placeholder($placeholderid),
            $courseid
        );
        file_service::create_stub($location, $userid);

        $request = generation_request::for_content(
            'local_dixeo',
            $courseid,
            $title !== '' ? $title : get_string('contentimagetitlefallback', 'local_dixeo'),
            $spec['prompt'],
            $spec['size'],
            $spec['quality'],
            $cmid > 0 ? job_binding_metadata::for_module('slideshow', $cmid) : null
        );
        job_orchestrator::queue_generation(content_target::from_location($location), $userid, $request, [
            'origin' => job_repository::ORIGIN_SLIDE_FEATURED,
            'targettable' => 'slideshow_slide',
            'targetfield' => 'layout',
            'targetid' => $slideid,
            'cmid' => $cmid,
            'prompt' => $spec['prompt'],
            'quality' => $spec['quality'],
            'mode' => $spec['mode'],
        ]);

        return true;
    }

    /**
     * Delete the featured image and show the slide as a background with no picture.
     *
     * Used when generation fails after the stub was stored.
     *
     * @param location $location Slide image file location.
     * @return void
     */
    public static function abandon(location $location): void {
        global $CFG, $DB;

        self::delete_file($location);
        if ($location->itemid <= 0 || $location->component !== 'mod_slideshow' || $location->filearea !== 'image') {
            return;
        }
        $lib = $CFG->dirroot . '/mod/slideshow/locallib.php';
        if (!is_readable($lib)) {
            return;
        }
        require_once($lib);
        $DB->set_field(
            'slideshow_slide',
            'layout',
            MOD_SLIDESHOW_LAYOUT_BACKGROUND,
            ['id' => $location->itemid]
        );
    }

    /**
     * Parse the first new-generation shortcode.
     *
     * @param string $value Featured image field from the API.
     * @return array<string, mixed>|null
     */
    private static function parse(string $value): ?array {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $parsed = shortcode_parser::find_all($value);
        if ($parsed === [] || !shortcode_parser::is_new_generation($parsed[0])) {
            return null;
        }

        return $parsed[0];
    }

    /**
     * Delete the file at a location when one is stored.
     *
     * @param location $location File location.
     * @return void
     */
    private static function delete_file(location $location): void {
        $stored = $location->get_stored_file();
        if ($stored) {
            $stored->delete();
        }
    }
}
