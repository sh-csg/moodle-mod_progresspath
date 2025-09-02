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

use mod_progresspath\migrationhelper;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/mod/progresspath/backup/moodle2/restore_progresspath_stepslib.php');

/**
 * Restore class for mod_progresspath
 *
 * @package     mod_progresspath
 * @copyright   2025 ISB Bayern
 * @author      Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_progresspath_activity_task extends restore_activity_task {
    /**
     * No specific settings for this activity
     *
     * @return void
     */
    protected function define_my_settings(): void {
    }

    /**
     * Defines the restore step for progresspath
     *
     * @return void
     */
    protected function define_my_steps(): void {
        $this->add_step(new restore_progresspath_activity_structure_step('progresspath_structure', 'progresspath.xml'));
    }

    /**
     * Calls decode functions of other plugins for the intro field. This is not necessary for
     * mod_progresspath itself but it makes small hacks in SVG possible and may be convenient for
     * future use.
     *
     * @return array
     */
    public static function define_decode_contents(): array {
        $contents = [];
        $contents[] = new restore_decode_content('progresspath', ['intro'], 'progresspath');
        return $contents;
    }

    /**
     * Defines rules for decoding links to view.php in restore step
     *
     * @return array
     */
    public static function define_decode_rules(): array {
        $rules = [];
        $rules[] = new restore_decode_rule('PROGRESSPATHVIEWBYID', '/mod/progresspath/view.php?id=$1', 'course_module');
        return $rules;
    }

    /**
     * Update itemstore to new module ids after restore is complete
     * @throws dml_exception
     */
    public function after_restore(): void {
        global $DB;
        $courseid = $this->get_courseid();
        $modinfo = get_fast_modinfo($courseid);

        $progresspath = $DB->get_record('progresspath', ['id' => $this->get_activityid()], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('progresspath', $progresspath->id, $courseid);
    }
}
