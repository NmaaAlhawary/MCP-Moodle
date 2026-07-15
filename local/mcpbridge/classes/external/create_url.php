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
 * External function to create a URL resource.
 *
 * @package    local_mcpbridge
 * @copyright  2026 Namaa Alhawary <namaa.alhawary@htu.edu.jo>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcpbridge\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use context_course;

/**
 * External function: create a URL resource in a course.
 */
class create_url extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'    => new external_value(PARAM_INT, 'ID of the course'),
            'section'     => new external_value(PARAM_INT, 'Section number (0 = top)', VALUE_DEFAULT, 0),
            'name'        => new external_value(PARAM_TEXT, 'Name of the URL resource'),
            'externalurl' => new external_value(PARAM_URL, 'The external URL to link to'),
            'intro'       => new external_value(PARAM_RAW, 'Optional description (HTML)', VALUE_DEFAULT, ''),
            'display'     => new external_value(PARAM_INT, 'Display mode (0=auto,1=embed,5=open,6=popup)', VALUE_DEFAULT, 0),
            'visible'     => new external_value(PARAM_INT, 'Visible (1) or hidden (0)', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * @param int $courseid ID of the course.
     * @param int $section Section number (0 = top).
     * @param string $name Name of the URL resource.
     * @param string $externalurl The external URL to link to.
     * @param string $intro Optional description (HTML).
     * @param int $display Display mode (0=auto, 1=embed, 5=open, 6=popup).
     * @param int $visible Visible (1) or hidden (0).
     * @return array cmid and instance id of the new URL.
     */
    public static function execute($courseid, $section, $name, $externalurl, $intro = '', $display = 0, $visible = 1) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/modlib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid'    => $courseid,
            'section'     => $section,
            'name'        => $name,
            'externalurl' => $externalurl,
            'intro'       => $intro,
            'display'     => $display,
            'visible'     => $visible,
        ]);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        $moduleinfo = new \stdClass();
        $moduleinfo->modulename  = 'url';
        $moduleinfo->module      = $DB->get_field('modules', 'id', ['name' => 'url'], MUST_EXIST);
        $moduleinfo->course      = $course->id;
        $moduleinfo->section     = $params['section'];
        $moduleinfo->visible     = $params['visible'];
        $moduleinfo->visibleoncoursepage = 1;
        $moduleinfo->name        = $params['name'];
        $moduleinfo->intro       = $params['intro'];
        $moduleinfo->introformat = FORMAT_HTML;
        $moduleinfo->externalurl = $params['externalurl'];
        $moduleinfo->display     = $params['display'];
        $moduleinfo->printintro  = 1;
        // url_get_optional_details expects a serialisable options blob.
        $moduleinfo->displayoptions = serialize([]);
        $moduleinfo->parameters  = serialize([]);

        $moduleinfo = add_moduleinfo($moduleinfo, $course);

        return [
            'cmid'       => (int) $moduleinfo->coursemodule,
            'instanceid' => (int) $moduleinfo->instance,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid'       => new external_value(PARAM_INT, 'Course module ID of the new URL'),
            'instanceid' => new external_value(PARAM_INT, 'Instance ID (mdl_url.id) of the new URL'),
        ]);
    }
}
