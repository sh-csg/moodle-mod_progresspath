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

namespace mod_progresspath\completion;

use mod_progresspath\activitymanager;

/**
 * Custom completion rules for mod_progresspath
 *
 * @package     mod_progresspath
 * @copyright   2025 ISB Bayern
 * @author      Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends \core_completion\activity_custom_completion {
    /**
     * No custom completion.
     */
    const NOCOMPLETION = 0;
    /**
     * Activity is completed when all activities are completed.
     */
    const COMPLETION_WITH_ALL_ACTIVITIES = 1;
    /**
     * Activity worker to handle completion
     * @var activitymanager
     */
    protected activitymanager $activitymanager;

    /**
     * Returns completion state of the custom completion rules
     *
     * @param string $rule
     * @return integer
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $map = $DB->get_record("progresspath", ["id" => $this->cm->instance], 'completiontype', MUST_EXIST);
        $cmids = $DB->get_fieldset('progresspath_items', 'cmid', ['progresspathid' => $this->cm->instance]);

        if ($map->completiontype != self::NOCOMPLETION) {
            $user = \core_user::get_user($this->userid);
            $group = (empty($this->cm->groupmode) ? 0 : groups_get_activity_group($this->cm, true));
            $this->activitymanager = new activitymanager($this->cm->get_course(), $user, $group);

            // Return COMPLETION_INCOMPLETE if there are no items.
            if (count($cmids) == 0) {
                return COMPLETION_INCOMPLETE;
            }

            foreach ($cmids as $cmid) {
                // Prevent infinite loop.
                if ($cmid == $this->cm->id) {
                    continue;
                }
                if (!$this->activitymanager->is_completed($cmid)) {
                    return COMPLETION_INCOMPLETE;
                }
            }

            return COMPLETION_COMPLETE;
        }

        return COMPLETION_INCOMPLETE;
    }

    /**
     * Defines the names of custom completion rules.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return [
            'completion_with_all_items',
        ];
    }

    /**
     * Returns the descriptions of the custom completion rules
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        return [
            'completion_with_all_items' => get_string('completiondetail:all_items', 'progresspath'),
        ];
    }

    /**
     * Returns the sort order of completion rules
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completion_with_all_items',
        ];
    }

    /**
     * Show the manual completion or not regardless of the course's showcompletionconditions setting.
     *
     * @return bool
     */
    public function manual_completion_always_shown(): bool {
        return true;
    }
}
