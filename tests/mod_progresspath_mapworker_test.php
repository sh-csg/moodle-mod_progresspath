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
use mod_progresspath\completion\custom_completion;

/**
 * Unit test for mod_progresspath
 *
 * @package     mod_progresspath
 * @copyright   2025 ISB Bayern
 * @author      Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_progresspath
 * @group      mebis
 * @covers     \mod_progresspath\mapworker
 */
final class mod_progresspath_mapworker_test extends \advanced_testcase {
    /** @var \stdClass $course The course used for testing */
    protected $course;
    /** @var \stdClass $progresspath The progresspath used for testing */
    protected $progresspath;
    /** @var array $activities The activities linked in the progresspath */
    protected $activities;
    /** @var \mod_progresspath\manager $manager */
    protected manager $manager;
    /** @var \stdClass $user1 The user used for testing */
    protected $user1;
    /** @var \course_modinfo|null $modinfo The modinfo object for the course */
    protected $modinfo;
    /** @var \completion_info $completion The completion info of the course */
    protected $completion;
    /** @var \cm_info $cm The cm_info object belonging to the progresspath (differs from the progresspath record) */
    protected $cm;
    /**
     * Prepare testing environment
     */
    public function setUp(): void {
        global $DB;
        $this->course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->progresspath = $this->getDataGenerator()->create_module('progresspath', [
            'course' => $this->course,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completiontype' => custom_completion::NOCOMPLETION,
        ]);
        $this->manager = new \mod_progresspath\manager($this->progresspath->id);

        $this->activities = [];
        for ($i = 0; $i < 9; $i++) {
            $this->activities[] = $this->getDataGenerator()->create_module(
                'page',
                [
                    'name' => 'A',
                    'content' => 'B',
                    'course' => $this->course,
                    'completion' => COMPLETION_TRACKING_AUTOMATIC,
                    'completionview' => COMPLETION_VIEW_REQUIRED,
                ]
            );
            $this->manager->add_item($this->activities[$i]->cmid, $i + 1);
        }

        $this->user1 = $this->getDataGenerator()->create_user(
            [
                'email' => 'user1@example.com',
                'username' => 'user1',
            ]
        );

        $this->modinfo = get_fast_modinfo($this->course, $this->user1->id);
        $this->completion = new \completion_info($this->modinfo->get_course());
        $this->cm = $this->modinfo->get_cm($this->progresspath->cmid);
        parent::setUp();
    }

    /**
     * Tests visibility dependent on activity completion
     *
     * @return void
     */
    public function test_visibility(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->setUser($this->user1);
        $svgcode = file_get_contents(__DIR__ . '/fixtures/progresspath_mapworker_test.svg');
        $mapworker = new mapworker($svgcode, $this->cm, false);
        $mapworker->process_map_objects();
        // There is no activity linked to item 10.
        $this->assertEquals(1, $mapworker->count_completed(10));
        $this->assertEquals(0, $mapworker->count_uncompleted(10));
        // All other item numbers should only show the uncompleted elements.
        for ($i = 0; $i < 9; $i++) {
            $acm = $this->modinfo->get_cm($this->activities[$i]->cmid);
            $this->completion->set_module_viewed($acm, $this->user1->id);
            $mapworker = new mapworker($svgcode, $this->cm, false);
            $mapworker->process_map_objects();
            for ($j = 0; $j <= $i; $j++) {
                $this->assertEquals(1, $mapworker->count_completed($j + 1), 'Item ' . ($j + 1) . ' should be completed after viewing activity ' . ($i + 1));
                $this->assertEquals(0, $mapworker->count_uncompleted($j + 1), 'Item ' . ($j + 1) . ' should not be uncompleted after viewing activity ' . ($i + 1));
            }
            for ($j = $i + 1; $j < 9; $j++) {
                $this->assertEquals(0, $mapworker->count_completed($j + 1), 'Item ' . ($j + 1) . ' should not be completed after viewing activity ' . ($i + 1));
                $this->assertEquals(1, $mapworker->count_uncompleted($j + 1), 'Item ' . ($j + 1) . ' should be uncompleted after viewing activity ' . ($i + 1));
            }
        }
    }
}
