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
 * External function to create a Book activity and its first chapter.
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
 * External function: create a Book activity plus its first chapter.
 *
 * add_moduleinfo() creates the book instance; the first chapter is a simple
 * row in mdl_book_chapters (there is no core helper for a single chapter, and
 * inserting the row is the same thing mod/book/edit.php does).
 */
class create_book extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'       => new external_value(PARAM_INT, 'ID of the course'),
            'section'        => new external_value(PARAM_INT, 'Section number (0 = top)', VALUE_DEFAULT, 0),
            'name'           => new external_value(PARAM_TEXT, 'Name of the book activity'),
            'intro'          => new external_value(PARAM_RAW, 'Book description/intro (HTML)', VALUE_DEFAULT, ''),
            'chaptertitle'   => new external_value(PARAM_TEXT, 'Title of the first chapter'),
            'chaptercontent' => new external_value(PARAM_RAW, 'HTML content of the first chapter'),
            'visible'        => new external_value(PARAM_INT, 'Visible (1) or hidden (0)', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * @return array{cmid:int, instanceid:int, chapterid:int}
     */
    public static function execute($courseid, $section, $name, $intro, $chaptertitle, $chaptercontent, $visible = 1) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/modlib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid'       => $courseid,
            'section'        => $section,
            'name'           => $name,
            'intro'          => $intro,
            'chaptertitle'   => $chaptertitle,
            'chaptercontent' => $chaptercontent,
            'visible'        => $visible,
        ]);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        // Create the book container.
        $moduleinfo = new \stdClass();
        $moduleinfo->modulename   = 'book';
        $moduleinfo->module       = $DB->get_field('modules', 'id', ['name' => 'book'], MUST_EXIST);
        $moduleinfo->course       = $course->id;
        $moduleinfo->section      = $params['section'];
        $moduleinfo->visible      = $params['visible'];
        $moduleinfo->visibleoncoursepage = 1;
        $moduleinfo->name         = $params['name'];
        $moduleinfo->intro        = $params['intro'];
        $moduleinfo->introformat  = FORMAT_HTML;
        $moduleinfo->numbering    = 1;   // BOOK_NUM_NUMBERS.
        $moduleinfo->navstyle     = 1;   // BOOK_LINK_IMAGE.
        $moduleinfo->customtitles = 0;

        $moduleinfo = add_moduleinfo($moduleinfo, $course);

        // Create the first chapter.
        $chapter = new \stdClass();
        $chapter->bookid        = $moduleinfo->instance;
        $chapter->pagenum       = 1;
        $chapter->subchapter    = 0;
        $chapter->title         = $params['chaptertitle'];
        $chapter->content       = $params['chaptercontent'];
        $chapter->contentformat = FORMAT_HTML;
        $chapter->hidden        = 0;
        $chapter->timecreated   = time();
        $chapter->timemodified  = time();
        $chapter->importsrc     = '';
        $chapter->external      = 0;

        $chapterid = $DB->insert_record('book_chapters', $chapter);

        return [
            'cmid'       => (int) $moduleinfo->coursemodule,
            'instanceid' => (int) $moduleinfo->instance,
            'chapterid'  => (int) $chapterid,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid'       => new external_value(PARAM_INT, 'Course module ID of the new book'),
            'instanceid' => new external_value(PARAM_INT, 'Instance ID (mdl_book.id) of the new book'),
            'chapterid'  => new external_value(PARAM_INT, 'ID of the first chapter (mdl_book_chapters.id)'),
        ]);
    }
}
