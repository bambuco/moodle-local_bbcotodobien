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

namespace local_bbcotodobien\local;

/**
 * Persists audit executions and recalculates course audit compliance.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class writer {
    /**
     * Create a new course audit execution.
     *
     * @param int $courseid Course id
     * @param int $audittypeid Audit type id
     * @param int $userid User who ran the audit; 0 for scheduled tasks
     * @return int New course audit id
     */
    public static function create_course_audit(int $courseid, int $audittypeid, int $userid = 0): int {
        global $DB;

        audit_type_manager::require_type($audittypeid);
        $now = time();
        $record = (object) [
            'courseid' => $courseid,
            'audittypeid' => $audittypeid,
            'userid' => $userid,
            'compliance' => 100,
            'status' => result::STATUS_NA,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        return (int) $DB->insert_record('local_bbcotodobien_course_audit', $record);
    }

    /**
     * Store a rule result (and its details) and recalculate the parent audit.
     *
     * Previous results for the same rule in this audit are kept; the latest id is the valid one.
     *
     * @param int $courseauditid Course audit id
     * @param int $ruleconfigid Rule config id
     * @param int $userid User who ran the evaluation; 0 for scheduled tasks
     * @param result $result Evaluation result
     * @return int New rule result id
     */
    public static function save_rule_result(
        int $courseauditid,
        int $ruleconfigid,
        int $userid,
        result $result
    ): int {
        global $DB;

        self::require_course_audit($courseauditid);
        audit_type_manager::require_rule_config($ruleconfigid);

        $now = time();
        $resultid = (int) $DB->insert_record('local_bbcotodobien_rule_result', (object) [
            'userid' => $userid,
            'courseauditid' => $courseauditid,
            'ruleconfigid' => $ruleconfigid,
            'compliance' => $result->compliance,
            'status' => $result->status,
            'timecreated' => $now,
        ]);

        foreach ($result->details as $detail) {
            $DB->insert_record('local_bbcotodobien_rule_detail', (object) [
                'ruleresultid' => $resultid,
                'targettype' => $detail->targettype,
                'targetid' => $detail->targetid,
                'targetname' => $detail->targetname,
                'status' => $detail->status,
                'details' => $detail->to_storage(),
                'timecreated' => $now,
            ]);
        }

        self::recalculate_course_audit($courseauditid);
        return $resultid;
    }

    /**
     * Recalculate compliance from the latest result of each rule in the audit.
     *
     * @param int $courseauditid Course audit id
     */
    public static function recalculate_course_audit(int $courseauditid): void {
        global $DB;

        $audit = self::require_course_audit($courseauditid);
        $latest = self::get_latest_results($courseauditid);
        $contributions = [];
        foreach ($latest as $row) {
            $config = $DB->get_record('local_bbcotodobien_rule_config', ['id' => $row->ruleconfigid]);
            if (!$config) {
                continue;
            }
            $contributions[] = aggregator::contribution(
                new result((float) $row->compliance, $row->status),
                !empty($config->mandatory)
            );
        }

        $aggregated = aggregator::from_rule_results($contributions);
        $audit->compliance = $aggregated->compliance;
        $audit->status = $aggregated->status;
        $audit->timemodified = time();
        $DB->update_record('local_bbcotodobien_course_audit', $audit);
    }

    /**
     * Return the latest result for each rule in a course audit.
     *
     * @param int $courseauditid Course audit id
     * @return \stdClass[]
     */
    public static function get_latest_results(int $courseauditid): array {
        global $DB;

        $sql = "SELECT rr.*
                  FROM {local_bbcotodobien_rule_result} rr
                  JOIN (
                        SELECT ruleconfigid, MAX(id) AS latestid
                          FROM {local_bbcotodobien_rule_result}
                         WHERE courseauditid = :courseauditid
                      GROUP BY ruleconfigid
                       ) latest ON latest.latestid = rr.id
              ORDER BY rr.ruleconfigid ASC";
        return $DB->get_records_sql($sql, ['courseauditid' => $courseauditid]);
    }

    /**
     * Return the latest result for one rule in a course audit.
     *
     * @param int $courseauditid Course audit id
     * @param int $ruleconfigid Rule config id
     * @return \stdClass|null
     */
    public static function get_latest_rule_result(int $courseauditid, int $ruleconfigid): ?\stdClass {
        global $DB;

        $records = $DB->get_records(
            'local_bbcotodobien_rule_result',
            ['courseauditid' => $courseauditid, 'ruleconfigid' => $ruleconfigid],
            'id DESC',
            '*',
            0,
            1
        );
        if (!$records) {
            return null;
        }
        return reset($records);
    }

    /**
     * Return every stored result for one rule in a course audit, newest first.
     *
     * @param int $courseauditid Course audit id
     * @param int $ruleconfigid Rule config id
     * @return \stdClass[]
     */
    public static function get_rule_results(int $courseauditid, int $ruleconfigid): array {
        global $DB;

        return $DB->get_records(
            'local_bbcotodobien_rule_result',
            ['courseauditid' => $courseauditid, 'ruleconfigid' => $ruleconfigid],
            'id DESC'
        );
    }

    /**
     * Return details for a rule result.
     *
     * @param int $ruleresultid Rule result id
     * @return \stdClass[]
     */
    public static function get_rule_details(int $ruleresultid): array {
        global $DB;

        return $DB->get_records('local_bbcotodobien_rule_detail', ['ruleresultid' => $ruleresultid], 'id ASC');
    }

    /**
     * Return the most recent course audit for a course and type.
     *
     * @param int $courseid Course id
     * @param int $audittypeid Audit type id
     * @return \stdClass|null
     */
    public static function get_latest_course_audit(int $courseid, int $audittypeid): ?\stdClass {
        global $DB;

        $records = $DB->get_records(
            'local_bbcotodobien_course_audit',
            ['courseid' => $courseid, 'audittypeid' => $audittypeid],
            'id DESC',
            '*',
            0,
            1
        );
        if (!$records) {
            return null;
        }
        return reset($records);
    }

    /**
     * Delete a course audit and its results.
     *
     * @param int $courseauditid Course audit id
     */
    public static function delete_course_audit(int $courseauditid): void {
        global $DB;

        $results = $DB->get_records('local_bbcotodobien_rule_result', ['courseauditid' => $courseauditid], '', 'id');
        foreach ($results as $result) {
            $DB->delete_records('local_bbcotodobien_rule_detail', ['ruleresultid' => $result->id]);
        }
        $DB->delete_records('local_bbcotodobien_rule_result', ['courseauditid' => $courseauditid]);
        $DB->delete_records('local_bbcotodobien_course_audit', ['id' => $courseauditid]);
    }

    /**
     * Delete every stored audit for a course.
     *
     * @param int $courseid Course id
     * @return int Number of course audits deleted
     */
    public static function delete_audits_for_course(int $courseid): int {
        global $DB;

        $audits = $DB->get_records('local_bbcotodobien_course_audit', ['courseid' => $courseid], '', 'id');
        foreach ($audits as $audit) {
            self::delete_course_audit((int) $audit->id);
        }
        return count($audits);
    }

    /**
     * Apply retention-by-age and per-course volume limits.
     *
     * Age is applied first, then the newest reports are kept up to the volume cap.
     *
     * @return int Number of course audits deleted
     */
    public static function cleanup_history(): int {
        return self::cleanup_history_by_age() + self::cleanup_history_by_volume();
    }

    /**
     * Delete course audits older than the configured retention period.
     *
     * @return int Number of course audits deleted
     */
    public static function cleanup_history_by_age(): int {
        global $DB;

        $days = (int) get_config('local_bbcotodobien', 'retentiondays');
        if ($days <= 0) {
            return 0;
        }

        $cutoff = time() - ($days * DAYSECS);
        $audits = $DB->get_records_select(
            'local_bbcotodobien_course_audit',
            'timecreated < :cutoff',
            ['cutoff' => $cutoff],
            'id ASC',
            'id'
        );
        foreach ($audits as $audit) {
            self::delete_course_audit((int) $audit->id);
        }
        return count($audits);
    }

    /**
     * Keep at most N course audits per course and audit type.
     *
     * @return int Number of course audits deleted
     */
    public static function cleanup_history_by_volume(): int {
        global $DB;

        $max = (int) get_config('local_bbcotodobien', 'maxhistorypercourse');
        if ($max <= 0) {
            return 0;
        }

        $sql = "SELECT MIN(id) AS id, courseid, audittypeid, COUNT(id) AS reportcount
                  FROM {local_bbcotodobien_course_audit}
              GROUP BY courseid, audittypeid
                HAVING COUNT(id) > :maxreports";
        $groups = $DB->get_records_sql($sql, ['maxreports' => $max]);
        $deleted = 0;
        foreach ($groups as $group) {
            $audits = $DB->get_records(
                'local_bbcotodobien_course_audit',
                ['courseid' => $group->courseid, 'audittypeid' => $group->audittypeid],
                'id DESC',
                'id'
            );
            $kept = 0;
            foreach ($audits as $audit) {
                $kept++;
                if ($kept <= $max) {
                    continue;
                }
                self::delete_course_audit((int) $audit->id);
                $deleted++;
            }
        }
        return $deleted;
    }

    /**
     * Fetch a course audit or throw a user-facing exception.
     *
     * @param int $id Course audit id
     * @return \stdClass
     */
    public static function require_course_audit(int $id): \stdClass {
        global $DB;

        $record = $DB->get_record('local_bbcotodobien_course_audit', ['id' => $id]);
        if (!$record) {
            throw new \moodle_exception('errorinvalidcourseaudit', 'local_bbcotodobien');
        }
        return $record;
    }
}
