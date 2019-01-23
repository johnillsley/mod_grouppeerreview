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
 * Privacy class for requesting user data.
 *
 * @package    mod_grouppeerreview
 * @author     John Illsley <j.s.illsley@bath.ac.uk>
 * @copyright  2018 University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_grouppeerreview\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\helper;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy class for requesting user data.
 *
 * @package    mod_grouppeerreview
 * @author     John Illsley <j.s.illsley@bath.ac.uk>
 * @copyright  2018 University of Bath
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        // This plugin stores personal data.
        \core_privacy\local\metadata\provider,

        // This plugin is a core_user_data_provider.
        \core_privacy\local\request\plugin\provider {

    /**
     * Return the fields which contain personal data.
     *
     * @param collection $items a reference to the collection to use to store the metadata.
     * @return collection the updated collection of metadata items.
     */
    public static function get_metadata(collection $items) : collection {
        $items->add_database_table(
                'grouppeerreview_marks',
                [
                        'peerid' => 'privacy:metadata:grouppeerreview_marks:peerid',
                        'groupid' => 'privacy:metadata:grouppeerreview_marks:groupid',
                        'userid' => 'privacy:metadata:grouppeerreview_marks:userid',
                        'reviewerid' => 'privacy:metadata:grouppeerreview_marks:reviewerid',
                        'grade' => 'privacy:metadata:grouppeerreview_marks:grade',
                        'comment' => 'privacy:metadata:grouppeerreview_marks:comment',
                        'timemodified' => 'privacy:metadata:grouppeerreview_marks:timemodified',
                ], 'privacy:metadata:grouppeerreview_marks'
        );
        return $items;
    }

    /**
     * Returns all of the contexts that has information relating to the userid.
     *
     * @param  int $userid The user ID.
     * @return contextlist an object with the contexts related to a userid.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {

        $sql = "SELECT ctx.id
                FROM {course_modules} cm
                JOIN {modules} m ON cm.module = m.id AND m.name = :modname
                JOIN {grouppeerreview} gpr ON cm.instance = gpr.id
                JOIN {context} ctx ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                JOIN {grouppeerreview_marks} gprm ON gpr.id = gprm.peerid
                        AND (gprm.userid = :userid OR gprm.reviewerid = :reviewerid)";

        $params = [
                'modname'       => 'grouppeerreview',
                'contextlevel'  => CONTEXT_MODULE,
                'userid'        => $userid,
                'reviewerid'    => $userid,
                ];

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Write out the user data filtered by contexts.
     *
     * @param approved_contextlist $contextlist contexts that we are writing data out from.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT cm.id AS cmid,
                       gpr.name,
                       gprm.id,
                       gprm.peerid,
                       gprm.groupid,
                       gprm.userid,
                       gprm.reviewerid,
                       gprm.grade,
                       gprm.comment,
                       gprm.timemodified
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {grouppeerreview} gpr ON gpr.id = cm.instance
                  JOIN {grouppeerreview_marks} gprm ON gpr.id = gprm.peerid
                          AND (gprm.userid = :userid OR gprm.reviewerid = :reviewerid)
                 WHERE c.id {$contextsql}
              ORDER BY cm.id";

        $params = [
                'modname'       => 'grouppeerreview',
                'contextlevel'  => CONTEXT_MODULE,
                'userid'        => $user->id,
                'reviewerid'    => $user->id,
                ] + $contextparams;

        $gprentries = $DB->get_recordset_sql($sql, $params);
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The module context.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        if ($cm = get_coursemodule_from_id('grouppeerreview', $context->instanceid)) {
            $DB->delete_records('grouppeerreview_mark', ['peerid' => $cm->instance]);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {

            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }
            $instanceid = $DB->get_field('course_modules', 'instance', ['id' => $context->instanceid], MUST_EXIST);
            $DB->delete_records('grouppeerreview_mark', ['peerid' => $instanceid, 'userid' => $userid]);
            $DB->delete_records('grouppeerreview_mark', ['peerid' => $instanceid, 'reviewerid' => $userid]);
        }

        // WHAT ABOUT GRADEBOOK DATA ???? - DOES THE GRADEBOOK PRIVACY PROVIDER DEAL WITH THIS????
    }
}
