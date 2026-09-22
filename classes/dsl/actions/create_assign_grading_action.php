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
 * DSL action for applying assign advanced grading (guide / rubric).
 *
 * @package    local_dixeo
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo\dsl\actions;

use local_dixeo\dsl\dsl_exception;
use local_dixeo\dsl\value_resolver;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/grade/grading/lib.php');
require_once($CFG->dirroot . '/grade/grading/form/lib.php');
require_once($CFG->dirroot . '/grade/grading/form/guide/lib.php');
require_once($CFG->dirroot . '/grade/grading/form/rubric/lib.php');

/**
 * Action handler for create_assign_grading.
 *
 * Expected action format:
 * {
 *   "action": "create_assign_grading",
 *   "module_ref": "$module",
 *   "fields": {
 *     "grading_method": {"source": "$.grading_method"},
 *     "marking_guide": {"source": "$.marking_guide"},
 *     "marking_rubric": {"source": "$.marking_rubric"}
 *   }
 * }
 */
class create_assign_grading_action {
    use action_validation;

    /**
     * Execute the create_assign_grading action.
     *
     * @param array $action The action specification.
     * @param value_resolver $resolver The value resolver.
     * @return array Applied grading summary (method + cmid).
     * @throws dsl_exception If grading setup fails.
     */
    public function execute(array $action, value_resolver $resolver): array {
        $this->require_action_fields($action, ['module_ref', 'fields'], 'create_assign_grading');

        $fields = $resolver->resolve_fields($action['fields'], true);
        $gradingmethod = isset($fields['grading_method']) ? (string) $fields['grading_method'] : 'simple';
        if (!in_array($gradingmethod, ['simple', 'guide', 'rubric'], true)) {
            throw new dsl_exception(
                "Invalid grading_method '$gradingmethod'",
                'create_assign_grading',
                ['grading_method' => $gradingmethod]
            );
        }

        $moduleref = $resolver->resolve_source($action['module_ref'], 'module_ref');
        if (!is_array($moduleref) || !isset($moduleref['id'], $moduleref['cmid'])) {
            throw new dsl_exception(
                'create_assign_grading requires module_ref to resolve to a create_module result',
                'create_assign_grading',
                ['module_ref' => $action['module_ref']]
            );
        }

        $instanceid = (int) $moduleref['id'];
        $cmid = (int) $moduleref['cmid'];
        $cm = $this->require_module_created_in_course($resolver, 'assign', $instanceid, $cmid);

        $context = \context_module::instance((int) $cm->id);
        $gradingman = get_grading_manager($context, 'mod_assign');
        $gradingman->set_area('submissions');

        // Moodle expects '' for simple direct grading.
        $activemethod = $gradingmethod === 'simple' ? '' : $gradingmethod;
        $gradingman->set_active_method($activemethod);

        if ($gradingmethod === 'guide') {
            $this->create_marking_guide_definition(
                $gradingman,
                is_array($fields['marking_guide'] ?? null) ? $fields['marking_guide'] : []
            );
        } else if ($gradingmethod === 'rubric') {
            $this->create_rubric_definition(
                $gradingman,
                is_array($fields['marking_rubric'] ?? null) ? $fields['marking_rubric'] : []
            );
        }

        return [
            'cmid' => (int) $cm->id,
            'grading_method' => $gradingmethod,
        ];
    }

    /**
     * Create marking guide definition and criteria.
     *
     * @param \grading_manager $gradingman Grading manager for the assign submissions area.
     * @param array $guide Optional AI marking_guide payload.
     */
    protected function create_marking_guide_definition(\grading_manager $gradingman, array $guide): void {
        $controller = $gradingman->get_controller('guide');

        $name = isset($guide['name']) && $guide['name'] !== ''
            ? (string) $guide['name']
            : get_string('pluginname', 'gradingform_guide');
        $description = isset($guide['description']) && $guide['description'] !== ''
            ? (string) $guide['description']
            : '<p></p>';
        $criteria = $guide['criteria'] ?? [];
        if (empty($criteria) || !is_array($criteria)) {
            $criteria = [
                [
                    'shortname' => get_string('criterion', 'gradingform_guide', 1),
                    'description' => '',
                    'descriptionmarkers' => '',
                    'maxscore' => 100,
                ],
            ];
        }

        $criteriadata = [];
        foreach ($criteria as $idx => $criterion) {
            if (!is_array($criterion)) {
                continue;
            }
            $criteriadata['NEWID' . ($idx + 1)] = [
                'sortorder' => $idx + 1,
                'shortname' => (string) ($criterion['shortname'] ?? ''),
                'description' => (string) ($criterion['description'] ?? ''),
                'descriptionformat' => FORMAT_MOODLE,
                'descriptionmarkers' => (string) ($criterion['descriptionmarkers'] ?? ''),
                'descriptionmarkersformat' => FORMAT_MOODLE,
                'maxscore' => (float) ($criterion['maxscore'] ?? 0),
            ];
        }

        $newdefinition = (object) [
            'name' => $name,
            'description_editor' => [
                'text' => $description,
                'format' => FORMAT_HTML,
                'itemid' => 0,
            ],
            'guide' => [
                'criteria' => $criteriadata,
                'options' => \gradingform_guide_controller::get_default_options(),
                'comments' => [],
            ],
            'status' => \gradingform_controller::DEFINITION_STATUS_READY,
        ];

        $controller->update_definition($newdefinition);
    }

    /**
     * Create rubric definition, criteria and levels.
     *
     * @param \grading_manager $gradingman Grading manager for the assign submissions area.
     * @param array $rubric Optional AI marking_rubric payload.
     */
    protected function create_rubric_definition(\grading_manager $gradingman, array $rubric): void {
        $controller = $gradingman->get_controller('rubric');

        $name = isset($rubric['name']) && $rubric['name'] !== ''
            ? (string) $rubric['name']
            : get_string('pluginname', 'gradingform_rubric');
        $description = isset($rubric['description']) && $rubric['description'] !== ''
            ? (string) $rubric['description']
            : '<p></p>';
        $criteria = $rubric['criteria'] ?? [];
        if (empty($criteria) || !is_array($criteria)) {
            $criteria = [
                [
                    'description' => get_string('criterion', 'gradingform_rubric', 1),
                    'levels' => [
                        ['score' => 0, 'definition' => 'Level 1'],
                        ['score' => 100, 'definition' => 'Level 2'],
                    ],
                ],
            ];
        }

        $criteriadata = [];
        foreach ($criteria as $idx => $criterion) {
            if (!is_array($criterion)) {
                continue;
            }
            $levels = $criterion['levels'] ?? [];
            if (empty($levels) || !is_array($levels)) {
                $levels = [['score' => 0, 'definition' => '-']];
            }
            $leveldata = [];
            foreach ($levels as $lid => $level) {
                if (!is_array($level)) {
                    continue;
                }
                $leveldata['NEWID' . ($lid + 1)] = [
                    'score' => (float) ($level['score'] ?? 0),
                    'definition' => (string) ($level['definition'] ?? ''),
                    'definitionformat' => FORMAT_MOODLE,
                ];
            }
            $criteriadata['NEWID' . ($idx + 1)] = [
                'sortorder' => $idx + 1,
                'description' => (string) ($criterion['description'] ?? ''),
                'descriptionformat' => FORMAT_MOODLE,
                'levels' => $leveldata,
            ];
        }

        $newdefinition = (object) [
            'name' => $name,
            'description_editor' => [
                'text' => $description,
                'format' => FORMAT_HTML,
                'itemid' => 0,
            ],
            'rubric' => [
                'criteria' => $criteriadata,
                'options' => \gradingform_rubric_controller::get_default_options(),
            ],
            'status' => \gradingform_controller::DEFINITION_STATUS_READY,
        ];

        $controller->update_definition($newdefinition);
    }
}
