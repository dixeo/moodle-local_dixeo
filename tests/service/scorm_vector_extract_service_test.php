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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests for SCORM vector text extraction.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo;

/**
 * Unit tests for scorm vector extract service.
 *
 * @covers \local_dixeo\service\scorm_vector_extract_service
 */
final class scorm_vector_extract_service_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    public function test_extract_sco_text_from_minimal_zip(): void {
        global $CFG;

        $manifest = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<manifest xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2"
    xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2"
    identifier="M">
  <organizations default="O1">
    <organization identifier="O1">
      <item identifier="I1" identifierref="R1"><title>T</title></item>
    </organization>
  </organizations>
  <resources>
    <resource identifier="R1" type="webcontent" adlcp:scormtype="sco" href="sco1.html"/>
  </resources>
</manifest>
XML;

        $html = '<html><body><p>Hello SCORM</p></body></html>';

        $path = $CFG->tempdir . '/dixeo_test_scorm_' . uniqid('', true) . '.zip';
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString('imsmanifest.xml', $manifest);
        $zip->addFromString('sco1.html', $html);
        $zip->close();

        $service = new \local_dixeo\service\scorm_vector_extract_service();
        $text = $service->extract_sco_text_from_zip_path($path);
        @unlink($path);

        $this->assertStringContainsString('Hello SCORM', $text);
    }

    public function test_extract_skips_non_sco_resources(): void {
        global $CFG;

        $manifest = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<manifest xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2"
    xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2"
    identifier="M">
  <resources>
    <resource identifier="R1" type="webcontent" adlcp:scormtype="asset" href="a.html"/>
  </resources>
</manifest>
XML;

        $path = $CFG->tempdir . '/dixeo_test_scorm_asset_' . uniqid('', true) . '.zip';
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString('imsmanifest.xml', $manifest);
        $zip->addFromString('a.html', '<html><body>Asset</body></html>');
        $zip->close();

        $service = new \local_dixeo\service\scorm_vector_extract_service();
        $text = $service->extract_sco_text_from_zip_path($path);
        @unlink($path);

        $this->assertSame('', $text);
    }

    public function test_get_package_title_from_manifest_organization(): void {
        global $CFG;

        $manifest = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<manifest xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2" identifier="M">
  <organizations default="O1">
    <organization identifier="O1">
      <title>My Storyline Course</title>
      <item identifier="I1" identifierref="R1"><title>Item</title></item>
    </organization>
  </organizations>
  <resources>
    <resource identifier="R1" type="webcontent" href="index.html"/>
  </resources>
</manifest>
XML;

        $path = $CFG->tempdir . '/dixeo_test_scorm_title_' . uniqid('', true) . '.zip';
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString('imsmanifest.xml', $manifest);
        $zip->close();

        $service = new \local_dixeo\service\scorm_vector_extract_service();
        $title = $service->get_package_title_from_zip_path($path, 'fallback.zip');
        @unlink($path);

        $this->assertSame('My Storyline Course', $title);
    }

    public function test_get_package_title_falls_back_to_filename(): void {
        global $CFG;

        $path = $CFG->tempdir . '/dixeo_test_scorm_fallback_' . uniqid('', true) . '.zip';
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString('readme.txt', 'not a scorm package');
        $zip->close();

        $service = new \local_dixeo\service\scorm_vector_extract_service();
        $title = $service->get_package_title_from_zip_path($path, 'My Upload Package.zip');
        @unlink($path);

        $this->assertSame('My Upload Package', $title);
    }

    public function test_is_storyline_extractable_requires_slide_text(): void {
        global $CFG;

        $slidejs = "window.globalProvideData('slide', '{\"id\":\"abcde12345\",\"title\":\"Intro\",\"text\":\"Hello Storyline\"}');";

        $path = $CFG->tempdir . '/dixeo_test_storyline_ok_' . uniqid('', true) . '.zip';
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString('html5/data/js/data.js', '// marker');
        $zip->addFromString('html5/data/js/abcde12345.js', $slidejs);
        $zip->close();

        $service = new \local_dixeo\service\scorm_vector_extract_service();
        $this->assertTrue($service->is_storyline_extractable($path));
        @unlink($path);
    }

    public function test_is_storyline_extractable_rejects_marker_only_zip(): void {
        global $CFG;

        $path = $CFG->tempdir . '/dixeo_test_storyline_empty_' . uniqid('', true) . '.zip';
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString('html5/data/js/data.js', '// marker only');
        $zip->close();

        $service = new \local_dixeo\service\scorm_vector_extract_service();
        $this->assertFalse($service->is_storyline_extractable($path));
        @unlink($path);
    }

    /**
     * A small zip whose slide entry declares an uncompressed size above the cap is not read.
     */
    public function test_extract_refuses_zip_entry_with_oversized_declared_length(): void {
        global $CFG;

        $slide = "window.globalProvideData('slide', '{\"id\":\"abcde12345\",\"title\":\"Intro\",\"text\":\"Hello Storyline\"}');";
        $path = $CFG->tempdir . '/dixeo_test_scorm_bomb_' . uniqid('', true) . '.zip';
        $this->write_stored_zip($path, [
            ['name' => 'html5/data/js/data.js', 'data' => '// marker', 'size' => null],
            ['name' => 'html5/data/js/abcde12345.js', 'data' => $slide, 'size' => 16 * 1024 * 1024],
        ]);

        $this->assertLessThan(4096, filesize($path));

        $probe = new \ZipArchive();
        $this->assertTrue($probe->open($path));
        $stat = $probe->statName('html5/data/js/abcde12345.js');
        $probe->close();
        $this->assertIsArray($stat);
        $this->assertGreaterThan(8 * 1024 * 1024, (int) $stat['size']);
        $this->assertLessThan(4096, (int) $stat['comp_size']);

        $service = new \local_dixeo\service\scorm_vector_extract_service();
        $text = $service->extract_sco_text_from_zip_path($path);
        $this->assertDebuggingCalled();
        $this->assertSame('', $text);

        $this->assertFalse($service->is_storyline_extractable($path));
        $this->assertDebuggingCalled();
        @unlink($path);
    }

    /**
     * Write a stored-method zip, optionally lying about each entry's uncompressed size.
     *
     * @param string $path Destination path.
     * @param array $entries Zip entries with name, data, and optional size.
     */
    private function write_stored_zip(string $path, array $entries): void {
        $locals = '';
        $central = '';
        $offset = 0;
        foreach ($entries as $entry) {
            $name = $entry['name'];
            $data = $entry['data'];
            $size = $entry['size'] ?? strlen($data);
            $crc = crc32($data) & 0xffffffff;
            $namelen = strlen($name);
            $local = pack(
                'VvvvvvVVVvv',
                0x04034b50,
                20,
                0,
                0,
                0,
                0,
                $crc,
                strlen($data),
                $size,
                $namelen,
                0
            ) . $name . $data;
            $central .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                0,
                0,
                0,
                0,
                $crc,
                strlen($data),
                $size,
                $namelen,
                0,
                0,
                0,
                0,
                0,
                $offset
            ) . $name;
            $locals .= $local;
            $offset += strlen($local);
        }
        $eocd = pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            count($entries),
            count($entries),
            strlen($central),
            strlen($locals),
            0
        );
        $this->assertNotFalse(file_put_contents($path, $locals . $central . $eocd));
    }
}
