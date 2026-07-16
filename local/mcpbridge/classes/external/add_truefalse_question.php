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
 * External function to add a true/false question to a quiz.
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
 * External function: add a true/false question to an existing quiz.
 *
 * @package    local_mcpbridge
 * @copyright  2026 Namaa Alhawary <namaa.alhawary@htu.edu.jo>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_truefalse_question extends external_api {
    /**
     * Describe the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'quizcmid'      => new external_value(PARAM_INT, 'Course module ID (cmid) of the target quiz'),
            'name'          => new external_value(PARAM_TEXT, 'Question name (bank label)'),
            'questiontext'  => new external_value(PARAM_RAW, 'The statement shown to students (HTML)'),
            'correctanswer' => new external_value(PARAM_INT, 'Correct answer: 1 = true, 0 = false'),
            'defaultmark'   => new external_value(PARAM_FLOAT, 'Marks this question is worth', VALUE_DEFAULT, 1.0),
        ]);
    }

    /**
     * Add the true/false question to the quiz.
     *
     * @param int $quizcmid Course module ID (cmid) of the target quiz.
     * @param string $name Question name (bank label).
     * @param string $questiontext The statement shown to students (HTML).
     * @param int $correctanswer Correct answer: 1 = true, 0 = false.
     * @param float $defaultmark Marks this question is worth.
     * @return array question id and its slot number in the quiz.
     */
    public static function execute($quizcmid, $name, $questiontext, $correctanswer, $defaultmark = 1.0) {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/question/editlib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->dirroot . '/mod/quiz/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'quizcmid'      => $quizcmid,
            'name'          => $name,
            'questiontext'  => $questiontext,
            'correctanswer' => $correctanswer,
            'defaultmark'   => $defaultmark,
        ]);

        $cm = get_coursemodule_from_id('quiz', $params['quizcmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/quiz:manage', $context);
        require_capability('moodle/question:add', $context);
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);

        $qcategory = question_get_default_category($context->id);
        if (!$qcategory) {
            $qcategory = question_make_default_categories([$context]);
        }

        $qtypeobj = \question_bank::get_qtype('truefalse');

        $question = new \stdClass();
        $question->id         = 0;
        $question->category   = $qcategory->id;
        $question->qtype      = 'truefalse';
        $question->contextid  = $context->id;
        $question->createdby  = $USER->id;
        $question->modifiedby = $USER->id;

        $form = new \stdClass();
        $form->category        = $qcategory->id . ',' . $context->id;
        $form->name            = $params['name'];
        $form->questiontext    = ['text' => $params['questiontext'], 'format' => FORMAT_HTML];
        $form->generalfeedback = ['text' => '', 'format' => FORMAT_HTML];
        $form->defaultmark     = $params['defaultmark'];
        $form->penalty         = 1.0;
        $form->status          = 'ready';
        $form->correctanswer   = $params['correctanswer'] ? 1 : 0;
        $form->feedbacktrue    = ['text' => '', 'format' => FORMAT_HTML];
        $form->feedbackfalse   = ['text' => '', 'format' => FORMAT_HTML];
        $form->showstandardinstruction = 1;

        $question = $qtypeobj->save_question($question, $form);

        quiz_add_quiz_question($question->id, $quiz, 0, $params['defaultmark']);
        // Recompute the quiz total (API changed across Moodle versions).
        if (class_exists('\\mod_quiz\\grade_calculator')) {
            \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()
                ->recompute_quiz_sumgrades();
        } else if (function_exists('quiz_update_sumgrades')) {
            quiz_update_sumgrades($quiz);
        }

        // In Moodle 4.0+ quiz_slots has no questionid column; the question was
        // just appended, so the highest slot is the one we added.
        $slot = (int) $DB->get_field('quiz_slots', 'MAX(slot)', ['quizid' => $quiz->id]);

        return [
            'questionid' => (int) $question->id,
            'slot'       => $slot,
        ];
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'questionid' => new external_value(PARAM_INT, 'ID of the created question in the question bank'),
            'slot'       => new external_value(PARAM_INT, 'Slot number the question occupies in the quiz'),
        ]);
    }
}
