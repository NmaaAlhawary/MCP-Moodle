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
 * Tests for the move_activity external function.
 *
 * @package    local_mcpbridge
 * @copyright  2026 Namaa Alhawary <namaa.alhawary@htu.edu.jo>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcpbridge\external;

use advanced_testcase;
use core_external\external_api;

/**
 * Unit tests for move_activity.
 *
 * @package    local_mcpbridge
 * @copyright  2026 Namaa Alhawary <namaa.alhawary@htu.edu.jo>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mcpbridge\external\move_activity
 */
final class move_activity_test extends advanced_testcase {
    /**
     * An activity is moved from section 0 to section 1.
     *
     * @return void
     */
    public function test_execute(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['numsections' => 2]);

        $created = create_page::execute($course->id, 0, 'P', '<p>x</p>');
        $created = external_api::clean_returnvalue(create_page::execute_returns(), $created);

        $result = move_activity::execute($created['cmid'], 1);
        $result = external_api::clean_returnvalue(move_activity::execute_returns(), $result);

        $this->assertEquals(1, $result['section']);
        $section1 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1]);
        $this->assertStringContainsString((string) $created['cmid'], ',' . $section1->sequence . ',');
    }
}
