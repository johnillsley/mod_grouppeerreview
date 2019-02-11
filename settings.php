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
 * Global Settings for the mod_grouppeerreview plugin
 *
 * @package    mod_grouppeerreview
 * @author     John Illsley <j.s.illsley@bath.ac.uk>
 * @copyright  2018 University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_configtext(
            'mod_grouppeerreview/maxrating',
            get_string('maxrating', 'grouppeerreview'),
            get_string('maxrating_desc', 'grouppeerreview'),
            5));

    $settings->add(new admin_setting_confightmleditor(
            'mod_grouppeerreview/defaultinstructions',
            get_string('defaultinstructions', 'grouppeerreview'),
            get_string('defaultinstructions_desc', 'grouppeerreview'),
            get_string('instructions_default', 'grouppeerreview')));

    $settings->add(new admin_setting_configtext(
            'mod_grouppeerreview/defaultweighting',
            get_string('defaultweighting', 'grouppeerreview'),
            get_string('defaultweighting_desc', 'grouppeerreview'),
            20));

    $settings->add(new admin_setting_confightmleditor(
            'mod_grouppeerreview/algorithmexplained',
            get_string('algorithmexplaination', 'grouppeerreview'),
            get_string('algorithmexplaination_desc', 'grouppeerreview'),
            get_string('calculations', 'grouppeerreview')));
}