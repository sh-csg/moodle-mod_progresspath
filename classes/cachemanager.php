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

namespace mod_progresspath;

/**
 * Cache manager class for mod_progresspath
 *
 * @package     mod_progresspath
 * @copyright   2025 ISB Bayern
 * @author      Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cachemanager {
    /**
     * Reset the backlink cache for a course (includes rebuilding it) or the whole instance (if $courseid is 0).
     *
     * @param int $courseid The id of the course (defaults to 0).
     * @return void
     */
    public static function reset_backlink_cache(int $courseid = 0): void {
        if (get_config('mod_progresspath', 'backlinkallowed') == 0) {
            return;
        }

        $cache = \cache::make('mod_progresspath', 'backlinks');

        if (empty($courseid)) {
            // This also deletes the cache key 'fillstate', so the whole cache is invalidated.
            $cache->purge();
        } else {
            $modinfo = get_fast_modinfo($courseid);
            $cms = $modinfo->get_cms();
            $cache->delete_many(array_keys($cms));
        }
        self::build_backlink_cache($courseid);
    }

    /**
     * Builds the backlink cache for a course or for the whole instance (e.g. after purging the cache).
     * Building the cache for a course should only be used as a fallback if the cache is not filled for the whole instance.
     *
     * @param int $courseid Id of the course, if 0 the cache will be built for the whole instance.
     * @return void
     */
    public static function build_backlink_cache(int $courseid = 0) {
        global $DB;

        if (get_config('mod_progresspath', 'backlinkallowed') == 0) {
            return;
        }

        $backlinks = [];
        $cache = \cache::make('mod_progresspath', 'backlinks');

        $conditions = ['backlink' => 1];
        if (!empty($courseid)) {
            $conditions['course'] = $courseid;
        }

        $records = $DB->get_recordset('progresspath', $conditions, '', 'id, placestore, backlink, course');
        foreach ($records as $record) {
            $modinfo = get_fast_modinfo($record->course);
            $module = $modinfo->instances['progresspath'][$record->id];
            $cmids = $DB->get_field('progresspath_items', 'cmid', ['progresspathid' => $record->id]);
            $coursepageurl = course_get_format($module->course)->get_view_url($module->sectionnum);
            $coursepageurl->set_anchor('module-' . $module->id);
            foreach ($cmids as $cmid) {
                $url = !empty($module->showdescription) ?
                    $coursepageurl->out() :
                    new \moodle_url('/mod/progresspath/view.php', ['id' => $module->id]);
                $backlinks[$cmid][$module->id] = [
                    'url' => $url,
                    'name' => $module->name,
                    'cmid' => $module->id,
                ];
            }
        }

        foreach ($backlinks as $cmid => $backlink) {
            $cache->set($cmid, $backlink);
        }

        if (empty($courseid)) {
            // Finally set the flag to indicate that the cache is properly built.
            $cache->set('fillstate', time());
        }
    }

    /**
     * Removes a cmid from the backlink cache (e.g. when the course module was deleted).
     *
     * @param int $cmid Course module id
     * @return void
     */
    public static function remove_cmid(int $cmid) {
        $cache = \cache::make('mod_progresspath', 'backlinks');
        $cache->delete($cmid);
    }
}
