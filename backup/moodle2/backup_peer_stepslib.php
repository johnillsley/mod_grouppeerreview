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
 * @package    mod_peer
 * @subpackage backup-moodle2
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the backup steps that will be used by the backup_peer_activity_task
 */

/**
 * Define the complete peer structure for backup, with file and id annotations
 */
class backup_peer_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated
        $peer = new backup_nested_element('peer', array('id'), array(
            'name', 'intro', 'introformat', 'course',
            'coursemodule', 'grouping', 'assignid', 'weighting',
            'timeopen', 'timeclose', 'timemodified'));

        $review_marks = new backup_nested_element('review_marks');

        $review_mark = new backup_nested_element('review_mark', array('id'), array(
            'groupid', 'userid', 'reviewid', 'grade', 'comment', 'timemodified'));

        // Build the tree
        $peer->add_child($review_marks);
        $review_marks->add_child($review_mark);

        // Define sources
        $peer->set_source_table('peer', array('id' => backup::VAR_ACTIVITYID));

        // All the rest of elements only happen if we are including user info
        if ($userinfo) {
            //$review_mark->set_source_table('peer_review_marks', array('peerid' => backup::VAR_PARENTID), 'id ASC');
            $review_mark->set_source_table('peer_review_marks', array('peerid' => '../../id'));
        }

        // Define id annotations
        $review_mark->annotate_ids('user', 'userid');

        // Define file annotations
        $peer->annotate_files('mod_peer', 'intro', null); // This file area hasn't itemid

        // Return the root element (peer), wrapped into standard activity structure
        return $this->prepare_activity_structure($peer);
    }
}
