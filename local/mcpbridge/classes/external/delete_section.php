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
 * External function to delete a course section.
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
 * External function: delete a section (and its activities) from a course.
 *
 * @package    local_mcpbridge
 * @copyright  2026 Namaa Alhawary <namaa.alhawary@htu.edu.jo>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_section extends external_api {
    /**
     * Describe the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'   => new external_value(PARAM_INT, 'ID of the course'),
            'sectionnum' => new external_value(PARAM_INT, 'Section number to delete (not 0)'),
        ]);
    }

    /**
     * Delete the section and everything in it.
     *
     * @param int $courseid ID of the course.
     * @param int $sectionnum Section number to delete (not 0).
     * @return array the course id, section number and a success flag.
     */
    public static function execute($courseid, $sectionnum) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid'   => $courseid,
            'sectionnum' => $sectionnum,
        ]);

        if ($params['sectionnum'] < 1) {
            throw new \invalid_parameter_exception('The general section (0) cannot be deleted.');
        }

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:update', $context);
        require_capability('moodle/course:manageactivities', $context);

        $deleted = course_delete_section($course, $params['sectionnum'], true);

        return [
            'courseid'   => (int) $course->id,
            'sectionnum' => (int) $params['sectionnum'],
            'deleted'    => (bool) $deleted,
        ];
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid'   => new external_value(PARAM_INT, 'Course ID'),
            'sectionnum' => new external_value(PARAM_INT, 'Section number that was deleted'),
            'deleted'    => new external_value(PARAM_BOOL, 'Whether deletion succeeded'),
        ]);
    }
}
