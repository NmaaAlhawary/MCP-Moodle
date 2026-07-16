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
 * External function to update a Page activity's content.
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
use context_module;

/**
 * External function: replace the content (and optionally intro) of a Page.
 *
 * @package    local_mcpbridge
 * @copyright  2026 Namaa Alhawary <namaa.alhawary@htu.edu.jo>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_page extends external_api {
    /**
     * Describe the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'    => new external_value(PARAM_INT, 'Course module ID of the page'),
            'content' => new external_value(PARAM_RAW, 'New HTML content for the page'),
            'intro'   => new external_value(PARAM_RAW, 'New description (empty = leave unchanged)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Update the page content.
     *
     * @param int $cmid Course module ID of the page.
     * @param string $content New HTML content for the page.
     * @param string $intro New description (empty = leave unchanged).
     * @return array the cmid and a success flag.
     */
    public static function execute($cmid, $content, $intro = '') {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'    => $cmid,
            'content' => $content,
            'intro'   => $intro,
        ]);

        $cm = get_coursemodule_from_id('page', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        $record = new \stdClass();
        $record->id = $cm->instance;
        $record->content = $params['content'];
        $record->contentformat = FORMAT_HTML;
        if (trim($params['intro']) !== '') {
            $record->intro = $params['intro'];
            $record->introformat = FORMAT_HTML;
        }
        $record->timemodified = time();
        $DB->update_record('page', $record);

        rebuild_course_cache($cm->course, true);

        return [
            'cmid'    => (int) $cm->id,
            'updated' => true,
        ];
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid'    => new external_value(PARAM_INT, 'Course module ID of the page'),
            'updated' => new external_value(PARAM_BOOL, 'Whether the page was updated'),
        ]);
    }
}
