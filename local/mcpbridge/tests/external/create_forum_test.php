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
 * Tests for the create_forum external function.
 *
 * @package    local_mcpbridge
 * @copyright  2026 Namaa Alhawary <namaa.alhawary@htu.edu.jo>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcpbridge\external;

use advanced_testcase;
use core_external\external_api;

/**
 * Unit tests for create_forum.
 *
 * @package    local_mcpbridge
 * @copyright  2026 Namaa Alhawary <namaa.alhawary@htu.edu.jo>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mcpbridge\external\create_forum
 */
final class create_forum_test extends advanced_testcase {
    /**
     * A forum is created with the requested type.
     *
     * @return void
     */
    public function test_execute(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $result = create_forum::execute($course->id, 0, 'Discussion', 'Talk here', 'qanda');
        $result = external_api::clean_returnvalue(create_forum::execute_returns(), $result);

        $this->assertGreaterThan(0, $result['cmid']);
        $forum = $DB->get_record('forum', ['id' => $result['instanceid']]);
        $this->assertNotEmpty($forum);
        $this->assertEquals('Discussion', $forum->name);
        $this->assertEquals('qanda', $forum->type);
    }
}
