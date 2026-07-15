<?php
// This file is part of the local_mcpbridge plugin for Moodle.
//
// Declares the external (web service) functions this plugin adds, and bundles
// them into a single named service so an admin can generate one token for all.

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
