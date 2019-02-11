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
 * @package    mod_grouppeerreview
 * @subpackage backup-moodle2
 * @author     John Illsley <j.s.illsley@bath.ac.uk>
 * @copyright  2018 University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_grouppeerreview_activity_task
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Structure step to restore one grouppeerreview activity
 */
class restore_grouppeerreview_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('grouppeerreview', '/activity/grouppeerreview');

        if ($userinfo) {
            $paths[] = new restore_path_element(
                    'grouppeerreview_mark',
                    '/activity/grouppeerreview/grouppeerreview_marks/grouppeerreview_mark');
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    protected function process_grouppeerreview($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
        // See MDL-9367.
        $data->timeopen = $this->apply_date_offset($data->timeopen);
        $data->timeclose = $this->apply_date_offset($data->timeclose);

        $data->groupingid = $this->get_mappingid('grouping', $data->groupingid);
        $data->assignid = $this->get_mappingid('assign', $data->assignid);

        // Insert the peer record.
        $newitemid = $DB->insert_record('grouppeerreview', $data);
        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }

    protected function process_grouppeerreview_mark($data) {
        global $DB;

        $data = (object)$data;
        $data->peerid = $this->get_new_parentid('grouppeerreview');
        $data->groupid = $this->get_mappingid('group', $data->groupid);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->reviewerid = $this->get_mappingid('user', $data->reviewerid);

        $newitemid = $DB->insert_record('grouppeerreview_marks', $data);
        // No need to save this mapping as far as nothing depend on it.
        // (child paths, file areas nor links decoder).
    }

    protected function after_execute() {
        // Add peer related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_grouppeerreview', 'intro', null);
    }
}
