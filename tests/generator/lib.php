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
 * mod_choice data generator.
 *
 * @package    mod_grouppeerreview
 * @category   test
 * @author     John Illsley <j.s.illsley@bath.ac.uk>
 * @copyright  2018 University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * mod_choice data generator class.
 *
 * @package    mod_grouppeerreview
 * @category   test
 * @author     John Illsley <j.s.illsley@bath.ac.uk>
 * @copyright  2018 University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_grouppeerreview_generator extends testing_module_generator {

    public function create_instance($record = null, array $options = null) {
        $record = (array)$record;
        $record['weighting'] = (empty($options['weighting'])) ? 15 : $options['weighting'];
        $record['maxrating'] = (empty($options['maxrating'])) ? 5 : $options['maxrating'];

        return parent::create_instance($record, $options);
    }
}
