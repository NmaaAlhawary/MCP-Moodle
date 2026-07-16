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
 * External function to update an activity's name and/or visibility.
 *
 * @package    local_mcpbridge
 * @copyright  2026 Namaa Alhawary <namaa.alhawary@htu.edu.jo>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcpbridge\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use context_module;

/**
 * External function: rename and/or show/hide an existing activity.
 *
 * @package    local_mcpbridge
 * @copyright  2026 Namaa Alhawary <namaa.alhawary@htu.edu.jo>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_activity extends external_api {
    /**
     * Describe the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'    => new external_value(PARAM_INT, 'Course module ID of the activity'),
            'name'    => new external_value(PARAM_TEXT, 'New name (empty = leave unchanged)', VALUE_DEFAULT, ''),
            'visible' => new external_value(PARAM_INT, 'New visibility: 1 show, 0 hide, -1 leave', VALUE_DEFAULT, -1),
        ]);
    }

    /**
     * Update the activity.
     *
     * @param int $cmid Course module ID of the activity.
     * @param string $name New name (empty = leave unchanged).
     * @param int $visible New visibility: 1 show, 0 hide, -1 leave.
     * @return array the cmid and what was changed.
     */
    public static function execute($cmid, $name = '', $visible = -1) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'    => $cmid,
            'name'    => $name,
            'visible' => $visible,
        ]);

        $cm = get_coursemodule_from_id('', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        $renamed = false;
        if (trim($params['name']) !== '') {
            $DB->set_field($cm->modname, 'name', $params['name'], ['id' => $cm->instance]);
            $renamed = true;
        }

        $visibilitychanged = false;
        if ($params['visible'] === 0 || $params['visible'] === 1) {
            set_coursemodule_visible($cm->id, $params['visible']);
            $visibilitychanged = true;
        }

        rebuild_course_cache($cm->course, true);

        return [
            'cmid'              => (int) $cm->id,
            'renamed'           => $renamed,
            'visibilitychanged' => $visibilitychanged,
        ];
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid'              => new external_value(PARAM_INT, 'Course module ID'),
            'renamed'           => new external_value(PARAM_BOOL, 'Whether the name was changed'),
            'visibilitychanged' => new external_value(PARAM_BOOL, 'Whether visibility was changed'),
        ]);
    }
}
