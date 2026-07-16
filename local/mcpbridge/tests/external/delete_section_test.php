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
 * Tests for the delete_section external function.
 *
 * @package    local_mcpbridge
 * @copyright  2026 Namaa Alhawary <namaa.alhawary@htu.edu.jo>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcpbridge\external;

use advanced_testcase;
use core_external\external_api;

/**
 * Unit tests for delete_section.
 *
 * @package    local_mcpbridge
 * @copyright  2026 Namaa Alhawary <namaa.alhawary@htu.edu.jo>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mcpbridge\external\delete_section
 */
final class delete_section_test extends advanced_testcase {
    /**
     * A section and its activities are removed.
     *
     * @return void
     */
    public function test_execute(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('coursebinenable', 0, 'tool_recyclebin');
        $course = $this->getDataGenerator()->create_course(['numsections' => 2]);

        create_page::execute($course->id, 1, 'InSection1', '<p>x</p>');
        $this->assertTrue($DB->record_exists('course_sections', ['course' => $course->id, 'section' => 1]));

        $result = delete_section::execute($course->id, 1);
        $result = external_api::clean_returnvalue(delete_section::execute_returns(), $result);

        $this->assertTrue($result['deleted']);
        $this->assertFalse($DB->record_exists('course_sections', ['course' => $course->id, 'section' => 2]));
    }

    /**
     * The general section (0) cannot be deleted.
     *
     * @return void
     */
    public function test_cannot_delete_general(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $this->expectException(\invalid_parameter_exception::class);
        delete_section::execute($course->id, 0);
    }
}
