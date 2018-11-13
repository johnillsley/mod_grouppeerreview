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
 * Moodle renderer used to display special elements of the grouppeerreview module
 *
 * @package   mod_grouppeerreview
 * @copyright
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/
define ('DISPLAY_HORIZONTAL_LAYOUT', 0);
define ('DISPLAY_VERTICAL_LAYOUT', 1);

class mod_grouppeerreview_renderer extends plugin_renderer_base {

    /**
     * Returns HTML to display grouppeerreview of option
     * @param object $options
     * @param int  $coursemoduleid
     * @param bool $vertical
     * @return string
     */
    public function display_options($options, $coursemoduleid) {

        $target = new moodle_url('/mod/grouppeerreview/view.php');
        $attributes = array('method'=>'POST', 'action'=>$target);
        $context = context_module::instance($coursemoduleid);
        $disabled = empty($options['previewonly']) ? array() : array('disabled' => 'disabled');

        $html  = html_writer::start_tag('form', $attributes);
        $html .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'sesskey', 'value'=>sesskey()));
        $html .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'action', 'value'=>'savereview'));
        $html .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'id', 'value'=>$coursemoduleid));

        $html .= "<form>";
        foreach( $options['groups'] as $group ) {

            $html .= "<h4>".$group->name."</h4>";
            $table = new html_table();
            $table->head = array('Group member', 'Your review', 'Comments (optional) maximum 100 characters');

            $table->attributes['class'] = 'generaltable';

            foreach($group->members as $member) {
                if( has_capability('mod/grouppeerreview:bereviewed', $context, $member->userid)) {
                    $select = $this->get_select_grades($member, $group );
                    $comment = $this->get_comment_input($member, $group );
                    $table->data[] = array(
                        $member->firstname." ".$member->lastname,
                        $select,
                        $comment
                    );
                }
            }
            $html .= html_writer::table($table);
        }
        $html .= html_writer::empty_tag('input', array(
            'type' => 'submit',
            'value' => get_string('savereview', 'grouppeerreview'),
            'class' => 'btn btn-primary'
        ));

        $html .= html_writer::end_tag('form');
        return $html;
    }

    private function get_select_grades( $member, $group ) {

        $select  = "\r\n" .'<select name="review['.$group->id.']['.$member->userid.'][grade]">';
        $select .= "\r\n" .'<option value="">Select</option>';
        for( $i=0 ; $i<=5 ; $i++ ) {
            $selected = ( ($member->grade == $i && !is_null($member->grade))) ? ' selected' : '';
            $select .= "\r\n" .'<option value="'.$i.'"'.$selected.'>'.$i.'</option>';
        }
        $select .= "\r\n</select>";

        return $select;
    }

    private function get_comment_input( $member, $group ) {

        $comment = "\r\n" .'<input type="text" name="review['.$group->id.']['.$member->userid.'][comment]" value="'.$member->comment.'" maxlength="100" size="100">';

        return $comment;
    }

    public function group_select_form($groups, $groupid) {

        $select = "\r\n" .'<select name="groupid" onchange="this.form.submit()">';
        $select .= "\r\n" .'<option value="">Select a group</option>';
        foreach($groups as $group) {
            $selected = ( $group->id==$groupid ) ? ' selected' : '';
            $select .= "\r\n" .'<option value="'.$group->id.'"'.$selected.'>'.$group->name.'</option>';
        }
        $select .= "\r\n</select>";

        return $select;
    }

    public function group_completion_summary($peer) {

        $summary = grouppeerreview_get_summary($peer);  //TODO - change this so only count mod/peer:bereviewed people being graded

        $output  = "\r\n" . '<table class="generaltable">';
        $output .= "\r\n" . '<tr><th>Group</th><th>Members</th><th>Expected responses</th><th>Actual responses</th><th>Published to gradebook</th></tr>';
        foreach( $summary as $group ) {

            $grades = grouppeerreview_get_grade_items($peer, explode( ',', $group->users ));
            $grade_count = 0;
            foreach($grades as $grade) {
                if( isset($grade->grade) ) {$grade_count++;}
            }
            $output .= "\r\n" . '<tr><td>'.$group->name.'</td>';
            $output .= '<td>'.$group->member_count.'</td>';
            $output .= '<td>'.($group->member_count * $group->member_count).'</td>';
            $output .= '<td>'.$group->response_count.'</td>';
            $output .= '<td>'.$grade_count.'</td>';
        }
        $output .= "\r\n" . '</table>';

        return $output;
    }

    /**
     * Returns HTML to display choices result
     * @param object $choices
     * @param bool $forcepublish
     * @return string
     */
    public function display_result($choices, $forcepublish = false) {
        /*
        if (empty($forcepublish)) { //allow the publish setting to be overridden
            $forcepublish = $choices->publish;
        }

        $displaylayout = $choices->display;

        if ($forcepublish) {  //CHOICE_PUBLISH_NAMES
            return $this->display_publish_name_vertical($choices);
        } else {
            return $this->display_publish_anonymous($choices, $displaylayout);
        }
        */
    }
}

