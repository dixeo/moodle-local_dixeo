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

namespace local_dixeo\service;

/**
 * Tests for the course structure sanitiser.
 *
 * @package    local_dixeo
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo\service\course_structure_sanitiser
 */
final class course_structure_sanitiser_test extends \advanced_testcase {
    /**
     * Identifier shapes that are accepted and rejected.
     */
    public function test_is_valid_module_type(): void {
        $this->assertTrue(course_structure_sanitiser::is_valid_module_type('page'));
        $this->assertTrue(course_structure_sanitiser::is_valid_module_type('h5p_flashcards'));
        $this->assertTrue(course_structure_sanitiser::is_valid_module_type('simplequiz2'));
        $this->assertTrue(course_structure_sanitiser::is_valid_module_type('Quiz'));
        $this->assertFalse(course_structure_sanitiser::is_valid_module_type(''));
        $this->assertFalse(course_structure_sanitiser::is_valid_module_type("x'),url(//e/x"));
        $this->assertFalse(course_structure_sanitiser::is_valid_module_type('page;color:red'));
        $this->assertFalse(course_structure_sanitiser::is_valid_module_type('2page'));
        $this->assertFalse(course_structure_sanitiser::is_valid_module_type("page\n"));
        $this->assertFalse(course_structure_sanitiser::is_valid_module_type(str_repeat('a', 51)));
        $this->assertFalse(course_structure_sanitiser::is_valid_module_type(['page']));
        $this->assertFalse(course_structure_sanitiser::is_valid_module_type(null));
    }

    /**
     * One pass empties malformed types and drops foreign images, at both levels.
     */
    public function test_sanitise_wrapped_structure(): void {
        $calls = [];
        $allowed = function (string $url) use (&$calls): bool {
            $calls[] = $url;
            return $url === 'https://site/owned.png';
        };

        $result = course_structure_sanitiser::sanitise([
            'image' => 'https://evil/x.png',
            'course_structure' => [
                'title' => 'Rivers',
                'image' => 'https://site/owned.png',
                'sections' => [
                    [
                        'title' => 'One',
                        'modules' => [
                            ['type' => "x'),url(//e/x", 'title' => 'Bad'],
                            ['type' => 'page', 'title' => 'Good'],
                            'not a module',
                            ['title' => 'No type'],
                        ],
                    ],
                    'not a section',
                ],
            ],
        ], $allowed);

        $this->assertArrayNotHasKey('image', $result);
        $this->assertSame('https://site/owned.png', $result['course_structure']['image']);
        $modules = $result['course_structure']['sections'][0]['modules'];
        $this->assertSame('', $modules[0]['type']);
        $this->assertSame('Bad', $modules[0]['title']);
        $this->assertSame('page', $modules[1]['type']);
        $this->assertSame('not a module', $modules[2]);
        $this->assertArrayNotHasKey('type', $modules[3]);
        $this->assertSame('not a section', $result['course_structure']['sections'][1]);
        $this->assertSame(['https://evil/x.png', 'https://site/owned.png'], $calls);
    }

    /**
     * A bare structure is sanitised, non-string images are dropped and empty images are kept.
     */
    public function test_sanitise_bare_structure(): void {
        $never = static fn(string $url): bool => false;

        $result = course_structure_sanitiser::sanitise([
            'sections' => [['modules' => [['type' => 'url(evil)'], ['type' => 'quiz']]]],
        ], $never);
        $this->assertSame('', $result['sections'][0]['modules'][0]['type']);
        $this->assertSame('quiz', $result['sections'][0]['modules'][1]['type']);

        $this->assertArrayNotHasKey('image', course_structure_sanitiser::sanitise(['image' => ['x']], $never));
        $this->assertSame('', course_structure_sanitiser::sanitise(['image' => ''], $never)['image']);
        $this->assertNull(course_structure_sanitiser::sanitise(['image' => null], $never)['image']);
    }
}
