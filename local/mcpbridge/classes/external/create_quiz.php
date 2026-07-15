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
 * External function to create an empty Quiz activity.
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
 * External function: create an empty Quiz activity (container/settings only).
 *
 * This creates the quiz with sensible defaults. Adding questions uses Moodle's
 * Question Bank API and is intentionally out of scope (stretch goal).
 */
class create_quiz extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'  => new external_value(PARAM_INT, 'ID of the course'),
            'section'   => new external_value(PARAM_INT, 'Section number (0 = top)', VALUE_DEFAULT, 0),
            'name'      => new external_value(PARAM_TEXT, 'Name of the quiz'),
            'intro'     => new external_value(PARAM_RAW, 'Quiz description/intro (HTML)', VALUE_DEFAULT, ''),
            'timeopen'  => new external_value(PARAM_INT, 'Open time (unix ts, 0 = none)', VALUE_DEFAULT, 0),
            'timeclose' => new external_value(PARAM_INT, 'Close time (unix ts, 0 = none)', VALUE_DEFAULT, 0),
            'timelimit' => new external_value(PARAM_INT, 'Time limit in seconds (0 = none)', VALUE_DEFAULT, 0),
            'grade'     => new external_value(PARAM_FLOAT, 'Maximum grade', VALUE_DEFAULT, 100.0),
            'attempts'  => new external_value(PARAM_INT, 'Attempts allowed (0 = unlimited)', VALUE_DEFAULT, 0),
            'visible'   => new external_value(PARAM_INT, 'Visible (1) or hidden (0)', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * @return array{cmid:int, instanceid:int}
     */
    public static function execute($courseid, $section, $name, $intro = '', $timeopen = 0, $timeclose = 0,
            $timelimit = 0, $grade = 100.0, $attempts = 0, $visible = 1) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/modlib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid'  => $courseid,
            'section'   => $section,
            'name'      => $name,
            'intro'     => $intro,
            'timeopen'  => $timeopen,
            'timeclose' => $timeclose,
            'timelimit' => $timelimit,
            'grade'     => $grade,
            'attempts'  => $attempts,
            'visible'   => $visible,
        ]);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        $moduleinfo = new \stdClass();
        $moduleinfo->modulename  = 'quiz';
        $moduleinfo->module      = $DB->get_field('modules', 'id', ['name' => 'quiz'], MUST_EXIST);
        $moduleinfo->course      = $course->id;
        $moduleinfo->section     = $params['section'];
        $moduleinfo->visible     = $params['visible'];
        $moduleinfo->visibleoncoursepage = 1;
        $moduleinfo->name        = $params['name'];
        $moduleinfo->intro       = $params['intro'];
        $moduleinfo->introformat = FORMAT_HTML;

        // Timing.
        $moduleinfo->timeopen        = $params['timeopen'];
        $moduleinfo->timeclose       = $params['timeclose'];
        $moduleinfo->timelimit       = $params['timelimit'];
        $moduleinfo->overduehandling = 'autosubmit';
        $moduleinfo->graceperiod     = 0;

        // Grade.
        $moduleinfo->grade           = $params['grade'];
        $moduleinfo->grademethod     = 1;   // QUIZ_GRADEHIGHEST.
        $moduleinfo->decimalpoints   = 2;
        $moduleinfo->questiondecimalpoints = -1;
        $moduleinfo->gradecat        = 0;
        $moduleinfo->gradepass       = 0;

        // Attempts / behaviour.
        $moduleinfo->attempts            = $params['attempts'];
        $moduleinfo->attemptonlast       = 0;
        $moduleinfo->preferredbehaviour  = 'deferredfeedback';
        $moduleinfo->canredoquestions    = 0;
        $moduleinfo->shuffleanswers      = 1;

        // Layout.
        $moduleinfo->questionsperpage = 1;
        $moduleinfo->navmethod        = 'free';
        $moduleinfo->sumgrades        = 0;

        // Review options (standard "deferred feedback" defaults).
        $moduleinfo->reviewattempt          = 69888;
        $moduleinfo->reviewcorrectness      = 4352;
        $moduleinfo->reviewmaxmarks         = 4352;
        $moduleinfo->reviewmarks            = 4352;
        $moduleinfo->reviewspecificfeedback = 4352;
        $moduleinfo->reviewgeneralfeedback  = 4352;
        $moduleinfo->reviewrightanswer      = 4352;
        $moduleinfo->reviewoverallfeedback  = 4352;

        // Display / security.
        $moduleinfo->showuserpicture   = 0;
        $moduleinfo->showblocks        = 0;
        $moduleinfo->quizpassword      = '';
        $moduleinfo->password          = '';
        $moduleinfo->subnet            = '';
        $moduleinfo->browsersecurity   = '-';
        $moduleinfo->delay1            = 0;
        $moduleinfo->delay2            = 0;
        $moduleinfo->completionattemptsexhausted = 0;
        $moduleinfo->completionminattempts       = 0;

        $moduleinfo = add_moduleinfo($moduleinfo, $course);

        return [
            'cmid'       => (int) $moduleinfo->coursemodule,
            'instanceid' => (int) $moduleinfo->instance,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid'       => new external_value(PARAM_INT, 'Course module ID of the new quiz'),
            'instanceid' => new external_value(PARAM_INT, 'Instance ID (mdl_quiz.id) of the new quiz'),
        ]);
    }
}
