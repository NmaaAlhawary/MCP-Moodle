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
 * External function to create a Page activity.
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
 * External function: create a Page activity in a course.
 *
 * Uses Moodle's official add_moduleinfo() helper (the same path the web UI
 * uses) so the course module, module instance and section link are all created
 * correctly and remain upgrade-safe.
 *
 * @package    local_mcpbridge
 * @copyright  2026 Namaa Alhawary <namaa.alhawary@htu.edu.jo>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_page extends external_api {
    /**
     * Describe the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'ID of the course to add the page to'),
            'section'  => new external_value(PARAM_INT, 'Section number (0 = top)', VALUE_DEFAULT, 0),
            'name'     => new external_value(PARAM_TEXT, 'Name of the page activity'),
            'content'  => new external_value(PARAM_RAW, 'HTML body content of the page'),
            'intro'    => new external_value(PARAM_RAW, 'Optional description/intro (HTML)', VALUE_DEFAULT, ''),
            'visible'  => new external_value(PARAM_INT, 'Visible to students (1) or hidden (0)', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Create the page.
     *
     * @param int $courseid ID of the course to add the page to.
     * @param int $section Section number (0 = top).
     * @param string $name Name of the page activity.
     * @param string $content HTML body content of the page.
     * @param string $intro Optional description/intro (HTML).
     * @param int $visible Visible to students (1) or hidden (0).
     * @return array cmid and instance id of the new page.
     */
    public static function execute($courseid, $section, $name, $content, $intro = '', $visible = 1) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/modlib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'section'  => $section,
            'name'     => $name,
            'content'  => $content,
            'intro'    => $intro,
            'visible'  => $visible,
        ]);

        // Load the course, verify context, and require the capability.
        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        // Build the module info object the way the mod_form would.
        $moduleinfo = new \stdClass();
        $moduleinfo->modulename   = 'page';
        $moduleinfo->module       = $DB->get_field('modules', 'id', ['name' => 'page'], MUST_EXIST);
        $moduleinfo->course       = $course->id;
        $moduleinfo->section      = $params['section'];
        $moduleinfo->visible      = $params['visible'];
        $moduleinfo->visibleoncoursepage = 1;
        $moduleinfo->name         = $params['name'];
        $moduleinfo->intro        = $params['intro'];
        $moduleinfo->introformat  = FORMAT_HTML;
        $moduleinfo->content      = $params['content'];
        $moduleinfo->contentformat = FORMAT_HTML;
        $moduleinfo->display      = 5;   // RESOURCELIB_DISPLAY_OPEN.
        $moduleinfo->printheading = 1;
        $moduleinfo->printintro   = 0;
        $moduleinfo->printlastmodified = 1;

        $moduleinfo = add_moduleinfo($moduleinfo, $course);

        return [
            'cmid'       => (int) $moduleinfo->coursemodule,
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
            'cmid'       => new external_value(PARAM_INT, 'Course module ID of the new page'),
            'instanceid' => new external_value(PARAM_INT, 'Instance ID (mdl_page.id) of the new page'),
        ]);
    }
}
