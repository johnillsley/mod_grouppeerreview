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
 * A custom renderer class that extends the plugin_renderer_base and
 * is used by the Group Peer Review module.
 *
 * @package    mod_grouppeerreview
 * @author     John Illsley <j.s.illsley@bath.ac.uk>
 * @copyright  2018 University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class mod_grouppeerreview_renderer extends plugin_renderer_base {

    /**
     * Returns HTML to display grouppeerreview student response form
     * @param object $options
     * @param int  $coursemoduleid
     * @param bool $vertical
     * @return string
     */
    public function display_options($grouppeerreview, $options, $coursemoduleid, $isopen = true) {
        global $USER;

        $target = new moodle_url('/mod/grouppeerreview/view.php');
        $attributes = array('method' => 'POST', 'action' => $target);
        $context = context_module::instance($coursemoduleid);

        $html  = html_writer::start_tag('div', array('id' => 'grouppeerreview-form', 'class' => 'grouppeerreview-form'));
        $html .= html_writer::start_tag('form', $attributes);
        $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
        $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'savereview'));
        $html .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id', 'value' => $coursemoduleid));

        foreach ($options['groups'] as $group) {
            $html .= html_writer::tag('h5', $group->name);

            $table = new html_table();
            $table->head = array(
                    get_string('groupmember', 'grouppeerreview'),
                    get_string('yourreview', 'grouppeerreview'),
                    get_string('comments', 'grouppeerreview') );
            $table->colclasses = array(null, null, 'grouppeerreview-fullwidth');

            $table->attributes['class'] = 'generaltable';
            foreach ($group->members as $member) {
                if (has_capability('mod/grouppeerreview:bereviewed', $context, $member->userid)) {
                    if ($grouppeerreview->selfassess || $member->userid != $USER->id) {
                        $gradeselect = $this->select_grades($grouppeerreview, $member, $group, $isopen);
                        $commentbox = $this->comment_input($member, $group, $isopen);

                        $table->data[] = array(
                                $member->firstname."&nbsp;".$member->lastname,
                                $gradeselect,
                                $commentbox
                        );
                    }
                }
            }
            $html .= html_writer::table($table);
        }
        if ($isopen) {
            $html .= html_writer::empty_tag('input', array(
                    'type' => 'submit',
                    'value' => get_string('savereview', 'grouppeerreview'),
                    'class' => 'btn btn-primary'
            ));
        }
        $html .= html_writer::end_tag('form');
        $html .= html_writer::end_tag('div');
        return $html;
    }

    private function select_grades($grouppeerreview, $member, $group, $isopen) {

        $html = '';

        if (!$isopen) {
            return "<strong>" . $member->grade . "</strong>";
        }
        $selectattributes = array(
                'name' => 'review[' . $group->id . '-' . $member->userid . '-grade]',
                'id' => 'review[' . $group->id . '-' . $member->userid . '-grade]'
        );
        $html .= html_writer::start_tag('select', $selectattributes);
        $html .= html_writer::tag('option', get_string('selectrating', 'grouppeerreview'), array('value' => ''));
        for ($i = 0; $i <= $grouppeerreview->maxrating; $i++) {
            if (!is_null($member->grade) && $member->grade == $i) {
                $optionattributes = array('value' => $i, 'selected' => 'selected');
            } else {
                $optionattributes = array('value' => $i);
            }
            $html .= html_writer::tag('option', $i, $optionattributes);
        }
        $html .= html_writer::end_tag('select');
        return $html;
    }

    private function comment_input( $member, $group, $isopen ) {

        if (!$isopen) {
            return $member->comment;
        }
        $textareaattributes = array(
                'name' => 'review[' . $group->id . '-' . $member->userid . '-comment]',
                'id' => 'review[' . $group->id . '-' . $member->userid . '-comment]',
                'class' => 'grouppeerreview-commentbox',
                'maxlength' => '1000'
        );
        $html = html_writer::tag('textarea', $member->comment, $textareaattributes);

        return $html;
    }

    /**
     * Returns a form for the user to select a group
     *
     * @param array $groups
     * @param integer $groupid indicates the selected group
     * @return string $html form element for selecting a group
     **/
    public function group_selector($cm, $groups, $groupid) {
        global $OUTPUT;

        // Select form element.
        $select = '';
        $formattributes = array(
                'action' => 'report.php#group-detail',
                'method' => 'get'
        );
        $inputattributes = array(
                'type' => 'hidden',
                'name' => 'id',
                'value' => $cm->id
        );
        $selectattributes = array(
                'id' => 'groupid',
                'name' => 'groupid',
                'onchange' => 'this.form.submit()'
        );
        $select .= html_writer::start_tag('form', $formattributes);
        $select .= html_writer::tag('input', null, $inputattributes);
        $select .= html_writer::start_tag('select', $selectattributes);
        $select .= html_writer::tag('option', get_string('selectagroup', 'grouppeerreview'));
        $groupidprev = $prev = null;
        $groupidnext = $next = null;
        foreach ($groups as $group) {
            if ($group->id == $groupid) {
                $optionattributes = array('value' => $group->id, 'selected' => 'selected');
                $groupidprev = $prev;
                $next = true;
            } else {
                $optionattributes = array('value' => $group->id);
                $prev = $group->id; // For previous group button.
                if ($next) {
                    $groupidnext = $group->id;
                    $next = false;
                }
            }
            $select .= html_writer::tag('option', $group->name, $optionattributes);
        }
        $select .= html_writer::end_tag('select');
        $select .= html_writer::end_tag('form');

        // Previous button.
        if ($groupidprev) {
            $attributes = array();
        } else {
            $attributes = array('disabled' => 'disabled');
        }
        $prevbutton = $OUTPUT->single_button(
                new moodle_url("report.php", array("id" => $cm->id, "groupid" => $groupidprev)),
                get_string('previousgroup', 'grouppeerreview'),
                'GET',
                $attributes
        );

        // Next button.
        if ($groupidnext) {
            $attributes = array();
        } else {
            $attributes = array('disabled' => 'disabled');
        }
        $nextbutton = $OUTPUT->single_button(
                new moodle_url("report.php", array("id" => $cm->id, "groupid" => $groupidnext)),
                get_string('nextgroup', 'grouppeerreview'),
                'GET',
                $attributes
        );

        $groupoptions = array();
        $groupoptions[] = html_writer::tag('li', $prevbutton, array('class' => 'reportoption list-inline-item'));
        $groupoptions[] = html_writer::tag('li', $select, array('class' => 'reportoption list-inline-item'));
        $groupoptions[] = html_writer::tag('li', $nextbutton, array('class' => 'reportoption list-inline-item'));
        $grouplist = html_writer::tag('ul', implode('', $groupoptions), array('class' => 'list-inline inline'));

        return html_writer::tag('div', $grouplist, array('class' => 'groupoptions', 'id' => 'groupoptions'));
    }

    /**
     * Returns the group summary report as html
     *
     * @param object $grouppeerreview
     * @param array $groups
     * @return string $html
     **/
    public function group_completion_summary($grouppeerreview, $groupssummary, $groupid) {

        $assign = get_coursemodule_from_instance('assign', $grouppeerreview->assignid);
        $assignlink = html_writer::link(new moodle_url('/mod/assign/view.php', array('id' => $assign->id)), $assign->name);
        $connectedassign = html_writer::tag('li', get_string("connectedassign", "grouppeerreview") . ": " . $assignlink);
        $weighting = html_writer::tag('li', get_string('weighting', 'grouppeerreview').': '.$grouppeerreview->weighting);
        if ($grouppeerreview->selfassess == 1) {
            $selfassess = html_writer::tag('li', get_string('selfassessyes', 'grouppeerreview'));
        } else {
            $selfassess = html_writer::tag('li', get_string('selfassessno', 'grouppeerreview'));
        }

        $html = '';
        $html .= html_writer::start_tag('div', array('id' => 'grouppeerreview_summaryreport'));
        $html .= $this->summary_intro($grouppeerreview);
        $html .= html_writer::tag('h4', get_string('completionsummary', 'grouppeerreview'));

        $table = new html_table();
        $table->head = array(
                get_string('groupname', 'grouppeerreview'),
                get_string('groupmembers', 'grouppeerreview'),
                get_string('expectedresponses', 'grouppeerreview'),
                get_string('actualresponses', 'grouppeerreview'),
                get_string('gradespublished', 'grouppeerreview'),
        );
        $table->attributes['class'] = 'generaltable';

        foreach ($groupssummary as $gsummary) {
            if ($gsummary->not_reviewed > 0) {
                $notreviewedtext = ' <em>(' . $gsummary->not_reviewed . ' ' .
                        get_string('notbeingreviewed', 'grouppeerreview') . ')</em>';
            } else {
                $notreviewedtext = '';
            }
            $responsescomplete = ($gsummary->expected_responses == $gsummary->actual_responses)
                    ? ' ' . $this->output->pix_icon('i/valid', get_string('yes'))
                    : '';
            $gradebookcomplete = (($gsummary->member_count - $gsummary->not_reviewed) == $gsummary->in_gradebook)
                    ? ' ' . $this->output->pix_icon('i/valid', get_string('yes'))
                    : '';

            $row = new html_table_row(array(
                    $gsummary->group_name,
                    $gsummary->member_count . $notreviewedtext,
                    $gsummary->expected_responses,
                    $gsummary->actual_responses . $responsescomplete,
                    $gsummary->in_gradebook . $gradebookcomplete));
            if ($gsummary->groupid == $groupid) {
                $row->attributes = array('class' => 'grouppeerreview-selected-group');
            }
            $table->data[] = $row;
        }
        $html .= html_writer::table($table);
        $html .= html_writer::end_tag('div');

        return $html;
    }

    public function summary_intro($grouppeerreview) {
        
        $assign = get_coursemodule_from_instance('assign', $grouppeerreview->assignid);
        $assignlink = html_writer::link(new moodle_url('/mod/assign/view.php', array('id' => $assign->id)), $assign->name);
        $connectedassign = html_writer::tag('li', get_string("connectedassign", "grouppeerreview") . ": " . $assignlink);
        $weighting = html_writer::tag('li', get_string('weighting', 'grouppeerreview').': '.$grouppeerreview->weighting);
        if ($grouppeerreview->selfassess == 1) {
            $selfassess = html_writer::tag('li', get_string('selfassessyes', 'grouppeerreview'));
        } else {
            $selfassess = html_writer::tag('li', get_string('selfassessno', 'grouppeerreview'));
        }
        
        return html_writer::tag('ul', $connectedassign . $weighting . $selfassess);
    }
    
    public function group_report($report, $groupid, $cm) {
        global $COURSE, $DB;

        $context = context_module::instance($cm->id);
        $formattributes = array(
                "action" => "report.php",
                "method" => "post"
        );
        $html = '';
        $html .= html_writer::tag('h4', 'Responses for selected group');
        $html .= html_writer::start_tag('div', array("id" => "grouppeerreview-report", "class" => "grouppeerreview-report"));
        $html .= html_writer::start_tag('form', $formattributes);
        $html .= html_writer::tag('input', '', array("type" => "hidden", "name" => "groupid", "value" => $groupid));
        $html .= html_writer::tag('input', '', array("type" => "hidden", "name" => "id", "value" => $cm->id));
        $html .= html_writer::tag('input', '', array("type" => "hidden", "name" => "action", "value" => "save_grades"));
        $html .= html_writer::tag('input', '', array("type" => "hidden", "name" => "sesskey", "value" => sesskey()));

        $table = new html_table();
        $table->head = array(
                '',
                get_string('students', 'grouppeerreview'),
                get_string('ratingsreceived', 'grouppeerreview'),
                get_string('weightedgroupmark', 'grouppeerreview'),
                get_string('weightedpeermark', 'grouppeerreview'),
                get_string('finalmark', 'grouppeerreview'));
        $table->attributes['class'] = 'generaltable';

        foreach ($report as $member) {
            $user = $DB->get_record('user', array("id" => $member->id));
            $urlparams = array('id' => $member->id, 'course' => $COURSE->id);
            $profileurl = new moodle_url('/user/view.php', $urlparams);
            $fullname = fullname($user);
            $gradetable = new html_table();
            $gradetable->attributes = array('class' => 'generaltable font-size-sm no-margin-bottom');
            $gradetable->colclasses = array(null, null, 'grouppeerreview-comment');

            foreach ($member->reviewed as $review) {
                if (is_null($review->grade)) {
                    $review->grade = get_string('noreply', 'grouppeerreview');
                } else {
                    $review->grade = '<strong>' . $review->grade . '</strong>';
                }
                $reviewername = (has_capability('mod/grouppeerreview:bereviewed', $context, $review->reviewerid))
                        ? $review->firstname . "&nbsp;" . $review->lastname
                        : "<em>" . $review->firstname . "&nbsp;" . $review->lastname . "</em>";

                $gradetable->data[] = array(
                        $reviewername,
                        $review->grade,
                        $review->comment,
                );
            }

            $groupmark = (is_numeric($member->adjusted_group_mark))
                    ? round($member->adjusted_group_mark, 2)
                    : '<span class="font-size-sm">' . get_string('assignmentnotmarked', 'grouppeerreview') . '</span>';
            if (isset($member->gradebook_grade)) {
                $finalgrade = $member->gradebook_grade;
                $gradenote = '<br/><span class="font-size-sm grade-saved">' .
                        get_string('gradesaved', 'grouppeerreview') . '</span>';
                $calcnote = '<br/><span class="font-size-sm">Calculated:&nbsp;'
                        . round($member->adjusted_group_mark + $member->peer_mark, 2)
                        . '</span>';
            } else {
                $finalgrade = $member->adjusted_group_mark + $member->peer_mark;
                $gradenote = '';
                $calcnote = '';
            }

            $gradebox = html_writer::tag('input', null, array(
                    "type" => "text",
                    "name" => "finalgrade[$member->id]",
                    "value" => round($finalgrade, 2),
                    "class" => "grade text"
            ));

            $table->data[] = array(
                    $this->output->user_picture($user),
                    $this->output->action_link($profileurl, $fullname),
                    html_writer::table($gradetable),
                    $groupmark,
                    round($member->peer_mark, 2),
                    $gradebox . $gradenote . $calcnote
            );
        }
        $html .= html_writer::table($table);
        $html .= html_writer::empty_tag('input', array(
                'type' => 'submit',
                'value' => get_string('savetogradebook', 'grouppeerreview'),
                'class' => 'btn btn-primary'
        ));
        $html .= html_writer::end_tag('form');
        $html .= html_writer::end_tag('div');

        return $html;
    }

    public function nothing_to_review() {

        $html = html_writer::tag('p', get_string('nothingtoreview', 'grouppeerreview'));

        return $html;
    }

    /**
     * @param integer
     * @return $html
     */
    public function show_reportlink($grouppeerreview, $cm) {
        global $DB;

        $responsecount = grouppeerreview_get_response_count($grouppeerreview);

        $html  = html_writer::start_tag('div', array('class' => 'reportlink'));
        $html .= html_writer::tag(
                'a',
                get_string("viewallresponses", "grouppeerreview", $responsecount),
                array('href' => 'report.php?id=' . $cm->id)
        );
        $html .= html_writer::end_tag('div');

        return $html;
    }

    public function download_report_buttons($cm) {
        global $OUTPUT;

        $downloadoptions = array();
        $options = array();
        $options["id"] = "$cm->id";

        $options["download"] = "ods";
        $button = $OUTPUT->single_button(new moodle_url("report.php", $options), get_string("downloadods"));
        $downloadoptions[] = html_writer::tag('li', $button, array('class' => 'reportoption list-inline-item'));

        $options["download"] = "xls";
        $button = $OUTPUT->single_button(new moodle_url("report.php", $options), get_string("downloadexcel"));
        $downloadoptions[] = html_writer::tag('li', $button, array('class' => 'reportoption list-inline-item'));

        $options["download"] = "txt";
        $button = $OUTPUT->single_button(new moodle_url("report.php", $options), get_string("downloadtext"));
        $downloadoptions[] = html_writer::tag('li', $button, array('class' => 'reportoption list-inline-item'));

        $downloadlist = html_writer::tag('ul', implode('', $downloadoptions), array('class' => 'list-inline inline'));
        $downloadlist .= html_writer::tag('div', '', array('class' => 'clearfloat'));

        return html_writer::tag('div', $downloadlist, array('class' => 'downloadreport m-t-1'));
    }

    public function report_summary() {

        $algorithmexplained = get_config('mod_grouppeerreview', 'algorithmexplained');
        return html_writer::tag(
                'div',
                $algorithmexplained,
                array('class' => 'alert alert-info alert-block')
        );
    }
}
