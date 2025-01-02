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

namespace mod_progresspath\task;

use mod_progresspath\cachemanager;

/**
 * Task to fill backlink cache.
 *
 * @package    mod_progresspath
 * @copyright   2025 ISB Bayern
 * @author      Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fill_backlink_cache extends \core\task\scheduled_task {
    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('fill_backlink_cache_task', 'mod_progresspath');
    }

    /**
     * Fill backlink cache.
     */
    public function execute() {
        if (get_config('mod_progresspath', 'backlinkallowed') == 0) {
            return;
        }

        $cache = \cache::make('mod_progresspath', 'backlinks');

        $fillstate = $cache->get('fillstate');

        // If the cache is filled within the last 24 hours, do nothing.
        if (!empty($fillstate) && $fillstate > time() - 60 * 60 * 24) {
            mtrace('Backlink cache is already filled within the last 24 hours. Exiting.');
            return;
        }

        mtrace('Building backlink cache started...');
        cachemanager::build_backlink_cache();
        mtrace('Building backlink cache finished.');
    }
}
