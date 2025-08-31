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
    /**
     * The course used for testing
     *
     * @var \stdClass
     */
    protected $course;
    /**
     * The progresspath used for testing
     *
     * @var \stdClass
     */
    protected $progresspath;
    /**
     * The activities linked in the progresspath
     *
     * @var array
     */
    protected $activities;
    /**
     * The user used for testing
     *
     * @var \stdClass
     */
    protected $user1;
    /**
     * The modinfo of the course
     *
     * @var \course_modinfo|null
     */
    protected $modinfo;
    /**
     * The completion info of the course
     *
     * @var \completion_info
     */
    protected $completion;
    /**
     * The cm_info object belonging to the progresspath (differs from the progresspath record)
     *
     * @var \cm_info
     */
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
            // The JSON contains spare course module IDs 9999x, replacing them by the real course module IDs here.
            $this->progresspath->itemstore = str_replace(99990 + $i, $this->activities[$i]->cmid, $this->progresspath->itemstore);
        }
        $DB->set_field('progresspath', 'itemstore', $this->progresspath->itemstore, ['id' => $this->progresspath->id]);

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
     * Tests slicemode
     *
     * @return void
     */
    public function test_slicemode(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->setUser($this->user1);
        $itemstore = json_decode($this->progresspath->itemstore, true);
        $itemstore['slicemode'] = true;
        $mapworker = new mapworker($this->progresspath->svgcode, $itemstore, $this->cm, false);
        $mapworker->process_map_objects();
        // The values the overlay path description is expected to have.
        $expectedvalues = [
            'M 0 0 L 0 2111 L 800 2111 L 800 0 Z M 72 47 L 338 47 L 338 108 L 72 108 Z',
            'M 0 0 L 0 2111 L 800 2111 L 800 0 Z M 72 47 L 338 47 L 338 242 L 72 242 Z',
            'M 0 0 L 0 2111 L 800 2111 L 800 0 Z M 72 47 L 481 47 L 481 349 L 72 349 Z',
            'M 0 0 L 0 2111 L 800 2111 L 800 0 Z M 72 47 L 481 47 L 481 349 L 72 349 Z',
            'M 0 0 L 0 2111 L 800 2111 L 800 0 Z M 72 47 L 481 47 L 481 349 L 72 349 Z',
            'M 0 0 L 0 2111 L 800 2111 L 800 0 Z M 72 47 L 481 47 L 481 349 L 72 349 Z',
            'M 0 0 L 0 2111 L 800 2111 L 800 0 Z M 72 47 L 649 47 L 649 349 L 72 349 Z',
            // When all items are visible, there is no overlay anymore.
            null,
        ];
        $overlay = $mapworker->get_attribute('progresspath-overlay', 'd');
        $this->assertEquals('M 0 0 L 0 2111 L 800 2111 L 800 0 Z M 37 12 L 137 12 L 137 112 L 37 112 Z', $overlay);

        for ($i = 0; $i < 8; $i++) {
            $activitycoursemodule = $this->modinfo->get_cm($this->activities[$i]->cmid);
            $this->completion->set_module_viewed($activitycoursemodule, $this->user1->id);
            $mapworker = new mapworker($this->progresspath->svgcode, $itemstore, $this->cm, false);
            $mapworker->process_map_objects();
            $overlay = $mapworker->get_attribute('progresspath-overlay', 'd');
            $this->assertEquals($expectedvalues[$i], $overlay);
        }
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
        $itemstore = json_decode($this->progresspath->itemstore, true);
        $mapworker = new mapworker($this->progresspath->svgcode, $itemstore, $this->cm, false);
        $mapworker->process_map_objects();
        // Place p0 is a starting place, so it should be visible by default.
        $this->assertEquals(['p0'], $mapworker->get_active());
        $expectedvalues = [
            ['p0', 'p1', 'p0_1'],
            ['p0', 'p1', 'p0_1', 'p4', 'p1_4'],
            ['p0', 'p1', 'p0_1', 'p4', 'p1_4', 'p2', 'p2_3', 'p3', 'p2_6', 'p6'],
            ['p0', 'p1', 'p0_1', 'p4', 'p1_4', 'p2', 'p2_3', 'p3', 'p2_6', 'p6', 'p3_6'],
            ['p0', 'p1', 'p0_1', 'p4', 'p1_4', 'p2', 'p2_3', 'p3', 'p2_6', 'p6', 'p3_6', 'p4_5', 'p5'],
            ['p0', 'p1', 'p0_1', 'p4', 'p1_4', 'p2', 'p2_3', 'p3', 'p2_6', 'p6', 'p3_6', 'p4_5', 'p5', 'p5_6'],
            ['p0', 'p1', 'p0_1', 'p4', 'p1_4', 'p2', 'p2_3', 'p3', 'p2_6', 'p6', 'p3_6', 'p4_5', 'p5', 'p5_6', 'p6_8', 'p8'],
            ['p0', 'p1', 'p0_1', 'p4', 'p1_4', 'p2', 'p2_3', 'p3', 'p2_6', 'p6', 'p3_6', 'p4_5', 'p5', 'p5_6', 'p6_8', 'p8', 'p8_9',
            'p9', ],
            ['p0', 'p1', 'p0_1', 'p4', 'p1_4', 'p2', 'p2_3', 'p3', 'p2_6', 'p6', 'p3_6', 'p4_5', 'p5', 'p5_6', 'p6_8', 'p8', 'p8_9',
            'p9', ],
        ];

        for ($i = 0; $i < count($itemstore['items']); $i++) {
            $acm = $this->modinfo->get_cm($itemstore['items'][$i]['linkedActivity']);
            $this->completion->set_module_viewed($acm, $this->user1->id);
            $mapworker = new mapworker($this->progresspath->svgcode, $itemstore, $this->cm, false);
            $mapworker->process_map_objects();
            // Calling array_unique removes duplicate entries (e.g. for starting items).
            $active = array_unique($mapworker->get_active());
            $this->assertEqualsCanonicalizing($expectedvalues[$i], $active);
        }
    }
}
