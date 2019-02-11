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
 * List of all group peer reviews in course.
 *
 * @package    mod_grouppeerreview
 * @author     John Illsley <j.s.illsley@bath.ac.uk>
 * @copyright  2018 University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once($CFG->dirroot . '/mod/grouppeerreview/locallib.php');

$id = required_param('id', PARAM_INT); // Course id.
$PAGE->set_url('/mod/grouppeerreview/index.php', array('id' => $id));

if (!$course = $DB->get_record("course", array("id" => $id))) {
    print_error('invalidcourseid');
}

require_course_login($course, true);
$PAGE->set_pagelayout('incourse');
$context = context_course::instance($course->id);
$params = array(
        'context' => $context
);

$event = \mod_grouppeerreview\event\course_module_instance_list_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->trigger();

$strname                = get_string("name");
$strgrouppeerreview     = get_string("modulename", "grouppeerreview");
$strgrouppeerreviews    = get_string("modulenameplural", "grouppeerreview");
$strconnectedassign     = get_string("connectedassign", "grouppeerreview");
$strresponses           = get_string("responses", "grouppeerreview");
$strdeadline            = get_string("deadline", "grouppeerreview");
$strnodeadline          = get_string("nodeadline", "grouppeerreview");

$PAGE->set_title($strgrouppeerreviews);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add($strgrouppeerreviews);
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($strgrouppeerreviews));

// Get all the appropriate data.
if (!$grouppeerreviews = get_all_instances_in_course('grouppeerreview', $course)) {
    notice(get_string('thereareno', 'moodle', $strgrouppeerreviews), "$CFG->wwwroot/course/view.php?id=$course->id");
    die;
}

$usesections = course_format_uses_sections($course->format);

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

if ($usesections) {
    $strsectionname = get_string('sectionname', 'format_'.$course->format);
    $table->head  = array ($strsectionname, $strname, $strdeadline, $strconnectedassign, $strresponses);
    $table->align = array ('center', 'left', 'center', 'left', 'right');
} else {
    $table->head  = array ($strname, $strdeadline, $strconnectedassign, $strresponses);
    $table->align = array ('left', 'center', 'left', 'right');
}

foreach ($grouppeerreviews as $grouppeerreview) {

    $cm = get_coursemodule_from_instance('grouppeerreview', $grouppeerreview->id);
    $class = $grouppeerreview->visible ? null : array('class' => 'dimmed'); // Hidden modules are dimmed.
    $gprlink = html_writer::link(new moodle_url('/mod/grouppeerreview/view.php', array('id' => $cm->id)),
            $grouppeerreview->name, $class);
    $due = ($grouppeerreview->timeclose == 0) ? $strnodeadline : userdate($grouppeerreview->timeclose);
    $assign = get_coursemodule_from_instance('assign', $grouppeerreview->assignid);
    $class = $assign->visible ? null : array('class' => 'dimmed');
    $assignlink = html_writer::link(new moodle_url('/mod/assign/view.php', array('id' => $assign->id)), $assign->name, $class);

    if (has_capability('mod/grouppeerreview:readresponses', $context)) {
        $status = grouppeerreview_get_response_count($grouppeerreview);
    } else {
        $responses = grouppeerreview_get_reviews($grouppeerreview, null, null, $USER->id);
        $submissiontimes = array_map(function ($i) {
            return $i->timemodified;
        }
            , $responses
        );
        if (count($submissiontimes) > 0) {
            $status = userdate(max($submissiontimes));
        } else {
            $status = get_string('nosubmissions', 'grouppeerreview');
        }
    }

    if ($usesections) {
        $table->data[] = array(
                get_section_name($course, $grouppeerreview->section),
                $gprlink,
                $due,
                $assignlink,
                $status
                );
    } else {
        $table->data[] = array(
                $gprlink,
                $due,
                $assignlink,
                $status
                );
    }
}
echo html_writer::table($table);
echo $OUTPUT->footer();