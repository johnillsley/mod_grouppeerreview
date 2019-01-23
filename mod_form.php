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
 * print the form to add or edit a grouppeerreview instance
 *
 * @package    mod_grouppeerreview
 * @author     John Illsley <j.s.illsley@bath.ac.uk>
 * @copyright  2018 University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

// It must be included from a Moodle page.
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}

require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_grouppeerreview_mod_form extends moodleform_mod {

    public function definition() {

        $mform = $this->_form;
        $this->_features->showdescription = false; // Prevents the show description box from being added to the form.
        $config = get_config('mod_grouppeerreview');

        //-------------------------------------------------------------------------------
        $mform->addElement('header', 'generalhdr', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name', 'grouppeerreview'), array('size' => '48'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $this->standard_intro_elements(get_string('studentinstructions', 'grouppeerreview'));
        $mform->addRule('introeditor', null, 'required', null, 'client');

        $mform->addElement(
                'select',
                'assignid',
                get_string('assignmentselect', 'grouppeerreview'),
                $this->get_assignments());
        $mform->setType('assignid', PARAM_INT);
        $mform->addRule('assignid', null, 'required', null, 'client');
        $mform->addHelpButton('assignid', 'assignmentselect', 'grouppeerreview');

        $mform->addElement(
                'select',
                'weighting',
                get_string('weighting', 'grouppeerreview'),
                $this->get_dropdown_values());
        $mform->addRule('weighting', null, 'required', null, 'client');
        $mform->setDefault('weighting', $config->defaultweighting);
        $mform->addHelpButton('weighting', 'weighting', 'grouppeerreview');

        $mform->addElement(
                'select',
                'maxrating',
                get_string('maxrating', 'grouppeerreview'),
                $this->get_dropdown_values());
        $mform->addRule('maxrating', null, 'required', null, 'client');
        $mform->setDefault('maxrating', $config->maxrating);
        $mform->addHelpButton('maxrating', 'maxrating', 'grouppeerreview');

        $mform->addElement(
                'select',
                'selfassess',
                get_string('selfassess', 'grouppeerreview'),
                array(
                        "1" => get_string('selfassessyes', 'grouppeerreview'),
                        "0" => get_string('selfassessno', 'grouppeerreview')
                ));
        $mform->addHelpButton('selfassess', 'selfassess', 'grouppeerreview');

        //-------------------------------------------------------------------------------
        $mform->addElement('header', 'timinghdr', get_string('availability'));
        $mform->setExpanded('timinghdr');

        $mform->addElement('date_time_selector', 'timeopen', get_string('peeropen', 'grouppeerreview'),
            array('optional' => true));

        $mform->addElement('date_time_selector', 'timeclose', get_string('peerclose', 'grouppeerreview'),
            array('optional' => true));

        // -------------------------------------------------------------------------------
        $this->standard_coursemodule_elements();
        $mform->setDefault('completion', 2);
        // -------------------------------------------------------------------------------
        $this->add_action_buttons();
    }

    /**
     * Prepares the form before data are set
     *
     * Additional wysiwyg editor are prepared here, the introeditor is prepared automatically by core.
     * Grade items are set here because the core modedit supports single grade item only.
     *
     * @param array $data to be set
     * @return void
     */
    public function data_preprocessing(&$data) {

        if ($this->current->instance) {
            /*
            $draftitemid = file_get_submitted_draft_itemid('studentinstructions');
            $data['studentinstructionseditor']['text'] = file_prepare_draft_area($draftitemid, $this->context->id,
                    'mod_grouppeerreview', 'studentinstructions', 0,
                    $this->_customdata['editoroptions'],
                    $data['studentinstructions']);
            $data['studentinstructionseditor']['format'] = editors_get_preferred_format();
            $data['studentinstructionseditor']['itemid'] = $draftitemid;
            */
        } else {
            $defaultcontent = get_config('mod_grouppeerreview', 'defaultinstructions');
            $data['introeditor'] = array('text' => $defaultcontent, 'format' => editors_get_preferred_format());
        }
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
        /*
        if (isset($data->studentinstructionseditor)) {
            $data->studentinstructions = $data->studentinstructionseditor['text'];
        }
        */
        // Set up completion section even if checkbox is not ticked.
        if (!empty($data->completionunlocked)) {
            if (empty($data->completionsubmit)) {
                $data->completionsubmit = 0;
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
        if ($data['timeopen'] && $data['timeclose'] && $data['timeclose'] < $data['timeopen']) {
            $errors['timeclose'] = get_string('closebeforeopen', 'grouppeerreview');
        }
        return $errors;
    }

    /**
     * Add any custom completion rules to the form.
     *
     * @return array Contains the names of the added form elements
     */
    public function add_completion_rules() {
        $mform =& $this->_form;

        $mform->addElement('checkbox', 'completionsubmit', '', get_string('completionsubmit', 'grouppeerreview'));
        // Enable this completion rule by default.
        $mform->setDefault('completionsubmit', 1);
        return array('completionsubmit');
    }

    public function completion_rule_enabled($data) {
        return !empty($data['completionsubmit']);
    }

    /**
     * Returns all the assignments (id and name) on the course that have group submission set
     *
     * @return array of assign id and name
     **/
    private function get_assignments() {
        global $DB, $COURSE;

        $conditions = array(
            "course" => $COURSE->id,
            "teamsubmission" => 1
        );
        $records = $DB->get_records( "assign", $conditions, 'name', 'id, name' );
        $assignments = array();
        $assignments[''] = get_string('pleaseselect', 'grouppeerreview');
        foreach ($records as $record) {
            $assignments[$record->id] = $record->name;
        }
        return $assignments;
    }

    /**
     * Returns an array containing numbers 0 to 100
     *
     * @return array
     **/
    private function get_dropdown_values() {
        $values = array();
        for ($i = 0; $i <= 100; $i++) {
            $values[$i] = $i;
        }
        return $values;
    }
}
