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
 * External function bridging to the block_chatbot RAG assistant.
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
 * External function: ask the block_chatbot course assistant a question.
 *
 * Bridges to block_chatbot's RAG pipeline (its content index and LLM
 * provider), so MCP clients get the same answers as the in-page chatbot.
 * Fails cleanly when the block is not installed on this site.
 *
 * @package    local_mcpbridge
 * @copyright  2026 Namaa Alhawary <namaa.alhawary@htu.edu.jo>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ask_chatbot extends external_api {
    /**
     * Describe the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course whose content index to answer from'),
            'question' => new external_value(PARAM_RAW_TRIMMED, 'The question to ask (max 4000 chars)'),
        ]);
    }

    /**
     * Ask the chatbot.
     *
     * @param int $courseid Course whose content index to answer from.
     * @param string $question The question to ask.
     * @return array the chatbot's reply.
     */
    public static function execute($courseid, $question) {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'question' => $question,
        ]);

        if ($params['question'] === '' || mb_strlen($params['question']) > 4000) {
            throw new \invalid_parameter_exception('Question must be 1 to 4000 characters.');
        }

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('moodle/course:view', $context);

        if (!class_exists('\\block_chatbot\\provider')) {
            throw new \moodle_exception('generalexceptionmessage', 'error', '',
                'block_chatbot is not installed on this Moodle site');
        }

        if (class_exists('\\block_chatbot\\manager')) {
            if (!\block_chatbot\manager::is_enabled($params['courseid'])) {
                throw new \moodle_exception('generalexceptionmessage', 'error', '',
                    'The chatbot is disabled for this course');
            }
            if (!\block_chatbot\manager::is_chat_available($params['courseid'])) {
                throw new \moodle_exception('generalexceptionmessage', 'error', '',
                    'The chatbot has not finished indexing this course yet');
            }
        }

        $reply = \block_chatbot\provider::reply($params['question'], $params['courseid'], $USER->id);

        return [
            'reply'    => (string) $reply,
            'courseid' => $params['courseid'],
        ];
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'reply'    => new external_value(PARAM_RAW, 'The chatbot answer'),
            'courseid' => new external_value(PARAM_INT, 'Course the answer was scoped to'),
        ]);
    }
}
