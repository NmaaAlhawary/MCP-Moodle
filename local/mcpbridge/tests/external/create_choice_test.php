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
 * Tests for the create_choice external function.
 *
 * @package    local_mcpbridge
 * @copyright  2026 Namaa Alhawary <namaa.alhawary@htu.edu.jo>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcpbridge\external;

use advanced_testcase;
use core_external\external_api;

/**
 * Unit tests for create_choice.
 *
 * @package    local_mcpbridge
 * @copyright  2026 Namaa Alhawary <namaa.alhawary@htu.edu.jo>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mcpbridge\external\create_choice
 */
final class create_choice_test extends advanced_testcase {
    /**
     * A choice is created with all its options.
     *
     * @return void
     */
    public function test_execute(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $result = create_choice::execute($course->id, 0, 'Poll', 'Pick one', ['A', 'B', 'C']);
        $result = external_api::clean_returnvalue(create_choice::execute_returns(), $result);

        $this->assertGreaterThan(0, $result['cmid']);
        $this->assertTrue($DB->record_exists('choice', ['id' => $result['instanceid']]));
        $optioncount = $DB->count_records('choice_options', ['choiceid' => $result['instanceid']]);
        $this->assertEquals(3, $optioncount);
    }

    /**
     * Fewer than two options is rejected.
     *
     * @return void
     */
    public function test_requires_two_options(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $this->expectException(\invalid_parameter_exception::class);
        create_choice::execute($course->id, 0, 'Poll', 'Pick one', ['OnlyOne']);
    }
}
