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
 * External function to create an Assignment activity.
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
 * External function: create an Assignment activity in a course.
 *
 * Enables online text and file submissions with feedback comments — the common
 * default. Other submission/feedback plugins keep their site defaults.
 *
 * @package    local_mcpbridge
 * @copyright  2026 Namaa Alhawary <namaa.alhawary@htu.edu.jo>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_assignment extends external_api {
    /**
     * Describe the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'ID of the course'),
            'section'  => new external_value(PARAM_INT, 'Section number (0 = top)', VALUE_DEFAULT, 0),
            'name'     => new external_value(PARAM_TEXT, 'Name of the assignment'),
            'intro'    => new external_value(PARAM_RAW, 'Assignment instructions (HTML)', VALUE_DEFAULT, ''),
            'duedate'  => new external_value(PARAM_INT, 'Due date (unix ts, 0 = none)', VALUE_DEFAULT, 0),
            'grade'    => new external_value(PARAM_INT, 'Maximum grade', VALUE_DEFAULT, 100),
            'visible'  => new external_value(PARAM_INT, 'Visible (1) or hidden (0)', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Create the assignment activity.
     *
     * @param int $courseid ID of the course.
     * @param int $section Section number (0 = top).
     * @param string $name Name of the assignment.
     * @param string $intro Assignment instructions (HTML).
     * @param int $duedate Due date (unix ts, 0 = none).
     * @param int $grade Maximum grade.
     * @param int $visible Visible (1) or hidden (0).
     * @return array cmid and instance id of the new assignment.
     */
    public static function execute($courseid, $section, $name, $intro = '', $duedate = 0, $grade = 100, $visible = 1) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/modlib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'section'  => $section,
            'name'     => $name,
            'intro'    => $intro,
            'duedate'  => $duedate,
            'grade'    => $grade,
            'visible'  => $visible,
        ]);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        $moduleinfo = new \stdClass();
        $moduleinfo->modulename = 'assign';
        $moduleinfo->module = $DB->get_field('modules', 'id', ['name' => 'assign'], MUST_EXIST);
        $moduleinfo->cmidnumber = '';
        $moduleinfo->course = $course->id;
        $moduleinfo->section = $params['section'];
        $moduleinfo->visible = $params['visible'];
        $moduleinfo->visibleoncoursepage = 1;
        $moduleinfo->name = $params['name'];
        $moduleinfo->intro = $params['intro'];
        $moduleinfo->introformat = FORMAT_HTML;
        $moduleinfo->alwaysshowdescription = 1;

        // Timing.
        $moduleinfo->allowsubmissionsfromdate = 0;
        $moduleinfo->duedate = $params['duedate'];
        $moduleinfo->cutoffdate = 0;
        $moduleinfo->gradingduedate = 0;

        // Submission behaviour.
        $moduleinfo->submissiondrafts = 0;
        $moduleinfo->requiresubmissionstatement = 0;
        $moduleinfo->sendnotifications = 0;
        $moduleinfo->sendlatenotifications = 0;
        $moduleinfo->sendstudentnotifications = 1;
        $moduleinfo->teamsubmission = 0;
        $moduleinfo->requireallteammemberssubmit = 0;
        $moduleinfo->teamsubmissiongroupingid = 0;
        $moduleinfo->preventsubmissionnotingroup = 0;
        $moduleinfo->attemptreopenmethod = 'none';
        $moduleinfo->maxattempts = -1;

        // Grading.
        $moduleinfo->grade = $params['grade'];
        $moduleinfo->blindmarking = 0;
        $moduleinfo->markinganonymous = 0;
        $moduleinfo->markingworkflow = 0;
        $moduleinfo->markingallocation = 0;

        // Submission plugins: online text + file.
        $moduleinfo->assignsubmission_onlinetext_enabled = 1;
        $moduleinfo->assignsubmission_onlinetext_wordlimit = 0;
        $moduleinfo->assignsubmission_onlinetext_wordlimit_enabled = 0;
        $moduleinfo->assignsubmission_file_enabled = 1;
        $moduleinfo->assignsubmission_file_maxfiles = 1;
        $moduleinfo->assignsubmission_file_maxsizebytes = 0;
        $moduleinfo->assignsubmission_file_filetypes = '';

        // Feedback plugins: comments.
        $moduleinfo->assignfeedback_comments_enabled = 1;
        $moduleinfo->assignfeedback_file_enabled = 0;
        $moduleinfo->assignfeedback_offline_enabled = 0;

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
            'cmid' => new external_value(PARAM_INT, 'Course module ID of the new assignment'),
            'instanceid' => new external_value(PARAM_INT, 'Instance ID (mdl_assign.id) of the new assignment'),
        ]);
    }
}
