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

/**
 * Library for mod_progresspath
 *
 * @package     mod_progresspath
 * @copyright   2025 ISB Bayern
 * @author      Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_progresspath\cachemanager;

/**
 * Adds a new progresspath instance
 *
 * @param stdClass $data progresspath record
 * @return int
 */
function progresspath_add_instance($data): int {
    global $DB;
    $progresspathid = $DB->insert_record('progresspath', $data);

    $context = \core\context\module::instance($data->coursemodule);
    if (!empty($data->imagefile)) {
        file_save_draft_area_files(
            $data->imagefile,
            $context->id,
            'mod_progresspath',
            'image',
            0,
            ['subdirs' => 0, 'maxfiles' => 1]
        );
    }
    return $progresspathid;
}

/**
 * Updates a progresspath instance
 *
 * @param stdClass $data progresspath record
 * @return int
 */
function progresspath_update_instance($data): int {
    global $DB;
    $data->id = $data->instance;

    $context = \core\context\module::instance($data->coursemodule);

    if (!empty($data->imagefile)) {
        // Delete old background files.
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_progresspath', 'image');
        file_save_draft_area_files(
            $data->imagefile,
            $context->id,
            'mod_progresspath',
            'image',
            0,
            ['subdirs' => 0, 'maxfiles' => 1]
        );
    }

    return $DB->update_record("progresspath", $data);
}

/**
 * Deletes a progresspath instance
 *
 * @param integer $id progresspath record
 * @return int
 */
function progresspath_delete_instance($id): int {
    global $DB;
    return $DB->delete_records("progresspath", ["id" => $id]);
}

/**
 * Returns whether a feature is supported by this module.
 * @uses FEATURE_IDNUMBER
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know or string for the module purpose.
 */
function progresspath_supports($feature) {
    switch ($feature) {
        case FEATURE_IDNUMBER:
            return true;
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT;
        default:
            return null;
    }
}

/**
 * Adds custom completion info to the course module info
 *
 * @param cm_info $cm
 * @return cached_cm_info|null
 */
function progresspath_get_coursemodule_info($cm): cached_cm_info {
    global $DB;

    if (!$map = $DB->get_record('progresspath', ['id' => $cm->instance], 'completiontype')) {
        return null;
    }

    $result = new cached_cm_info();

    $completiontypes = [
        'nocompletion',
        'completion_with_one_target',
        'completion_with_all_targets',
        'completion_with_all_places',
    ];

    if ($cm->completion == COMPLETION_TRACKING_AUTOMATIC && $map->completiontype > 0) {
        $result->customdata['customcompletionrules'][$completiontypes[$map->completiontype]] = 1;
    }

    return $result;
}

/**
 * Removes the view link if showdescription is set.
 *
 * @param cm_info $cm
 * @return void
 */
function progresspath_cm_info_dynamic(cm_info $cm): void {
    global $DB;
    $showoncoursepage = $DB->get_field('progresspath', 'showoncoursepage', ['id' => $cm->instance]);
    // Decides whether to display the link.
    if (!empty($showoncoursepage)) {
        $cm->set_no_view_link(true);
    }
}

/**
 * Generates course module info, especially the map (as intro).
 * If showdescription is set, this function outputs the intro and the map.
 *
 * @param cm_info $cm
 * @return void
 */
function progresspath_cm_info_view(cm_info $cm): void {
    global $DB, $OUTPUT;

    $progresspath = $DB->get_record('progresspath', ['id' => $cm->instance]);
    $intro = '';
    $groupdropdown = '';
    $mapcontainer = '';

    if (!empty($cm->showdescription) && !empty($progresspath->intro)) {
        $intro = format_module_intro('progresspath', $progresspath, $cm->id);
    }

    // Only show map on course page if showoncoursepage is set.
    if (!empty($progresspath->showoncoursepage)) {
        if (!empty($cm->groupmode)) {
            $groupdropdown = groups_print_activity_menu(
                $cm,
                new moodle_url(
                    '/course/view.php',
                    ['id' => $cm->get_course()->id, 'section' => $cm->sectionnum],
                    'module-' . $cm->id
                ),
                true
            );
            // Since there is no way to replace the core string just for this dropdown
            // we have to change it in this ugly way.
            $groupdropdown = str_replace(
                get_string('allparticipants'),
                get_string('ownprogress', 'mod_progresspath'),
                $groupdropdown
            );
        }

        $contentbeforemap = $groupdropdown . $intro;
        $hascontentbeforemap = !empty($contentbeforemap);

        $mapcontainer = $OUTPUT->render_from_template(
            'mod_progresspath/rendercontainer',
            [
                'cmId' => $cm->id,
                'enableLiveUpdater' => true,
                'contentbeforemap' => $contentbeforemap,
                'hascontentbeforemap' => $hascontentbeforemap,
            ]
        );

        $cm->set_custom_cmlist_item(true);
    }

    $cm->set_content($mapcontainer, true);
}

/**
 * Returns all course module ids for places of a certain progresspath.
 * @param cm_info $cm course module object for the progresspath
 * @return array
 */
function progresspath_get_place_cm(cm_info $cm): array {
    global $DB;
    $modules = $DB->get_field('progresspath_items', 'cmid', ['progresspathid' => $cm->instance]);
    return $modules;
}

/**
 * Returns the code of the progresspath.
 *
 * @param cm_info $cm
 * @return string
 */
function progresspath_get_progresspath(cm_info $cm): string {
    global $DB, $OUTPUT;

    $fs = get_file_storage();

    $context = \core\context\module::instance($cm->id);

    $file = $fs->get_area_files($context->id, 'mod_progresspath', 'image', 0, 'filename', false)[0];

    $svg = $file->get_content();

    $items = $DB->get_records('progresspath_items', ['progresspathid' => $cm->instance]);

    $map = $DB->get_record("progresspath", ["id" => $cm->instance]);

    $group = (empty($cm->groupmode) ? 0 : groups_get_activity_group($cm, true));

    $worker = new \mod_progresspath\mapworker($svg, $items, $cm, $group);
    $worker->process_map_objects();
    $worker->remove_tags_before_svg();

    return(
        $OUTPUT->render_from_template(
            'mod_progresspath/mapcontainer',
            ['mapcode' => $worker->get_svgcode()]
        )
    );
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * At this moment nothing needs to be done.
 *
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function progresspath_reset_userdata($data) {
    return [];
}
