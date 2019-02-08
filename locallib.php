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
 * Local library of functions and constants for module grouppeerreview
 * includes the main-part of grouppeerreview functions
 *
 * @package    mod_grouppeerreview
 * @author     John Illsley <j.s.illsley@bath.ac.uk>
 * @copyright  2018 University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function grouppeerreview_get_grade_items($grouppeerreview, $users) {
    global $CFG;

    if (!function_exists('grade_update')) { // Workaround for buggy PHP versions.
        require_once($CFG->libdir . '/gradelib.php');
    }

    $gradinginfo = grade_get_grades($grouppeerreview->course, 'mod', 'grouppeerreview', $grouppeerreview->id, $users);

    return $gradinginfo->items[0]->grades;
}

function grouppeerreview_get_summary($grouppeerreview, $groups) {

    $cm = get_coursemodule_from_instance('grouppeerreview', $grouppeerreview->id);
    $context = context_module::instance($cm->id);
    $groupsummary = array();
    foreach ($groups as $group) {
        $g = new stdClass();
        $users = array();
        $g->groupid = $group->id;
        $g->group_name = $group->name;
        $bereviewed = 0;
        $ingradebook = 0;
        $groupmembers = groups_get_members($group->id);
        foreach ($groupmembers as $groupmember) {
            if (has_capability('mod/grouppeerreview:bereviewed', $context, $groupmember->id)) {
                $bereviewed++;
                $users[] = $groupmember->id;
            }
        }
        $g->member_count = count($groupmembers);
        $g->not_reviewed = $g->member_count - $bereviewed;
        if ($grouppeerreview->selfassess == 1) {
            $g->expected_responses = $g->member_count * $bereviewed;
        } else {
            $g->expected_responses = ($g->member_count - 1) * $bereviewed;
        }
        $reviews = grouppeerreview_get_reviews($grouppeerreview, $group->id);
        $g->actual_responses = count($reviews);
        // Get gradebook entries.
        $gradebookentries = grouppeerreview_get_grade_items($grouppeerreview, $users);
        foreach ($gradebookentries as $entry) {
            if (isset($entry->grade)) {
                $ingradebook++;
            }
        }
        $g->in_gradebook = $ingradebook;

        $groupsummary[] = $g;
    }
    return $groupsummary;
}

function grouppeerreview_get_reviews($grouppeerreview, $groupid = null, $userid = null, $reviewerid = null) {
    global $DB;

    $params = array();
    $params[] = $grouppeerreview->id;

    if (isset($groupid)) {
        $sqlgroupid = 'AND groupid = ?';
        $params[] = $groupid;
    } else {
        $sqlgroupid = '';
    }

    if (isset($userid)) {
        $sqluserid = 'AND userid = ?';
        $params[] = $userid;
    } else {
        $sqluserid = '';
    }

    if (isset($reviewerid)) {
        $sqlreviewerid = 'AND reviewerid = ?';
        $params[] = $reviewerid;
    } else {
        $sqlreviewerid = '';
    }

    $reviews = $DB->get_records_sql('
        SELECT
          gprm.id
        , gprm.peerid
        , gprm.groupid
        , gprm.userid
        , gprm.reviewerid
        , gprm.grade
        , gprm.comment
        , gprm.timemodified
        , grp.name AS groupname
        , u1.firstname userfirstname
        , u1.lastname userlastname
        , u2.firstname reviewerfirstname
        , u2.lastname reviewerlastname
        FROM {grouppeerreview_marks} gprm
        LEFT JOIN {user} u1 ON u1.id = gprm.userid
        LEFT JOIN {user} u2 ON u2.id = gprm.reviewerid
        LEFT JOIN {groups} grp ON grp.id = gprm.groupid
        WHERE gprm.peerid = ?
        ' . $sqlgroupid . '
        ' . $sqluserid . '
        ' . $sqlreviewerid . '
        AND u1.deleted = 0
        AND u2.deleted = 0
        AND gprm.grade IS NOT NULL
        ORDER BY gprm.groupid, u1.lastname, u1.firstname, u2.lastname, u2.firstname
        ', $params);

    return $reviews;
}

function grouppeerreview_check_all_responses($grouppeerreview, $userid) {
    $userresponses = grouppeerreview_prepare_options($grouppeerreview, $userid);

    $allcomplete = true;
    foreach ($userresponses["groups"] as $groups) {
        foreach ($groups->members as $review) {
            if ($review->grade == "") {
                $allcomplete = false;
                break 2;
            }
        }

    }
    return $allcomplete;
}

function grouppeerreview_get_report($grouppeerreview, $groupid) {
    global $DB;

    $cm = get_coursemodule_from_instance('grouppeerreview', $grouppeerreview->id);
    $context = context_module::instance($cm->id);
    $members = groups_get_members($groupid, 'u.id, u.firstname, u.lastname');
    
    $gradebookgrades = grouppeerreview_get_grade_items($grouppeerreview, array_column($members, 'id'));
    $groupmark = grouppeerreview_get_group_mark($grouppeerreview, $members);
    $maxgrade = $DB->get_field('assign', 'grade', array('id' => $grouppeerreview->assignid));
    if (is_numeric($groupmark)) {
        $peerreviewbase = $groupmark * $grouppeerreview->weighting / $maxgrade;
        $adjustedmark = $groupmark - $peerreviewbase;
    } else {
        $peerreviewbase = $maxgrade; // For display of results in the absence of a group mark.
        $adjustedmark = null;
    }

    $report = array();
    foreach ($members as $member) {
        if (has_capability('mod/grouppeerreview:bereviewed', $context, $member->id)) {
            $member->adjusted_group_mark = $adjustedmark;
            $member->gradebook_grade = $gradebookgrades[$member->id]->grade;
            $grades = grouppeerreview_get_group_member_grades($grouppeerreview, $groupid, $member->id);
            $member->reviewed = $grades;

            $totalpeergrades = 0;
            $normalisedtotal = 0;
            foreach ($grades as $grade) {
                // Sum the normalised grades from each reviewer.
                if (is_numeric($grade->grade) && $grade->total > 0) {
                    $totalpeergrades++;
                    $normalisedtotal += $grade->grade / $grade->total;
                }
            }
            $member->peer_bias = $normalisedtotal;
            if ($totalpeergrades > 0) {
                $member->peer_mark = (count($members) / $totalpeergrades) * $normalisedtotal * $peerreviewbase;
            } else {
                $member->peer_mark = null;
            }
            $report[] = $member;
        }
    }
    return $report;
}

function grouppeerreview_get_grouppeerreview($grouppeerreviewid) {
    global $DB;

    if ($grouppeerreview = $DB->get_record("grouppeerreview", array("id" => $grouppeerreviewid))) {
        return $grouppeerreview;
    }
    return false;
}

/**
 * @global object
 * @param object $grouppeerreview
 * @param object $user
 * @param object $coursemodule
 * @param array $allresponses
 * @return array
 */
function grouppeerreview_prepare_options($grouppeerreview, $userid = null) {
    global $DB;
    // TODO - can this function be replaced with grouppeerreview_get_reviews?
    $prdisplay = array();
    $prdisplay['peerid'] = $grouppeerreview->id;

    $params = array();
    $params[] = $userid;
    $groups = grouppeerreview_get_groups($grouppeerreview, $userid);

    foreach ($groups as $key => $group) {
        $params = array();
        $params[] = $grouppeerreview->id;
        $params[] = $userid;
        $params[] = $group->id;
        
        $members = $DB->get_records_sql("
            SELECT
              gm.userid
            , u.firstname
            , u.lastname
            , gm.groupid
            , prm.grade
            , prm.comment
            FROM {groups_members} gm
            LEFT JOIN {user} u ON gm.userid = u.id
            LEFT JOIN {grouppeerreview_marks} prm
              ON prm.userid = u.id
              AND prm.groupid = gm.groupid
              AND prm.peerid = ?
              AND prm.reviewerid = ?
            WHERE gm.groupid = ?
            AND u.deleted = 0
            ORDER BY u.lastname ASC, u.firstname ASC", $params
        );

        $groups[$key]->members = $members;
    }
    $prdisplay['groups'] = $groups;

    return $prdisplay;
}

function grouppeerreview_get_group_mark($grouppeerreview, $members) {
    global $DB;

    $groupmark = null;
    $conditions = array('assignment' => $grouppeerreview->assignid);
    foreach ($members as $member) {
        $conditions['userid'] = $member->id;
        $groupmark = $DB->get_field("assign_grades", "grade", $conditions);
        if ($groupmark > 0) {
            return $groupmark;
        }
    }
    return null;
}

function grouppeerreview_get_group_member_grades($grouppeerreview, $groupid, $userid) {
    global $DB;

    if ($grouppeerreview->selfassess == 0) {
        $extrasql = "AND gm.userid != " . $userid;
    } else {
        $extrasql = "";
    }
    $grades = $DB->get_records_sql("
        SELECT
          u.id reviewerid
        , u.firstname
        , u.lastname
        , prm.grade
        , prm.comment
        , prm.timemodified
        , reviewer.total
        FROM {groups_members} gm
        LEFT JOIN {user} u
          ON u.id = gm.userid
        LEFT JOIN {grouppeerreview_marks} prm
          ON prm.groupid = gm.groupid
          AND prm.reviewerid = gm.userid
          AND prm.peerid = " . $grouppeerreview->id . "
          AND prm.userid = " . $userid . "
        LEFT JOIN (
            SELECT prm2.reviewerid, SUM(prm2.grade) total
            FROM {grouppeerreview_marks} prm2
            WHERE prm2.groupid = " . $groupid . "
            AND prm2.peerid = " . $grouppeerreview->id . "
            GROUP BY prm2.reviewerid
        ) reviewer ON prm.reviewerid = reviewer.reviewerid
        WHERE gm.groupid = " . $groupid . "
        " . $extrasql . "
        ORDER BY u.lastname, u.firstname");

    return $grades;
}

/**
 * Process user submitted responses for a group peer review,
 * and either updating them or saving new answers.
 * Trigger the appropriate event.
 *
 * @param object $grouppeerreview
 * @param array $reviews the selected responses.
 * @param int $reviewerid user identifier.
 * @param object $course current course.
 * @param object $cm course context.
 * @return void
 */
function grouppeerreview_user_submit_response($grouppeerreview, $reviews, $reviewerid, $course, $cm) {
    global $CFG, $DB, $USER;

    require_once($CFG->libdir.'/eventslib.php');

    $dbupdated      = false;
    $context        = context_module::instance($cm->id);

    foreach ($reviews as $groupid => $users) {
        foreach ($users as $userid => $review) {
            if ((has_capability('mod/grouppeerreview:bereviewed', $context, $userid))) {
                if ($review['grade'] == "") {
                    $review['grade'] = null;
                }
                $conditions = new stdClass();
                $conditions->peerid     = $grouppeerreview->id;
                $conditions->groupid    = $groupid;
                $conditions->userid     = $userid;
                $conditions->reviewerid = $reviewerid;

                if ($update = $DB->get_record("grouppeerreview_marks", (array)$conditions)) {
                    // Update existing record.
                    $update->grade          = $review['grade'];
                    $update->comment        = $review['comment'];
                    $update->timemodified   = (isset($review['timemodified'])) ? $review['timemodified'] : time();
                    $DB->update_record("grouppeerreview_marks", $update);
                    $dbupdated = true;
                } else {
                    if (isset($review['grade'])) {
                        // Insert a new record.
                        $insert = $conditions;
                        $insert->grade          = $review['grade'];
                        $insert->comment        = $review['comment'];
                        $insert->timemodified   = (isset($review['timemodified'])) ? $review['timemodified'] : time();
                        $DB->insert_record("grouppeerreview_marks", $insert);
                    }
                }
            }
        }
    }
    // Update completion state.
    $completion = new completion_info($course);
    if ($completion->is_enabled($cm) && $grouppeerreview->completionsubmit) {
        $completion->update_state($cm);
    }

    $eventdata = array();
    $eventdata['objectid']      = $grouppeerreview->id;
    $eventdata['context']       = $context;
    $eventdata['courseid']      = $grouppeerreview->course;
    $eventdata['relateduserid'] = $USER->id;

    if ($dbupdated == true) {
        $event = \mod_grouppeerreview\event\review_updated::create($eventdata);
    } else {
        $event = \mod_grouppeerreview\event\review_created::create($eventdata);
    }
    $event->trigger();
}

/**
 * Check if a grouppeerreview is available for the current user.
 *
 * @param  stdClass  $grouppeerreview
 * @return array                       status (available or not and possible warnings)
 */
function grouppeerreview_get_availability_status($grouppeerreview) {
    $available = true;
    $warnings = array();

    $timenow = time();

    if (!empty($grouppeerreview->timeopen) && ($grouppeerreview->timeopen > $timenow)) {
        $available = false;
        $warnings['notopenyet'] = userdate($grouppeerreview->timeopen);
    } else if (!empty($grouppeerreview->timeclose) && ($timenow > $grouppeerreview->timeclose)) {
        $available = false;
        $warnings['expired'] = userdate($grouppeerreview->timeclose);
    }

    return array($available, $warnings);
}

function grouppeerreview_get_editor_options() {
    return array(
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'trusttext' => true
    );
}

/**
 * This creates new calendar events given as timeopen and timeclose by $grouppeerreview.
 *
 * @param stdClass $choice
 * @return void
 */
function grouppeerreview_set_events($grouppeerreview) {
    global $DB, $CFG;

    require_once($CFG->dirroot.'/calendar/lib.php');

    // Get CMID if not sent as part of $feedback.
    if (!isset($grouppeerreview->coursemodule)) {
        $cm = get_coursemodule_from_instance('grouppeerreview', $grouppeerreview->id, $grouppeerreview->course);
        $grouppeerreview->coursemodule = $cm->id;
    }

    // Group peer review start calendar events.
    $eventid = $DB->get_field('event', 'id',
            array(
                    'modulename' => 'grouppeerreview',
                    'instance' => $grouppeerreview->id,
                    'eventtype' => GROUPPEERREVIEW_EVENT_TYPE_OPEN));

    if (isset($grouppeerreview->timeopen) && $grouppeerreview->timeopen > 0) {
        $event = new stdClass();
        $event->eventtype    = GROUPPEERREVIEW_EVENT_TYPE_OPEN;
        $event->type         = empty($grouppeerreview->timeclose) ? CALENDAR_EVENT_TYPE_ACTION : CALENDAR_EVENT_TYPE_STANDARD;
        $event->name         = get_string('calendarstart', 'grouppeerreview', $grouppeerreview->name);
        $event->description  = format_module_intro('grouppeerreview', $grouppeerreview, $grouppeerreview->coursemodule);
        $event->timestart    = $grouppeerreview->timeopen;
        $event->timesort     = $grouppeerreview->timeopen;
        $event->visible      = instance_is_visible('grouppeerreview', $grouppeerreview);
        $event->timeduration = 0;
        if ($eventid) {
            // Calendar event exists so update it.
            $event->id = $eventid;
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event);
        } else {
            // Event doesn't exist so create one.
            $event->courseid     = $grouppeerreview->course;
            $event->groupid      = 0;
            $event->userid       = 0;
            $event->modulename   = 'grouppeerreview';
            $event->instance     = $grouppeerreview->id;
            $event->eventtype    = GROUPPEERREVIEW_EVENT_TYPE_OPEN;
            calendar_event::create($event);
        }
    } else if ($eventid) {
        // Calendar event is on longer needed.
        $calendarevent = calendar_event::load($eventid);
        $calendarevent->delete();
    }

    // Group peer review close calendar events.
    $eventid = $DB->get_field('event', 'id',
            array(
                    'modulename' => 'grouppeerreview',
                    'instance' => $grouppeerreview->id,
                    'eventtype' => GROUPPEERREVIEW_EVENT_TYPE_CLOSE));

    if (isset($grouppeerreview->timeclose) && $grouppeerreview->timeclose > 0) {
        $event = new stdClass();
        $event->type         = CALENDAR_EVENT_TYPE_ACTION;
        $event->eventtype    = GROUPPEERREVIEW_EVENT_TYPE_CLOSE;
        $event->name         = get_string('calendarend', 'grouppeerreview', $grouppeerreview->name);
        $event->description  = format_module_intro('grouppeerreview', $grouppeerreview, $grouppeerreview->coursemodule);
        $event->timestart    = $grouppeerreview->timeclose;
        $event->timesort     = $grouppeerreview->timeclose;
        $event->visible      = instance_is_visible('grouppeerreview', $grouppeerreview);
        $event->timeduration = 0;
        if ($eventid) {
            // Calendar event exists so update it.
            $event->id = $eventid;
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event);
        } else {
            // Event doesn't exist so create one.
            $event->courseid     = $grouppeerreview->course;
            $event->groupid      = 0;
            $event->userid       = 0;
            $event->modulename   = 'grouppeerreview';
            $event->instance     = $grouppeerreview->id;
            calendar_event::create($event);
        }
    } else if ($eventid) {
        // Calendar event is on longer needed.
        $calendarevent = calendar_event::load($eventid);
        $calendarevent->delete();
    }
}

function grouppeerreview_get_response_count($gpr) {
    global $DB;

    $grouppeerreview = $DB->get_record("grouppeerreview", array("id" => $gpr->id));
    $params = array();
    $params[] = $grouppeerreview->course;
    $params[] = $grouppeerreview->id;
    
    if ($grouppeerreview->groupingid > 0) {
        $groupingfrom = "JOIN {groupings_groups} gg";
        $groupingwhere = "AND gg.groupingid = ? AND gg.groupid = gr.id";
        $params[] = $grouppeerreview->groupingid;
    } else {
        $groupingfrom = "";
        $groupingwhere = "";
    }

    $responsecount = $DB->count_records_sql("
            SELECT COUNT(gprm.id)
            FROM {grouppeerreview} gpr
            JOIN {grouppeerreview_marks} gprm
            JOIN {groups} gr
            JOIN {groups_members} grm1
            JOIN {groups_members} grm2
            " . $groupingfrom . "
            WHERE gr.id = gprm.groupid
            AND grm1.groupid = gr.id
            AND grm1.userid = gprm.reviewerid
            AND grm2.groupid = gr.id
            AND grm2.userid = gprm.userid
            AND gr.courseid = ?
            AND gprm.peerid = gpr.id
            AND gpr.id = ?
            AND gprm.grade IS NOT NULL
            " . $groupingwhere . "
    ", $params);
    
    return $responsecount;
}

function grouppeerreview_get_groups($grouppeerreview, $userid = null) {
    global $DB;

    $params = array();
    $params[] = $grouppeerreview->course;
    
    if ($userid) {
        $params[] = $userid;
        $extrafrom = "JOIN {groups_members} gm";
        $extrawhere = "AND gm.userid = ? AND gr.id = gm.groupid";
    } else {
        $extrafrom = "";
        $extrawhere = "";
    }
    
    if ($grouppeerreview->groupingid > 0) {
        $params[] = $grouppeerreview->groupingid;

        $groups = $DB->get_records_sql("
                SELECT
                  gr.id
                , gr.name
                FROM {groups} gr
                JOIN {groupings_groups} gg
                " . $extrafrom . "
                WHERE gg.groupid = gr.id
                AND gr.courseid = ?
                " . $extrawhere . "
                AND gg.groupingid = ?
                ORDER BY gr.name ASC", $params
        );
    } else {
        $groups = $DB->get_records_sql("
                SELECT
                  gr.id
                , gr.name
                FROM {groups} gr
                " . $extrafrom . "
                WHERE gr.courseid = ?
                " . $extrawhere . "
                ORDER BY gr.name ASC", $params
        );
    }
    return $groups;
}

function grouppeerreview_get_data_for_csv($grouppeerreview) {
    global $DB;
    
    $cm = get_coursemodule_from_instance('grouppeerreview', $grouppeerreview->id);
    $context = context_module::instance($cm->id);
    
    $reviews = array();
    $groups = grouppeerreview_get_groups($grouppeerreview);
    foreach ($groups as $group) {
        $groupmembers = groups_get_members($group->id);
        foreach ($groupmembers as $groupmember) {
            if (!has_capability('mod/grouppeerreview:bereviewed', $context, $groupmember->id)) {
                // This person does not get reviewed;
                continue;
            }
            $reviewers = (object) grouppeerreview_get_group_member_grades($grouppeerreview, $group->id, $groupmember->id);
            foreach ($reviewers as $reviewer) {
                $record = new stdClass();
                $record->groupname          = $group->name;
                $record->firstname          = $groupmember->firstname;
                $record->lastname           = $groupmember->lastname;
                $record->username           = $groupmember->username;
                $record->reviewer_firstname = $reviewer->firstname;
                $record->reviewer_lastname  = $reviewer->lastname;
                $record->grade              = $reviewer->grade;
                $record->comment            = $reviewer->comment;
                $record->timemodified       = $reviewer->timemodified;
                $reviews[] = $record;
            }
        }
    }
    return $reviews;
}