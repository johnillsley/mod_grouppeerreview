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
 * Define all the backup steps that will be used by the backup_grouppeerreview_activity_task
 */

defined('MOODLE_INTERNAL') || die();

class backup_grouppeerreview_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated.
        $grouppeerreview = new backup_nested_element('grouppeerreview', array('id'),
                array('name',
                    'intro',
                    'introformat',
                    'course',
                    'groupingid',
                    'assignid',
                    'weighting',
                    'maxrating',
                    'selfassess',
                    'completionsubmit',
                    'timeopen',
                    'timeclose',
                    'timemodified'));

        $grouppeerreviewmarks = new backup_nested_element('grouppeerreview_marks');

        $grouppeerreviewmark = new backup_nested_element('grouppeerreview_mark', array('id'),
                array('groupid',
                    'userid',
                    'reviewerid',
                    'grade',
                    'comment',
                    'timemodified'));

        // Build the tree.
        $grouppeerreview->add_child($grouppeerreviewmarks);
        $grouppeerreviewmarks->add_child($grouppeerreviewmark);

        // Define sources.
        $grouppeerreview->set_source_table('grouppeerreview', array('id' => backup::VAR_ACTIVITYID));

        // All the rest of elements only happen if we are including user info.
        if ($userinfo) {
            $grouppeerreviewmark->set_source_table('grouppeerreview_marks', array('peerid' => backup::VAR_PARENTID));
        }

        // Define id annotations.
        $grouppeerreview->annotate_ids('course', 'course');
        $grouppeerreview->annotate_ids('grouping', 'groupingid');
        $grouppeerreview->annotate_ids('assign', 'assignid');

        $grouppeerreviewmark->annotate_ids('group', 'groupid');
        $grouppeerreviewmark->annotate_ids('user', 'userid');
        $grouppeerreviewmark->annotate_ids('user', 'reviewerid');

        // Define file annotations.
        $grouppeerreview->annotate_files('mod_grouppeerreview', 'intro', null); // This file area hasn't itemid.
        $grouppeerreview->annotate_files('mod_grouppeerreview', 'studentinstructions', null); // This file area hasn't itemid.

        // Return the root element (grouppeerreview), wrapped into standard activity structure.
        return $this->prepare_activity_structure($grouppeerreview);
    }
}
