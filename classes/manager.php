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
 * Class manager
 *
 * @package    mod_progresspath
 * @copyright  2025 ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** @var int progresspathid */
    private int $progresspathid;

    /**
     * Constructor
     * @param int $progresspathid
     */
    public function __construct(int $progresspathid) {
        $this->progresspathid = $progresspathid;
    }

    /**
     * Add an item to the progress path.
     * @param int $cmid
     * @param int $itemid (defaults to 0, that means to use the next available itemid)
     * @return int The id of the item table entry
     */
    public function add_item(int $cmid, int $itemid = 0): int {
        global $DB;
        if (empty($itemid)) {
            $itemid = (int)$DB->get_field(
                'progresspath_items',
                'MAX(itemid)',
                ['progresspathid' => $this->progresspathid],
                IGNORE_MISSING
            ) + 1;
        }
        return $DB->insert_record('progresspath_items', [
            'progresspathid' => $this->progresspathid,
            'cmid' => $cmid,
            'itemid' => $itemid,
        ]);
    }

    /**
     * Remove an item from the progress path.
     * @param int $itemid
     * @return bool
     */
    public function remove_item(int $itemid): bool {
        global $DB;
        return $DB->delete_records('progresspath_items', [
            'progresspathid' => $this->progresspathid,
            'itemid' => $itemid,
        ]);
    }

    /**
     * Remove all items from the progress path.
     * @return bool
     */
    public function remove_items(): bool {
        global $DB;
        return $DB->delete_records('progresspath_items', [
            'progresspathid' => $this->progresspathid,
        ]);
    }

    /**
     * Adds a badge to the progress path.
     * @param int $badgeid
     * @return int
     */
    public function add_badge(int $badgeid): int {
        global $DB;
        return $DB->insert_record('progresspath_badges', [
            'progresspathid' => $this->progresspathid,
            'badgeid' => $badgeid,
        ]);
    }

    /**
     * Removes a badge from the progress path.
     * @param int $badgeid
     * @return bool
     */
    public function remove_badge(int $badgeid): bool {
        global $DB;
        return $DB->delete_records('progresspath_badges', [
            'progresspathid' => $this->progresspathid,
            'badgeid' => $badgeid,
        ]);
    }

    /**
     * Removes all badges from the progress path.
     * @return bool
     */
    public function remove_badges(): bool {
        global $DB;
        return $DB->delete_records('progresspath_badges', [
            'progresspathid' => $this->progresspathid,
        ]);
    }
}
