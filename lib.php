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
 * @package    mod_grouppeerreview
 * @author     John Illsley <j.s.illsley@bath.ac.uk>
 * @copyright  2018 University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Event types.
define('GROUPPEERREVIEW_EVENT_TYPE_OPEN', 'open');
define('GROUPPEERREVIEW_EVENT_TYPE_CLOSE', 'close');

/**
 * Return the list if Moodle features this module supports
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function grouppeerreview_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:
            return false;
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return false;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        default:
            return null;
    }
}

/**
 * this will create a new instance and return the id number
 * of the new instance.
 *
 * @global object
 * @param object $grouppeerreview the object given by mod_grouppeerreview_mod_form
 * @return int
 */
function grouppeerreview_add_instance($grouppeerreview) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/grouppeerreview/locallib.php');
    
    $grouppeerreview->timemodified = time();

    // Get groupingid from assignment and use for group peer review.
    $grouppeerreview->groupingid = $DB->get_field(
            'assign',
            'teamsubmissiongroupingid',
            array('teamsubmission' => 1, 'id' => $grouppeerreview->assignid));

    if (empty($grouppeerreview->site_after_submit)) {
        $grouppeerreview->site_after_submit = '';
    }

    // Saving the group peer review in db.
    $grouppeerreview->id = $DB->insert_record("grouppeerreview", $grouppeerreview);

    grouppeerreview_set_events($grouppeerreview);
    grouppeerreview_grade_item_update($grouppeerreview);

    $cm = get_coursemodule_from_instance('grouppeerreview', $grouppeerreview->id);
    if (!empty($grouppeerreview->completionexpected)) {
        \core_completion\api::update_completion_date_event($cm->id, 'grouppeerreview', $grouppeerreview->id,
            $grouppeerreview->completionexpected);
    }

    return $grouppeerreview->id;
}

/**
 * this will update a given instance
 *
 * @global object
 * @param object $grouppeerreview the object given by mod_grouppeerreview_mod_form
 * @return boolean
 */
function grouppeerreview_update_instance($grouppeerreview) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/grouppeerreview/locallib.php');

    $grouppeerreview->timemodified = time();
    $grouppeerreview->id = $grouppeerreview->instance;

    // Get groupingid from assignment and use for group peer review.
    $grouppeerreview->groupingid = $DB->get_field(
            'assign',
            'teamsubmissiongroupingid',
            array('teamsubmission' => 1, 'id' => $grouppeerreview->assignid));

    if (empty($grouppeerreview->site_after_submit)) {
        $grouppeerreview->site_after_submit = '';
    }

    // Save the feedback into the db.
    $DB->update_record("grouppeerreview", $grouppeerreview);

    // Create or update the new events.
    grouppeerreview_set_events($grouppeerreview);
    grouppeerreview_grade_item_update($grouppeerreview);

    $cm = get_coursemodule_from_instance('grouppeerreview', $grouppeerreview->id);
    $completionexpected = (!empty($grouppeerreview->completionexpected)) ? $grouppeerreview->completionexpected : null;
    \core_completion\api::update_completion_date_event($cm->id, 'grouppeerreview', $grouppeerreview->id, $completionexpected);

    return true;
}

/**
 * Create/update grade item for given grouppeerreview
 *
 * @uses GRADE_TYPE_VALUE
 * @param stdClass $grouppeerreview object with extra cmidnumber
 * @param mixed $grades Optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok
*/
function grouppeerreview_grade_item_update($grouppeerreview, $grades=null) {
    global $CFG;

    if (!function_exists('grade_update')) { // Workaround for buggy PHP versions.
        require_once($CFG->libdir . '/gradelib.php');
    }
    $params = array('itemname' => $grouppeerreview->name, 'idnumber' => $grouppeerreview->cmidnumber);
    
    if (isset($grouppeerreview->maximumgrade)) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = $grouppeerreview->maximumgrade;
    }
    // Recalculate rawgrade relative to grademax.
    if (isset($grouppeerreview->rawgrade) && isset($grouppeerreview->rawgrademax) && $grouppeerreview->rawgrademax != 0) {
        // Get max grade Obs: do not try to use grade_get_grades because it requires context which we don't have inside an ajax.
        $gradeitem = grade_item::fetch(array(
            'itemtype' => 'mod',
            'itemmodule' => 'grouppeerreview',
            'iteminstance' => $grouppeerreview->id,
            'courseid' => $grouppeerreview->course
        ));
        if (isset($gradeitem) && isset($gradeitem->grademax)) {
            $grades->rawgrade = ($grouppeerreview->rawgrade / $grouppeerreview->rawgrademax) * $gradeitem->grademax;
        }
    }
    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update(
            'mod/grouppeerreview',
            $grouppeerreview->course,
            'mod',
            'grouppeerreview',
            $grouppeerreview->id,
            0,
            $grades,
            $params
    );
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

    if (!$grouppeerreview = $DB->get_record('grouppeerreview', array('id' => $id))) {
        return false;
    }

    if (!$cm = get_coursemodule_from_instance('grouppeerreview', $grouppeerreview->id)) {
        print_error('invalidcoursemodule');
    }

    $result = true;

    // Deleting the review marks.
    if (!$DB->delete_records("grouppeerreview_marks", array("peerid" => $grouppeerreview->id))) {
        $result = false;
    }

    // Deleting old events.
    if (!$DB->delete_records('event', array('modulename' => 'grouppeerreview', 'instance' => $grouppeerreview->id))) {
        $result = false;
    }

    if (!$DB->delete_records("grouppeerreview", array("id" => $grouppeerreview->id))) {
        $result = false;
    }

    grade_update(
            'mod/grouppeerreview',
            $cm->course,
            'mod',
            'grouppeerreview',
            $grouppeerreview->id,
            0,
            null,
            array('deleted' => 1)
    );

    return $result;
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $grouppeerreview       grouppeerreview object
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 * @since Moodle 3.0
 */
function grouppeerreview_view($grouppeerreview, $course, $cm, $context) {
    global $CFG, $USER;

    // Trigger course_module_viewed event.
    $params = array(
        'context' => $context,
        'objectid' => $grouppeerreview->id,
        'relateduserid' => $USER->id
    );
    // TODO - remove relateuserid from above? change if admins can update all student responses. Also in unit test.
    $event = \mod_grouppeerreview\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('grouppeerreview', $grouppeerreview);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm, $USER->id);
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * grouppeerreview responses for course $data->courseid.
 *
 * @global object
 * @global object
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function grouppeerreview_reset_userdata($data) {
    global $CFG, $DB;

    $componentstr = get_string('modulenameplural', 'grouppeerreview');
    $status = array();

    if (!empty($data->reset_grouppeerreview)) {
        $gprsql = "SELECT gpr.id
                   FROM {grouppeerreview} gpr
                   WHERE gpr.course=?";

        $DB->delete_records_select('grouppeerreview_marks', "peerid IN ($gprsql)", array($data->courseid));
        $status[] = array(
                'component' => $componentstr,
                'item' => get_string('removeresponses', 'grouppeerreview'),
                'error' => false
        );
    }

    // Updating dates - shift may be negative too.
    if ($data->timeshift) {
        // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
        // See MDL-9367.
        shift_course_mod_dates('grouppeerreview', array('timeopen', 'timeclose'), $data->timeshift, $data->courseid);
        $status[] = array('component' => $componentstr, 'item' => get_string('datechanged'), 'error' => false);
    }

    return $status;
}

/**
 * Removes all grades from gradebook
 *
 * @param int $courseid The ID of the course to reset
 * @param string $type Optional type of assignment to limit the reset to a particular assignment type
 */
function grouppeerreview_reset_gradebook($courseid, $type = '') {
    global $CFG, $DB;

    $params = array('moduletype' => 'grouppeerreview', 'courseid' => $courseid);
    $sql = 'SELECT gpr.*, cm.idnumber as cmidnumber, a.course as courseid
            FROM {grouppeerreview} gpr, {course_modules} cm, {modules} m
            WHERE m.name=:moduletype AND m.id=cm.module AND cm.instance=gpr.id AND gpr.course=:courseid';

    if ($grouppeerreviews = $DB->get_records_sql($sql, $params)) {
        foreach ($grouppeerreviews as $grouppeerreview) {
            assign_grade_item_update($grouppeerreview, 'reset');
        }
    }
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the grouppeerreview.
 *
 * @param $mform the course reset form that is being built.
 */
function grouppeerreview_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'grouppeerreviewheader', get_string('modulenameplural', 'grouppeerreview'));
    $mform->addElement('advcheckbox', 'reset_grouppeerreview', get_string('removeresponses', 'grouppeerreview'));
}

/**
 * Course reset form defaults.
 * @return array
 */
function grouppeerreview_reset_course_form_defaults($course) {
    return array('reset_grouppeerreview' => 1);
}

/**
 * Add a get_coursemodule_info function in case any quiz type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info An object on information that the courses
 *                        will know about (most noticeably, an icon).
 */
function grouppeerreview_get_coursemodule_info($coursemodule) {
    global $DB;

    $dbparams = ['id' => $coursemodule->instance];
    $fields = 'id, name, intro, introformat, completionsubmit, timeopen, timeclose';
    if (!$grouppeerreview = $DB->get_record('grouppeerreview', $dbparams, $fields)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $grouppeerreview->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $result->content = format_module_intro('grouppeerreview', $grouppeerreview, $coursemodule->id, false);
    }

    // Populate the custom completion rules as key => value pairs, but only if the completion mode is 'automatic'.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completionsubmit'] = $grouppeerreview->completionsubmit;
    }

    // Populate some other values that can be used in calendar or on dashboard.
    if ($grouppeerreview->timeopen) {
        $result->customdata['timeopen'] = $grouppeerreview->timeopen;
    }
    if ($grouppeerreview->timeclose) {
        $result->customdata['timeclose'] = $grouppeerreview->timeclose;
    }

    return $result;
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function grouppeerreview_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $modulepagetype = array('mod-grouppeerreview-*' => get_string('page-mod-grouppeerreview-x', 'grouppeerreview'));
    return $modulepagetype;
}

/**
 * Callback which returns human-readable strings describing the active completion custom rules for the module instance.
 *
 * @param cm_info|stdClass $cm object with fields ->completion and ->customdata['customcompletionrules']
 * @return array $descriptions the array of descriptions for the custom rules.
 */
function mod_grouppeerreview_get_completion_active_rule_descriptions($cm) {
    // Values will be present in cm_info, and we assume these are up to date.

    if (empty($cm->customdata['customcompletionrules'])
            || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }

    $descriptions = [];
    foreach ($cm->customdata['customcompletionrules'] as $key => $val) {
        switch ($key) {
            case 'completionsubmit':
                if (empty($val)) {
                    continue;
                }
                $descriptions[] = get_string('completionsubmit', 'grouppeerreview');
                break;
            default:
                break;
        }
    }
    return $descriptions;
}

/**
 * Obtains the automatic completion state for this grouppeerreview based on any conditions
 * in grouppeerreview settings.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not, $type if conditions not set.
 */
function grouppeerreview_get_completion_state($course, $cm, $userid, $type) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/grouppeerreview/locallib.php');

    // Get grouppeerreview details.
    $grouppeerreview = $DB->get_record('grouppeerreview', array('id' => $cm->instance), '*', MUST_EXIST);

    // If completion option is enabled, evaluate it and return true/false.
    if ($grouppeerreview->completionsubmit) {
        $allcomplete = grouppeerreview_check_all_responses($grouppeerreview, $userid);
        return $allcomplete;
    } else {
        // Completion option is not enabled so just return $type.
        return $type;
    }
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function grouppeerreview_get_view_actions() {
    return array('view', 'report');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function grouppeerreview_get_post_actions() {
    return array('submit');
}

/**
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module data
 * @param  int $from the time to check updates from
 * @param  array $filter  if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 * @since Moodle 3.2
 */
function grouppeerreview_check_updates_since(cm_info $cm, $from, $filter = array()) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/grouppeerreview/locallib.php');

    $updates = new stdClass();
    $grouppeerreview = $DB->get_record($cm->modname, array('id' => $cm->instance), '*', MUST_EXIST);
    list($available, $warnings) = grouppeerreview_get_availability_status($grouppeerreview);
    if (!$available) {
        return $updates;
    }

    $updates = course_check_module_updates_since($cm, $from, array(), $filter);

    // Check if there are new responses in the grouppeerreview.
    $updates->marks = (object) array('updated' => false);
    $select = 'peerid = :id AND timemodified > :since';
    $params = array('id' => $grouppeerreview->id, 'since' => $from);
    $marks = $DB->get_records_select('grouppeerreview_marks', $select, $params, '', 'id');
    if (!empty($marks)) {
        $updates->marks->updated = true;
        $updates->marks->itemids = array_keys($marks);
    }

    return $updates;
}

/**
 * Returns all other caps used in module
 *
 * @return array
 */
function grouppeerreview_get_extra_capabilities() {
    return array('moodle/site:accessallgroups', 'moodle/grade:viewall');
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_grouppeerreview_core_calendar_provide_event_action(calendar_event $event,
        \core_calendar\action_factory $factory) {

    global $CFG, $DB, $USER;
    require_once($CFG->dirroot . '/mod/grouppeerreview/locallib.php');

    $cm = get_fast_modinfo($event->courseid)->instances['grouppeerreview'][$event->instance];
    $now = time();

    if (!empty($cm->customdata['timeclose']) && $cm->customdata['timeclose'] < $now) {
        // The grouppeerreview has closed so the user can no longer submit anything.
        return null;
    }

    // The grouppeerreview is actionable if we don't have a start time or the start time is
    // in the past.
    $actionable = (empty($cm->customdata['timeopen']) || $cm->customdata['timeopen'] <= $now);

    $grouppeerreview = $DB->get_record("grouppeerreview", array("id" => $cm->instance));
    if ($actionable && grouppeerreview_check_all_responses($grouppeerreview, $USER->id)) {
        // There is no action if the user has already submitted their grouppeerreview.
        return null;
    }

    return $factory->create_instance(
            get_string('viewresponses', 'grouppeerreview'),
            new \moodle_url('/mod/grouppeerreview/view.php', array('id' => $cm->id)),
            1,
            $actionable
    );
}

/**
 * This function calculates the minimum and maximum cutoff values for the timestart of
 * the given event.
 *
 * It will return an array with two values, the first being the minimum cutoff value and
 * the second being the maximum cutoff value. Either or both values can be null, which
 * indicates there is no minimum or maximum, respectively.
 *
 * If a cutoff is required then the function must return an array containing the cutoff
 * timestamp and error string to display to the user if the cutoff value is violated.
 *
 * A minimum and maximum cutoff return value will look like:
 * [
 *     [1505704373, 'The date must be after this date'],
 *     [1506741172, 'The date must be before this date']
 * ]
 *
 * @param calendar_event $event The calendar event to get the time range for
 * @param stdClass $grouppeerreview The module instance to get the range from
 */
function mod_grouppeerreview_core_calendar_get_valid_event_timestart_range(\calendar_event $event, \stdClass $grouppeerreview) {
    $mindate = null;
    $maxdate = null;

    if ($event->eventtype == GROUPPEERREVIEW_EVENT_TYPE_OPEN) {
        if (!empty($grouppeerreview->timeclose)) {
            $maxdate = [
                    $grouppeerreview->timeclose,
                    get_string('openafterclose', 'grouppeerreview')
            ];
        }
    } else if ($event->eventtype == GROUPPEERREVIEW_EVENT_TYPE_CLOSE) {
        if (!empty($grouppeerreview->timeopen)) {
            $mindate = [
                    $grouppeerreview->timeopen,
                    get_string('closebeforeopen', 'grouppeerreview')
            ];
        }
    }

    return [$mindate, $maxdate];
}

/**
 * This function will update the grouppeerreview module according to the
 * event that has been modified.
 *
 * It will set the timeopen or timeclose value of the grouppeerreview instance
 * according to the type of event provided.
 *
 * @throws \moodle_exception
 * @param \calendar_event $event
 * @param stdClass $grouppeerreview The module instance to get the range from
 */
function mod_grouppeerreview_core_calendar_event_timestart_updated(\calendar_event $event, \stdClass $grouppeerreview) {
    global $DB;

    if (!in_array($event->eventtype, [GROUPPEERREVIEW_EVENT_TYPE_OPEN, GROUPPEERREVIEW_EVENT_TYPE_CLOSE])) {
        return;
    }

    $courseid = $event->courseid;
    $modulename = $event->modulename;
    $instanceid = $event->instance;
    $modified = false;

    // Something weird going on. The event is for a different module so
    // we should ignore it.
    if ($modulename != 'grouppeerreview') {
        return;
    }

    if ($grouppeerreview->id != $instanceid) {
        return;
    }

    $coursemodule = get_fast_modinfo($courseid)->instances[$modulename][$instanceid];
    $context = context_module::instance($coursemodule->id);

    // The user does not have the capability to modify this activity.
    if (!has_capability('moodle/course:manageactivities', $context)) {
        return;
    }

    if ($event->eventtype == GROUPPEERREVIEW_EVENT_TYPE_OPEN) {
        // If the event is for the grouppeerreview activity opening then we should.
        // Set the start time of the grouppeerreview activity to be the new start.
        // Time of the event.
        if ($grouppeerreview->timeopen != $event->timestart) {
            $grouppeerreview->timeopen = $event->timestart;
            $modified = true;
        }
    } else if ($event->eventtype == GROUPPEERREVIEW_EVENT_TYPE_CLOSE) {
        // If the event is for the grouppeerreview activity closing then we should.
        // Set the end time of the grouppeerreview activity to be the new start.
        // Time of the event.
        if ($grouppeerreview->timeclose != $event->timestart) {
            $grouppeerreview->timeclose = $event->timestart;
            $modified = true;
        }
    }

    if ($modified) {
        $grouppeerreview->timemodified = time();
        // Persist the instance changes.
        $DB->update_record('grouppeerreview', $grouppeerreview);
        $event = \core\event\course_module_updated::create_from_cm($coursemodule, $context);
        $event->trigger();
    }
}

/**
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every grouppeerreview event in the site is checked, else
 * only grouppeerreview events belonging to the course specified are checked.
 * This function is used, in its new format, by restore_refresh_events()
 *
 * @param int $courseid
 * @param int|stdClass $instance grouppeerreview module instance or ID.
 * @param int|stdClass $cm Course module object or ID (not used in this module).
 * @return bool
 */
function grouppeerreview_refresh_events($courseid = 0, $instance = null, $cm = null) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/grouppeerreview/locallib.php');

    // If we have instance information then we can just update the one event instead of updating all events.
    if (isset($instance)) {
        if (!is_object($instance)) {
            $instance = $DB->get_record('grouppeerreview', array('id' => $instance), '*', MUST_EXIST);
        }
        grouppeerreview_set_events($instance);
        return true;
    }

    if ($courseid) {
        if (! $grouppeerreviews = $DB->get_records("grouppeerreview", array("course" => $courseid))) {
            return true;
        }
    } else {
        if (! $grouppeerreviews = $DB->get_records("grouppeerreview")) {
            return true;
        }
    }

    foreach ($grouppeerreviews as $grouppeerreview) {
        grouppeerreview_set_events($grouppeerreview);
    }
    return true;
}

/**
 * Returns all grouppeerreview submissions since a given time.
 *
 * @param array $activities The activity information is returned in this array
 * @param int $index The current index in the activities array
 * @param int $timestart The earliest activity to show
 * @param int $courseid Limit the search to this course
 * @param int $cmid The course module id
 * @param int $userid Optional user id
 * @param int $groupid Optional group id
 * @return void
 */
function grouppeerreview_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0) {
    global $CFG, $COURSE, $USER, $DB;

    if ($COURSE->id == $courseid) {
        $course = $COURSE;
    } else {
        $course = $DB->get_record('course', array('id' => $courseid));
    }

    $modinfo = get_fast_modinfo($course);

    $cm = $modinfo->cms[$cmid];
    $params = array($timestart, $cm->instance);

    if ($userid) {
        $userselect = "AND u.id = ?";
        $params[] = $userid;
    } else {
        $userselect = "";
    }

    if ($groupid) {
        $groupselect = "AND gpr.groupid = ?";
        $params[] = $groupid;
    } else {
        $groupselect = "";
    }

    $allnames = get_all_user_name_fields(true, 'u');
    if (!$submissions = $DB->get_records_sql("SELECT
                                              gprm.*,
                                              $allnames, u.email, u.picture, u.imagealt, u.email
                                         FROM {grouppeerreview_marks} gprm
                                              JOIN {grouppeerreview} gpr ON gpr.id = gprm.peerid
                                              JOIN {user} u              ON u.id = gprm.reviewerid
                                        WHERE gprm.timemodified > ? AND gpr.id = ?
                                              $userselect $groupselect
                                     GROUP BY gprm.timemodified, gprm.reviewerid
                                     ORDER BY gprm.id ASC", $params)) { // Order by initial submission date.

        return;
    }

    $groupmode       = groups_get_activity_groupmode($cm, $course);
    $cmcontext       = context_module::instance($cm->id);
    $accessallgroups = has_capability('moodle/site:accessallgroups', $cmcontext);
    $grader          = has_capability('moodle/grade:viewall', $cmcontext);

    $printsubmissions = array();
    foreach ($submissions as $submission) {

        if ($groupmode) {
            if ($groupmode == VISIBLEGROUPS or $accessallgroups) {
                // Oki (Open discussions have groupid -1).
            } else {
                // Separate mode.
                if (isguestuser()) {
                    // Shortcut.
                    continue;
                }

                if (!in_array($submission->groupid, $modinfo->get_groups($cm->groupingid))) {
                    continue;
                }
            }
        }
        $printsubmissions[] = $submission;
    }

    if (!$printsubmissions) {
        return;
    }

    if ($grader) {
        require_once($CFG->libdir . '/gradelib.php');
        $userids = array();
        foreach ($printsubmissions as $id => $submission) {
            $userids[] = $submission->userid;
        }
        $grades = grade_get_grades($courseid, 'mod', 'grouppeerreview', $cm->instance, $userids);
    }

    $aname = format_string($cm->name, true);
    foreach ($printsubmissions as $submission) {
        $tmpactivity = new stdClass();

        $tmpactivity->type         = 'grouppeerreview';
        $tmpactivity->cmid         = $cm->id;
        $tmpactivity->name         = $aname;
        $tmpactivity->sectionnum   = $cm->sectionnum;
        $tmpactivity->timestamp    = $submission->timemodified;
        $tmpactivity->content      = new stdClass();
        $tmpactivity->content->id  = $submission->id;
        $tmpactivity->user         = new stdClass();
        $additionalfields          = array('id' => 'userid', 'picture', 'imagealt', 'email');
        $additionalfields          = explode(',', user_picture::fields());
        $tmpactivity->user         = username_load_fields_from_object($tmpactivity->user, $submission, null, $additionalfields);
        $tmpactivity->user->id     = $submission->userid;
        if ($grader) {
            $tmpactivity->grade = $grades->items[0]->grades[$submission->userid]->str_long_grade;
        }
        $activities[$index++] = $tmpactivity;
    }

    return;
}

/**
 * Outputs the grouppeerreview submissions indicated by $activity.
 *
 * @param object $activity      the activity object the grouppeerreview submissions resides in
 * @param int    $courseid      the id of the course the grouppeerreview submissions resides in
 * @param bool   $detail        not used, but required for compatibilty with other modules
 * @param int    $modnames      not used, but required for compatibilty with other modules
 * @param bool   $viewfullnames not used, but required for compatibilty with other modules
 */
function grouppeerreview_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
    global $CFG, $OUTPUT;

    $tableoptions = [
            'border' => '0',
            'cellpadding' => '3',
            'cellspacing' => '0',
            'class' => 'grouppeerreview-recent'
    ];
    $output = html_writer::start_tag('table', $tableoptions);
    $output .= html_writer::start_tag('tr');

    $pictureoptions = [
            'courseid' => $courseid,
            'link' => true,
            'alttext' => true,
    ];
    $picture = $OUTPUT->user_picture($activity->user, $pictureoptions);
    $output .= html_writer::tag('td', $picture, ['class' => 'userpicture', 'valign' => 'top']);
    $output .= html_writer::start_tag('td');

    if ($detail) {
        $modname = $modnames[$activity->type];
        $modlink = html_writer::tag(
                'a',
                $activity->name,
                array('href' => $CFG->wwwroot . '/mod/grouppeerreview/view.php?id=' . $activity->cmid)
        );
        $output .= html_writer::tag(
                'div',
                $OUTPUT->image_icon('icon', $modname, 'grouppeerreview') . $modlink,
                array('class' => 'title')
        );
    }

    if (isset($activity->grade)) {
        $output .= html_writer::tag(
                'div',
                get_string('grade') . ': ' . $activity->grade,
                array('class' => 'grade')
        );
    }

    $userlink = html_writer::tag(
            'a',
            fullname($activity->user),
            array('href' => $CFG->wwwroot . '/user/view.php?id=' . $activity->user->id . '&amp;course=' . $courseid)
    );
    $output .= html_writer::tag(
            'div',
            $userlink . ' - ' . userdate($activity->timestamp),
            array('class' => 'user')
    );

    $output .= html_writer::end_tag('td');
    $output .= html_writer::end_tag('tr');
    $output .= html_writer::end_tag('table');

    echo $output;
}

/**
 * Print recent activity from all grouppeerreview in a given course
 *
 * This is used by the recent activity block
 * @param mixed $course the course to print activity for
 * @param bool $viewfullnames boolean to determine whether to show full names or not
 * @param int $timestart the time the rendering started
 * @return bool true if activity was printed, false otherwise.
 */
function grouppeerreview_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $USER, $DB, $OUTPUT;
    require_once($CFG->dirroot . '/mod/assign/locallib.php');

    $params = array($timestart, $course->id, 'grouppeerreview');
    $namefields = user_picture::fields('u', null, 'userid');
    if (!$submissions = $DB->get_records_sql("SELECT
                                              gprm.*, cm.id AS cmid,
                                              $namefields, u.email, u.picture, u.imagealt, u.email
                                         FROM {grouppeerreview_marks} gprm
                                              JOIN {grouppeerreview} gpr ON gpr.id = gprm.peerid
                                              JOIN {course_modules} cm   ON cm.instance = gpr.id
                                              JOIN {modules} md          ON md.id = cm.module
                                              JOIN {user} u              ON u.id = gprm.reviewerid
                                        WHERE gprm.timemodified > ? AND
                                              gpr.course = ? AND
                                              md.name = ?
                                     GROUP BY gprm.timemodified, gprm.reviewerid
                                     ORDER BY gprm.id ASC", $params)) { // Order by initial submission date.

        return;
    }

    $modinfo = get_fast_modinfo($course);
    $show    = array();
    $grader  = array();

    foreach ($submissions as $submission) {
        if (!array_key_exists($submission->cmid, $modinfo->get_cms())) {
            continue;
        }
        $cm = $modinfo->get_cm($submission->cmid);
        if (!$cm->uservisible) {
            continue;
        }
        if ($submission->userid == $USER->id) {
            $show[] = $submission;
            continue;
        }

        $context = context_module::instance($submission->cmid);
        /*
        // The act of submitting of assignment may be considered private -
        // only graders will see it if specified.
        if (empty($showrecentsubmissions)) {
            if (!array_key_exists($cm->id, $grader)) {
                $grader[$cm->id] = has_capability('moodle/grade:viewall', $context);
            }
            if (!$grader[$cm->id]) {
                continue;
            }
        }
        */
        $groupmode = groups_get_activity_groupmode($cm, $course);

        if ($groupmode == SEPARATEGROUPS &&
                !has_capability('moodle/site:accessallgroups',  $context)) {
            if (isguestuser()) {
                // Shortcut - guest user does not belong into any group.
                continue;
            }

            // This will be slow - show only users that share group with me in this cm.
            if (!$modinfo->get_groups($cm->groupingid)) {
                continue;
            }
            $usersgroups = groups_get_all_groups($course->id, $submission->userid, $cm->groupingid);
            if (is_array($usersgroups)) {
                $usersgroups = array_keys($usersgroups);
                $intersect = array_intersect($usersgroups, $modinfo->get_groups($cm->groupingid));
                if (empty($intersect)) {
                    continue;
                }
            }
        }
        $show[] = $submission;
    }

    if (empty($show)) {
        return false;
    }

    echo $OUTPUT->heading(get_string('newsubmissions', 'grouppeerreview').':', 3);

    foreach ($show as $submission) {
        $cm = $modinfo->get_cm($submission->cmid);
        $context = context_module::instance($submission->cmid);
        $link = $CFG->wwwroot.'/mod/grouppeerreview/view.php?id='.$cm->id;

        print_recent_activity_note($submission->timemodified,
                $submission,
                $cm->name,
                $link,
                false,
                $viewfullnames);
    }

    return true;
}