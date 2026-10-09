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

namespace local_dixeo\settings;

/**
 * Parent chain for a Dixeo or course setting.
 *
 * The course chain is site, then Dixeo, then course. Activity is not a parent of course.
 * A value equal to its parent is not an override.
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class resolver {
    /** @var string Shipped default layer. Always present. */
    public const LAYER_SITE = 'site';

    /** @var string Site-wide Dixeo value stored in plugin config. */
    public const LAYER_DIXEO = 'dixeo';

    /** @var string Course override stored for the course context. */
    public const LAYER_COURSE = 'course';

    /**
     * Layers from the site default through $level.
     *
     * @param string $level {@see self::LAYER_DIXEO} or {@see self::LAYER_COURSE}.
     * @return string[]
     */
    public static function chain(string $level): array {
        if ($level === self::LAYER_DIXEO) {
            return [self::LAYER_SITE, self::LAYER_DIXEO];
        }
        if ($level === self::LAYER_COURSE) {
            return [self::LAYER_SITE, self::LAYER_DIXEO, self::LAYER_COURSE];
        }
        throw new \coding_exception("Dixeo settings have no chain for level \"{$level}\".");
    }

    /**
     * Effective value for $level and the layer that supplied it.
     *
     * @param string $level Level being read.
     * @param array<string, string|null> $stored Own value per layer. Null means this layer has no stored value.
     * @return array{value: string, source: string}
     */
    public static function effective(string $level, array $stored): array {
        $source = self::LAYER_SITE;
        $value = (string) ($stored[self::LAYER_SITE] ?? '');
        foreach (self::chain($level) as $layer) {
            if ($layer === self::LAYER_SITE) {
                continue;
            }
            if (array_key_exists($layer, $stored) && $stored[$layer] !== null) {
                $source = $layer;
                $value = (string) $stored[$layer];
            }
        }
        return ['value' => $value, 'source' => $source];
    }

    /**
     * True when $value differs from the parent effective value and must be stored.
     *
     * @param string $level Level being written.
     * @param string $value Cleaned value to store.
     * @param array<string, string|null> $stored Own values of the parent layers. The current level is ignored.
     * @return bool
     */
    public static function stores_override(string $level, string $value, array $stored): bool {
        if ($level === self::LAYER_DIXEO) {
            $parent = (string) ($stored[self::LAYER_SITE] ?? '');
            return $value !== $parent;
        }
        if ($level === self::LAYER_COURSE) {
            $parent = self::effective(self::LAYER_DIXEO, $stored);
            return $value !== $parent['value'];
        }
        throw new \coding_exception("Dixeo settings have no chain for level \"{$level}\".");
    }
}
