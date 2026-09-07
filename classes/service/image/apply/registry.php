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


use local_dixeo\service\image\content\apply_handler;
use local_dixeo\service\image\content_target;
use local_dixeo\service\image\image_target;
use local_dixeo\service\image\structure\structure_target;

/**
 * Routes completed poll results to content or structure apply handlers.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class registry {
    /** @var string Modal apply handler provided by the optional image editor filter. */
    private const MODAL_HANDLER_CLASS = '\\filter_dixeo_imageeditor\\adapter\\modal_apply_handler';

    /** @var apply_handler|null Explicitly injected modal handler (tests). */
    private static ?apply_handler $modalhandler = null;

    /**
     * Inject a modal handler, overriding the autodetected one (mainly for tests).
     *
     * @param apply_handler|null $handler
     * @return void
     */
    public static function set_modal_handler(?apply_handler $handler): void {
        self::$modalhandler = $handler;
    }

    /**
     * Resolve the modal apply handler (version history path) if the filter is installed.
     *
     * Resolved on demand so it works in any context (web, cron, CLI) without
     * relying on the filter's lib.php having been included.
     *
     * @return apply_handler|null
     */
    private static function get_modal_handler(): ?apply_handler {
        if (self::$modalhandler !== null) {
            return self::$modalhandler;
        }

        $classname = self::MODAL_HANDLER_CLASS;
        if (class_exists($classname)) {
            return new $classname();
        }

        return null;
    }

    /**
     * Apply.
     * @param image_target $target
     * @param array $result
     * @param int $userid
     * @param string $source
     * @param \stdClass|null $jobrow
     * @return void
     */
    public static function apply(
        image_target $target,
        array $result,
        int $userid,
        string $source = 'generated',
        ?\stdClass $jobrow = null
    ): void {
        if ($target instanceof structure_target) {
            structure_handler::apply($target, $result, $userid);
            return;
        }

        if ($target instanceof content_target) {
            content_handler::apply($target, $result, $userid, $source, $jobrow, self::get_modal_handler());
        }
    }
}
