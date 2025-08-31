<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_progresspath;

/**
 * Unit test for mod_progresspath
 *
 * @package     mod_progresspath
 * @copyright   2025 ISB Bayern
 * @author      Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_progresspath
 * @group      mebis
 * @covers     \mod_progresspath_generator
 */
final class mod_progresspath_generation_test extends \advanced_testcase {
    /**
     * Tests the data generator for this module
     *
     * @return void
     */
    public function test_create_instance(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $this->assertFalse($DB->record_exists('progresspath', ['course' => $course->id]));
        $progresspath = $this->getDataGenerator()->create_module('progresspath', ['course' => $course]);

        $records = $DB->get_records('progresspath', ['course' => $course->id], 'id');
        $this->assertCount(1, $records);
        $this->assertTrue(array_key_exists($progresspath->id, $records));
    }
}
