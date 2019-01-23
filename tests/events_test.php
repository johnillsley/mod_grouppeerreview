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
 * Events tests.
 *
 * @group      mod_grouppeerreview
 * @group      bath
 * @package    mod_grouppeerreview
 * @category   test
 * @author     John Illsley <j.s.illsley@bath.ac.uk>
 * @copyright  2018 University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/grouppeerreview/lib.php');

/**
 * Events tests class.
 *
 * @package    mod_grouppeerreview
 * @category   test
 * @author     John Illsley <j.s.illsley@bath.ac.uk>
 * @copyright  2018 University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_grouppeerreview_events_testcase extends advanced_testcase {
    /** @var grouppeerreview_object */
    protected $grouppeerreview;

    /** @var course_object */
    protected $course;

    /** @var cm_object Course module object. */
    protected $cm;

    /** @var context_object */
    protected $context;

    /** @var user_object */
    protected $user;

    /** @var group_object */
    protected $group;

    /**
     * Setup often used objects for the following tests.
     */
    protected function setup() {
        global $DB;

        $this->resetAfterTest();

        $studentroleid = 5;
        $teacherroleid = 3;

        $this->course = $this->getDataGenerator()->create_course();
        $this->group = $this->getDataGenerator()->create_group(array('courseid' => $this->course->id));

        for ($i = 0; $i < 4; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($user->id, $this->course->id, $studentroleid);
            $this->getDataGenerator()->create_group_member(array('userid' => $user->id, 'groupid' => $this->group->id));
        }
        $this->user = $user; // Make last student accessible to tests.

        // Add a teacher too.
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, $teacherroleid);
        $this->getDataGenerator()->create_group_member(array('userid' => $user->id, 'groupid' => $this->group->id));

        $grouping = $this->getDataGenerator()->create_grouping(array('courseid' => $this->course->id));
        $this->getDataGenerator()->create_grouping_group(array('groupingid' => $grouping->id, 'groupid' => $this->group->id));

        // Assignment must be created with group submission.
        $assign = $this->getDataGenerator()->create_module('assign', array(
                'course' => $this->course->id,
                'teamsubmission' => 1,
                'teamsubmissiongroupingid' => $grouping->id));

        $this->grouppeerreview = $this->getDataGenerator()->create_module(
                'grouppeerreview',
                array('course' => $this->course->id, 'assignid' => $assign->id));

        $this->cm = get_coursemodule_from_instance('grouppeerreview', $this->grouppeerreview->id);
        $this->context = context_module::instance($this->cm->id);
    }

    public function test_course_module_viewed() {

        $this->setUser($this->user);

        // Redirect event.
        $sink = $this->redirectEvents();
        grouppeerreview_view($this->grouppeerreview, $this->course, $this->cm, $this->context);
        $events = $sink->get_events();

        // Data checking.
        $this->assertCount(1, $events);
        $this->assertInstanceOf('\mod_grouppeerreview\event\course_module_viewed', $events[0]);
        $this->assertEquals($this->user->id, $events[0]->userid);
        $this->assertEquals($this->user->id, $events[0]->relateduserid);
        $this->assertEquals($this->context, $events[0]->get_context());
        $this->assertEventContextNotUsed($events[0]);

        $sink->close();
    }

    public function test_review_submit() {

        $this->setUser($this->user);

        // First do an insert.
        $reviews = array(
                $this->group->id => array(
                        $this->user->id => array(
                                'grade' => '2',
                                'comment' => 'XYZ')));

        // Redirect event.
        $sink = $this->redirectEvents();
        grouppeerreview_user_submit_response($this->grouppeerreview, $reviews, $this->user->id, $this->course, $this->cm);
        $events = $sink->get_events();

        // Data checking.
        $this->assertCount(1, $events);
        $this->assertInstanceOf('\mod_grouppeerreview\event\review_created', $events[0]);
        $this->assertEquals($this->user->id, $events[0]->userid);
        $this->assertEquals($this->user->id, $events[0]->relateduserid);
        $this->assertEquals($this->context, $events[0]->get_context());
        $this->assertEventContextNotUsed($events[0]);

        // Now do an update.
        $reviews = array(
                $this->group->id => array(
                        $this->user->id => array(
                                'grade' => '1',
                                'comment' => 'ABC')));

        grouppeerreview_user_submit_response($this->grouppeerreview, $reviews, $this->user->id, $this->course, $this->cm);
        $events = $sink->get_events();

        // Data checking.
        $this->assertCount(2, $events);
        $this->assertInstanceOf('\mod_grouppeerreview\event\review_updated', $events[1]);
        $this->assertEquals($this->user->id, $events[1]->userid);
        $this->assertEquals($this->user->id, $events[1]->relateduserid);
        $this->assertEquals($this->context, $events[1]->get_context());
        $this->assertEventContextNotUsed($events[1]);
        $sink->close();
    }

    public function test_report_viewed() {
        global $USER;

        $this->setAdminUser();

        $eventdata = array();
        $eventdata['objectid'] = $this->grouppeerreview->id;
        $eventdata['context'] = $this->context;
        $eventdata['courseid'] = $this->course->id;
        $event = \mod_grouppeerreview\event\report_viewed::create($eventdata);

        // Redirect event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $event = $sink->get_events();

        // Data checking.
        $this->assertCount(1, $event);
        $this->assertInstanceOf('\mod_grouppeerreview\event\report_viewed', $event[0]);
        $this->assertEquals($USER->id, $event[0]->userid);
        $this->assertEquals($this->context, $event[0]->get_context());
        $expected = array($this->course->id, "grouppeerreview", "report", 'report.php?id=' . $this->context->instanceid,
                $this->grouppeerreview->id, $this->context->instanceid);
        $this->assertEventLegacyLogData($expected, $event[0]);
        $this->assertEventContextNotUsed($event[0]);

        $sink->close();
    }

    public function test_report_downloaded() {
        global $USER;

        $this->setAdminUser();

        $download = "xls";

        $eventdata = array();
        $eventdata['objectid'] = $this->grouppeerreview->id;
        $eventdata['context'] = $this->context;
        $eventdata['courseid'] = $this->course->id;
        $eventdata['other']['format'] = $download;
        $eventdata['other']['peerid'] = $this->grouppeerreview->id;
        $event = \mod_grouppeerreview\event\report_downloaded::create($eventdata);

        // Redirect event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $event = $sink->get_events();

        // Data checking.
        $this->assertCount(1, $event);
        $this->assertInstanceOf('\mod_grouppeerreview\event\report_downloaded', $event[0]);
        $this->assertEquals($USER->id, $event[0]->userid);
        $this->assertEquals($this->context, $event[0]->get_context());
        $expected = array($this->course->id, "grouppeerreview", "report", 'report.php?id=' . $this->context->instanceid,
                $this->grouppeerreview->id, $this->context->instanceid);
        $this->assertEventLegacyLogData($expected, $event[0]);
        $this->assertEventContextNotUsed($event[0]);

        $sink->close();
    }

    public function test_grades_saved() {

        $this->setAdminUser();
    }
}