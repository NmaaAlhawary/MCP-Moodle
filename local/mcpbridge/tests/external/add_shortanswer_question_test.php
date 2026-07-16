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
 * Tests for the add_shortanswer_question external function.
 *
 * @package    local_mcpbridge
 * @copyright  2026 Namaa Alhawary <namaa.alhawary@htu.edu.jo>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcpbridge\external;

use advanced_testcase;
use core_external\external_api;

/**
 * Unit tests for add_shortanswer_question.
 *
 * @package    local_mcpbridge
 * @copyright  2026 Namaa Alhawary <namaa.alhawary@htu.edu.jo>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mcpbridge\external\add_shortanswer_question
 */
final class add_shortanswer_question_test extends advanced_testcase {
    /**
     * A short-answer question is created with its accepted answers.
     *
     * @return void
     */
    public function test_execute(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        $result = add_shortanswer_question::execute(
            $quiz->cmid, 'Capital', 'Capital of France?', ['Paris', 'paris']);
        $result = external_api::clean_returnvalue(add_shortanswer_question::execute_returns(), $result);

        $this->assertGreaterThan(0, $result['questionid']);
        $this->assertEquals('shortanswer', $DB->get_field('question', 'qtype', ['id' => $result['questionid']]));
        $this->assertEquals(1, $DB->count_records('quiz_slots', ['quizid' => $quiz->id]));
    }
}
