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
 * This file is the report page for the mod_grouppeerreview module.
 *
 * @package    mod_grouppeerreview
 * @author     John Illsley <j.s.illsley@bath.ac.uk>
 * @copyright  2018 University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once($CFG->dirroot . '/mod/grouppeerreview/locallib.php');

$id         = required_param('id', PARAM_INT); // Moduleid.
$download   = optional_param('download', '', PARAM_ALPHA);
$action     = optional_param('action', '', PARAM_ALPHANUMEXT);
$attemptids = optional_param_array('attemptid', array(), PARAM_INT); // Get array of responses to delete or modify.
$userids    = optional_param_array('userid', array(), PARAM_INT); // Get array of users whose peers need to be modified.
$groupid    = optional_param('groupid', null, PARAM_INT);
$grades     = optional_param_array('finalgrade', null, PARAM_NUMBER);

list ($course, $cm) = get_course_and_cm_from_cmid($id, 'grouppeerreview');
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/grouppeerreview:review', $context);

$url = new moodle_url('/mod/grouppeerreview/report.php', array('id' => $id));
if ($download !== '') {
    $url->param('download', $download);
}
if ($action !== '') {
    $url->param('action', $action);
}
$PAGE->set_url($url);
$PAGE->set_context(context_module::instance($id));
$PAGE->set_pagelayout('base');

if (!$cm = get_coursemodule_from_id('grouppeerreview', $id)) {
    print_error("invalidcoursemodule");
}
if (!$course = $DB->get_record("course", array("id" => $cm->course))) {
    print_error("coursemisconf");
}

if (!$grouppeerreview = grouppeerreview_get_grouppeerreview($cm->instance)) {
    print_error('invalidcoursemodule');
}

$strpeer = get_string("modulename", "grouppeerreview");
$strpeers = get_string("modulenameplural", "grouppeerreview");
$strresponses = get_string("responses", "grouppeerreview");

if (data_submitted() && has_capability('mod/grouppeerreview:readresponses', $context) && confirm_sesskey()) {
    if ($action === 'save_grades') {
        foreach ($grades as $k => $g) {
            if (is_numeric($g)) {
                $grades[$k] = array( 'userid' => $k, 'rawgrade' => $g);
            } else {
                unset($grades[$k]);
            }
        }
        grouppeerreview_grade_item_update($grouppeerreview, $grades);
        redirect("report.php?id=$cm->id&groupid=$groupid");
    }

    if ($action === 'delete') {
        // Delete responses of other users.
        grouppeerreview_delete_responses($attemptids, $grouppeerreview, $cm, $course);
        redirect("report.php?id=$cm->id");
    }
    if (preg_match('/^choose_(\d+)$/', $action, $actionmatch)) {
        // Modify responses of other users.
        $newoptionid = (int)$actionmatch[1];
        grouppeerreview_modify_responses($userids, $attemptids, $newoptionid, $grouppeerreview, $cm, $course);
        redirect("report.php?id=$cm->id");
    }
}

if (!$download) {
    $PAGE->navbar->add($strresponses);
    $PAGE->set_title(format_string($grouppeerreview->name).": $strresponses");
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->heading($grouppeerreview->name, 2, null);

    $eventdata = array();
    $eventdata['objectid'] = $grouppeerreview->id;
    $eventdata['context'] = $context;
    $eventdata['courseid'] = $course->id;
    $event = \mod_grouppeerreview\event\report_viewed::create($eventdata);
    $event->trigger();
} else {
    $groupmode = groups_get_activity_groupmode($cm);

    // Trigger the report downloaded event.
    $eventdata = array();
    $eventdata['objectid'] = $grouppeerreview->id;
    $eventdata['context'] = $context;
    $eventdata['courseid'] = $course->id;
    $eventdata['other']['format'] = $download;
    $eventdata['other']['peerid'] = $grouppeerreview->id;
    $event = \mod_grouppeerreview\event\report_downloaded::create($eventdata);
    $event->trigger();
}

if ($download == "ods" && has_capability('mod/choice:downloadresponses', $context)) {
    require_once("$CFG->libdir/odslib.class.php");

    // Calculate file name.
    $filename = clean_filename("$course->shortname - ".strip_tags(format_string($grouppeerreview->name, true))).'.ods';
    // Creating a workbook.
    $workbook = new MoodleODSWorkbook("-");
    // Send HTTP headers.
    $workbook->send($filename);
    // Creating the first worksheet.
    $myxls = $workbook->add_worksheet($strresponses);

    // Print names of all the fields.
    $i = 0;
    $myxls->write_string(0, $i++, get_string("group"));
    $myxls->write_string(0, $i++, get_string("lastname"));
    $myxls->write_string(0, $i++, get_string("firstname"));
    $myxls->write_string(0, $i++, get_string("username", "mod_grouppeerreview"));
    $myxls->write_string(0, $i++, get_string("grade"));
    $myxls->write_string(0, $i++, get_string("comments", "mod_grouppeerreview"));
    $myxls->write_string(0, $i++, get_string("reviewer", "mod_grouppeerreview"));
    $myxls->write_string(0, $i++, get_string("lastupdated", "mod_grouppeerreview"));

    $reviews = grouppeerreview_get_data_for_csv($grouppeerreview);
    $row = 1;
    foreach ($reviews as $review) {
        $i = 0;
        $myxls->write_string($row, $i++, $review->groupname);
        $myxls->write_string($row, $i++, $review->lastname);
        $myxls->write_string($row, $i++, $review->firstname);
        $myxls->write_string($row, $i++, $review->username);
        $myxls->write_string($row, $i++, $review->grade);
        $myxls->write_string($row, $i++, $review->comment);
        $myxls->write_string($row, $i++, $review->reviewer_firstname . ' ' . $review->reviewer_lastname);
        $myxls->write_string($row, $i++, userdate($review->timemodified));
        $row++;
    }
    // Close the workbook.
    $workbook->close();
    exit;
}

// Print spreadsheet if one is asked for.
if ($download == "xls" && has_capability('mod/choice:downloadresponses', $context)) {
    require_once("$CFG->libdir/excellib.class.php");

    // Calculate file name.
    $filename = clean_filename("$course->shortname - " . strip_tags(format_string($grouppeerreview->name, true))) . '.xls';
    // Creating a workbook.
    $workbook = new MoodleExcelWorkbook("-");
    // Send HTTP headers.
    $workbook->send($filename);
    // Creating the first worksheet.
    $myxls = $workbook->add_worksheet($strresponses);

    // Print names of all the fields.
    $i = 0;
    $myxls->write_string(0, $i++, get_string("group"));
    $myxls->write_string(0, $i++, get_string("lastname"));
    $myxls->write_string(0, $i++, get_string("firstname"));
    $myxls->write_string(0, $i++, get_string("username", "mod_grouppeerreview"));
    $myxls->write_string(0, $i++, get_string("grade"));
    $myxls->write_string(0, $i++, get_string("comments", "mod_grouppeerreview"));
    $myxls->write_string(0, $i++, get_string("reviewer", "mod_grouppeerreview"));
    $myxls->write_string(0, $i++, get_string("lastupdated", "mod_grouppeerreview"));

    $reviews = grouppeerreview_get_data_for_csv($grouppeerreview);
    $row = 1;
    foreach ($reviews as $review) {
        $i = 0;
        $myxls->write_string($row, $i++, $review->groupname);
        $myxls->write_string($row, $i++, $review->lastname);
        $myxls->write_string($row, $i++, $review->firstname);
        $myxls->write_string($row, $i++, $review->username);
        $myxls->write_string($row, $i++, $review->grade);
        $myxls->write_string($row, $i++, $review->comment);
        $myxls->write_string($row, $i++, $review->reviewer_firstname . ' ' . $review->reviewer_lastname);
        $myxls->write_string($row, $i++, userdate($review->timemodified));
        $row++;
    }
    // Close the workbook.
    $workbook->close();
    exit;
}

// Print text file.
if ($download == "txt" && has_capability('mod/choice:downloadresponses', $context)) {
    $filename = clean_filename("$course->shortname - ".strip_tags(format_string($grouppeerreview->name, true))).'.txt';

    header("Content-Type: application/download\n");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Expires: 0");
    header("Cache-Control: must-revalidate,post-check=0,pre-check=0");
    header("Pragma: public");

    // Print names of all the fields.
    echo get_string("group") . "\t" .
            get_string("lastname") . "\t" .
            get_string("firstname") . "\t" .
            get_string("username", "mod_grouppeerreview") . "\t" .
            get_string("grade") . "\t" .
            get_string("comments", "mod_grouppeerreview") . "\t" .
            get_string("reviewer", "mod_grouppeerreview") . "\t" .
            get_string("lastupdated", "mod_grouppeerreview");

    $reviews = grouppeerreview_get_data_for_csv($grouppeerreview);
    foreach ($reviews as $review) {
        echo "\n";
        echo $review->groupname . "\t" .
                $review->lastname . "\t" .
                $review->firstname . "\t" .
                $review->username . "\t" .
                $review->grade . "\t" .
                $review->comment . "\t" .
                $review->reviewer_firstname . ' ' . $review->reviewer_lastname . "\t" .
                userdate($review->timemodified);
    }
    exit;
}

$groups = groups_get_all_groups($course->id, 0, $grouppeerreview->groupingid, 'g.id, g.name');
$groupssummary = grouppeerreview_get_summary($grouppeerreview, $groups);

if (empty($groupid)) {
    $groupid = reset($groups)->id;
}

$renderer = $PAGE->get_renderer('mod_grouppeerreview');
echo $renderer->group_completion_summary($grouppeerreview, $groupssummary, $groupid);
echo $renderer->group_selector($cm, $groups, $groupid);

if (!empty($groupid)) {
    $report = grouppeerreview_get_report($grouppeerreview, $groupid);
    echo $renderer->group_report($report, $groupid, $cm);
}

echo $renderer->download_report_buttons($cm);
echo $renderer->report_summary();

echo $OUTPUT->footer();
