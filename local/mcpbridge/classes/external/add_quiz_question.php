<?php
// This file is part of the local_mcpbridge plugin for Moodle.

namespace local_mcpbridge\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_value;
use core_external\external_single_structure;
use context_module;

/**
 * External function: add a multiple-choice question to an existing quiz.
 *
 * This is the "stretch goal" — it uses Moodle's Question Bank API, which is
 * considerably heavier and more version-sensitive than activity creation:
 *   1. find/create a default question category in the quiz's module context,
 *   2. build a multichoice question via its qtype and save it, then
 *   3. attach it to the quiz as a slot and recompute the quiz's max grade.
 *
 * Only single/multi-answer multiple-choice is supported here. Other qtypes
 * (essay, matching, cloze, ...) each need their own form-data shape.
 */
class add_quiz_question extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'quizcmid'     => new external_value(PARAM_INT, 'Course module ID (cmid) of the target quiz'),
            'name'         => new external_value(PARAM_TEXT, 'Question name (bank label)'),
            'questiontext' => new external_value(PARAM_RAW, 'The question text shown to students (HTML)'),
            'answers'      => new external_multiple_structure(
                new external_single_structure([
                    'text'     => new external_value(PARAM_RAW, 'Answer option text (HTML)'),
                    'fraction' => new external_value(PARAM_FLOAT,
                        'Grade fraction: 1.0 = fully correct, 0 = wrong, negatives penalise'),
                    'feedback' => new external_value(PARAM_RAW, 'Optional per-answer feedback', VALUE_DEFAULT, ''),
                ]),
                'The answer options (at least two)'
            ),
            'single'      => new external_value(PARAM_INT,
                'Single correct answer (1) or multiple correct (0)', VALUE_DEFAULT, 1),
            'defaultmark' => new external_value(PARAM_FLOAT, 'Marks this question is worth', VALUE_DEFAULT, 1.0),
        ]);
    }

    /**
     * @return array{questionid:int, slot:int}
     */
    public static function execute($quizcmid, $name, $questiontext, $answers, $single = 1, $defaultmark = 1.0) {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/question/editlib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->dirroot . '/mod/quiz/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'quizcmid'     => $quizcmid,
            'name'         => $name,
            'questiontext' => $questiontext,
            'answers'      => $answers,
            'single'       => $single,
            'defaultmark'  => $defaultmark,
        ]);

        if (count($params['answers']) < 2) {
            throw new \invalid_parameter_exception('A multiple-choice question needs at least two answers.');
        }

        // Resolve the quiz, its course and module context; check capabilities.
        $cm = get_coursemodule_from_id('quiz', $params['quizcmid'], 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/quiz:manage', $context);
        require_capability('moodle/question:add', $context);
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);

        // Find (or create) a question category living in this quiz's context.
        $qcategory = question_get_default_category($context->id);
        if (!$qcategory) {
            $qcategory = question_make_default_categories([$context]);
        }

        // Build the multichoice question the way the edit form would submit it.
        $qtypeobj = \question_bank::get_qtype('multichoice');

        $question = new \stdClass();
        $question->id        = 0;
        $question->category  = $qcategory->id;
        $question->qtype     = 'multichoice';
        $question->contextid = $context->id;
        $question->createdby = $USER->id;
        $question->modifiedby = $USER->id;

        $form = new \stdClass();
        $form->category        = $qcategory->id . ',' . $context->id;
        $form->name            = $params['name'];
        $form->questiontext    = ['text' => $params['questiontext'], 'format' => FORMAT_HTML];
        $form->generalfeedback = ['text' => '', 'format' => FORMAT_HTML];
        $form->defaultmark     = $params['defaultmark'];
        $form->penalty         = 0.3333333;
        $form->status          = 'ready';

        // Multichoice-specific options.
        $form->single                = $params['single'] ? 1 : 0;
        $form->shuffleanswers        = 1;
        $form->answernumbering       = 'abc';
        $form->showstandardinstruction = 1;
        $form->correctfeedback         = ['text' => get_string('correctfeedbackdefault', 'qtype_multichoice'), 'format' => FORMAT_HTML];
        $form->partiallycorrectfeedback = ['text' => get_string('partiallycorrectfeedbackdefault', 'qtype_multichoice'), 'format' => FORMAT_HTML];
        $form->incorrectfeedback       = ['text' => get_string('incorrectfeedbackdefault', 'qtype_multichoice'), 'format' => FORMAT_HTML];
        $form->shownumcorrect          = 1;

        // Answer options.
        $form->answer   = [];
        $form->fraction = [];
        $form->feedback = [];
        foreach ($params['answers'] as $i => $a) {
            $form->answer[$i]   = ['text' => $a['text'], 'format' => FORMAT_HTML];
            $form->fraction[$i] = $a['fraction'];
            $form->feedback[$i] = ['text' => $a['feedback'] ?? '', 'format' => FORMAT_HTML];
        }

        // Save the question into the bank.
        $question = $qtypeobj->save_question($question, $form);

        // Attach the question to the quiz and recompute grades.
        quiz_add_quiz_question($question->id, $quiz, 0, $params['defaultmark']);
        quiz_update_sumgrades($quiz);

        // Report the slot number this question landed in.
        $slot = (int) $DB->get_field('quiz_slots', 'slot',
            ['quizid' => $quiz->id, 'questionid' => $question->id], IGNORE_MULTIPLE);
        if (!$slot) {
            // Moodle 4.1+ stores the question reference indirectly; fall back to the max slot.
            $slot = (int) $DB->get_field('quiz_slots', 'MAX(slot)', ['quizid' => $quiz->id]);
        }

        return [
            'questionid' => (int) $question->id,
            'slot'       => $slot,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'questionid' => new external_value(PARAM_INT, 'ID of the created question in the question bank'),
            'slot'       => new external_value(PARAM_INT, 'Slot number the question occupies in the quiz'),
        ]);
    }
}
