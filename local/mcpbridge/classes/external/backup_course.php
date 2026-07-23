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
 * External function to back a course up to a .mbz file.
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
 * External function: run a full course backup and return the .mbz as base64.
 *
 * @package    local_mcpbridge
 * @copyright  2026 Namaa Alhawary <namaa.alhawary@htu.edu.jo>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_course extends external_api {
    /** Refuse to inline backups bigger than this (base64 over JSON). */
    const MAX_BYTES = 100 * 1024 * 1024;

    /**
     * Describe the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'     => new external_value(PARAM_INT, 'ID of the course to back up'),
            'includeusers' => new external_value(PARAM_INT, 'Include user data (1) or content only (0)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Run the backup and return the file.
     *
     * @param int $courseid ID of the course to back up.
     * @param int $includeusers Include user data (1) or content only (0).
     * @return array filename, size and base64 content of the .mbz backup.
     */
    public static function execute($courseid, $includeusers = 0) {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid'     => $courseid,
            'includeusers' => $includeusers,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('moodle/backup:backupcourse', $context);

        $bc = new \backup_controller(\backup::TYPE_1COURSE, $params['courseid'],
            \backup::FORMAT_MOODLE, \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, $USER->id);

        try {
            if (!$params['includeusers']) {
                $plan = $bc->get_plan();
                if ($plan->setting_exists('users')) {
                    $plan->get_setting('users')->set_value(0);
                }
            }
            $bc->execute_plan();
            $results = $bc->get_results();
            $file = $results['backup_destination'];

            if (!$file) {
                throw new \moodle_exception('backupfailed', 'error', '', null,
                    'Backup produced no destination file');
            }
            if ($file->get_filesize() > self::MAX_BYTES) {
                $file->delete();
                throw new \moodle_exception('backupfailed', 'error', '', null,
                    'Backup exceeds the 100MB inline transfer limit');
            }

            $content = $file->get_content();
            $filename = $file->get_filename();
            $file->delete();
        } finally {
            $bc->destroy();
        }

        return [
            'filename'       => $filename,
            'size'           => strlen($content),
            'content_base64' => base64_encode($content),
        ];
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'filename'       => new external_value(PARAM_FILE, 'Suggested filename for the backup (.mbz)'),
            'size'           => new external_value(PARAM_INT, 'Size of the backup in bytes'),
            'content_base64' => new external_value(PARAM_RAW, 'Base64-encoded .mbz content'),
        ]);
    }
}
