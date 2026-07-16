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
 * Tests for the update_activity and delete_activity external functions.
 *
 * @package    local_mcpbridge
 * @copyright  2026 Namaa Alhawary <namaa.alhawary@htu.edu.jo>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcpbridge\external;

use advanced_testcase;
use core_external\external_api;

/**
 * Unit tests for update_activity and delete_activity.
 *
 * @package    local_mcpbridge
 * @copyright  2026 Namaa Alhawary <namaa.alhawary@htu.edu.jo>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mcpbridge\external\delete_activity
 * @covers     \local_mcpbridge\external\update_activity
 */
final class delete_activity_test extends advanced_testcase {
    /**
     * An activity can be renamed and then deleted.
     *
     * @return void
     */
    public function test_update_then_delete(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $created = create_page::execute($course->id, 0, 'Temp', '<p>x</p>');
        $created = external_api::clean_returnvalue(create_page::execute_returns(), $created);
        $cmid = $created['cmid'];

        // Rename it.
        $updated = update_activity::execute($cmid, 'Renamed', -1);
        $updated = external_api::clean_returnvalue(update_activity::execute_returns(), $updated);
        $this->assertTrue($updated['renamed']);
        $this->assertEquals('Renamed', $DB->get_field('page', 'name', ['id' => $created['instanceid']]));

        // Delete it.
        $deleted = delete_activity::execute($cmid);
        $deleted = external_api::clean_returnvalue(delete_activity::execute_returns(), $deleted);
        $this->assertTrue($deleted['deleted']);
        $this->assertFalse($DB->record_exists('course_modules', ['id' => $cmid]));
        $this->assertFalse($DB->record_exists('page', ['id' => $created['instanceid']]));
    }
}
