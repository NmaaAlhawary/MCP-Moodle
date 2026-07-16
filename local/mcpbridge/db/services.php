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
 * External web service function and service definitions for MCP Bridge.
 *
 * @package    local_mcpbridge
 * @copyright  2026 Namaa Alhawary <namaa.alhawary@htu.edu.jo>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_mcpbridge_create_page' => [
        'classname'    => 'local_mcpbridge\external\create_page',
        'methodname'   => 'execute',
        'description'  => 'Create a Page activity (HTML content) in a course',
        'type'         => 'write',
        'capabilities' => 'moodle/course:manageactivities',
        'ajax'         => false,
    ],
    'local_mcpbridge_create_book' => [
        'classname'    => 'local_mcpbridge\external\create_book',
        'methodname'   => 'execute',
        'description'  => 'Create a Book activity plus its first chapter in a course',
        'type'         => 'write',
        'capabilities' => 'moodle/course:manageactivities',
        'ajax'         => false,
    ],
    'local_mcpbridge_create_label' => [
        'classname'    => 'local_mcpbridge\external\create_label',
        'methodname'   => 'execute',
        'description'  => 'Create a Label (inline text) in a course',
        'type'         => 'write',
        'capabilities' => 'moodle/course:manageactivities',
        'ajax'         => false,
    ],
    'local_mcpbridge_create_url' => [
        'classname'    => 'local_mcpbridge\external\create_url',
        'methodname'   => 'execute',
        'description'  => 'Create a URL resource in a course',
        'type'         => 'write',
        'capabilities' => 'moodle/course:manageactivities',
        'ajax'         => false,
    ],
    'local_mcpbridge_create_quiz' => [
        'classname'    => 'local_mcpbridge\external\create_quiz',
        'methodname'   => 'execute',
        'description'  => 'Create an empty Quiz activity (container/settings only) in a course',
        'type'         => 'write',
        'capabilities' => 'moodle/course:manageactivities',
        'ajax'         => false,
    ],
    'local_mcpbridge_add_quiz_question' => [
        'classname'    => 'local_mcpbridge\external\add_quiz_question',
        'methodname'   => 'execute',
        'description'  => 'Add a multiple-choice question to an existing quiz (Question Bank API)',
        'type'         => 'write',
        'capabilities' => 'mod/quiz:manage, moodle/question:add',
        'ajax'         => false,
    ],
    'local_mcpbridge_create_forum' => [
        'classname'    => 'local_mcpbridge\external\create_forum',
        'methodname'   => 'execute',
        'description'  => 'Create a Forum activity in a course',
        'type'         => 'write',
        'capabilities' => 'moodle/course:manageactivities',
        'ajax'         => false,
    ],
    'local_mcpbridge_create_choice' => [
        'classname'    => 'local_mcpbridge\external\create_choice',
        'methodname'   => 'execute',
        'description'  => 'Create a Choice (poll) activity in a course',
        'type'         => 'write',
        'capabilities' => 'moodle/course:manageactivities',
        'ajax'         => false,
    ],
    'local_mcpbridge_create_assignment' => [
        'classname'    => 'local_mcpbridge\external\create_assignment',
        'methodname'   => 'execute',
        'description'  => 'Create an Assignment activity in a course',
        'type'         => 'write',
        'capabilities' => 'moodle/course:manageactivities',
        'ajax'         => false,
    ],
    'local_mcpbridge_create_section' => [
        'classname'    => 'local_mcpbridge\external\create_section',
        'methodname'   => 'execute',
        'description'  => 'Create a new section (topic) in a course',
        'type'         => 'write',
        'capabilities' => 'moodle/course:update',
        'ajax'         => false,
    ],
    'local_mcpbridge_update_activity' => [
        'classname'    => 'local_mcpbridge\external\update_activity',
        'methodname'   => 'execute',
        'description'  => 'Rename and/or show/hide an existing activity',
        'type'         => 'write',
        'capabilities' => 'moodle/course:manageactivities',
        'ajax'         => false,
    ],
    'local_mcpbridge_delete_activity' => [
        'classname'    => 'local_mcpbridge\external\delete_activity',
        'methodname'   => 'execute',
        'description'  => 'Delete an activity (course module) from a course',
        'type'         => 'write',
        'capabilities' => 'moodle/course:manageactivities',
        'ajax'         => false,
    ],
];

$services = [
    'MCP Bridge Service' => [
        'functions'       => array_keys($functions),
        'restrictedusers' => 1,
        'enabled'         => 1,
        'shortname'       => 'local_mcpbridge_service',
        'downloadfiles'   => 0,
        'uploadfiles'     => 0,
    ],
];
