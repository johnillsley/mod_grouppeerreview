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
 * Privacy provider tests.
 *
 * @group      mod_grouppeerreview
 * @group      bath
 * @package    mod_grouppeerreview
 * @category   test
 * @author     John Illsley <j.s.illsley@bath.ac.uk>
 * @copyright  2018 University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_privacy\local\metadata\collection;
use core_privacy\local\request\deletion_criteria;
use mod_grouppeerreview\privacy\provider;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider tests class.
 *
 * @group      mod_grouppeerreview
 * @group      bath
 * @package    mod_grouppeerreview
 * @category   test
 * @author     John Illsley <j.s.illsley@bath.ac.uk>
 * @copyright  2018 University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_grouppeerreview_privacy_provider_testcase extends \core_privacy\tests\provider_testcase {
    /** @var stdClass The user object. */
    protected $user;

    /** @var stdClass The group peer review object. */
    protected $grouppeerreview;

    /** @var stdClass The course object. */
    protected $course;

    private $timeopen   = 1000000;
    private $timeclose  = 1000001;
    /**
     * Setup often used objects for the following tests.
     */
    protected function setUp() {

        $this->resetAfterTest();
        $this->setAdminUser();
        $studentroleid = 5;

        $this->course = $this->getDataGenerator()->create_course();
        $this->group = $this->getDataGenerator()->create_group(array('courseid' => $this->course->id));

        for ($i = 0; $i < 4; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($user->id, $this->course->id, $studentroleid);
            $this->getDataGenerator()->create_group_member(array('userid' => $user->id, 'groupid' => $this->group->id));
        }
        $this->user = $user; // Make last student accessible to tests.

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

        $timestamp = 200000;
        $members = groups_get_members($this->group->id);

        $records = array();
        $grade = 0;
        foreach ($members as $member) {
            $records[] = array(
                    'userid' => $member->id,
                    'peerid' => $this->grouppeerreview->id,
                    'groupid' => $this->group->id,
                    'reviewerid' => $this->user->id,
                    'grade' => $grade,
                    'comment' => 'test' . $grade,
                    'timemodified' => $timestamp,
            );
            $grade++;
        }
        $reviews = [$this->group->id => $records];
        grouppeerreview_user_submit_response($this->grouppeerreview, $reviews, $this->user->id, $this->course, $this->cm);
    }

    /**
     * Test for provider::get_metadata().
     */
    public function test_get_metadata() {
        $collection = new collection('mod_grouppeerreview');
        $newcollection = provider::get_metadata($collection);
        $itemcollection = $newcollection->get_collection();
        $this->assertCount(1, $itemcollection);

        $table = reset($itemcollection);
        $this->assertEquals('grouppeerreview_marks', $table->get_name());

        $privacyfields = $table->get_privacy_fields();
        $this->assertArrayHasKey('peerid', $privacyfields);
        $this->assertArrayHasKey('groupid', $privacyfields);
        $this->assertArrayHasKey('userid', $privacyfields);
        $this->assertArrayHasKey('reviewerid', $privacyfields);
        $this->assertArrayHasKey('grade', $privacyfields);
        $this->assertArrayHasKey('comment', $privacyfields);
        $this->assertArrayHasKey('timemodified', $privacyfields);

        $this->assertEquals('privacy:metadata:grouppeerreview_marks', $table->get_summary());
    }

    /**
     * Test for provider::get_contexts_for_userid().
     */
    public function test_get_contexts_for_userid() {

        $contextlist = provider::get_contexts_for_userid($this->user->id);
        $this->assertCount(1, $contextlist);
        $contextforuser = $contextlist->current();
        $cmcontext = context_module::instance($this->cm->id);
        $this->assertEquals($cmcontext->id, $contextforuser->id);
    }

    /**
     * Test for provider::export_user_data().
     */
    public function test_export_for_context() {

        $cmcontext = context_module::instance($this->cm->id);

        // Export all of the data for the context.
        $this->export_context_data_for_user($this->user->id, $cmcontext, 'mod_grouppeerreview');
        $writer = \core_privacy\local\request\writer::with_context($cmcontext);

        // $this->assertTrue($writer->has_any_data());
    }

    /**
     * Test for provider::delete_data_for_all_users_in_context().
     */

    /*
    public function test_delete_data_for_all_users_in_context() {
        global $DB;

        $choice = $this->choice;
        $generator = $this->getDataGenerator();
        $cm = get_coursemodule_from_instance('choice', $this->choice->id);

        // Create another student who will answer the choice activity.
        $student = $generator->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $generator->enrol_user($student->id, $this->course->id, $studentrole->id);

        $choicewithoptions = choice_get_choice($choice->id);
        $optionids = array_keys($choicewithoptions->option);

        choice_user_submit_response($optionids[1], $choice, $student->id, $this->course, $cm);

        // Before deletion, we should have 2 responses.
        $count = $DB->count_records('choice_answers', ['choiceid' => $choice->id]);
        $this->assertEquals(2, $count);

        // Delete data based on context.
        $cmcontext = context_module::instance($cm->id);
        provider::delete_data_for_all_users_in_context($cmcontext);

        // After deletion, the choice answers for that choice activity should have been deleted.
        $count = $DB->count_records('choice_answers', ['choiceid' => $choice->id]);
        $this->assertEquals(0, $count);
    }
*/

    /**
     * Test for provider::delete_data_for_user().
     */
    /*
    public function test_delete_data_for_user_() {
        global $DB;

        $choice = $this->choice;
        $generator = $this->getDataGenerator();
        $cm1 = get_coursemodule_from_instance('choice', $this->choice->id);

        // Create a second choice activity.
        $options = ['Boracay', 'Camiguin', 'Bohol', 'Cebu', 'Coron'];
        $params = [
                'course' => $this->course->id,
                'option' => $options,
                'name' => 'Which do you think is the best island in the Philippines?',
                'showpreview' => 0
        ];
        $plugingenerator = $generator->get_plugin_generator('mod_choice');
        $choice2 = $plugingenerator->create_instance($params);
        $plugingenerator->create_instance($params);
        $cm2 = get_coursemodule_from_instance('choice', $choice2->id);

        // Make a selection for the first student for the 2nd choice activity.
        $choicewithoptions = choice_get_choice($choice2->id);
        $optionids = array_keys($choicewithoptions->option);
        choice_user_submit_response($optionids[2], $choice2, $this->student->id, $this->course, $cm2);

        // Create another student who will answer the first choice activity.
        $otherstudent = $generator->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $generator->enrol_user($otherstudent->id, $this->course->id, $studentrole->id);

        $choicewithoptions = choice_get_choice($choice->id);
        $optionids = array_keys($choicewithoptions->option);

        choice_user_submit_response($optionids[1], $choice, $otherstudent->id, $this->course, $cm1);

        // Before deletion, we should have 2 responses.
        $count = $DB->count_records('choice_answers', ['choiceid' => $choice->id]);
        $this->assertEquals(2, $count);

        $context1 = context_module::instance($cm1->id);
        $context2 = context_module::instance($cm2->id);
        $contextlist = new \core_privacy\local\request\approved_contextlist($this->student, 'choice',
                [context_system::instance()->id, $context1->id, $context2->id]);
        provider::delete_data_for_user($contextlist);

        // After deletion, the choice answers for the first student should have been deleted.
        $count = $DB->count_records('choice_answers', ['choiceid' => $choice->id, 'userid' => $this->student->id]);
        $this->assertEquals(0, $count);

        // Confirm that we only have one choice answer available.
        $choiceanswers = $DB->get_records('choice_answers');
        $this->assertCount(1, $choiceanswers);
        $lastresponse = reset($choiceanswers);
        // And that it's the other student's response.
        $this->assertEquals($otherstudent->id, $lastresponse->userid);
    }
    */
}
