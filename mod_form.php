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
 * print the form to add or edit a peer review instance
 *
 * @author John Illsley
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_peer
 */

//It must be included from a Moodle page
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}

require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_peer_mod_form extends moodleform_mod {

    public function definition() {
        global $CFG, $DB;

        $editoroptions = peer_get_editor_options();

        $mform    =& $this->_form;

        //-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name', 'peer'), array('size'=>'64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements(get_string('description', 'peer'));

        $mform->addElement('select',
            'assignid',
            get_string('selectassignment', 'peer'),
            $this->get_assignments());
        $mform->addRule('assignid', null, 'required', null, 'client');

        $mform->addElement('select',
            'grouping',
            get_string('selectgrouping', 'peer'),
            $this->get_groupings());
        $mform->addRule('grouping', null, 'required', null, 'client');

        $mform->addElement('select',
            'weighting',
            get_string('weighting', 'peer'),
            $this->get_weightings());
        $mform->addRule('weighting', null, 'required', null, 'client');

        //-------------------------------------------------------------------------------
        $mform->addElement('header', 'timinghdr', get_string('availability'));

        $mform->addElement('date_time_selector', 'timeopen', get_string('peeropen', 'peer'),
            array('optional' => true));

        $mform->addElement('date_time_selector', 'timeclose', get_string('peerclose', 'peer'),
            array('optional' => true));

        //-------------------------------------------------------------------------------
        $this->standard_coursemodule_elements();
        //-------------------------------------------------------------------------------
        // buttons
        $this->add_action_buttons();
    }

    public function data_preprocessing(&$default_values) {
/*
        $editoroptions = peer_get_editor_options();

        if ($this->current->instance) {
            // editing an existing peer review - let us prepare the added editor elements (intro done automatically)
            $draftitemid = file_get_submitted_draft_itemid('page_after_submit');
            $default_values['page_after_submit_editor']['text'] =
                                    file_prepare_draft_area($draftitemid, $this->context->id,
                                    'mod_peer', 'page_after_submit', false,
                                    $editoroptions,
                                    $default_values['page_after_submit']);

            $default_values['page_after_submit_editor']['format'] = $default_values['page_after_submitformat'];
            $default_values['page_after_submit_editor']['itemid'] = $draftitemid;
        } else {
            // adding a new peer instance
            $draftitemid = file_get_submitted_draft_itemid('page_after_submit_editor');

            // no context yet, itemid not used
            file_prepare_draft_area($draftitemid, null, 'mod_peer', 'page_after_submit', false);
            $default_values['page_after_submit_editor']['text'] = '';
            $default_values['page_after_submit_editor']['format'] = editors_get_preferred_format();
            $default_values['page_after_submit_editor']['itemid'] = $draftitemid;
        }
*/
    }

    /**
     * Allows module to modify the data returned by form get_data().
     * This method is also called in the bulk activity completion form.
     *
     * Only available on moodleform_mod.
     *
     * @param stdClass $data the form data to be modified.
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        if (isset($data->page_after_submit_editor)) {
            $data->page_after_submitformat = $data->page_after_submit_editor['format'];
            $data->page_after_submit = $data->page_after_submit_editor['text'];

            if (!empty($data->completionunlocked)) {
                // Turn off completion settings if the checkboxes aren't ticked
                $autocompletion = !empty($data->completion) &&
                    $data->completion == COMPLETION_TRACKING_AUTOMATIC;
                if (!$autocompletion || empty($data->completionsubmit)) {
                    $data->completionsubmit=0;
                }
            }
        }
    }

    /**
     * Enforce validation rules here
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array
     **/
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Check open and close times are consistent.
        if ($data['timeopen'] && $data['timeclose'] &&
                $data['timeclose'] < $data['timeopen']) {
            $errors['timeclose'] = get_string('closebeforeopen', 'peer');
        }
        return $errors;
    }

    public function add_completion_rules() {
        $mform =& $this->_form;

        $mform->addElement('checkbox',
                           'completionsubmit',
                           '',
                           get_string('completionsubmit', 'peer'));
        // Enable this completion rule by default.
        $mform->setDefault('completionsubmit', 1);
        return array('completionsubmit');
    }

    public function completion_rule_enabled($data) {
        return !empty($data['completionsubmit']);
    }

    private function get_assignments() {
        global $DB, $COURSE;

        $conditions = array(
            "course" => $COURSE->id,
        );
        $records = $DB->get_records( "assign", $conditions, 'name', 'id, name' );
        $assignments = array();
        $assignments[0] = 'Please select...';
        foreach($records as $record) {
            $assignments[$record->id] = $record->name;
        }

        return $assignments;
    }

    private function get_groupings() {
        global $DB, $COURSE;

        $conditions = array(
            "courseid" => $COURSE->id,
        );
        $records = $DB->get_records( "groupings", $conditions, 'name', 'id, name' );
        $groupings = array();
        $groupings[0] = 'Please select...';
        foreach($records as $record) {
            $groupings[$record->id] = $record->name;
        }

        return $groupings;
    }

    private function get_weightings() {

        for( $i=0 ; $i<=100 ; $i++ ) {
            $weightings[$i] = $i;
        }
        return $weightings;
    }
}
