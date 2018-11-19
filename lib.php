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
 * Library of functions and constants for module grouppeerreview
 * includes the main-part of grouppeerreview functions
 *
 * @package mod_grouppeerreview
 * @copyright
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** Include eventslib.php */
require_once($CFG->libdir.'/eventslib.php');
// Include forms lib.
require_once($CFG->libdir.'/formslib.php');

/**
 * this will create a new instance and return the id number
 * of the new instance.
 *
 * @global object
 * @param object $peer the object given by mod_grouppeerreview_mod_form
 * @return int
 */
function grouppeerreview_add_instance($peer) {
    global $DB;

    $peer->timemodified = time();
    $peer->id = '';
    // Get groupingid from assignment and use for peer review
    $groupingid = $DB->get_field('assign', 'teamsubmissiongroupingid', array("teamsubmission" => 1, "id" => $peer->assignid));
    $peer->grouping = $groupingid;
    
    if (empty($peer->site_after_submit)) {
        $peer->site_after_submit = '';
    }

    //saving the peer review in db
    $peerid = $DB->insert_record("grouppeerreview", $peer);
    $peer->id = $peerid;

    grouppeerreview_set_events($peer);

    grouppeerreview_grade_item_update($peer);

    if (!empty($peer->completionexpected)) {
        \core_completion\api::update_completion_date_event($peer->coursemodule, 'grouppeerreview', $peer->id,
            $peer->completionexpected);
    }

    return $peerid;
}

/**
 * this will update a given instance
 *
 * @global object
 * @param object $peer the object given by mod_grouppeerreview_mod_form
 * @return boolean
 */
function grouppeerreview_update_instance($peer) {
    global $DB;

    $peer->timemodified = time();
    $peer->id = $peer->instance;

    if (empty($peer->site_after_submit)) {
        $peer->site_after_submit = '';
    }

    //save the feedback into the db
    $DB->update_record("grouppeerreview", $peer);

    //create or update the new events
    grouppeerreview_set_events($peer);
    $completionexpected = (!empty($peer->completionexpected)) ? $peer->completionexpected : null;
    \core_completion\api::update_completion_date_event($peer->coursemodule, 'grouppeerreview', $peer->id, $completionexpected);

    grouppeerreview_grade_item_update($peer);
    return true;
}
function grouppeerreview_get_grade_items($peer, $users) {
    global $CFG;

    if (!function_exists('grade_update')) { // Workaround for buggy PHP versions.
        require_once($CFG->libdir . '/gradelib.php');
    }

    $grading_info = grade_get_grades($peer->course, 'mod', 'grouppeerreview', $peer->id, $users);
    /*
    print "<pre>";
    print_r($grading_info);
    print "</pre>";
    */
    return $grading_info->items[0]->grades;
}
function grouppeerreview_grade_item_update($peer, $grades=null) {
    global $CFG;

    if (!function_exists('grade_update')) { // Workaround for buggy PHP versions.
        require_once($CFG->libdir . '/gradelib.php');
    }
/*
    if (!$cm = get_coursemodule_from_id('grouppeerreview', $peer->coursemodule)) {
        print_error('invalidcoursemodule');
    }
*/

    $params = array('itemname' => $peer->name, 'idnumber' => $peer->coursemodule);
    if (isset($peer->maximumgrade)) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = $peer->maximumgrade;
    }
    // Recalculate rawgrade relative to grademax.
    if (isset($peer->rawgrade) && isset($peer->rawgrademax) && $peer->rawgrademax != 0) {
        // Get max grade Obs: do not try to use grade_get_grades because it
        // requires context which we don't have inside an ajax.
        $gradeitem = grade_item::fetch(array(
            'itemtype' => 'mod',
            'itemmodule' => 'grouppeerreview',
            'iteminstance' => $peer->id,
            'courseid' => $peer->course
        ));
        if (isset($gradeitem) && isset($gradeitem->grademax)) {
            $grades->rawgrade = ($peer->rawgrade / $peer->rawgrademax) * $gradeitem->grademax;
        }
    }
    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }
    return grade_update('mod/grouppeerreview', $peer->course, 'mod', 'grouppeerreview', $peer->id, 0, $grades, $params);
}


/**
 * this will delete a given instance.
 * all referenced data also will be deleted
 *
 * @global object
 * @param int $id the instanceid of peer
 * @return boolean
 */
function grouppeerreview_delete_instance($id) {
    global $DB;

    if (!$peer = $DB->get_record('grouppeerreview', array('id'=>$id))) {
        return false;
    }

    if (!$cm = get_coursemodule_from_id('grouppeerreview', $peer->coursemodule)) {
        print_error('invalidcoursemodule');
    }

    $result = true;

    //deleting the review marks
    if(!$DB->delete_records("grouppeerreview_marks", array("peerid"=>$id))) {
        $result = false;
    }

    //deleting the review final marks
    if(!$DB->delete_records("grouppeerreview_final_mark", array("peerid"=>$id))) {
        $result = false;
    }

    //deleting old events
    if(!$DB->delete_records('event', array('modulename'=>'grouppeerreview', 'instance'=>$id))) {
        $result = false;
    }

    if(!$DB->delete_records("grouppeerreview", array("id"=>$id))) {
        $result = false;
    }

    grade_update('mod/grouppeerreview', $cm->course, 'mod', 'grouppeerreview', $peer->id, 0, NULL, array('deleted'=>1));

    return $result;
}

function grouppeerreview_set_events($peer) {
    global $DB, $CFG;
}

function grouppeerreview_get_editor_options() {
    return array('maxfiles' => EDITOR_UNLIMITED_FILES,
        'trusttext'=>true);
}

/**
 * Check if a choice is available for the current user.
 *
 * @param  stdClass  $choice            choice record
 * @return array                       status (available or not and possible warnings)
 */
function grouppeerreview_get_availability_status($peer) {
    $available = true;
    $warnings = array();

    $timenow = time();

    if (!empty($peer->timeopen) && ($peer->timeopen > $timenow)) {
        $available = false;
        $warnings['notopenyet'] = userdate($peer->timeopen);
    } else if (!empty($peer->timeclose) && ($timenow > $peer->timeclose)) {
        $available = false;
        $warnings['expired'] = userdate($peer->timeclose);
    }
    /*
    if (!$peer->allowupdate && choice_get_my_response($peer)) {
        $available = false;
        $warnings['choicesaved'] = '';
    }
*/
    // Choice is available.
    return array($available, $warnings);
}

function grouppeerreview_user_submit_response($reviews, $peer, $reviewerid) {
    global $DB;

    $timenow = time();
    $completion = true;

    foreach( $reviews as $groupid=>$users ) {

        foreach( $users as $userid=>$review ) {
            $conditions = array(
                "peerid"=>$peer->id,
                "groupid"=>$groupid,
                "userid"=>$userid,
                "reviewerid"=>$reviewerid
            );
            if( $current = $DB->get_record( "grouppeerreview_marks", $conditions ) ) {
                if($review['grade']=="") {
                    $current->grade = null;
                    $completion = false;
                } else {
                    $current->grade = $review['grade'];
                }
                //$current->grade = ($review['grade']=="") ? null : $review['grade'];
                $current->comment = $review['comment'];
                $current->timemodified = $timenow;
                $DB->update_record("grouppeerreview_marks", $current);
            } else {
                // There is data to write
                $insert = $conditions;
                if($review['grade']=="") {
                    $insert['grade'] = null;
                    $completion = false;
                } else {
                    $insert['grade'] = $review['grade'];
                }
                //$insert['grade'] = ($review['grade']=="") ? null : $review['grade'];
                $insert['comment'] = $review['comment'];
                $insert['timemodified'] = $timenow;
                $DB->insert_record("grouppeerreview_marks", $insert);
            }
        }
    }
    return $completion;
}


/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $peer       peer object
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 * @since Moodle 3.0
 */
function grouppeerreview_view($peer, $course, $cm, $context) {

    // Trigger course_module_viewed event.
    $params = array(
        'context' => $context,
        'objectid' => $peer->id
    );

    $event = \mod_choice\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('grouppeerreview', $peer);
    $event->trigger();

}

function grouppeerreview_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return false;
        case FEATURE_GROUPINGS:               return false;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_HAS_RULES:    return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;

        default: return null;
    }
}

/**
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @param object $peer
 * @param object $cm
 * @param int $groupmode
 * @param bool $onlyactive Whether to get response data for active users only.
 * @return array
 */
function grouppeerreview_get_response_data($peer, $cm) {
    global $CFG, $USER, $DB;

    $context = context_module::instance($cm->id);
/*
/// Get the current group
    if ($groupmode > 0) {
        $currentgroup = groups_get_activity_group($cm);
    } else {
        $currentgroup = 0;
    }

/// Initialise the returned array, which is a matrix:  $allresponses[responseid][userid] = responseobject
    $allresponses = array();

/// First get all the users who have access here
/// To start with we assume they are all "unanswered" then move them later
    $extrafields = get_extra_user_fields($context);
    $allresponses[0] = get_enrolled_users($context, 'mod/choice:choose', $currentgroup,
        user_picture::fields('u', $extrafields), null, 0, 0, $onlyactive);

/// Get all the recorded responses for this choice
    $rawresponses = $DB->get_records('choice_answers', array('choiceid' => $choice->id));

/// Use the responses to move users into the correct column

    if ($rawresponses) {
        $answeredusers = array();
        foreach ($rawresponses as $response) {
            if (isset($allresponses[0][$response->userid])) {   // This person is enrolled and in correct group
                $allresponses[0][$response->userid]->timemodified = $response->timemodified;
                $allresponses[$response->optionid][$response->userid] = clone($allresponses[0][$response->userid]);
                $allresponses[$response->optionid][$response->userid]->answerid = $response->id;
                $answeredusers[] = $response->userid;
            }
        }
        foreach ($answeredusers as $answereduser) {
            unset($allresponses[0][$answereduser]);
        }
    }
    return $allresponses;
*/

    /**
     * Get my responses on a given peer review.
     *
     * @param stdClass $peer Peer review record
     * @return array of Peer review records
     * @since  Moodle 3.0
     */
    function grouppeerreview_get_my_response($peer) {
        global $DB, $USER;
        return $DB->get_records('grouppeerreview_marks', array('peerid' => $peer->id, 'reviewerid' => $USER->id), 'id');
    }

}

/**
 * Return true if we are allowd to view the Peer review results.
 *
 * @param stdClass $peer Peer review record
 * @param rows|null $current my Peer review responses
 * @param bool|null $peeropen if the Peer review is open
 * @return bool true if we can view the results, false otherwise.
 * @since  Moodle 3.0
 */
function grouppeerreview_can_view_results($peer, $current = null, $peereopen = null) {
/*
    if (is_null($peeropen)) {
        $timenow = time();

        if ($peer->timeopen != 0 && $timenow < $peer->timeopen) {
            // If the choice is not available, we can't see the results.
            return false;
        }

        if ($peer->timeclose != 0 && $timenow > $peer->timeclose) {
            $choiceopen = false;
        } else {
            $choiceopen = true;
        }
    }
    if (empty($current)) {
        $current = grouppeerreview_get_my_response($peer);
    }

    if (($peer->showresults == CHOICE_SHOWRESULTS_ALWAYS ||
        $peer->showresults == CHOICE_SHOWRESULTS_AFTER_ANSWER and !empty($current)) ||
        ($peer->showresults == CHOICE_SHOWRESULTS_AFTER_CLOSE and !$peer)) {
        return true;
    }
*/
    return false;
}
/*
function grouppeerreview_get_groups($peer) {
    global $DB;

    $groups = $DB->get_records_sql( "
        SELECT 
          gr.id
        , gr.name
        FROM {groups} AS gr
        JOIN {groupings_groups} AS gg 
        WHERE gr.id = gg.groupid
        AND gg.groupingid = ".$peer->grouping );

    return $groups;
}
*/
/*
function grouppeerreview_get_group_members($peer, $groupid) {
    global $DB;

    $groups = $DB->get_records_sql(" 
        SELECT
          u.id AS userid
        , u.firstname
        , u.lastname
        FROM {user} AS u 
        JOIN {groups_members} AS gm 
        WHERE gm.userid = u.id
        AND gm.groupid = ".$groupid."
        ORDER BY u.lastname, u.firstname");

    return $groups;
}
*/
function grouppeerreview_get_group_member_grades($peer, $groupid, $userid) {
    global $DB;

    $grades = $DB->get_records_sql(" 
        SELECT
          u.id AS reviewerid
        , u.firstname
        , u.lastname
        , prm.grade
        , prm.comment
        , prm.timemodified
        , reviewer.total
        FROM {groups_members} AS gm
        LEFT JOIN {user} AS u 
          ON u.id = gm.userid
        LEFT JOIN {grouppeerreview_marks} AS prm 
          ON prm.groupid = gm.groupid
          AND prm.reviewerid = gm.userid
          AND prm.peerid = ".$peer->id."
          AND prm.userid = ".$userid."
        LEFT JOIN (
            SELECT prm2.reviewerid, SUM(prm2.grade) AS total 
            FROM {grouppeerreview_marks} AS prm2
            WHERE prm2.groupid = ".$groupid."
            AND prm2.peerid = ".$peer->id."
            GROUP BY prm2.reviewerid
        ) AS reviewer ON prm.reviewerid = reviewer.reviewerid
        WHERE gm.groupid = ".$groupid."
        ORDER BY u.lastname, u.firstname");

    return $grades;
}

function grouppeerreview_get_group_mark($peer, $members) {
    global $DB;

    $groupmark = null;
    $conditions = array();
    $conditions['assignment'] = $peer->assignid;
    foreach( $members as $member ) {
        $conditions['userid'] = $member->id;
        $groupmark = $DB->get_field("assign_grades", "grade", $conditions );
        if($groupmark>0) return $groupmark;
    }

    return null;
}
/**
 * @param array $user
 * @param object $cm
 * @return void Output is echo'd
 */
function grouppeerreview_show_reportlink($user, $cm, $peer) {
    global $DB;

    $responsecount = $DB->count_records("grouppeerreview_marks", array("peerid"=>$peer->id));

    echo '<div class="reportlink">';
    echo "<a href=\"report.php?id=$cm->id\">".get_string("viewallresponses", "peer", $responsecount)."</a>";
    echo '</div>';
}

/**
 * @global object
 * @param object $peer
 * @param object $user
 * @param object $coursemodule
 * @param array $allresponses
 * @return array
 */
function grouppeerreview_prepare_options($peer, $user) {
    global $DB;

    // TODO - If no grouping on assignment just use all course groups
    // if $peer->grouping == 0
    
    $prdisplay = array();
    $prdisplay['peerid'] = $peer->id;

    if ($peer->grouping > 0) {
        $groups = $DB->get_records_sql("
        SELECT
          gr.id
        , gr.name
        FROM
          {groups_members} AS gm
        , {groups} AS gr
        , {groupings_groups} AS gg
        WHERE gg.groupid = gr.id
        AND gr.id = gm.groupid
        AND gm.userid = " . $user->id . "
        AND gg.groupingid = " . $peer->grouping
        );
    } else {
        $groups = $DB->get_records_sql("
        SELECT
          gr.id
        , gr.name
        FROM
          {groups_members} AS gm
        , {groups} AS gr
        WHERE gr.id = gm.groupid
        AND gm.userid = " . $user->id
        );
    }

    foreach( $groups as $key=>$group ) {

         $members = $DB->get_records_sql("
            SELECT
              gm.userid
            , u.firstname
            , u.lastname
            , gm.groupid
            , prm.grade
            , prm.comment
            FROM
              {groups_members} AS gm
            , {user} AS u
            LEFT JOIN
              {grouppeerreview_marks} AS prm
              ON prm.userid = u.id 
              AND prm.groupid = " . $group->id . " 
              AND prm.peerid = " . $peer->id . "
              AND prm.reviewerid = ".$user->id."
            WHERE gm.userid = u.id
            AND gm.groupid = " . $group->id . "
            ORDER BY u.lastname, u.firstname"
         );

         $groups[$key]->members = $members;
    }

    $prdisplay['groups'] = $groups;
/*
    $prdisplay = array('options'=>array());

    $prdisplay['limitanswers'] = true;
    $context = context_module::instance($coursemodule->id);

    foreach ($peer->option as $optionid => $text) {
        if (isset($text)) { //make sure there are no dud entries in the db with blank text values.
            $option = new stdClass;
            $option->attributes = new stdClass;
            $option->attributes->value = $optionid;
            $option->text = format_string($text);
            $option->maxanswers = $peer->maxanswers[$optionid];
            $option->displaylayout = $peer->display;

            if (isset($allresponses[$optionid])) {
                $option->countanswers = count($allresponses[$optionid]);
            } else {
                $option->countanswers = 0;
            }
            if ($DB->record_exists('grouppeerreview_marks', array('peerid' => $peer->id, 'reviewerid' => $user->id, 'optionid' => $optionid))) {
                $option->attributes->checked = true;
            }
            if ( $peer->limitanswers && ($option->countanswers >= $option->maxanswers) && empty($option->attributes->checked)) {
                $option->attributes->disabled = true;
            }
            $prdisplay['options'][] = $option;
        }
    }
    
    $prdisplay['hascapability'] = is_enrolled($context, NULL, 'mod/gouppeerreview:review'); //only enrolled users are allowed to peer review

    if ($peer->allowupdate && $DB->record_exists('grouppeerreview_review_marks', array('peerid'=> $peer->id, 'reviewerid'=> $user->id))) {
        $prdisplay['allowupdate'] = true;
    }

    if ($peer->showpreview && $peer->timeopen > time()) {
        $prdisplay['previewonly'] = true;
    }
*/
    return $prdisplay;
}

function prepare_grouppeerreview_show_results($peer, $course, $cm, $groupid) {
    global $DB;


}


function grouppeerreview_get_peer($peerid) {
    global $DB;

    if ($peer = $DB->get_record("grouppeerreview", array("id" => $peerid))) {
        return $peer;
    }
    return false;
}

function grouppeerreview_get_summary($peer)
{
    global $DB;
    if ($peer->grouping > 0) {
        $grouping_sql = "
        JOIN mdl_groupings_groups AS gg
        WHERE gg.groupingid = " . $peer->grouping . "
        AND gg.groupid = members.groupid";
    } else {
        $grouping_sql = "";
    }
    $summary = $DB->get_records_sql("
    SELECT
      members.groupid
    , members.name
    , IFNULL(members.member_count, 0) AS member_count
    , IFNULL(responses.response_count, 0) AS response_count
    , members.users
      FROM
    (
    SELECT 
      gr.id AS groupid
    , gr.name
    , COUNT(*) AS member_count
    , GROUP_CONCAT(gm.userid) AS users
    FROM mdl_groups AS gr
    JOIN mdl_groups_members AS gm
    WHERE gm.groupid = gr.id
    AND gr.courseid = " . $peer->course . "
    GROUP BY gr.id
    ORDER BY gr.name
    ) AS members
    LEFT JOIN 
    (
    SELECT 
      prm.groupid
    , COUNT(*) AS response_count
    FROM mdl_grouppeerreview_marks AS prm
    WHERE prm.peerid = " . $peer->id . "
    GROUP BY prm.groupid
    ) AS responses ON members.groupid = responses.groupid" . $grouping_sql);
        /*
        $summary = $DB->get_records_sql("
        SELECT
          gr.id
        , gr.name
        , IFNULL(members . total, 0) AS member_count
        , IFNULL(responses . total, 0) AS response_count
        , members.users
        FROM
        {grouppeerreview} AS p 
        LEFT JOIN {groupings_groups} AS gg ON p . grouping = gg . groupingid
        LEFT JOIN {groups} AS gr ON gr . id = gg . groupid
        LEFT JOIN
        (
            SELECT gm . groupid, gg . groupingid, count(*) AS total, GROUP_CONCAT(gm.userid) AS users
            FROM 
              {groups_members} AS gm
            , {groupings_groups} AS gg
            WHERE gm . groupid = gg . groupid
            GROUP BY gm . groupid, gg . groupingid
        ) AS members ON members . groupid = gg . groupid AND members . groupingid = p . grouping
        
        LEFT JOIN
        (
            SELECT gg . groupid, gg . groupingid, count(*) AS total
            FROM 
              {groupings_groups} AS gg
            , {grouppeerreview_marks} AS prm 
            WHERE prm . groupid = gg . groupid
            GROUP BY gg . groupid, gg . groupingid
        ) AS responses ON responses . groupid = gg . groupid AND responses . groupingid = p . grouping
        
        WHERE p . id = ".$peer->id);
        */
        
    return $summary;
}

?>