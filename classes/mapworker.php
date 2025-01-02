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
     * Array containing the placestore
     * @var array
     */
    protected array $placestore;
    /**
     * Course module object belonging to the map - only needed for completion
     * @var cm_info
     */
    protected \cm_info $cm;
    /**
     * Whether to prepare the code for edit mode
     * @var bool
     */
    protected bool $edit;
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
     * Active places
     * @var array
     */
    protected array $active;

    /**
     * Creates mapworker from SVG code
     *
     * @param string $svgcode The SVG code to build the map from
     * @param array $placestore The placestore data to use while processing the map
     * @param cm_info|null $cm The course module that belongs to the map (null by default)
     * @param int $group Group id to use (default 0 means no group)
     */
    public function __construct(
        string $svgcode,
        array $placestore,
        \cm_info $cm = null, // phpcs:ignore
        int $group = 0
    ) {
        global $USER;
        $this->svgmap = new svgmap($svgcode, $placestore);
        $this->group = $group;
        if (!is_null($cm)) {
            $this->cm = $cm;
            $this->activitymanager = new activitymanager($cm->get_course(), $USER, $group);
        }
        $this->active = [];
    }

    /**
     * Replaces the stylesheet with a new one generated from placestore
     *
     * @param array $placestoreoverride array of overrides for placestore
     * @return void
     */
    public function replace_stylesheet(array $placestoreoverride = []): void {
        $this->svgmap->replace_stylesheet($placestoreoverride);
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
     * Process the map to show / hide paths and places
     * @return void
     */
    public function process_map_objects(): void {
        global $CFG, $USER;
        $this->active = [];
        $completedplaces = [];
        $notavailable = [];
        $allplaces = [];
        $links = [];

        $modinfo = get_fast_modinfo($this->cm->get_course(), $USER->id);

        $allcms = array_keys($modinfo->get_cms());
        $allcmids = [];
        $cmidtoplaces = [];

        // Walk through all places in the map.
        foreach ($this->placestore['places'] as $place) {
            $allplaces[] = $place['id'];
            // Remove places that are not linked to an activity or where the activity is missing.
            if (empty($place['linkedActivity']) || !in_array($place['linkedActivity'], $allcms)) {
                $impossible[] = $place['id'];
                if (!$this->edit) {
                    $this->svgmap->remove_place_or_path($place['id']);
                }
                continue;
            }
            $allcmids[] = $place['linkedActivity'];
            $cmidtoplaces[$place['linkedActivity']][] = $place['id'];

            $placecm = $modinfo->get_cm($place['linkedActivity']);

            // Set the link URL in the map.
            if (!empty($placecm->url)) {
                // Link modules that have a view page to their corresponding url.
                $url = '' . $placecm->url;
            } else {
                // Other modules (like labels) are shown on the course page. Link to the corresponding anchor.
                $url = $CFG->wwwroot . '/course/view.php?id=' . $placecm->course .
                '&section=' . $placecm->sectionnum . '#module-' . $placecm->id;
            }
            if (!$this->edit) {
                $this->svgmap->set_link($place['linkId'], $url);
            }
            $links[$place['id']] = $place['linkId'];
            $this->svgmap->update_text_and_title(
                $place['id'],
                $placecm->get_formatted_name(),
                // Add info to target places (for accessibility).
                in_array($place['id'], $this->placestore['targetplaces']) ?
                ' (' . get_string('targetplace', 'progresspath') . ')' :
                ''
            );
            // If the place is a starting place, add it to the active places.
            if (in_array($place['id'], $this->placestore['startingplaces'])) {
                $this->active[] = $place['id'];
            }
            // If the activity linked to the place is already completed, add it to the completed
            // and to the active places.
            if ($this->activitymanager->is_completed($placecm)) {
                $completedplaces[] = $place['id'];
                $this->active[] = $place['id'];
            }
            // Places that are not accessible (e.g. because of additional availability restrictions)
            // are only shown on the map if showall mode is active.
            if (!$placecm->available) {
                $notavailable[] = $place['id'];
            }
            // Places that are not visible and not in stealth mode (i.e. reachable by link)
            // are impossible to reach.
            if ($placecm->visible == 0 && !$placecm->is_stealth()) {
                $impossible[] = $place['id'];
            }
        }
        if (!($this->edit)) {
            foreach ($this->placestore['paths'] as $path) {
                // If the beginning or the ending of the path is a completed place and this place is available,
                // show path and the place on the other end.
                if (in_array($path['sid'], $completedplaces) || in_array($path['fid'], $completedplaces)) {
                    // Only set paths visible if hidepaths is not set in placestore.
                    if (!$this->placestore['hidepaths']) {
                        $this->active[] = $path['id'];
                    }
                    $this->active[] = $path['fid'];
                    $this->active[] = $path['sid'];
                }
            }
            $this->active = array_unique($this->active);
            // Set all active paths and places to visible.
            foreach ($this->active as $a) {
                $this->svgmap->set_reachable($a);
            }
            // Make all completed places visible and set color for visited places.
            foreach ($completedplaces as $place) {
                $this->svgmap->set_visited($place);
                // If the option "usecheckmark" is selected, add the checkmark to the circle.
                if ($this->placestore['usecheckmark']) {
                    $this->svgmap->add_checkmark($place);
                }
            }
            $notavailable = array_merge(
                array_diff($allplaces, $notavailable, $completedplaces, $this->active, $impossible),
                $notavailable
            );
            // Handle unavailable places.
            foreach ($notavailable as $place) {
                if (empty($this->placestore['showall'])) {
                    $this->svgmap->remove_place_or_path($place);
                } else {
                    $this->svgmap->set_hidden($links[$place]);
                    $this->svgmap->remove_link($links[$place]);
                }
            }
            // Remove all places that are impossible to reach.
            foreach ($impossible as $place) {
                $this->svgmap->remove_place_or_path($place);
            }
        }
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
     * Get active paths and places (for unit testing). process_map_objects() must be called first.
     * @return array names of active paths and places
     */
    public function get_active(): array {
        return $this->active;
    }
}
