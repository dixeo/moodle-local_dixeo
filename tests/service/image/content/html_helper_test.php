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


/**
 * Tests for html_helper_test.
 * @covers \local_dixeo\service\image\content\html_helper
 * @package local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class html_helper_test extends \advanced_testcase {
    /**
     * Test swap removes pending when class precedes data attribute.
     */
    public function test_swap_removes_pending_when_class_precedes_data_attribute(): void {
        $id = 'abc-123';
        $html = '<img src="http://x/y.png" class="img-fluid dixeo-img-gen-pending" data-dixeo-img-gen="' . $id . '" alt="" />';
        $updated = html_helper::swap_img_class_for_placeholder($html, $id, 'dixeo-img-gen-pending', '');
        $this->assertStringNotContainsString('dixeo-img-gen-pending', $updated);
        $this->assertStringContainsString('data-dixeo-img-gen="' . $id . '"', $updated);
        $this->assertStringContainsString('class="img-fluid"', $updated);
    }

    /**
     * Test normalize legacy intro pluginfile urls.
     */
    public function test_normalize_legacy_intro_pluginfile_urls(): void {
        $broken = '<img src="http://dixeo.local/pluginfile.php/409/mod_label/intro/0/dixeo-gen-x.png" alt="" />';
        $fixed = html_helper::normalize_legacy_intro_pluginfile_urls($broken);
        $this->assertStringContainsString('@@PLUGINFILE@@/dixeo-gen-x.png', $fixed);
        $this->assertStringNotContainsString('/intro/0/', $fixed);
    }
}
