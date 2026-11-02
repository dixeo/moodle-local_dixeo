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
 * Tests for the .h5p archive entry reader and writer.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service;

/**
 * Unit tests for h5p_package_archive.
 *
 * @covers \local_dixeo\service\h5p_package_archive
 */
final class h5p_package_archive_test extends \advanced_testcase {
    /**
     * Writing creates the archive with the given entries.
     */
    public function test_write_creates_the_archive(): void {
        $path = make_request_directory() . '/package.h5p';

        h5p_package_archive::write($path, ['h5p.json' => '{}', h5p_package_archive::CONTENT_JSON => '{"cards":[]}']);

        $this->assertSame('{}', h5p_package_archive::read($path, 'h5p.json'));
        $this->assertSame('{"cards":[]}', h5p_package_archive::read($path, h5p_package_archive::CONTENT_JSON));
    }

    /**
     * Writing to an existing archive replaces matching entries and keeps the others.
     */
    public function test_write_updates_an_existing_archive(): void {
        $path = make_request_directory() . '/package.h5p';
        h5p_package_archive::write($path, ['h5p.json' => '{}', h5p_package_archive::CONTENT_JSON => 'old']);

        h5p_package_archive::write($path, [
            h5p_package_archive::CONTENT_JSON => 'new',
            'content/images/card-0.png' => 'png-bytes',
        ]);

        $this->assertSame('{}', h5p_package_archive::read($path, 'h5p.json'));
        $this->assertSame('new', h5p_package_archive::read($path, h5p_package_archive::CONTENT_JSON));
        $this->assertSame('png-bytes', h5p_package_archive::read($path, 'content/images/card-0.png'));
    }

    /**
     * Reading a missing entry or archive returns null.
     */
    public function test_read_returns_null_when_missing(): void {
        $path = make_request_directory() . '/package.h5p';
        $this->assertNull(h5p_package_archive::read($path, 'h5p.json'));

        h5p_package_archive::write($path, ['h5p.json' => '{}']);
        $this->assertNull(h5p_package_archive::read($path, h5p_package_archive::CONTENT_JSON));
    }
}
