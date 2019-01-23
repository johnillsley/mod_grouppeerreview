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
 * Generator tests.
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
 * Generator tests class.
 *
 * @package    mod_grouppeerreview
 * @category   test
 * @author     John Illsley <j.s.illsley@bath.ac.uk>
 * @copyright  2018 University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_grouppeerreview_generator_testcase extends advanced_testcase {

    public function test_create_instance() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $grouping = $this->getDataGenerator()->create_grouping(array('courseid' => $course->id));
        $assign = $this->getDataGenerator()->create_module('assign', array(
                'course' => $course->id,
                'teamsubmission' => 1,
                'teamsubmissiongroupingid' => $grouping->id));

        $this->assertFalse($DB->record_exists('grouppeerreview', array('course' => $course->id)));

        $grouppeerreview = $this->getDataGenerator()->create_module(
                'grouppeerreview',
                array('course' => $course->id, 'assignid' => $assign->id));
        $this->assertEquals(1, $DB->count_records('grouppeerreview', array('course' => $course->id)));
        $this->assertTrue($DB->record_exists('grouppeerreview', array('course' => $course->id)));
        $this->assertTrue($DB->record_exists('grouppeerreview', array('id' => $grouppeerreview->id)));

        $grouppeerreview = $this->getDataGenerator()->create_module(
                'grouppeerreview',
                array('course' => $course->id, 'assignid' => $assign->id, 'name' => 'One more grouppeerreview'));
        $this->assertEquals(2, $DB->count_records('grouppeerreview', array('course' => $course->id)));
        $this->assertEquals('One more grouppeerreview',
                $DB->get_field_select('grouppeerreview', 'name', 'id = :id', array('id' => $grouppeerreview->id)));
    }
}