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
 * External function to restore a course from a .mbz file.
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
use context_coursecat;

/**
 * External function: restore a base64 .mbz backup into a brand-new course.
 *
 * @package    local_mcpbridge
 * @copyright  2026 Namaa Alhawary <namaa.alhawary@htu.edu.jo>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_course extends external_api {
    /**
     * Describe the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'content_base64' => new external_value(PARAM_RAW, 'Base64-encoded .mbz backup content'),
            'categoryid'     => new external_value(PARAM_INT, 'Category to create the new course in', VALUE_DEFAULT, 1),
            'fullname'       => new external_value(PARAM_TEXT, 'Full name for the new course (default: from backup)', VALUE_DEFAULT, ''),
            'shortname'      => new external_value(PARAM_TEXT, 'Short name for the new course (default: from backup)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Restore the backup into a new course.
     *
     * @param string $contentbase64 Base64-encoded .mbz content.
     * @param int $categoryid Category to create the new course in.
     * @param string $fullname Full name for the new course.
     * @param string $shortname Short name for the new course.
     * @return array id, fullname and shortname of the new course.
     */
    public static function execute($contentbase64, $categoryid = 1, $fullname = '', $shortname = '') {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'content_base64' => $contentbase64,
            'categoryid'     => $categoryid,
            'fullname'       => $fullname,
            'shortname'      => $shortname,
        ]);

        $context = context_coursecat::instance($params['categoryid']);
        self::validate_context($context);
        require_capability('moodle/restore:restorecourse', $context);

        $content = base64_decode($params['content_base64'], true);
        if ($content === false || $content === '') {
            throw new \invalid_parameter_exception('content_base64 is not valid base64.');
        }

        // Unpack the .mbz into the backup temp area.
        $backupid = \restore_controller::get_tempdir_name($params['categoryid'], $USER->id);
        $tempdir = make_backup_temp_directory($backupid);
        $mbzfile = $tempdir . '.mbz';
        file_put_contents($mbzfile, $content);
        $packer = get_file_packer('application/vnd.moodle.backup');
        if (!$packer->extract_to_pathname($mbzfile, $tempdir)) {
            @unlink($mbzfile);
            throw new \moodle_exception('invalidbackupfile', 'error');
        }
        @unlink($mbzfile);

        // Placeholder names are replaced from the backup during precheck unless
        // the caller supplied explicit ones.
        $newfullname = $params['fullname'] !== '' ? $params['fullname'] : 'Restored course';
        $newshortname = $params['shortname'] !== '' ? $params['shortname']
            : uniqid('restored_');
        $courseid = \restore_dbops::create_new_course($newfullname, $newshortname, $params['categoryid']);

        $rc = new \restore_controller($backupid, $courseid, \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL, $USER->id, \backup::TARGET_NEW_COURSE);
        try {
            if (!$rc->execute_precheck()) {
                $results = $rc->get_precheck_results();
                if (!empty($results['errors'])) {
                    throw new \moodle_exception('restoreerror', 'error', '', null,
                        json_encode($results['errors']));
                }
            }
            $rc->execute_plan();
        } finally {
            $rc->destroy();
        }

        // If the caller gave explicit names, enforce them post-restore (the
        // backup's own names win during the restore itself).
        $update = new \stdClass();
        $update->id = $courseid;
        if ($params['fullname'] !== '') {
            $update->fullname = $params['fullname'];
        }
        if ($params['shortname'] !== '') {
            $update->shortname = $params['shortname'];
        }
        $DB->update_record('course', $update);

        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname, shortname', MUST_EXIST);

        return [
            'courseid'  => (int) $course->id,
            'fullname'  => $course->fullname,
            'shortname' => $course->shortname,
        ];
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid'  => new external_value(PARAM_INT, 'ID of the newly created course'),
            'fullname'  => new external_value(PARAM_TEXT, 'Full name of the new course'),
            'shortname' => new external_value(PARAM_TEXT, 'Short name of the new course'),
        ]);
    }
}
