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

use core\plugininfo\mod;
use stdClass;

/**
 * Unit test for mod_progresspath backlink cache
 *
 * @package     mod_progresspath
 * @copyright   2025 ISB Bayern
 * @author      Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::progresspath_before_http_headers()
 * @covers     \mod_progresspath\cachemanager
 */
final class mod_progresspath_backlink_cache_test extends \advanced_testcase {
    /** @var array $courses Courses used for testing */
    private array $courses;

    /** @var array $progresspaths progress paths used for testing */
    private array $progresspaths;

    /** @var array $activities Activities used for testing */
    private array $activities;

    /** @var array $managers Manager objects for the progresspaths */
    private array $managers;

    /** @var stdClass $user User used for testing */
    private stdClass $user;

    /**
     * Prepare testing environment.
     * @return void
     */
    public function setup(): void {
        global $DB;
        $this->setAdminUser();
        $this->courses[0] = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->courses[1] = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        // Set up the progresspaths for this test. First one has backlink enabled, second one has backlink disabled,
        // third one has backlink enabled but is not available.
        $this->progresspaths[0] = $this->getDataGenerator()->create_module(
            'progresspath',
            ['course' => $this->courses[0], 'backlink' => 1]
        );
        $this->managers[0] = new \mod_progresspath\manager($this->progresspaths[0]->id);
        $this->progresspaths[1] = $this->getDataGenerator()->create_module(
            'progresspath',
            ['course' => $this->courses[0], 'backlink' => 0]
        );
        $this->managers[1] = new \mod_progresspath\manager($this->progresspaths[1]->id);
        $this->progresspaths[2] = $this->getDataGenerator()->create_module(
            'progresspath',
            ['course' => $this->courses[0], 'backlink' => 1, 'visible' => 0, 'visibleold' => 1]
        );
        $this->managers[2] = new \mod_progresspath\manager($this->progresspaths[2]->id);

        $this->activities = [];
        // Create activities for this test. The first 9 activities are in the first progresspath, the next 9 in the second.
        for ($i = 0; $i < 27; $i++) {
            $progresspathnumber = (int)($i / 9);
            $activitynumber = $i % 9;
            $this->activities[$progresspathnumber][$activitynumber] = $this->getDataGenerator()->create_module(
                'page',
                [
                    'name' => 'A',
                    'content' => 'B',
                    'course' => $this->courses[0],
                    'completion' => COMPLETION_TRACKING_AUTOMATIC,
                    'completionview' => COMPLETION_VIEW_REQUIRED,
                ]
            );

            $this->managers[$progresspathnumber]->add_item($this->activities[$progresspathnumber][$activitynumber]->cmid);
        }
        $this->activities[3][0] = $this->getDataGenerator()->create_module(
            'page',
            [
                'name' => 'A',
                'content' => 'B',
                'course' => $this->courses[1],
            ]
        );

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);

        $this->user = $this->getDataGenerator()->create_user(
            [
                'email' => 'user1@example.com',
                'username' => 'user1',
            ]
        );
        // Enrol user in both courses.
        $this->getDataGenerator()->enrol_user($this->user->id, $this->courses[0]->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($this->user->id, $this->courses[1]->id, $studentrole->id);
    }

    /**
     * Test the reset_backlink_cache() method.
     *
     * @return void
     */
    public function test_reset_backlink_cache(): void {
        global $DB;
        $this->resetAfterTest();
        $cache = \cache::make('mod_progresspath', 'backlinks');
        cachemanager::build_backlink_cache();
        $DB->set_field('progresspath', 'backlink', 0, ['id' => $this->progresspaths[0]->id]);
        cachemanager::reset_backlink_cache($this->courses[0]->id);
        $this->assertNotEquals(false, $cache->get('fillstate'));
        // There are no backlinks anymore for the first progresspath but still for the third one.
        // The second progresspath has still backlinks disabled.
        for ($i = 0; $i < 9; $i++) {
            $this->assertEquals(false, $cache->get($this->activities[0][$i]->cmid));
            $this->assertEquals(false, $cache->get($this->activities[1][$i]->cmid));
            $this->assertNotEquals(false, $cache->get($this->activities[2][$i]->cmid));
        }

        // Now reset the whole instance. Re-enable backlink for the first progresspath.
        $DB->set_field('progresspath', 'backlink', 1, ['id' => $this->progresspaths[0]->id]);

        // Set invalid cache key.
        $cache->set('test', 'test');
        cachemanager::reset_backlink_cache();

        $this->assertNotEquals(false, $cache->get('fillstate'));
        for ($i = 0; $i < 9; $i++) {
            // Only activities in first and third progresspath have cached backlinks. Be aware that availability
            // checking is not done here.
            $this->assertNotEquals(false, $cache->get($this->activities[0][$i]->cmid));
            $this->assertEquals(false, $cache->get($this->activities[1][$i]->cmid));
            $this->assertNotEquals(false, $cache->get($this->activities[2][$i]->cmid));
        }
        // Invalid key should be deleted.
        $this->assertEquals(false, $cache->get('test'));
    }

    /**
     * Test the build_backlink_cache() method.
     *
     * @return void
     */
    public function test_build_backlink_cache(): void {
        $this->resetAfterTest();
        $cache = \cache::make('mod_progresspath', 'backlinks');
        // Cache should be empty.
        $this->assertEquals(false, $cache->get('fillstate'));

        cachemanager::build_backlink_cache();

        $this->assertNotEquals(false, $cache->get('fillstate'));
    }

    /**
     * Test the progresspath_before_http_headers() function, along with on demand backlink generation if cache is not yet filled.
     *
     * @return void
     */
    public function test_backlink_generation(): void {
        global $PAGE, $OUTPUT;
        $this->setUser($this->user);
        $this->resetAfterTest();

        $modinfo = get_fast_modinfo($this->courses[0]);

        // Test an activity that is part of the first progresspath (with backlink enabled).
        $PAGE->set_cm($modinfo->get_cm($this->activities[0][0]->cmid));
        $PAGE->set_activity_record($this->activities[0][0]);

        $descriptionbefore = $PAGE->activityheader->export_for_template($OUTPUT)['description'];
        $beforehttpheadershook = new \core\hook\output\before_http_headers($OUTPUT);
        \mod_progresspath\local\hook_callbacks::inject_backlinks_into_activity_header($beforehttpheadershook);

        $descriptionafter = $PAGE->activityheader->export_for_template($OUTPUT)['description'];
        $this->assertNotEquals($descriptionbefore, $descriptionafter);
        $this->assertTrue(str_contains($descriptionafter, $this->progresspaths[0]->cmid));

        // Test an activity that is part of the second progresspath (with backlink disabled).
        $PAGE = new \moodle_page();
        $PAGE->set_cm($modinfo->get_cm($this->activities[1][1]->cmid));
        $PAGE->set_activity_record($this->activities[1][1]);

        $descriptionbefore = $PAGE->activityheader->export_for_template($OUTPUT)['description'];
        $beforehttpheadershook = new \core\hook\output\before_http_headers($OUTPUT);
        \mod_progresspath\local\hook_callbacks::inject_backlinks_into_activity_header($beforehttpheadershook);

        $descriptionafter = $PAGE->activityheader->export_for_template($OUTPUT)['description'];
        $this->assertEquals($descriptionbefore, $descriptionafter);

        // Test an activity that is part of the second course (without any progresspaths).
        $PAGE = new \moodle_page();
        $modinfo = get_fast_modinfo($this->courses[1]);
        $PAGE->set_cm($modinfo->get_cm($this->activities[3][0]->cmid));
        $PAGE->set_activity_record($this->activities[3][0]);

        $descriptionbefore = $PAGE->activityheader->export_for_template($OUTPUT)['description'];
        $beforehttpheadershook = new \core\hook\output\before_http_headers($OUTPUT);
        \mod_progresspath\local\hook_callbacks::inject_backlinks_into_activity_header($beforehttpheadershook);

        $descriptionafter = $PAGE->activityheader->export_for_template($OUTPUT)['description'];
        $this->assertEquals($descriptionbefore, $descriptionafter);

        // Learningmap is invisible for the user. Backlink should not be generated.
        $PAGE = new \moodle_page();
        $modinfo = get_fast_modinfo($this->courses[0]);
        $PAGE->set_cm($modinfo->get_cm($this->activities[2][1]->cmid));
        $PAGE->set_activity_record($this->activities[2][1]);

        $descriptionbefore = $PAGE->activityheader->export_for_template($OUTPUT)['description'];
        $beforehttpheadershook = new \core\hook\output\before_http_headers($OUTPUT);
        \mod_progresspath\local\hook_callbacks::inject_backlinks_into_activity_header($beforehttpheadershook);

        $descriptionafter = $PAGE->activityheader->export_for_template($OUTPUT)['description'];
        $this->assertEquals($descriptionbefore, $descriptionafter);
    }
}
