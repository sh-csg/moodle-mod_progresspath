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

use mod_progresspath\completion\custom_completion;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/progresspath/lib.php');

/**
 * Editing form for mod_progresspath
 *
 * @package     mod_progresspath
 * @copyright   2025 ISB Bayern
 * @author      Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_progresspath_mod_form extends moodleform_mod {
    /**
     * Defines the editing form for mod_progresspath
     *
     * @return void
     */
    public function definition(): void {
        $mform = &$this->_form;

        $cm = get_fast_modinfo($this->current->course);

        $s = [];
        $activitysel = [];
        // Gets only sections with content.
        foreach ($cm->get_sections() as $sectionnum => $section) {
            $sectioninfo = $cm->get_section_info($sectionnum);
            $s['name'] = $sectioninfo->name;
            if (empty($s['name'])) {
                $s['name'] = get_string('section') . ' ' . $sectionnum;
            }
            $s['coursemodules'] = [];
            foreach ($section as $cmid) {
                $module = $cm->get_cm($cmid);
                // Get only course modules which are not deleted.
                if ($module->deletioninprogress == 0) {
                    $s['coursemodules'][] = [
                        'id' => $cmid,
                        'name' => s($module->name),
                        'completionenabled' => $module->completion > 0,
                        'hidden' => $module->visible == 0,
                    ];
                }
            }
            $activitysel[] = $s;
        }

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name', 'progresspath'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addHelpButton('name', 'name', 'progresspath');

        $this->standard_intro_elements();

        $mform->addElement('advcheckbox', 'showoncoursepage', get_string('showoncoursepage', 'progresspath'));
        $mform->setType('showoncoursepage', PARAM_INT);
        $mform->addHelpButton('showoncoursepage', 'showoncoursepage', 'progresspath');

        $backlinkallowed = get_config('mod_progresspath', 'backlinkallowed');

        if ($backlinkallowed) {
            $mform->addElement('advcheckbox', 'backlink', get_string('showbacklink', 'progresspath'));
            $mform->setType('backlink', PARAM_INT);
            $mform->addHelpButton('backlink', 'showbacklink', 'progresspath');
        } else {
            $mform->addElement('hidden', 'backlink', 0);
        }

        $mform->addElement(
            'filemanager',
            'image',
            get_string('image', 'progresspath'),
            null,
            [
                'accepted_types' => 'svg',
                'maxfiles' => 1,
                'subdirs' => 0,
            ]
        );
        $mform->addRule('image', null, 'required', null, 'client');
        $mform->addHelpButton('image', 'image', 'progresspath');

        $mform->closeHeaderBefore('header');

        $this->standard_coursemodule_elements();

        $this->add_action_buttons(true, false, null);

        $mform->addHelpButton('groupmode', 'groupmode', 'progresspath');
    }

    /**
     * Remove visible groups here to avoid warning
     *
     * @return void
     */
    public function definition_after_data() {
        $this->_form->_elements[$this->_form->_elementIndex['groupmode']]->removeOption(VISIBLEGROUPS);
        parent::definition_after_data();
    }

    /**
     * Returns whether the custom completion rules are enabled.
     *
     * @param array $data form data
     * @return bool
     */
    public function completion_rule_enabled($data): bool {
        return (!empty($data['completiontype' . $this->get_suffix()]));
    }

    /**
     * Adds the custom completion rules for mod_progresspath
     *
     * @return array
     */
    public function add_completion_rules(): array {
        $mform = $this->_form;

        $completionoptions = [
            custom_completion::NOCOMPLETION => get_string('nocompletion', 'progresspath'),
            custom_completion::COMPLETION_WITH_ALL_ACTIVITIES => get_string('completion_with_all_activities', 'mod_progresspath'),
        ];

        $completiontype = 'completiontype' . $this->get_suffix();

        $mform->addElement(
            'select',
            $completiontype,
            get_string('completiontype', 'progresspath'),
            $completionoptions,
            []
        );

        $mform->setType($completiontype, PARAM_INT);
        $mform->hideIf($completiontype, 'completion', 'neq', COMPLETION_TRACKING_AUTOMATIC);

        return([$completiontype]);
    }

    /**
     * Processes the form data before loading the form.
     *
     * @param array $defaultvalues
     * @return void
     */
    public function data_preprocessing(&$defaultvalues): void {
        // Initialize a new progresspath instance.
        if (!$this->current->instance) {
            $defaultvalues['showdescription'] = 1;
            $defaultvalues['showoncoursepage'] = 1;
        }
    }
}
