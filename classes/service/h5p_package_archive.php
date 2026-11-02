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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Reads and writes entries of a .h5p archive on disk.
 *
 * Shared by the package builder and the flashcard image handler, which adds a
 * card image to an existing package: both write the same archive layout.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service;

use coding_exception;
use ZipArchive;

/**
 * Entry-level access to a .h5p (zip) file.
 */
final class h5p_package_archive {
    /** @var string Archive path of the content parameters. */
    public const CONTENT_JSON = 'content/content.json';

    /** @var int json_encode flags for the JSON files of a package. */
    public const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /**
     * Write entries into the archive, creating it when missing and replacing entries with the same name.
     *
     * @param string $path Absolute path of the .h5p file.
     * @param string[] $entries Bytes keyed by archive path.
     * @return void
     * @throws coding_exception If the archive cannot be written.
     */
    public static function write(string $path, array $entries): void {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE) !== true) {
            throw new coding_exception("Failed to open .h5p archive for writing: {$path}");
        }
        foreach ($entries as $name => $bytes) {
            $zip->addFromString($name, $bytes);
        }
        if (!$zip->close()) {
            throw new coding_exception("Failed to write .h5p archive: {$path}");
        }
    }

    /**
     * Read one entry of the archive.
     *
     * @param string $path Absolute path of the .h5p file.
     * @param string $name Archive path of the entry.
     * @return string|null Entry bytes, or null when the archive or the entry is missing.
     */
    public static function read(string $path, string $name): ?string {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return null;
        }
        $bytes = $zip->getFromName($name);
        $zip->close();

        return $bytes === false ? null : $bytes;
    }
}
