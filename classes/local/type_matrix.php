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
 * Builds the dynamic-column matrix for an audit type snapshot.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class type_matrix {
    /**
     * Column keys and translated headers for a type.
     *
     * Keys are stable; values are labels. Rule columns use rule_{id}.
     *
     * @param int $audittypeid Audit type id
     * @return array<string, string>
     */
    public static function get_columns(int $audittypeid): array {
        audit_type_manager::require_type($audittypeid);

        $columns = [
            'auditdate' => get_string('snapshotcolauditdate', 'local_bbcotodobien'),
            'contactid' => get_string('snapshotcolcontactid', 'local_bbcotodobien'),
            'contactusername' => get_string('snapshotcolcontactusername', 'local_bbcotodobien'),
            'contactidnumber' => get_string('snapshotcolcontactidnumber', 'local_bbcotodobien'),
            'contactfirstname' => get_string('snapshotcolcontactfirstname', 'local_bbcotodobien'),
            'contactlastname' => get_string('snapshotcolcontactlastname', 'local_bbcotodobien'),
            'contactemail' => get_string('snapshotcolcontactemail', 'local_bbcotodobien'),
            'courseid' => get_string('snapshotcolcourseid', 'local_bbcotodobien'),
            'courseshortname' => get_string('snapshotcolcourseshortname', 'local_bbcotodobien'),
            'coursefullname' => get_string('snapshotcolcoursefullname', 'local_bbcotodobien'),
            'courseidnumber' => get_string('snapshotcolcourseidnumber', 'local_bbcotodobien'),
            'coursecategory' => get_string('snapshotcolcoursecategory', 'local_bbcotodobien'),
            'categorypath' => get_string('snapshotcolcategorypath', 'local_bbcotodobien'),
        ];

        $namecounts = [];
        $configs = audit_type_manager::get_rule_configs($audittypeid, true);
        foreach ($configs as $config) {
            $name = (string) $config->name;
            $namecounts[$name] = ($namecounts[$name] ?? 0) + 1;
        }
        foreach ($configs as $config) {
            $label = (string) $config->name;
            if ($namecounts[$label] > 1) {
                $label .= ' #' . $config->id;
            }
            $columns['rule_' . $config->id] = $label;
        }

        return $columns;
    }

    /**
     * Yield denormalised snapshot rows (one per course contact).
     *
     * @param int $audittypeid Audit type id
     * @return \Generator<int, array>
     */
    public static function iterate_rows(int $audittypeid): \Generator {
        $columns = self::get_columns($audittypeid);
        $configs = audit_type_manager::get_rule_configs($audittypeid, true);
        $ruleids = array_map(static fn($config): int => (int) $config->id, $configs);

        $audits = self::get_latest_audits($audittypeid);
        if (!$audits) {
            return;
        }

        $courses = [];
        foreach ($audits as $audit) {
            $courseid = (int) $audit->courseid;
            $courses[$courseid] = (object) ['id' => $courseid];
        }
        $contactsbycourse = self::load_contacts($courses);
        $results = self::get_latest_results(array_map('intval', array_keys($audits)), $ruleids);
        $categorypaths = [];

        foreach ($audits as $audit) {
            $courseid = (int) $audit->courseid;
            $categoryid = (int) $audit->category;
            if (!array_key_exists($categoryid, $categorypaths)) {
                $categorypaths[$categoryid] = self::format_category_path($categoryid);
            }

            $baserow = [
                'auditdate' => userdate((int) $audit->timemodified),
                'courseid' => $courseid,
                'courseshortname' => (string) $audit->shortname,
                'coursefullname' => (string) $audit->fullname,
                'courseidnumber' => (string) $audit->idnumber,
                'coursecategory' => $categoryid,
                'categorypath' => $categorypaths[$categoryid],
            ];
            foreach ($ruleids as $ruleid) {
                $stored = $results[(int) $audit->id][$ruleid] ?? null;
                $baserow['rule_' . $ruleid] = $stored
                    ? self::format_rule_cell($stored->status, (float) $stored->compliance)
                    : '';
            }

            $contacts = $contactsbycourse[$courseid] ?? [];
            if (!$contacts) {
                $contacts = [self::empty_contact()];
            }
            foreach ($contacts as $contact) {
                $row = $baserow;
                $row['contactid'] = $contact->id;
                $row['contactusername'] = $contact->username;
                $row['contactidnumber'] = $contact->idnumber;
                $row['contactfirstname'] = $contact->firstname;
                $row['contactlastname'] = $contact->lastname;
                $row['contactemail'] = $contact->email;
                yield self::order_row($columns, $row);
            }
        }
    }

    /**
     * Latest course audit per course for the type, joined to existing courses.
     *
     * @param int $audittypeid Audit type id
     * @return \stdClass[] keyed by course audit id
     */
    protected static function get_latest_audits(int $audittypeid): array {
        global $DB;

        $sql = "SELECT ca.id, ca.courseid, ca.timemodified,
                       c.shortname, c.fullname, c.idnumber, c.category
                  FROM {local_bbcotodobien_course_audit} ca
                  JOIN (
                        SELECT MAX(id) AS id
                          FROM {local_bbcotodobien_course_audit}
                         WHERE audittypeid = :audittypeid
                      GROUP BY courseid
                       ) latest ON latest.id = ca.id
                  JOIN {course} c ON c.id = ca.courseid
              ORDER BY c.sortorder ASC, c.id ASC";
        return $DB->get_records_sql($sql, ['audittypeid' => $audittypeid]);
    }

    /**
     * Latest stored status and compliance per audit and active rule.
     *
     * @param int[] $auditids Course audit ids
     * @param int[] $ruleconfigids Active rule config ids
     * @return array<int, array<int, \stdClass>> Objects with status and compliance
     */
    protected static function get_latest_results(array $auditids, array $ruleconfigids): array {
        global $DB;

        if (!$auditids || !$ruleconfigids) {
            return [];
        }

        [$auditin, $auditparams] = $DB->get_in_or_equal($auditids, SQL_PARAMS_NAMED, 'a');
        [$rulein, $ruleparams] = $DB->get_in_or_equal($ruleconfigids, SQL_PARAMS_NAMED, 'r');
        $sql = "SELECT rr.courseauditid, rr.ruleconfigid, rr.status, rr.compliance
                  FROM {local_bbcotodobien_rule_result} rr
                  JOIN (
                        SELECT courseauditid, ruleconfigid, MAX(id) AS latestid
                          FROM {local_bbcotodobien_rule_result}
                         WHERE courseauditid {$auditin}
                           AND ruleconfigid {$rulein}
                      GROUP BY courseauditid, ruleconfigid
                       ) latest ON latest.latestid = rr.id";
        $records = $DB->get_recordset_sql($sql, $auditparams + $ruleparams);
        $results = [];
        foreach ($records as $record) {
            $results[(int) $record->courseauditid][(int) $record->ruleconfigid] = (object) [
                'status' => (string) $record->status,
                'compliance' => (float) $record->compliance,
            ];
        }
        $records->close();
        return $results;
    }

    /**
     * Format a rule cell for the snapshot export.
     *
     * Pass and fail show compliance as a percentage. Not applicable, error and
     * missing results are empty (never forced to 0).
     *
     * @param string $status Result status
     * @param float $compliance Compliance percentage 0-100
     * @return string
     */
    protected static function format_rule_cell(string $status, float $compliance): string {
        if ($status === result::STATUS_PASS || $status === result::STATUS_FAIL) {
            return format_float($compliance, 2);
        }
        return '';
    }

    /**
     * Course contacts keyed by course id, unique per user.
     *
     * @param \stdClass[] $courses Courses keyed by id, with id populated
     * @return array<int, \stdClass[]>
     */
    protected static function load_contacts(array $courses): array {
        global $DB;

        $bycourse = [];
        foreach (array_keys($courses) as $id) {
            $bycourse[$id] = [];
        }
        if (!$courses) {
            return $bycourse;
        }

        \core_course_category::preload_course_contacts($courses);

        $userids = [];
        foreach ($courses as $id => $course) {
            if (empty($course->managers)) {
                continue;
            }
            foreach ($course->managers as $manager) {
                $userid = (int) $manager->id;
                $bycourse[$id][$userid] = $userid;
                $userids[$userid] = $userid;
            }
        }
        if (!$userids) {
            return array_map(static fn(): array => [], $bycourse);
        }

        $users = $DB->get_records_list(
            'user',
            'id',
            $userids,
            'id ASC',
            'id, username, idnumber, firstname, lastname, email'
        );
        $result = [];
        foreach ($bycourse as $courseid => $ids) {
            $result[$courseid] = [];
            ksort($ids);
            foreach ($ids as $userid) {
                if (isset($users[$userid])) {
                    $result[$courseid][] = $users[$userid];
                }
            }
        }
        return $result;
    }

    /**
     * Nested category path from the root, or empty when missing.
     *
     * @param int $categoryid Category id
     * @return string
     */
    protected static function format_category_path(int $categoryid): string {
        if ($categoryid <= 0) {
            return '';
        }
        $category = \core_course_category::get($categoryid, IGNORE_MISSING, true);
        return $category ? $category->get_nested_name(false) : '';
    }

    /**
     * Placeholder contact used when a course has none.
     *
     * @return \stdClass
     */
    protected static function empty_contact(): \stdClass {
        return (object) [
            'id' => '',
            'username' => '',
            'idnumber' => '',
            'firstname' => '',
            'lastname' => '',
            'email' => '',
        ];
    }

    /**
     * Align a keyed row to the column order expected by dataformat.
     *
     * @param array $columns Column map
     * @param array $row Keyed values
     * @return array
     */
    protected static function order_row(array $columns, array $row): array {
        $ordered = [];
        foreach (array_keys($columns) as $key) {
            $ordered[] = $row[$key] ?? '';
        }
        return $ordered;
    }
}
