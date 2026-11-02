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
 * Tests for the .h5p package builder.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

use coding_exception;
use local_dixeo\service\h5p_library_service;
use local_dixeo\service\h5p_packaging_service;
use ZipArchive;

/**
 * Unit tests for h5p packaging service.
 *
 * @covers \local_dixeo\service\h5p_packaging_service::build_package
 */
final class h5p_packaging_service_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        h5p_library_service::reset_cache();
    }

    public function test_build_package_writes_h5p_json_with_main_library(): void {
        $this->seed_library('H5P.QuestionSet', 1, 20);
        $service = new h5p_packaging_service();

        $path = $service->build_package(
            'H5P.QuestionSet 1.20',
            ['questions' => []],
            'Sample quiz',
            'fr'
        );

        $this->assertFileExists($path);

        $h5pjson = $this->read_zip_entry($path, 'h5p.json');
        $decoded = json_decode($h5pjson, true);

        $this->assertSame('Sample quiz', $decoded['title']);
        $this->assertSame('fr', $decoded['language']);
        $this->assertSame('fr', $decoded['defaultLanguage']);
        $this->assertSame('H5P.QuestionSet', $decoded['mainLibrary']);
        $this->assertSame(
            [['machineName' => 'H5P.QuestionSet', 'majorVersion' => 1, 'minorVersion' => 20]],
            $decoded['preloadedDependencies']
        );
    }

    public function test_build_package_writes_installed_minor_not_pinned_minor(): void {
        // Site has a newer minor than the API's pinned version — the package must reference
        // the installed one, otherwise Moodle's H5P import rejects the package.
        $this->seed_library('H5P.QuestionSet', 1, 22);
        $service = new h5p_packaging_service();

        $path = $service->build_package('H5P.QuestionSet 1.20', ['questions' => []]);

        $decoded = json_decode($this->read_zip_entry($path, 'h5p.json'), true);
        $this->assertSame(
            [['machineName' => 'H5P.QuestionSet', 'majorVersion' => 1, 'minorVersion' => 22]],
            $decoded['preloadedDependencies'],
        );
    }

    public function test_build_package_writes_content_json_verbatim(): void {
        $this->seed_library('H5P.QuestionSet', 1, 20);
        $service = new h5p_packaging_service();
        $content = ['questions' => [['title' => 'Q1', 'type' => 'multichoice']]];

        $path = $service->build_package('H5P.QuestionSet 1.20', $content);

        $contentjson = $this->read_zip_entry($path, 'content/content.json');
        $this->assertSame($content, json_decode($contentjson, true));
    }

    public function test_build_package_falls_back_to_machine_name_when_title_empty(): void {
        $this->seed_library('H5P.Flashcards', 1, 7);
        $service = new h5p_packaging_service();

        $path = $service->build_package('H5P.Flashcards 1.7', ['cards' => []]);

        $h5pjson = $this->read_zip_entry($path, 'h5p.json');
        $decoded = json_decode($h5pjson, true);

        $this->assertSame('H5P.Flashcards', $decoded['title']);
    }

    public function test_build_package_rejects_malformed_library_identifier(): void {
        $service = new h5p_packaging_service();

        $this->expectException(coding_exception::class);

        $service->build_package('not a valid identifier', []);
    }

    public function test_build_package_rejects_when_library_not_installed(): void {
        $service = new h5p_packaging_service();

        $this->expectException(coding_exception::class);

        $service->build_package('H5P.Flashcards 1.7', ['cards' => []]);
    }

    /**
     * Insert a fake installed H5P library row for packaging tests.
     *
     * @param string $machinename
     * @param int $major
     * @param int $minor
     */
    private function seed_library(string $machinename, int $major, int $minor): void {
        global $DB;

        $DB->insert_record('h5p_libraries', (object) [
            'machinename' => $machinename,
            'majorversion' => $major,
            'minorversion' => $minor,
            'patchversion' => 0,
            'runnable' => 1,
            'enabled' => 1,
            'fullscreen' => 0,
            'embedtypes' => '',
            'preloadedjs' => '',
            'preloadedcss' => '',
            'droplibrarycss' => '',
            'semantics' => '[]',
            'addto' => '',
            'coremajor' => null,
            'coreminor' => null,
            'metadatasettings' => '',
            'tutorial' => '',
            'example' => '',
            'title' => $machinename,
        ]);
    }

    /**
     * Read a single entry from a zip archive on disk.
     *
     * @param string $path Path to the .h5p (zip) file.
     * @param string $entry The entry name to read.
     * @return string The entry contents.
     */
    private function read_zip_entry(string $path, string $entry): string {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true, "failed to open zip {$path}");
        $contents = $zip->getFromName($entry);
        $zip->close();

        $this->assertNotFalse($contents, "missing zip entry {$entry}");
        return $contents;
    }
}
