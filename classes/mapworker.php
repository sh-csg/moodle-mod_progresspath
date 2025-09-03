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

use cm_info;
use core_badges\badge;

/**
 * Class for handling the content of the progresspath
 *
 * @package     mod_progresspath
 * @copyright   2025 ISB Bayern
 * @author      Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mapworker {
    /**
     * Object to process the SVG
     * @var svgmap;
     */
    protected svgmap $svgmap;
    /**
     * Course module object belonging to the map - only needed for completion
     * @var cm_info
     */
    protected cm_info $cm;
    /**
     * Stores the group id when using group mode. 0 if no group is used.
     * @var int
     */
    protected int $group;
    /**
     * Activity worker to handle completion
     * @var activitymanager
     */
    protected activitymanager $activitymanager;

    /**
     * Creates mapworker from SVG code
     *
     * @param string $svgcode The SVG code to build the map from
     * @param cm_info|null $cm The course module that belongs to the map (null by default)
     * @param int $group Group id to use (default 0 means no group)
     */
    public function __construct(
        string $svgcode,
        ?cm_info $cm = null,
        int $group = 0
    ) {
        global $USER;
        $this->svgmap = new svgmap($svgcode);
        $this->group = $group;
        if (!is_null($cm)) {
            $this->cm = $cm;
            $this->activitymanager = new activitymanager($cm->get_course(), $USER, $group);
        }
    }

    /**
     * Reitems the stylesheet with a new one generated from itemstore
     *
     * @param array $itemstoreoverride array of overrides for itemstore
     * @return void
     */
    public function replace_stylesheet(array $itemstoreoverride = []): void {
        $this->svgmap->replace_stylesheet($itemstoreoverride);
    }

    /**
     * Removes tags before the SVG tag to avoid parsing problems
     *
     * @return void
     */
    public function remove_tags_before_svg(): void {
        $this->svgmap->remove_tags_before_svg();
    }

    /**
     * Returns the completed class name for a progress path item.
     *
     * @param int $itemid The ID of the item
     * @return string The completed class name
     */
    public function get_completed_classname_for_item(int $itemid): string {
        return 'progresspath_' . $itemid . '_completed';
    }

    /**
     * Returns the uncompleted class name for a progress path item.
     *
     * @param int $itemid The ID of the item
     * @return string The uncompleted class name
     */
    public function get_uncompleted_classname_for_item(int $itemid): string {
        return 'progresspath_' . $itemid . '_uncompleted';
    }

    /**
     * Process the map to show / hide paths and items
     * @return void
     */
    public function process_map_objects(): void {
        global $CFG, $DB, $USER;
        $modinfo = get_fast_modinfo($this->cm->get_course(), $USER->id);
        $allcms = array_keys($modinfo->get_cms());

        $items = $DB->get_records('progresspath_items', ['progresspathid' => $this->cm->instance]);

        // Walk through all items in the map.
        foreach ($items as $item) {
            if (!in_array($item->cmid, $allcms)) {
                // The course module does not exist anymore. Hide the item.
                $this->svgmap->set_hidden($this->get_completed_classname_for_item($item->id));
                $this->svgmap->set_hidden($this->get_uncompleted_classname_for_item($item->id));
                continue;
            }

            $itemcm = $modinfo->get_cm($item->cmid);

            // Set the link URL in the map.
            if (!empty($itemcm->url)) {
                // Link modules that have a view page to their corresponding url.
                $url = $itemcm->url->out();
            } else {
                // Other modules (like labels) are shown on the course page. Link to the corresponding anchor.
                $url = $CFG->wwwroot . '/course/view.php?id=' . $itemcm->course .
                '&section=' . $itemcm->sectionnum . '#module-' . $itemcm->id;
            }

            $notavailable = !$itemcm->available || ($itemcm->visible == 0 && !$itemcm->is_stealth());

            // If the activity linked to the place is already completed, add it to the completed
            // and to the active items.
            if ($this->activitymanager->is_completed($itemcm)) {
                $this->svgmap->remove_elements_by_classname($this->get_uncompleted_classname_for_item($item->itemid));
                if(!$notavailable) {
                    $this->svgmap->wrap_items_in_links($this->get_completed_classname_for_item($item->itemid), $url);
                }
            } else {
                $this->svgmap->remove_elements_by_classname($this->get_completed_classname_for_item($item->itemid));
                if(!$notavailable) {
                    $this->svgmap->wrap_items_in_links($this->get_uncompleted_classname_for_item($item->itemid), $url);
                }
            }
        }

        $badges = $DB->get_records_sql('
            SELECT DISTINCT p.badgeid 
            FROM {progresspath_badges} p 
            JOIN {badge} b 
            ON p.badgeid = b.id
            WHERE progresspathid = ?',
            [$this->cm->instance]
        );
        foreach ($badges as $badge) {
            $b = new badge($badge->badgeid);
            if ($b->is_issued($USER->id)) {
                $badgeimage = \moodle_url::make_pluginfile_url($b->get_context()->id, 'badges', 'badgeimage', $b->id, '/', 'f1', false);
                $badgeelementid = $this->svgmap->insert_image('progresspath_badges', $badgeimage, 100, 100);
                $badgeurl = new \moodle_url('/badges/badge.php');
                $bi = $DB->get_record('badge_issued', ['badgeid' => $badge->badgeid, 'userid' => $USER->id]);
                $badgeurl->param('hash', $bi->uniquehash);
                $this->svgmap->wrap_in_link($badgeelementid, $badgeurl->out());
            }
        }

        $this->remove_width_and_height();
        $this->svgmap->save_svg_data();
    }

    /**
     * Returns the current svg code
     *
     * @return string
     */
    public function get_svgcode(): string {
        return $this->svgmap->get_svgcode();
    }

    /**
     * Get attribute value (for unit testing)
     *
     * @param string $id The id of the DOM element
     * @param string $attribute The name of the attribute
     * @return ?string null, if element doesn't exist
     */
    public function get_attribute(string $id, string $attribute): ?string {
        return $this->svgmap->get_attribute($id, $attribute);
    }

    /**
     * Removes the width and height attributes from all SVG elements.
     *
     * @return void
     */
    public function remove_width_and_height() {
        $this->svgmap->remove_width_and_height();
    }
}
