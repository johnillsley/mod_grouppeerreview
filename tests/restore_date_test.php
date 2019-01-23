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
 * Restore date tests.
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
require_once($CFG->libdir . "/phpunit/classes/restore_date_testcase.php");

/**
 * Restore date tests.
 *
 * @package    mod_grouppeerreview
 * @category   test
 * @author     John Illsley <j.s.illsley@bath.ac.uk>
 * @copyright  2018 University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_grouppeerreview_restore_date_testcase extends restore_date_testcase {

    private $timeopen   = 1000000;
    private $timeclose  = 1000001;
    /**
     * Setup often used objects for the following tests.
     */
    public function setup() {

        $this->resetAfterTest();
        $this->setAdminUser();

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
        $assign = $this->getDataGenerator()->create_module(
                'assign',
                array(
                        'course' => $this->course->id,
                        'teamsubmission' => 1,
                        'teamsubmissiongroupingid' => $grouping->id
                )
        );

        $this->grouppeerreview = $this->getDataGenerator()->create_module(
                'grouppeerreview',
                array(
                        'course' => $this->course->id,
                        'assignid' => $assign->id,
                        'timeopen' => $this->timeopen,
                        'timeclose' => $this->timeclose
                        )
        );

        $this->cm = get_coursemodule_from_instance('grouppeerreview', $this->grouppeerreview->id);
        $this->context = context_module::instance($this->cm->id);
    }

    public function test_restore_dates() {
        global $DB;

        $timestamp = 200000;
        $groups = $DB->get_records('groupings_groups', array('groupingid' => $this->grouppeerreview->groupingid));
        $groups = groups_get_all_groups($this->course->id, 0, $this->grouppeerreview->groupingid);
        $group = array_pop($groups);
        $members = groups_get_members($this->group->id);

        $records = array();
        foreach ($members as $member) {
            $records[] = array(
                    'userid' => $member->id,
                    'peerid' => $this->grouppeerreview->id,
                    'groupid' => $group->id,
                    'reviewerid' => $this->user->id,
                    'grade' => '1',
                    'comment' => 'test',
                    'timemodified' => $timestamp,
            );
        }
        $reviews = [$group->id => $records];

        grouppeerreview_user_submit_response($this->grouppeerreview, $reviews, $this->user->id, $this->course, $this->cm);

        // Do backup and restore.
        $newcourseid = $this->backup_and_restore($this->course);

        $newcourse = $course = $DB->get_record("course", array("id" => $newcourseid));
        $newgrouppeerreviews = get_all_instances_in_course('grouppeerreview', $newcourse);
        $newgrouppeerreview = array_pop($newgrouppeerreviews);

        $newmarks = grouppeerreview_get_reviews($newgrouppeerreview);

        $this->assertEquals($this->grouppeerreview->timeopen, $newgrouppeerreview->timeopen);
        $this->assertEquals($this->grouppeerreview->timeclose, $newgrouppeerreview->timeclose);

        foreach ($newmarks as $newmark) {
            $this->assertEquals($timestamp, $newmark->timemodified);
        }
    }
}