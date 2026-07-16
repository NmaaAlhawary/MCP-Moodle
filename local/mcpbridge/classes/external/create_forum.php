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
 * External function to create a Forum activity.
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
use context_course;

/**
 * External function: create a Forum activity in a course.
 *
 * @package    local_mcpbridge
 * @copyright  2026 Namaa Alhawary <namaa.alhawary@htu.edu.jo>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_forum extends external_api {
    /**
     * Describe the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'ID of the course'),
            'section'  => new external_value(PARAM_INT, 'Section number (0 = top)', VALUE_DEFAULT, 0),
            'name'     => new external_value(PARAM_TEXT, 'Name of the forum'),
            'intro'    => new external_value(PARAM_RAW, 'Forum description (HTML)', VALUE_DEFAULT, ''),
            'type'     => new external_value(PARAM_ALPHA, 'Forum type', VALUE_DEFAULT, 'general'),
            'visible'  => new external_value(PARAM_INT, 'Visible (1) or hidden (0)', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Create the forum activity.
     *
     * @param int $courseid ID of the course.
     * @param int $section Section number (0 = top).
     * @param string $name Name of the forum.
     * @param string $intro Forum description (HTML).
     * @param string $type Forum type (general, eachuser, single, qanda, blog).
     * @param int $visible Visible (1) or hidden (0).
     * @return array cmid and instance id of the new forum.
     */
    public static function execute($courseid, $section, $name, $intro = '', $type = 'general', $visible = 1) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/modlib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'section'  => $section,
            'name'     => $name,
            'intro'    => $intro,
            'type'     => $type,
            'visible'  => $visible,
        ]);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        $moduleinfo = new \stdClass();
        $moduleinfo->modulename = 'forum';
        $moduleinfo->module = $DB->get_field('modules', 'id', ['name' => 'forum'], MUST_EXIST);
        $moduleinfo->course = $course->id;
        $moduleinfo->section = $params['section'];
        $moduleinfo->visible = $params['visible'];
        $moduleinfo->visibleoncoursepage = 1;
        $moduleinfo->name = $params['name'];
        $moduleinfo->intro = $params['intro'];
        $moduleinfo->introformat = FORMAT_HTML;
        $moduleinfo->type = $params['type'];
        $moduleinfo->forcesubscribe = 0;
        $moduleinfo->trackingtype = 1;
        $moduleinfo->maxbytes = 0;
        $moduleinfo->maxattachments = 1;
        $moduleinfo->assessed = 0;
        $moduleinfo->scale = 0;
        $moduleinfo->grade_forum = 0;
        $moduleinfo->rsstype = 0;
        $moduleinfo->rssarticles = 0;
        $moduleinfo->warnafter = 0;
        $moduleinfo->blockafter = 0;
        $moduleinfo->blockperiod = 0;
        $moduleinfo->duedate = 0;
        $moduleinfo->cutoffdate = 0;
        $moduleinfo->displaywordcount = 0;
        $moduleinfo->lockdiscussionafter = 0;

        $moduleinfo = add_moduleinfo($moduleinfo, $course);

        return [
            'cmid' => (int) $moduleinfo->coursemodule,
            'instanceid' => (int) $moduleinfo->instance,
        ];
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module ID of the new forum'),
            'instanceid' => new external_value(PARAM_INT, 'Instance ID (mdl_forum.id) of the new forum'),
        ]);
    }
}
