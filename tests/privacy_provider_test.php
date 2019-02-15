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
        $this->user = $user; // Make last student accessible to tests through class property.

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

        foreach ($members as $reviewer) {
            $grade = 0;
            $records = array();
            foreach ($members as $user) {
                $records[$user->id] = array(
                        'userid' => $user->id,
                        'peerid' => $this->grouppeerreview->id,
                        'groupid' => $this->group->id,
                        'reviewerid' => $reviewer->id,
                        'grade' => $grade,
                        'comment' => 'test' . $grade,
                        'timemodified' => $timestamp,
                );
                $grade++;
            }
            $reviews = [$this->group->id => $records];
            grouppeerreview_user_submit_response($this->grouppeerreview, $reviews, $reviewer->id, $this->course, $this->cm);
        }
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

        $this->assertTrue($writer->has_any_data());
    }

    /**
     * Test for provider::delete_data_for_all_users_in_context().
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;

        $grouppeerreview = $this->grouppeerreview;

        // Before deletion, we should have 2 responses.
        $count = $DB->count_records('grouppeerreview_marks', ['peerid' => $grouppeerreview->id]);
        $this->assertEquals(16, $count);

        // Delete data based on context.
        $cmcontext = context_module::instance($this->cm->id);
        provider::delete_data_for_all_users_in_context($cmcontext);

        // After deletion, the grouppeerreview_marks for that grouppeerreview activity should have been deleted.
        $count = $DB->count_records('grouppeerreview_marks', ['peerid' => $grouppeerreview->id]);
        $this->assertEquals(0, $count);
    }

    /**
     * Test for provider::delete_data_for_user().
     */
    public function test_delete_data_for_user_() {
        global $DB;

        $grouppeerreview = $this->grouppeerreview;

        // Before deletion, we should have 2 responses.
        $count = $DB->count_records('grouppeerreview_marks', ['peerid' => $grouppeerreview->id]);
        $this->assertEquals(16, $count);

        // Delete data based on context.
        $cmcontext = context_module::instance($this->cm->id);
        $contextlist = new \core_privacy\local\request\approved_contextlist($this->user, 'grouppeerreview',
                [context_system::instance()->id, $cmcontext->id]);
        
        provider::delete_data_for_user($contextlist);

        // After deletion, the grouppeerreview_marks for that student should have been deleted, which will leave 9 left.
        $count = $DB->count_records('grouppeerreview_marks', ['peerid' => $grouppeerreview->id]);
        $this->assertEquals(9, $count);

        // Check that there are none left for the selected user.
        $count = $DB->count_records_sql('
                SELECT count(*) 
                FROM {grouppeerreview_marks}
                WHERE (userid = ' . $this->user->id . ' OR reviewerid = '. $this->user->id .')
                AND peerid = ' . $grouppeerreview->id
                );
        $this->assertEquals(0, $count);
    }

    /**
     * Export all data within a context for a component for the specified user.
     *
     * @param   int         $userid     The userid of the user to fetch.
     * @param   \context    $context    The context to export data for.
     * @param   string      $component  The component to get export data for.
     */
    public function export_context_data_for_user(int $userid, \context $context, string $component) {
        $contextlist = new \core_privacy\tests\request\approved_contextlist(
                \core_user::get_user($userid),
                $component,
                [$context->id]
        );

        $classname = $this->get_provider_classname($component);
        $classname::export_user_data($contextlist);
    }

    /**
     * Determine the classname and ensure that it is a provider.
     *
     * @param   string      $component      The classname.
     * @return  string
     */
    protected function get_provider_classname($component) {
        $classname = "\\${component}\\privacy\\provider";

        if (!class_exists($classname)) {
            throw new \coding_exception("{$component} does not implement any provider");
        }

        $rc = new \ReflectionClass($classname);
        if (!$rc->implementsInterface(\core_privacy\local\metadata\provider::class)) {
            throw new \coding_exception("{$component} does not implement metadata provider");
        }

        if (!$rc->implementsInterface(\core_privacy\local\request\core_user_data_provider::class)) {
            throw new \coding_exception("{$component} does not declare that it provides any user data");
        }

        return $classname;
    }
}
