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
 * External function to create a course section (topic).
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
 * External function: create a new section (topic) in a course.
 *
 * @package    local_mcpbridge
 * @copyright  2026 Namaa Alhawary <namaa.alhawary@htu.edu.jo>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_section extends external_api {
    /**
     * Describe the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'ID of the course'),
            'name'     => new external_value(PARAM_TEXT, 'Name/title of the section'),
            'summary'  => new external_value(PARAM_RAW, 'Section summary (HTML)', VALUE_DEFAULT, ''),
            'position' => new external_value(PARAM_INT, 'Position (0 = append to end)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Create the course section.
     *
     * @param int $courseid ID of the course.
     * @param string $name Name/title of the section.
     * @param string $summary Section summary (HTML).
     * @param int $position Position (0 = append to end).
     * @return array section id and section number.
     */
    public static function execute($courseid, $name, $summary = '', $position = 0) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'name'     => $name,
            'summary'  => $summary,
            'position' => $position,
        ]);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:update', $context);

        $section = course_create_section($course->id, $params['position']);

        $data = new \stdClass();
        $data->name = $params['name'];
        $data->summary = $params['summary'];
        $data->summaryformat = FORMAT_HTML;
        course_update_section($course, $section, $data);

        return [
            'sectionid'  => (int) $section->id,
            'sectionnum' => (int) $section->section,
        ];
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'sectionid'  => new external_value(PARAM_INT, 'ID of the new section (mdl_course_sections.id)'),
            'sectionnum' => new external_value(PARAM_INT, 'Section number within the course'),
        ]);
    }
}
