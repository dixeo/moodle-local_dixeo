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

use local_dixeo\repository\tutor_usage_rollup_repository;
use local_dixeo\util\site_calendar;

/**
 * Additive tutor usage totals of one scope and period, per user, per day and per entity.
 *
 * Built days are read from the daily totals with set-based GROUP BY queries. Every other
 * day of the period up to now (today, or a day not built yet) is computed from the events
 * with the same per-day SQL the builder uses, so both sources give identical figures.
 * The period covers whole site days, from the day of $timestart to the day of $timeend.
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tutor_usage_dataset {
    /** @var int Maximum days read live by one statement. */
    public const LIVE_CHUNK_DAYS = 31;

    /** @var string[] Additive count fields of a user partial. */
    public const COUNT_FIELDS = [
        'messages', 'msgnormal', 'msgguide', 'msgquiz', 'msgteach', 'quizcreated', 'lessoncreated',
    ];

    /** @var string[] Additive count fields of a day partial. */
    private const DAY_FIELDS = ['msgnormal', 'msgguide', 'msgquiz', 'msgteach', 'quizcreated', 'lessoncreated'];

    /** @var tutor_usage_scope Report scope. */
    private tutor_usage_scope $scope;

    /** @var int Period start. */
    private int $timestart;

    /** @var int Period end. */
    private int $timeend;

    /** @var tutor_usage_rollup_repository Daily totals repository. */
    private tutor_usage_rollup_repository $rollup;

    /** @var int[]|null Built days of the period, read once. */
    private ?array $covered = null;

    /** @var array|null Live partials: users, user days, days and entities. */
    private ?array $live = null;

    /** @var array|null Memoised per_user() result. */
    private ?array $peruser = null;

    /** @var array|null Memoised per_day() result. */
    private ?array $perday = null;

    /** @var array|null Memoised per_entity() result. */
    private ?array $perentity = null;

    /**
     * Constructor.
     *
     * @param tutor_usage_scope $scope Report scope.
     * @param int $timestart Period start.
     * @param int $timeend Period end.
     * @param tutor_usage_rollup_repository|null $rollup Daily totals repository.
     */
    public function __construct(
        tutor_usage_scope $scope,
        int $timestart,
        int $timeend,
        ?tutor_usage_rollup_repository $rollup = null
    ) {
        $this->scope = $scope;
        $this->timestart = $timestart;
        $this->timeend = $timeend;
        $this->rollup = $rollup ?? new tutor_usage_rollup_repository();
    }

    /**
     * Totals per user.
     *
     * @return array<int, array<string, int>> User id to messages, msgnormal, msgguide, msgquiz, msgteach,
     *     quizcreated, lessoncreated, activedays and lastactive.
     */
    public function per_user(): array {
        if ($this->peruser !== null) {
            return $this->peruser;
        }

        $peruser = [];
        $covered = $this->covered_days();
        if ($covered !== []) {
            [$daysql, $params] = $this->covered_condition($covered);
            $records = $this->recordset(
                "SELECT d.userid, " . $this->sum_columns() . ",
                        MAX(d.lastactive) AS lastactive, COUNT(DISTINCT d.daystart) AS activedays
                   FROM {" . tutor_usage_rollup_repository::TABLE_DAILY . "} d
                  WHERE {$daysql}
               GROUP BY d.userid",
                $params
            );
            foreach ($records as $record) {
                $this->add_partial($peruser[(int) $record->userid], $record, (int) $record->activedays);
            }
            $records->close();
        }

        $live = $this->live();
        foreach ($live['users'] as $userid => $partial) {
            $this->merge_partial($peruser[$userid], $partial);
        }

        $this->peruser = $peruser;
        return $peruser;
    }

    /**
     * Totals per site day.
     *
     * @return array<int, array> Day start to msgnormal, msgguide, msgquiz, msgteach, quizcreated,
     *     lessoncreated and slots (eight message counts).
     */
    public function per_day(): array {
        if ($this->perday !== null) {
            return $this->perday;
        }

        $perday = [];
        $covered = $this->covered_days();
        if ($covered !== []) {
            [$daysql, $params] = $this->covered_condition($covered);
            $columns = [];
            foreach (self::DAY_FIELDS as $field) {
                $columns[] = "SUM(d.{$field}) AS {$field}";
            }
            for ($k = 0; $k < site_calendar::SLOTS; $k++) {
                $columns[] = "SUM(d.slot{$k}) AS slot{$k}";
            }
            $records = $this->recordset(
                "SELECT d.daystart, " . implode(', ', $columns) . "
                   FROM {" . tutor_usage_rollup_repository::TABLE_DAILY . "} d
                  WHERE {$daysql}
               GROUP BY d.daystart",
                $params
            );
            foreach ($records as $record) {
                $this->add_day($perday[(int) $record->daystart], $record);
            }
            $records->close();
        }

        foreach ($this->live()['days'] as $daystart => $partial) {
            if (!isset($perday[$daystart])) {
                $perday[$daystart] = $partial;
                continue;
            }
            foreach (self::DAY_FIELDS as $field) {
                $perday[$daystart][$field] += $partial[$field];
            }
            for ($k = 0; $k < site_calendar::SLOTS; $k++) {
                $perday[$daystart]['slots'][$k] += $partial['slots'][$k];
            }
        }

        ksort($perday);
        $this->perday = $perday;
        return $perday;
    }

    /**
     * Totals per report entity.
     *
     * At site level: course id to user id to a user partial plus incoursescope (whether the user
     * is in scope in that course). At course and user levels: user id to a user partial.
     *
     * @return array
     */
    public function per_entity(): array {
        if ($this->perentity !== null) {
            return $this->perentity;
        }
        if ($this->scope->get_level() !== tutor_usage_report_service::LEVEL_SITE) {
            $this->perentity = $this->per_user();
            return $this->perentity;
        }

        $perentity = [];
        $covered = $this->covered_days();
        if ($covered !== []) {
            [$daysql, $params] = $this->covered_condition($covered);
            [$pairsql, $pairparams] = $this->scope->pair_in_scope('g');
            $records = $this->recordset(
                "SELECT g.*, CASE WHEN {$pairsql} THEN 1 ELSE 0 END AS incoursescope
                   FROM (SELECT d.courseid, d.userid, " . $this->sum_columns() . ",
                                MAX(d.lastactive) AS lastactive, COUNT(1) AS activedays
                           FROM {" . tutor_usage_rollup_repository::TABLE_DAILY . "} d
                          WHERE {$daysql}
                       GROUP BY d.courseid, d.userid) g",
                $params + $pairparams
            );
            foreach ($records as $record) {
                $entry = &$perentity[(int) $record->courseid][(int) $record->userid];
                $this->add_partial($entry, $record, (int) $record->activedays);
                $entry['incoursescope'] = !empty($record->incoursescope);
                unset($entry);
            }
            $records->close();
        }

        foreach ($this->live()['entities'] as $courseid => $users) {
            foreach ($users as $userid => $partial) {
                $entry = &$perentity[$courseid][$userid];
                $incoursescope = !empty($entry['incoursescope']) || $partial['incoursescope'];
                $this->merge_partial($entry, $partial);
                $entry['incoursescope'] = $incoursescope;
                unset($entry);
            }
        }

        $this->perentity = $perentity;
        return $perentity;
    }

    /**
     * Built days of the period, read once per dataset.
     *
     * @return int[]
     */
    private function covered_days(): array {
        if ($this->covered === null) {
            $this->covered = $this->rollup->covered_days(
                site_calendar::day_start($this->timestart),
                site_calendar::day_start($this->timeend)
            );
        }
        return $this->covered;
    }

    /**
     * Partials of the days that are not built, computed from the events in one pass.
     *
     * @return array{users: array, days: array, entities: array}
     */
    private function live(): array {
        if ($this->live !== null) {
            return $this->live;
        }

        $now = \core\di::get(\core\clock::class)->time();
        $covered = array_flip($this->covered_days());
        $days = [];
        foreach (site_calendar::days($this->timestart, $this->timeend) as $daystart) {
            if ($daystart <= $now && !isset($covered[$daystart])) {
                $days[] = $daystart;
            }
        }

        $issite = $this->scope->get_level() === tutor_usage_report_service::LEVEL_SITE;
        $users = [];
        $userdays = [];
        $perday = [];
        $entities = [];
        $until = min($now, $this->timeend);
        foreach (array_chunk($days, self::LIVE_CHUNK_DAYS) as $chunk) {
            $branches = [];
            $params = [];
            foreach ($chunk as $daystart) {
                [$branchsql, $branchparams] = $this->rollup->day_rows_sql($daystart, $until);
                $branches[] = $branchsql;
                $params += $branchparams;
            }
            [$wheresql, $whereparams] = $this->scope->where('x');
            $select = 'x.*';
            if ($issite) {
                [$pairsql, $pairparams] = $this->scope->pair_in_scope('x');
                $select .= ", CASE WHEN {$pairsql} THEN 1 ELSE 0 END AS incoursescope";
                $params += $pairparams;
            }
            $records = $this->recordset(
                "SELECT {$select} FROM (" . implode(' UNION ALL ', $branches) . ") x WHERE {$wheresql}",
                $params + $whereparams
            );
            foreach ($records as $record) {
                $daystart = (int) $record->daystart;
                $userid = (int) $record->userid;
                $this->add_partial($users[$userid], $record, 0);
                $userdays[$userid][$daystart] = true;
                $this->add_day($perday[$daystart], $record);
                if ($issite) {
                    $entry = &$entities[(int) $record->courseid][$userid];
                    $this->add_partial($entry, $record, 1);
                    $entry['incoursescope'] = !empty($entry['incoursescope']) || !empty($record->incoursescope);
                    unset($entry);
                }
            }
            $records->close();
        }

        foreach ($userdays as $userid => $daysofuser) {
            $users[$userid]['activedays'] = count($daysofuser);
        }

        $this->live = [
            'users' => $users,
            'days' => $perday,
            'entities' => $entities,
        ];
        return $this->live;
    }

    /**
     * Condition on the built days of the period and the scope, for the daily table aliased d.
     *
     * @param int[] $covered Built day starts.
     * @return array{0: string, 1: array}
     */
    private function covered_condition(array $covered): array {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal($covered, SQL_PARAMS_NAMED, 'tudday');
        [$wheresql, $whereparams] = $this->scope->where('d');
        return ["d.daystart {$insql} AND {$wheresql}", $params + $whereparams];
    }

    /**
     * SUM() columns of the additive count fields over the daily table aliased d.
     *
     * @return string
     */
    private function sum_columns(): string {
        $columns = [];
        foreach (self::COUNT_FIELDS as $field) {
            $columns[] = "SUM(d.{$field}) AS {$field}";
        }
        return implode(', ', $columns);
    }

    /**
     * Run a query as a recordset.
     *
     * @param string $sql SQL.
     * @param array $params Parameters.
     * @return \moodle_recordset
     */
    private function recordset(string $sql, array $params): \moodle_recordset {
        global $DB;

        return $DB->get_recordset_sql($sql, $params);
    }

    /**
     * Add the counts of a result row to a user partial.
     *
     * @param array|null $partial Partial (by reference; created when null).
     * @param \stdClass $record Row with the count fields and lastactive.
     * @param int $activedays Active days to add.
     */
    private function add_partial(?array &$partial, \stdClass $record, int $activedays): void {
        if ($partial === null) {
            $partial = self::empty_partial();
        }
        foreach (self::COUNT_FIELDS as $field) {
            $partial[$field] += (int) $record->{$field};
        }
        $partial['activedays'] += $activedays;
        $partial['lastactive'] = max($partial['lastactive'], (int) $record->lastactive);
    }

    /**
     * Merge one user partial into another.
     *
     * @param array|null $target Target partial (by reference; created when null).
     * @param array $source Source partial.
     */
    private function merge_partial(?array &$target, array $source): void {
        if ($target === null) {
            $target = self::empty_partial();
        }
        foreach (self::COUNT_FIELDS as $field) {
            $target[$field] += $source[$field];
        }
        $target['activedays'] += $source['activedays'];
        $target['lastactive'] = max($target['lastactive'], $source['lastactive']);
    }

    /**
     * Add the counts of a result row to a day partial.
     *
     * @param array|null $partial Day partial (by reference; created when null).
     * @param \stdClass $record Row with the day fields and slot columns.
     */
    private function add_day(?array &$partial, \stdClass $record): void {
        if ($partial === null) {
            $partial = array_fill_keys(self::DAY_FIELDS, 0);
            $partial['slots'] = array_fill(0, site_calendar::SLOTS, 0);
        }
        foreach (self::DAY_FIELDS as $field) {
            $partial[$field] += (int) $record->{$field};
        }
        for ($k = 0; $k < site_calendar::SLOTS; $k++) {
            $partial['slots'][$k] += (int) $record->{'slot' . $k};
        }
    }

    /**
     * Empty user partial.
     *
     * @return array<string, int>
     */
    public static function empty_partial(): array {
        $partial = array_fill_keys(self::COUNT_FIELDS, 0);
        $partial['activedays'] = 0;
        $partial['lastactive'] = 0;
        return $partial;
    }
}
