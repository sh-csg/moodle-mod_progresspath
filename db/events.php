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

/**
 * Events for mod_progresspath
 *
 * @package     mod_progresspath
 * @copyright   2025 ISB Bayern
 * @author      Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_module_completion_updated',
        'callback' => '\mod_progresspath\autoupdate::update_from_event',
    ],
    [
        'eventname' => '\core\event\course_module_deleted',
        'callback' => '\mod_progresspath\autoupdate::update_from_delete_event',
    ],
    [
        'eventname' => '\core\event\course_module_created',
        'callback' => '\mod_progresspath\autoupdate::reset_backlink_cache_if_necessary',
    ],
    [
        'eventname' => '\core\event\course_module_updated',
        'callback' => '\mod_progresspath\autoupdate::reset_backlink_cache_if_necessary',
    ],
    // Necessary for updating the backlinks if the course format changes.
    [
        'eventname' => '\core\event\course_updated',
        'callback' => '\mod_progresspath\autoupdate::reset_backlink_cache_if_necessary',
    ],
];
