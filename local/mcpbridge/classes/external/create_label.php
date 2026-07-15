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
 * External function: create a Label (inline text/HTML) in a course.
 *
 * A label stores its displayed text in the "intro" field of the module.
 */
class create_label extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'ID of the course'),
            'section'  => new external_value(PARAM_INT, 'Section number (0 = top)', VALUE_DEFAULT, 0),
            'content'  => new external_value(PARAM_RAW, 'HTML content shown inline on the course page'),
            'name'     => new external_value(PARAM_TEXT, 'Optional short name for the label', VALUE_DEFAULT, ''),
            'visible'  => new external_value(PARAM_INT, 'Visible (1) or hidden (0)', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * @return array{cmid:int, instanceid:int}
     */
    public static function execute($courseid, $section, $content, $name = '', $visible = 1) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/modlib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'section'  => $section,
            'content'  => $content,
            'name'     => $name,
            'visible'  => $visible,
        ]);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        // Labels derive their display name from the content if none is given.
        $name = trim($params['name']);
        if ($name === '') {
            $name = shorten_text(trim(html_to_text($params['content'])), 50);
            if ($name === '') {
                $name = get_string('modulename', 'label');
            }
        }

        $moduleinfo = new \stdClass();
        $moduleinfo->modulename  = 'label';
        $moduleinfo->module      = $DB->get_field('modules', 'id', ['name' => 'label'], MUST_EXIST);
        $moduleinfo->course      = $course->id;
        $moduleinfo->section     = $params['section'];
        $moduleinfo->visible     = $params['visible'];
        $moduleinfo->visibleoncoursepage = 1;
        $moduleinfo->name        = $name;
        $moduleinfo->intro       = $params['content'];
        $moduleinfo->introformat = FORMAT_HTML;

        $moduleinfo = add_moduleinfo($moduleinfo, $course);

        return [
            'cmid'       => (int) $moduleinfo->coursemodule,
            'instanceid' => (int) $moduleinfo->instance,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid'       => new external_value(PARAM_INT, 'Course module ID of the new label'),
            'instanceid' => new external_value(PARAM_INT, 'Instance ID (mdl_label.id) of the new label'),
        ]);
    }
}
