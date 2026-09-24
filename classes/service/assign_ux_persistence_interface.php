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
 * Persistence contract for assign UX authorship records.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\service;

/**
 * CRUD for authorship confidence / quiz persistence.
 */
interface assign_ux_persistence_interface {
    /**
     * Replace any existing row for (userid, component, area, itemid), then insert.
     *
     * @param int $userid Student user id.
     * @param string $component Component name (e.g. mod_assign).
     * @param string $area Area name (e.g. submission).
     * @param int $itemid Item id (e.g. assign_submission id).
     * @param float $initialconfidence Initial human-authorship confidence 0–100.
     * @param array|null $quizdata Decoded quiz structure to store, or null.
     * @return \stdClass|null Record including id, or null on failure.
     */
    public function create_authorship_record(
        int $userid,
        string $component,
        string $area,
        int $itemid,
        float $initialconfidence,
        ?array $quizdata = null
    ): ?\stdClass;

    /**
     * Load an authorship record by primary key.
     *
     * @param int $recordid Record id.
     * @return \stdClass|null
     */
    public function get_authorship_record(int $recordid): ?\stdClass;

    /**
     * Load an authorship record by student + component context.
     *
     * @param int $userid Student user id.
     * @param string $component Component name.
     * @param string $area Area name.
     * @param int $itemid Item id.
     * @return \stdClass|null
     */
    public function get_authorship_by_context(
        int $userid,
        string $component,
        string $area,
        int $itemid
    ): ?\stdClass;

    /**
     * Persist quiz answers and evaluation results.
     *
     * @param int $recordid Record id.
     * @param array $responses Question id => answer.
     * @param float $finalconfidence Final confidence 0–100.
     * @param float|null $quizgrade Objective quiz grade 0–100, or null.
     * @return \stdClass|null Updated record, or null if missing / update failed.
     */
    public function save_quiz_result(
        int $recordid,
        array $responses,
        float $finalconfidence,
        ?float $quizgrade
    ): ?\stdClass;
}
