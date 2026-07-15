<?php
// This file is part of the local_mcpbridge plugin for Moodle.

namespace local_mcpbridge\external;

defined('MOODLE_INTERNAL') || die();

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
 */
class create_page extends external_api {

    /**
     * Describe the input parameters.
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
     * @return array{cmid:int, instanceid:int}
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
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid'       => new external_value(PARAM_INT, 'Course module ID of the new page'),
            'instanceid' => new external_value(PARAM_INT, 'Instance ID (mdl_page.id) of the new page'),
        ]);
    }
}
