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
 * The mod_grouppeerreview group peer review report downloaded event.
 *
 * @package    mod_grouppeerreview
 * @author     John Illsley <j.s.illsley@bath.ac.uk>
 * @copyright  2018 University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_grouppeerreview\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_grouppeerreview group peer review report downloaded event class.
 *
 * @package    mod_grouppeerreview
 * @author     John Illsley <j.s.illsley@bath.ac.uk>
 * @copyright  2018 University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_downloaded extends \core\event\base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'grouppeerreview';
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventreportdownloaded', 'mod_grouppeerreview');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {

        return "The user with id '$this->userid' has downloded the report in the '".$this->other['format']."' format for
                the group peer review activity with course module id '$this->contextinstanceid'.";
    }

    /**
     * Get url related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/grouppeerreview/report.php', array('id' => $this->contextinstanceid));
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        // Report format downloaded.
        if (!isset($this->other['format'])) {
            throw new \coding_exception('The \'format\' value must be set in other.');
        }
    }

    /**
     * Return legacy data for add_to_log().
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        $url = new \moodle_url('report.php', array('id' => $this->contextinstanceid));
        return array($this->courseid, 'grouppeerreview', 'report', $url->out(), $this->objectid, $this->contextinstanceid);
    }

    public static function get_objectid_mapping() {
        return array('db' => 'grouppeerreview', 'restore' => 'grouppeerreview');
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['grouppeerreviewid'] = array('db' => 'grouppeerreview', 'restore' => 'grouppeerreview');

        return $othermapped;
    }
}
