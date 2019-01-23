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
 * This file is the entry point to the mod_grouppeerreview module. All pages are rendered from here
 *
 * @package    mod_grouppeerreview
 * @author     John Illsley <j.s.illsley@bath.ac.uk>
 * @copyright  2018 University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once($CFG->dirroot . '/mod/grouppeerreview/locallib.php');
require_once($CFG->libdir . '/completionlib.php');

$id     = required_param('id', PARAM_INT); // Course Module ID.
$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$r      = optional_param_array('review', array(), PARAM_RAW); // Get array of responses to delete or modify.
$notify = optional_param('notify', '', PARAM_ALPHA);

list ($course, $cm) = get_course_and_cm_from_cmid($id, 'grouppeerreview');
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/grouppeerreview:review', $context);

if (!$grouppeerreview = $DB->get_record("grouppeerreview", array("id" => $cm->instance))) {
    print_error('invalidcoursemodule');
}

// Moodle function optional_param_array does not work with multidimensional arrays so create multidimensional array now.
$reviews = array();

foreach ($r as $k => $v) {
    $split = explode('-', $k);
    $reviews[$split[0]][$split[1]][$split[2]] = $v;
}

$url = new moodle_url('/mod/grouppeerreview/view.php', array('id' => $id));
if ($action !== '') {
    $url->param('action', $action);
}
$PAGE->set_url($url);
$PAGE->set_context(context_module::instance($id));
$PAGE->set_pagelayout('base');
$PAGE->set_title($grouppeerreview->name);
$PAGE->set_heading($course->fullname);

list($available, $warnings) = grouppeerreview_get_availability_status($grouppeerreview);

// Submit any new data if there is any.
if (data_submitted() && !empty($action) && confirm_sesskey()) {
    $timenow = time();
    if (has_capability('mod/grouppeerreview:review', $context)) {
        grouppeerreview_user_submit_response($grouppeerreview, $reviews, $USER->id, $course, $cm);
        redirect(new moodle_url('/mod/grouppeerreview/view.php',
            array('id' => $cm->id, 'notify' => 'peersaved', 'sesskey' => sesskey())));
    }

    $eventdata = array();
    $eventdata['objectid']      = $grouppeerreview->id;
    $eventdata['context']       = $context;
    $eventdata['courseid']      = $grouppeerreview->course;
    $event = \mod_grouppeerreview\event\grades_saved::create($eventdata);
    $event->trigger();
}

// Trigger event.
grouppeerreview_view($grouppeerreview, $course, $cm, $context);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($grouppeerreview->name), 2, null);
$renderer = $PAGE->get_renderer('mod_grouppeerreview');
echo $renderer->summary_intro($grouppeerreview);

if ($notify and confirm_sesskey()) {
    if ($notify === 'peersaved') {
        echo $OUTPUT->notification(get_string('peersaved', 'grouppeerreview'), 'notifysuccess');
    }
}
if (has_capability('mod/grouppeerreview:readresponses', $context)) {
    echo $renderer->show_reportlink($grouppeerreview, $cm);
}
echo '<div class="clearer"></div>';

// Print the form.
$grouppeerreviewopen = true;
if ((!empty($grouppeerreview->timeopen)) && ($grouppeerreview->timeopen > time())) {
    echo $OUTPUT->box(
            "<strong>" . get_string("notopenyet", "grouppeerreview", userdate($grouppeerreview->timeopen)) . "</strong><br/><br/>",
            "generalbox notopenyet");
    echo $OUTPUT->footer();
    exit;
} else if ((!empty($grouppeerreview->timeclose)) && (time() > $grouppeerreview->timeclose)) {
    echo $OUTPUT->box(
            "<strong>" . get_string("expired", "grouppeerreview", userdate($grouppeerreview->timeclose)) . "</strong><br/><br/>",
            "generalbox expired");
    $grouppeerreviewopen = false;
}

$options = grouppeerreview_prepare_options($grouppeerreview, $USER->id);

if (count($options["groups"]) > 0) {
    if ($grouppeerreview->intro) {
        echo $OUTPUT->box(format_module_intro('grouppeerreview', $grouppeerreview, $cm->id), 'generalbox', 'intro');
    }
    echo $renderer->display_options($grouppeerreview, $options, $cm->id, $grouppeerreviewopen);
} else {
    echo $renderer->nothing_to_review();
}
echo $OUTPUT->footer();