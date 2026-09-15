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

namespace local_bbcotodobien\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API implementation for local_bbcotodobien.
 *
 * Metadata, export and deletion: userid fields are anonymised; institutional audit rows are kept.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe stored personal data.
     *
     * @param collection $collection Metadata collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_bbcotodobien_course_audit', [
            'courseid' => 'privacy:metadata:course_audit:courseid',
            'audittypeid' => 'privacy:metadata:course_audit:audittypeid',
            'userid' => 'privacy:metadata:course_audit:userid',
            'compliance' => 'privacy:metadata:course_audit:compliance',
            'status' => 'privacy:metadata:course_audit:status',
            'timecreated' => 'privacy:metadata:course_audit:timecreated',
            'timemodified' => 'privacy:metadata:course_audit:timemodified',
        ], 'privacy:metadata:course_audit');

        $collection->add_database_table('local_bbcotodobien_rule_result', [
            'userid' => 'privacy:metadata:rule_result:userid',
            'courseauditid' => 'privacy:metadata:rule_result:courseauditid',
            'ruleconfigid' => 'privacy:metadata:rule_result:ruleconfigid',
            'compliance' => 'privacy:metadata:rule_result:compliance',
            'status' => 'privacy:metadata:rule_result:status',
            'timecreated' => 'privacy:metadata:rule_result:timecreated',
        ], 'privacy:metadata:rule_result');

        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:snapshots');

        return $collection;
    }

    /**
     * Get course contexts containing data for a user.
     *
     * @param int $userid User ID
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {local_bbcotodobien_course_audit} ca ON ca.courseid = ctx.instanceid
                 WHERE ctx.contextlevel = :contextlevel1
                   AND ca.userid = :userid1
              UNION
                SELECT ctx.id
                  FROM {context} ctx
                  JOIN {local_bbcotodobien_course_audit} ca ON ca.courseid = ctx.instanceid
                  JOIN {local_bbcotodobien_rule_result} rr ON rr.courseauditid = ca.id
                 WHERE ctx.contextlevel = :contextlevel2
                   AND rr.userid = :userid2";
        $contextlist->add_from_sql($sql, [
            'contextlevel1' => CONTEXT_COURSE,
            'userid1' => $userid,
            'contextlevel2' => CONTEXT_COURSE,
            'userid2' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Export a user's approved data.
     *
     * @param approved_contextlist $contextlist Approved contexts
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $subcontext = [get_string('pluginname', 'local_bbcotodobien')];

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }

            $audits = $DB->get_records(
                'local_bbcotodobien_course_audit',
                ['courseid' => $context->instanceid, 'userid' => $userid],
                'timecreated, id'
            );
            $exportdata = [];
            foreach ($audits as $audit) {
                $exportdata[] = (object) [
                    'audittypeid' => $audit->audittypeid,
                    'compliance' => $audit->compliance,
                    'status' => $audit->status,
                    'timecreated' => transform::datetime($audit->timecreated),
                    'timemodified' => transform::datetime($audit->timemodified),
                ];
            }

            $sql = "SELECT rr.id, rr.ruleconfigid, rr.compliance, rr.status, rr.timecreated
                      FROM {local_bbcotodobien_rule_result} rr
                      JOIN {local_bbcotodobien_course_audit} ca ON ca.id = rr.courseauditid
                     WHERE ca.courseid = :courseid
                       AND rr.userid = :userid
                  ORDER BY rr.timecreated, rr.id";
            $results = $DB->get_records_sql($sql, [
                'courseid' => $context->instanceid,
                'userid' => $userid,
            ]);
            $exportedresults = [];
            foreach ($results as $result) {
                $exportedresults[] = (object) [
                    'ruleconfigid' => $result->ruleconfigid,
                    'compliance' => $result->compliance,
                    'status' => $result->status,
                    'timecreated' => transform::datetime($result->timecreated),
                ];
            }

            if ($exportdata || $exportedresults) {
                writer::with_context($context)->export_data($subcontext, (object) [
                    'courseaudits' => $exportdata,
                    'ruleresults' => $exportedresults,
                ]);
            }
        }
    }

    /**
     * Delete all component user identifiers in a context.
     *
     * Institutional audit rows are kept; userid is anonymised.
     *
     * @param \context $context Context being deleted
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        $DB->set_field('local_bbcotodobien_course_audit', 'userid', 0, ['courseid' => $context->instanceid]);
        self::anonymise_rule_results($context->instanceid);
    }

    /**
     * Delete approved data for one user.
     *
     * @param approved_contextlist $contextlist Approved contexts
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }
            $DB->set_field(
                'local_bbcotodobien_course_audit',
                'userid',
                0,
                ['courseid' => $context->instanceid, 'userid' => $userid]
            );
            self::anonymise_rule_results($context->instanceid, [$userid]);
        }
    }

    /**
     * Add users with data in a context to a user list.
     *
     * @param userlist $userlist User list
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        $sql = "SELECT ca.userid
                  FROM {local_bbcotodobien_course_audit} ca
                 WHERE ca.courseid = :courseid1
                   AND ca.userid <> 0
              UNION
                SELECT rr.userid
                  FROM {local_bbcotodobien_rule_result} rr
                  JOIN {local_bbcotodobien_course_audit} ca ON ca.id = rr.courseauditid
                 WHERE ca.courseid = :courseid2
                   AND rr.userid <> 0";
        $userlist->add_from_sql('userid', $sql, [
            'courseid1' => $context->instanceid,
            'courseid2' => $context->instanceid,
        ]);
    }

    /**
     * Delete approved users' data in a context.
     *
     * @param approved_userlist $userlist Approved users
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_COURSE || $userlist->count() === 0) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params['courseid'] = $context->instanceid;
        $DB->set_field_select(
            'local_bbcotodobien_course_audit',
            'userid',
            0,
            "userid $insql AND courseid = :courseid",
            $params
        );
        self::anonymise_rule_results($context->instanceid, $userlist->get_userids());
    }

    /**
     * Anonymise rule result user identifiers for a course.
     *
     * @param int $courseid Course id
     * @param array|null $userids Optional subset of user ids, or null for all
     */
    private static function anonymise_rule_results(int $courseid, ?array $userids = null): void {
        global $DB;

        $auditids = $DB->get_fieldset_select(
            'local_bbcotodobien_course_audit',
            'id',
            'courseid = :courseid',
            ['courseid' => $courseid]
        );
        if (!$auditids) {
            return;
        }

        [$auditinsql, $params] = $DB->get_in_or_equal($auditids, SQL_PARAMS_NAMED);
        $select = "courseauditid $auditinsql";
        if ($userids) {
            [$userinsql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $select .= " AND userid $userinsql";
            $params = array_merge($params, $userparams);
        }
        $DB->set_field_select('local_bbcotodobien_rule_result', 'userid', 0, $select, $params);
    }
}
