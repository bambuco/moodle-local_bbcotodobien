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

use local_bbcotodobien\event\audit_completed;
use local_bbcotodobien\event\rule_reevaluated;
use local_bbcotodobien\local\rules\base;
use local_bbcotodobien\local\rules\factory;

/**
 * Synchronous runner for full audit types and single-rule re-evaluations.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class engine {
    /**
     * Run every active rule of an audit type against a course.
     *
     * Always creates a new course audit execution.
     *
     * @param int $courseid Course id
     * @param int $audittypeid Audit type id
     * @param int|null $userid Runner; null uses the current user, 0 for CLI/cron
     * @return \stdClass The stored course audit
     */
    public static function run_audit(int $courseid, int $audittypeid, ?int $userid = null): \stdClass {
        $userid = self::resolve_userid($userid);
        $course = self::require_course($courseid);
        $type = audit_type_manager::get_type($audittypeid);
        self::require_type_applies($type, $course);

        $auditid = writer::create_course_audit($courseid, $audittypeid, $userid);
        foreach (audit_type_manager::get_rule_configs($audittypeid, true) as $config) {
            $result = self::evaluate_config($course, $config);
            writer::save_rule_result($auditid, (int) $config->id, $userid, $result);
        }

        $audit = writer::require_course_audit($auditid);
        self::trigger_audit_completed($audit);
        return $audit;
    }

    /**
     * Re-evaluate a single rule on a course.
     *
     * Inserts a new result on the latest course audit of that type.
     * If none exists, a course audit is created first.
     *
     * @param int $courseid Course id
     * @param int $ruleconfigid Rule configuration id
     * @param int|null $userid Runner; null uses the current user, 0 for CLI/cron
     * @return \stdClass The stored rule result
     */
    public static function run_rule(int $courseid, int $ruleconfigid, ?int $userid = null): \stdClass {
        $userid = self::resolve_userid($userid);
        $course = self::require_course($courseid);
        $config = audit_type_manager::get_rule_config($ruleconfigid);
        $type = audit_type_manager::get_type((int) $config->audittypeid);
        self::require_type_applies($type, $course);

        $audit = writer::get_latest_course_audit($courseid, (int) $config->audittypeid);
        $auditid = $audit ? (int) $audit->id : writer::create_course_audit(
            $courseid,
            (int) $config->audittypeid,
            $userid
        );

        $result = self::evaluate_config($course, $config);
        writer::save_rule_result($auditid, (int) $config->id, $userid, $result);
        $stored = writer::get_latest_rule_result($auditid, (int) $config->id);
        if (!$stored) {
            throw new \moodle_exception('errorinvalidcourseaudit', 'local_bbcotodobien');
        }

        self::trigger_rule_reevaluated($courseid, $type, $stored);
        return $stored;
    }

    /**
     * Resolve the userid stored on executions.
     *
     * @param int|null $userid Explicit userid or null for the current user
     * @return int
     */
    public static function resolve_userid(?int $userid): int {
        global $USER;

        if ($userid !== null) {
            return $userid;
        }
        if (!empty($USER->id) && !isguestuser()) {
            return (int) $USER->id;
        }
        return 0;
    }

    /**
     * Evaluate one rule configuration, converting unexpected failures into an error result.
     *
     * @param \stdClass $course Course record
     * @param \stdClass $config Rule configuration with paramsdecoded
     * @return result
     */
    public static function evaluate_config(\stdClass $course, \stdClass $config): result {
        try {
            $rule = factory::create($config->ruleclass);
            $params = $config->paramsdecoded ?? audit_type_manager::decode_params($config->params ?? '');
            return $rule->evaluate($course, $params);
        } catch (\Throwable) {
            return new result(0.0, result::STATUS_ERROR, [
                new detail(
                    base::GRANULARITY_COURSE,
                    (int) $course->id,
                    $course->fullname ?? '',
                    result::STATUS_ERROR,
                    ['identifier' => 'ruleevaluationerror']
                ),
            ]);
        }
    }

    /**
     * Fetch a real course that can be audited.
     *
     * @param int $courseid Course id
     * @return \stdClass
     */
    private static function require_course(int $courseid): \stdClass {
        global $DB;

        if ($courseid <= 0 || $courseid === (int) SITEID) {
            throw new \moodle_exception('invalidcourseid');
        }
        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            throw new \moodle_exception('invalidcourseid');
        }
        return $course;
    }

    /**
     * Ensure the audit type applies to the course.
     *
     * @param \stdClass $type Audit type record
     * @param \stdClass $course Course record
     */
    private static function require_type_applies(\stdClass $type, \stdClass $course): void {
        if (!scope::type_applies_to_course($type->categories, $course)) {
            throw new \moodle_exception('erroraudittypenotapplicable', 'local_bbcotodobien');
        }
    }

    /**
     * Trigger the full-run completion event.
     *
     * @param \stdClass $audit Course audit record
     */
    private static function trigger_audit_completed(\stdClass $audit): void {
        $event = audit_completed::create([
            'objectid' => $audit->id,
            'context' => \context_course::instance((int) $audit->courseid),
            'userid' => (int) $audit->userid,
            'other' => [
                'audittypeid' => (int) $audit->audittypeid,
                'compliance' => (float) $audit->compliance,
            ],
        ]);
        $event->trigger();
    }

    /**
     * Trigger the single-rule re-evaluation event.
     *
     * @param int $courseid Course id
     * @param \stdClass $type Audit type record
     * @param \stdClass $stored Stored rule result
     */
    private static function trigger_rule_reevaluated(int $courseid, \stdClass $type, \stdClass $stored): void {
        $event = rule_reevaluated::create([
            'objectid' => $stored->id,
            'context' => \context_course::instance($courseid),
            'userid' => (int) $stored->userid,
            'other' => [
                'audittypeid' => (int) $type->id,
                'ruleconfigid' => (int) $stored->ruleconfigid,
                'compliance' => (float) $stored->compliance,
            ],
        ]);
        $event->trigger();
    }
}
