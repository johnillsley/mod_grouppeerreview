<?php

    require_once("../../config.php");
    require_once("lib.php");

    $id         = required_param('id', PARAM_INT);   //moduleid
    $download   = optional_param('download', '', PARAM_ALPHA);
    $action     = optional_param('action', '', PARAM_ALPHANUMEXT);
    $attemptids = optional_param_array('attemptid', array(), PARAM_INT); // Get array of responses to delete or modify.
    $userids    = optional_param_array('userid', array(), PARAM_INT); // Get array of users whose peers need to be modified.
    $groupid    = optional_param('groupid', null, PARAM_INT);
    $grades     = optional_param_array('finalgrade', null, PARAM_INT);

    $url = new moodle_url('/mod/peer/report.php', array('id'=>$id));
    if ($download !== '') {
        $url->param('download', $download);
    }
    if ($action !== '') {
        $url->param('action', $action);
    }
    $PAGE->set_url($url);
   //$PAGE->set_pagelayout('base');

    if (! $cm = get_coursemodule_from_id('grouppeerreview', $id)) {
        print_error("invalidcoursemodule");
    }

    if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
        print_error("coursemisconf");
    }

    require_login($course, false, $cm);

    $context = context_module::instance($cm->id);

    require_capability('mod/grouppeerreview:readresponses', $context);

    if (!$peer = grouppeerreview_get_peer($cm->instance)) {
        print_error('invalidcoursemodule');
    }

    $strpeer = get_string("modulename", "peer");
    $strpeers = get_string("modulenameplural", "peer");
    $strresponses = get_string("responses", "peer");

    $eventdata = array();
    $eventdata['objectid'] = $peer->id;
    $eventdata['context'] = $context;
    $eventdata['courseid'] = $course->id;
    $eventdata['other']['content'] = 'peerreportcontentviewed';

    //$event = \mod_peer\event\report_viewed::create($eventdata);
    //$event->trigger();

    if (data_submitted() && has_capability('mod/grouppeerreview:deleteresponses', $context) && confirm_sesskey()) {
        if ($action === 'save_grades') {
            foreach( $grades as $k=>$g) {
                if(is_numeric($g)) {
                    $grades[$k] = array( 'userid'=>$k, 'rawgrade'=>$g);
                } else {
                    unset($grades[$k]);
                }
            }
            grouppeerreview_grade_item_update($peer, $grades);
            redirect("report.php?id=$cm->id&groupid=$groupid");
        }

        if ($action === 'delete') {
            // Delete responses of other users.
            grouppeerreview_delete_responses($attemptids, $peer, $cm, $course);
            redirect("report.php?id=$cm->id");
        }
        if (preg_match('/^choose_(\d+)$/', $action, $actionmatch)) {
            // Modify responses of other users.
            $newoptionid = (int)$actionmatch[1];
            grouppeerreview_modify_responses($userids, $attemptids, $newoptionid, $peer, $cm, $course);
            redirect("report.php?id=$cm->id");
        }
    }

    if (!$download) {
        $PAGE->navbar->add($strresponses);
        $PAGE->set_title(format_string($peer->name).": $strresponses");
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        echo $OUTPUT->heading($peer->name, 2, null);
        /// Check to see if groups are being used in this peer
        $groupmode = groups_get_activity_groupmode($cm);
        if ($groupmode) {
            groups_get_activity_group($cm, true);
            groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/peer/report.php?id='.$id);
        }
    } else {
        $groupmode = groups_get_activity_groupmode($cm);

        // Trigger the report downloaded event.
        $eventdata = array();
        $eventdata['context'] = $context;
        $eventdata['courseid'] = $course->id;
        $eventdata['other']['content'] = 'peerreportcontentviewed';
        $eventdata['other']['format'] = $download;
        $eventdata['other']['peerid'] = $peer->id;
        $event = \mod_grouppeerreview\event\report_downloaded::create($eventdata);
        $event->trigger();
    }

    $extrafields = get_extra_user_fields($context);

    $groups = groups_get_all_groups($course->id, 0, $peer->grouping, 'g.id, g.name');

    $renderer = $PAGE->get_renderer('mod_grouppeerreview');

    echo "\r\n" . '<h5>Completion summary</h5>';
    echo "\r\n" . '<p>'.get_string('weighting', 'grouppeerreview').': <strong>'.$peer->weighting.'%</strong></p>';
    echo $renderer->group_completion_summary($peer);

    echo "\r\n" . '<form action="report.php" method="get">';
    echo "\r\n" . '<input type="hidden" name="id" value="'.$id.'">';
    echo $renderer->group_select_form($groups, $groupid);
    echo "\r\n" . '</form>';

    if( isset($groupid) ) {

        $members = groups_get_members($groupid, 'u.id, u.firstname, u.lastname' );
        $gradebook_grades = grouppeerreview_get_grade_items($peer, array_column($members, 'id'));

        $groupmark = grouppeerreview_get_group_mark($peer, $members);
        $maxgrade = $DB->get_field( 'assign', 'grade', array('id'=>$peer->assignid));

        echo "\r\n" . '<h5>Group results</h5>';
        echo "\r\n" . '<p>'.get_string('groupmark', 'grouppeerreview').': <strong>';
        if( is_numeric($groupmark) ) {
            echo floatval($groupmark);
            $automatic_mark = $groupmark * $peer->weighting / $maxgrade;
            $adjusted_mark = $groupmark - $automatic_mark;
        } else {
            echo "Not marked";
        }
        echo '</strong></p>';
        echo "\r\n" . '<form action="report.php" method="post">';
        echo "\r\n" . '<input type="hidden" name="id" value="'.$id.'">';
        echo "\r\n" . '<input type="hidden" name="groupid" value="'.$groupid.'">';
        echo "\r\n" . '<input type="hidden" name="action" value="save_grades">';
        echo "\r\n" . '<input type="hidden" name="sesskey" value="'.sesskey().'">';
        echo "\r\n" . '<input type="submit" name="submit" value="Save individual marks to gradebook">';
        echo "\r\n" . '<table class="generaltable">';
        echo "\r\n" . '<tr><th>Student</th><th>Grades received</th><th>Adjustment</th><th>Calculated</th><th>Final mark</th></tr>';
        foreach( $members as $member) {
            if( has_capability('mod/grouppeerreview:bereviewed', $context, $member->id)) {

                echo "\r\n" . '<tr><td><a href="'.$CFG->wwwroot.'/user/view.php?id='.$member->id.'&course='.$course->id.'">'.$member->firstname.' '.$member->lastname.'</a></td>';

                $grades = grouppeerreview_get_group_member_grades($peer, $groupid, $member->id);
                $peer_grades = 0;
                $peer_total = 0;

                echo "\r\n" . '<td><table style="font-size:90%;">';
                foreach($grades as $grade) {
                    $display_grade = ( is_numeric($grade->grade ) ) ? floatval($grade->grade) : '-';
                    echo "\r\n" . '<tr><td>'.$grade->firstname.' '.$grade->lastname.'</td>';
                    echo '<td><strong>'.$display_grade.'</strong></td>';
                    echo '<td>'.$grade->comment.'</td></tr>';
                    if( is_numeric($grade->grade) ) {
                        $peer_grades++;
                        if( $grade->total>0 ) {
                            $peer_total += $grade->grade / $grade->total;
                        }
                    }
                }
                if( $peer_grades==0 ) {
                    $peer_weighting = 'No grades received';
                    $peer_calculated = '-';
                    $final_mark = '';
                } elseif( !is_numeric($groupmark)) {
                    $peer_weighting = round(( count($members)/$peer_grades ) * $peer_total, 2);
                    $peer_calculated = 'Group assignment submission not marked';
                    $final_mark = '';
                } else {
                    $peer_weighting = round( ( count($members)/$peer_grades ) * $peer_total, 2);
                    $peer_calculated = round( $peer_weighting * $adjusted_mark ) + $automatic_mark;
                    $final_mark = (isset($gradebook_grades[$member->id]->grade))
                                ? floatval($gradebook_grades[$member->id]->grade)
                                : $peer_calculated;
                }

                echo "\r\n" . '</table></td>';
                echo "\r\n" . '<td>'.$peer_weighting.'</td>';
                echo "\r\n" . '<td><strong>'.$peer_calculated.'</strong></td>';
                echo "\r\n" . '<td><input type="text" name="finalgrade['.$member->id.']" value="'.$final_mark.'" class="text" style="width: 50px;"/></td>';
                echo "\r\n" . '</tr>';
            }
        }
        echo "\r\n" . '</table>';
        echo "\r\n" . '</form>';
    }

   //now give links for downloading spreadsheets.
    if (!empty($users) && has_capability('mod/grouppeerreview:downloadresponses',$context)) {
        $downloadoptions = array();
        $options = array();
        $options["id"] = "$cm->id";
        $options["download"] = "ods";
        $button =  $OUTPUT->single_button(new moodle_url("report.php", $options), get_string("downloadods"));
        $downloadoptions[] = html_writer::tag('li', $button, array('class' => 'reportoption list-inline-item'));

        $options["download"] = "xls";
        $button = $OUTPUT->single_button(new moodle_url("report.php", $options), get_string("downloadexcel"));
        $downloadoptions[] = html_writer::tag('li', $button, array('class' => 'reportoption list-inline-item'));

        $options["download"] = "txt";
        $button = $OUTPUT->single_button(new moodle_url("report.php", $options), get_string("downloadtext"));
        $downloadoptions[] = html_writer::tag('li', $button, array('class' => 'reportoption list-inline-item'));

        $downloadlist = html_writer::tag('ul', implode('', $downloadoptions), array('class' => 'list-inline inline'));
        $downloadlist .= html_writer::tag('div', '', array('class' => 'clearfloat'));
        echo html_writer::tag('div',$downloadlist, array('class' => 'downloadreport m-t-1'));
    }
    echo $OUTPUT->footer();

