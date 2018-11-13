<?php

require_once("../../config.php");
require_once("lib.php");
require_once($CFG->libdir . '/completionlib.php');

$id         = required_param('id', PARAM_INT);                 // Course Module ID
$action     = optional_param('action', '', PARAM_ALPHANUMEXT);
$attemptids = optional_param_array('attemptid', array(), PARAM_INT); // Get array of responses to delete or modify.
$userids    = optional_param_array('userid', array(), PARAM_INT); // Get array of users whose peers reviews need to be modified.
$notify     = optional_param('notify', '', PARAM_ALPHA);

$url = new moodle_url('/mod/grouppeerreview/view.php', array('id'=>$id));
if ($action !== '') {
    $url->param('action', $action);
}
$PAGE->set_url($url);

if (! $cm = get_coursemodule_from_id('grouppeerreview', $id)) {
    print_error('invalidcoursemodule');
}

if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
    print_error('coursemisconf');
}

require_course_login($course, false, $cm);

if (!$peer = $DB->get_record("grouppeerreview", array("id" => $cm->instance))) {
    print_error('invalidcoursemodule');
}

$strpeer = get_string('modulename', 'grouppeerreview');
$strpeers = get_string('modulenameplural', 'grouppeerreview');

$context = context_module::instance($cm->id);

list($peeravailable, $warnings) = grouppeerreview_get_availability_status($peer);

if ($action == 'delpeer' and confirm_sesskey() and is_enrolled($context, NULL, 'mod/grouppeerreview:review') and $peer->allowupdate
        and $peeravailable) {
    $answercount = $DB->count_records('grouppeerreview_answers', array('peerid' => $peer->id, 'userid' => $USER->id));
    if ($answercount > 0) {
        $peeranswers = $DB->get_records('grouppeerreview_answers', array('peerid' => $peer->id, 'userid' => $USER->id),
            '', 'id');
        $todelete = array_keys($peeranswers);
        grouppeerreview_delete_responses($todelete, $peer, $cm, $course);
        redirect("view.php?id=$cm->id");
    }
}

$PAGE->set_title($peer->name);
$PAGE->set_heading($course->fullname);

/// Submit any new data if there is any
if (data_submitted() && !empty($action) && confirm_sesskey()) {
    $timenow = time();
    if (has_capability('mod/grouppeerreview:deleteresponses', $context)) {
        if ($action === 'delete') {
            // Some responses need to be deleted.
            grouppeerreview_delete_responses($attemptids, $peer, $cm, $course);
            redirect("view.php?id=$cm->id");
        }
        if (preg_match('/^choose_(\d+)$/', $action, $actionmatch)) {
            // Modify responses of other users.
            $newoptionid = (int)$actionmatch[1];
            grouppeerreview_modify_responses($userids, $attemptids, $newoptionid, $peer, $cm, $course);
            redirect("view.php?id=$cm->id");
        }
    }
    if (has_capability('mod/grouppeerreview:deleteresponses', $context)) {
        $reviews = $_POST["review"]; //TODO - this should be validated like all other POST variables
        $is_complete = grouppeerreview_user_submit_response($reviews, $peer, $USER->id);

        // Update completion state
        $completion=new completion_info($course);
        if($completion->is_enabled($cm) && $is_complete) {
            $completion->update_state($cm,COMPLETION_COMPLETE);
        } else {
            $completion->update_state($cm,COMPLETION_INCOMPLETE);
        }

        redirect(new moodle_url('/mod/grouppeerreview/view.php',
            array('id' => $cm->id, 'notify' => 'peersaved', 'sesskey' => sesskey())));
    }
/*
    // Redirection after all POSTs breaks block editing, we need to be more specific!
    if ($peer->allowmultiple) {
        $answer = optional_param_array('answer', array(), PARAM_INT);
    } else {
        $answer = optional_param('answer', '', PARAM_INT);
    }

    if (!$peeravailable) {
        $reason = current(array_keys($warnings));
        throw new moodle_exception($reason, 'grouppeerreview', '', $warnings[$reason]);
    }
*/


    if ($reviews && is_enrolled($context, null, 'mod/grouppeerreview:review')) {


    }
}

// Completion and trigger events.
grouppeerreview_view($peer, $course, $cm, $context);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($peer->name), 2, null);

if ($notify and confirm_sesskey()) {
    if ($notify === 'peersaved') {
        echo $OUTPUT->notification(get_string('peersaved', 'grouppeerreview'), 'notifysuccess');
    } else if ($notify === 'mustchooseone') {
        echo $OUTPUT->notification(get_string('mustchooseone', 'grouppeerreview'), 'notifyproblem');
    }
}

/// Display the peer and possibly results
$eventdata = array();
$eventdata['objectid'] = $peer->id;
$eventdata['context'] = $context;

/// Check to see if groups are being used in this peer
$groupmode = groups_get_activity_groupmode($cm);

if ($groupmode) {
    groups_get_activity_group($cm, true);
    groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/grouppeerreview/view.php?id='.$id);
}

// Check if we want to include responses from inactive users.
//$onlyactive = $peer->includeinactive ? false : true;

$allresponses = grouppeerreview_get_response_data($peer, $cm);

if (has_capability('mod/grouppeerreview:readresponses', $context)) {
    grouppeerreview_show_reportlink($allresponses, $cm, $peer);
}

echo '<div class="clearer"></div>';


//if user has already made a selection, and they are not allowed to update it or if peer is not open, show their selected answer.
/*
if (isloggedin() && (!empty($current)) &&
    (empty($peer->allowupdate) || ($timenow > $peer->timeclose)) ) {
    $peertexts = array();
    foreach ($current as $c) {
        $peertexts[] = format_string(grouppeerreview_get_option_text($peer, $c->optionid));
    }
    echo $OUTPUT->box(get_string("yourselection", "peer", userdate($peer->timeopen)).": ".implode('; ', $peertexts), 'generalbox', 'yourselection');
}
*/
/// Print the form


//if ( (!$current or $peer->allowupdate) and $peeropen and is_enrolled($context, NULL, 'mod/gouppeerreview:review')) {
// They haven't made their peer yet or updates allowed and peer is open

    $options = grouppeerreview_prepare_options($peer, $USER);

if (count($options["groups"]) > 0) {
    if ($peer->intro) {
        $peer->introformat = FORMAT_HTML;
        echo $OUTPUT->box(format_module_intro('grouppeerreview', $peer, $cm->id), 'generalbox', 'intro');
    }

    $timenow = time();
    $current = grouppeerreview_get_my_response($peer);
    
    $peeropen = true;
    if ((!empty($peer->timeopen)) && ($peer->timeopen > $timenow)) {
        if ($peer->showpreview) {
            echo $OUTPUT->box(get_string('previewonly', 'grouppeerreview', userdate($peer->timeopen)), 'generalbox alert');
        } else {
            echo $OUTPUT->box(get_string("notopenyet", "grouppeerreview", userdate($peer->timeopen)), "generalbox notopenyet");
            echo $OUTPUT->footer();
            exit;
        }
    } else if ((!empty($peer->timeclose)) && ($timenow > $peer->timeclose)) {
        echo $OUTPUT->box(get_string("expired", "grouppeerreview", userdate($peer->timeclose)), "generalbox expired");
        $peeropen = false;
    }    
    
    $renderer = $PAGE->get_renderer('mod_grouppeerreview');
    echo $renderer->display_options($options, $cm->id);
    $peerformshown = true; 
} else {
    $peerformshown = false;
    print "You cannot see the group peer review rating form because you are not in any of the groups that submitted an assignment.";
}
/*
if (!$peerformshown) {
    $sitecontext = context_system::instance();

    if (isguestuser()) {
        // Guest account
        echo $OUTPUT->confirm(get_string('noguestchoose', 'grouppeerreview').'<br /><br />'.get_string('liketologin'),
                     get_login_url(), new moodle_url('/course/view.php', array('id'=>$course->id)));
    } else if (!is_enrolled($context)) {
        // Only people enrolled can make a peer
        $SESSION->wantsurl = qualified_me();
        $SESSION->enrolcancel = get_local_referer(false);

        $coursecontext = context_course::instance($course->id);
        $courseshortname = format_string($course->shortname, true, array('context' => $coursecontext));

        echo $OUTPUT->box_start('generalbox', 'notice');
        echo '<p align="center">'. get_string('notenrolledchoose', 'grouppeerreview') .'</p>';
        echo $OUTPUT->container_start('continuebutton');
        echo $OUTPUT->single_button(new moodle_url('/enrol/index.php?', array('id'=>$course->id)), get_string('enrolme', 'core_enrol', $courseshortname));
        echo $OUTPUT->container_end();
        echo $OUTPUT->box_end();
    }
    // print the results at the bottom of the screen
    if (grouppeerreview_can_view_results($peer, $current, $peeropen)) {

        $results = prepare_grouppeerreview_show_results($peer, $course, $cm, $allresponses);
        $renderer = $PAGE->get_renderer('mod_grouppeerreview');
        $resultstable = $renderer->display_result($results);
        echo $OUTPUT->box($resultstable);

    } else if (!$peerformshown) {
        echo $OUTPUT->box(get_string('noresultsviewable', 'grouppeerreview'));
    }
}
*/
echo $OUTPUT->footer();