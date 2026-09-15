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

use local_bbcotodobien\observer;
use local_bbcotodobien\task\audit_courses;
use local_bbcotodobien\task\cleanup_history;
use local_bbcotodobien\tests\fixtures\stub_rule;

/**
 * Tests for scheduled audits, skip policies, retention and course deletion.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(scheduler::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(writer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(observer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(audit_courses::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(cleanup_history::class)]
final class scheduler_test extends \advanced_testcase {
    /**
     * Load the fixture class.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/local/bbcotodobien/tests/fixtures/stub_rule.php');
    }

    /**
     * Create an active audit type with a stub rule.
     *
     * @param bool $pass Whether the stub rule passes
     * @param array|string $categories Category scope
     * @return array{0: int, 1: int} Type id and rule config id
     */
    private function create_type_with_rule(bool $pass = true, array|string $categories = ''): array {
        $typeid = audit_type_manager::create_type('Scheduled', $categories);
        $ruleid = audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => stub_rule::class,
            'name' => get_string('stubrule', 'local_bbcotodobien'),
            'mandatory' => true,
            'active' => true,
            'params' => ['pass' => $pass],
        ]);
        return [$typeid, $ruleid];
    }

    /**
     * Age a course audit so skip/retention policies can see it as old.
     *
     * @param int $auditid Course audit id
     * @param int $age Seconds in the past
     */
    private function age_audit(int $auditid, int $age): void {
        global $DB;
        $when = time() - $age;
        $DB->set_field('local_bbcotodobien_course_audit', 'timecreated', $when, ['id' => $auditid]);
        $DB->set_field('local_bbcotodobien_course_audit', 'timemodified', $when, ['id' => $auditid]);
    }

    /**
     * Hidden courses are skipped unless includehidden is enabled.
     */
    public function test_hidden_courses_are_skipped_unless_enabled(): void {
        global $DB;
        $this->resetAfterTest();
        [$typeid] = $this->create_type_with_rule();
        $visible = $this->getDataGenerator()->create_course();
        $hidden = $this->getDataGenerator()->create_course(['visible' => 0]);

        set_config('includehidden', '0', 'local_bbcotodobien');
        $stats = scheduler::run_scheduled_audits();
        $this->assertSame(1, $stats['ran']);
        $this->assertTrue($DB->record_exists('local_bbcotodobien_course_audit', [
            'courseid' => $visible->id,
            'audittypeid' => $typeid,
        ]));
        $this->assertFalse($DB->record_exists('local_bbcotodobien_course_audit', [
            'courseid' => $hidden->id,
            'audittypeid' => $typeid,
        ]));

        set_config('includehidden', '1', 'local_bbcotodobien');
        $stats = scheduler::run_scheduled_audits();
        $this->assertGreaterThanOrEqual(1, $stats['ran']);
        $this->assertTrue($DB->record_exists('local_bbcotodobien_course_audit', [
            'courseid' => $hidden->id,
            'audittypeid' => $typeid,
        ]));
    }

    /**
     * Fully compliant latest executions are skipped when skipcomplete is on.
     */
    public function test_skipcomplete_skips_hundred_percent(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        [$passid] = $this->create_type_with_rule(true);
        [$failid] = $this->create_type_with_rule(false);
        engine::run_audit((int) $course->id, $passid, 0);
        engine::run_audit((int) $course->id, $failid, 0);

        set_config('skipcomplete', '1', 'local_bbcotodobien');
        $stats = scheduler::run_scheduled_audits();
        $this->assertSame(1, $stats['ran']);
        $this->assertSame(1, $stats['skipped']);
        $this->assertCount(1, $DB->get_records('local_bbcotodobien_course_audit', [
            'courseid' => $course->id,
            'audittypeid' => $passid,
        ]));
        $this->assertCount(2, $DB->get_records('local_bbcotodobien_course_audit', [
            'courseid' => $course->id,
            'audittypeid' => $failid,
        ]));
    }

    /**
     * Unchanged skip requires both the course and the last audit to be older than N days.
     */
    public function test_skipunchanged_requires_old_course_and_audit(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        [$typeid] = $this->create_type_with_rule(false);
        $audit = engine::run_audit((int) $course->id, $typeid, 0);
        $this->age_audit((int) $audit->id, 10 * DAYSECS);
        $DB->set_field('course', 'timemodified', time() - (10 * DAYSECS), ['id' => $course->id]);
        $DB->set_field('course', 'timecreated', time() - (10 * DAYSECS), ['id' => $course->id]);
        $course = get_course($course->id);

        set_config('skipunchangeddays', '7', 'local_bbcotodobien');
        $this->assertTrue(scheduler::should_skip($course, audit_type_manager::get_type($typeid)));

        $stats = scheduler::run_scheduled_audits();
        $this->assertSame(0, $stats['ran']);
        $this->assertSame(1, $stats['skipped']);
        $this->assertCount(1, $DB->get_records('local_bbcotodobien_course_audit', [
            'courseid' => $course->id,
            'audittypeid' => $typeid,
        ]));

        $DB->set_field('course', 'timemodified', time(), ['id' => $course->id]);
        $course = get_course($course->id);
        $this->assertFalse(scheduler::should_skip($course, audit_type_manager::get_type($typeid)));
    }

    /**
     * A course that was never audited is not skipped by the unchanged policy.
     */
    public function test_skipunchanged_does_not_skip_never_audited(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        [$typeid] = $this->create_type_with_rule();
        set_config('skipunchangeddays', '7', 'local_bbcotodobien');
        $this->assertFalse(scheduler::should_skip($course, audit_type_manager::get_type($typeid)));

        $stats = scheduler::run_scheduled_audits();
        $this->assertSame(1, $stats['ran']);
        $this->assertSame(0, $stats['skipped']);
    }

    /**
     * Disabled skip settings do not skip old courses.
     */
    public function test_skip_policies_disabled_by_default(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        [$typeid] = $this->create_type_with_rule();
        $audit = engine::run_audit((int) $course->id, $typeid, 0);
        $this->age_audit((int) $audit->id, 10 * DAYSECS);

        $this->assertFalse(scheduler::should_skip($course, audit_type_manager::get_type($typeid)));
    }

    /**
     * Scheduled runs persist userid 0 and ignore inactive types and out-of-scope courses.
     */
    public function test_scheduler_userid_and_scope(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $incategory = $this->getDataGenerator()->create_category();
        $other = $this->getDataGenerator()->create_category();
        $incourse = $this->getDataGenerator()->create_course(['category' => $incategory->id]);
        $outcourse = $this->getDataGenerator()->create_course(['category' => $other->id]);
        [$typeid] = $this->create_type_with_rule(true, [$incategory->id]);
        audit_type_manager::create_type('Inactive', '', false);

        $stats = scheduler::run_scheduled_audits();
        $this->assertSame(1, $stats['ran']);
        $audit = writer::get_latest_course_audit((int) $incourse->id, $typeid);
        $this->assertNotNull($audit);
        $this->assertSame(0, (int) $audit->userid);
        $this->assertFalse($DB->record_exists('local_bbcotodobien_course_audit', [
            'courseid' => $outcourse->id,
            'audittypeid' => $typeid,
        ]));
        $this->assertCount(1, $DB->get_records('local_bbcotodobien_course_audit'));
    }

    /**
     * Retention deletes old reports; volume keeps the newest N per course and type.
     */
    public function test_cleanup_respects_age_and_volume(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        [$typeid] = $this->create_type_with_rule();
        [$otherid] = $this->create_type_with_rule();

        $oldone = writer::create_course_audit((int) $course->id, $typeid, 0);
        $oldtwo = writer::create_course_audit((int) $course->id, $typeid, 0);
        $recent = writer::create_course_audit((int) $course->id, $typeid, 0);
        $extra = writer::create_course_audit((int) $course->id, $typeid, 0);
        $newest = writer::create_course_audit((int) $course->id, $typeid, 0);
        $othertype = writer::create_course_audit((int) $course->id, $otherid, 0);
        $othercourse = writer::create_course_audit((int) $other->id, $typeid, 0);
        $this->age_audit($oldone, 10 * DAYSECS);
        $this->age_audit($oldtwo, 10 * DAYSECS);

        set_config('retentiondays', '5', 'local_bbcotodobien');
        set_config('maxhistorypercourse', '2', 'local_bbcotodobien');
        $deleted = writer::cleanup_history();
        $this->assertSame(3, $deleted);

        $remaining = $DB->get_records('local_bbcotodobien_course_audit', [
            'courseid' => $course->id,
            'audittypeid' => $typeid,
        ], 'id ASC');
        $this->assertCount(2, $remaining);
        $this->assertEqualsCanonicalizing([$extra, $newest], array_map('intval', array_keys($remaining)));
        $this->assertTrue($DB->record_exists('local_bbcotodobien_course_audit', ['id' => $othertype]));
        $this->assertTrue($DB->record_exists('local_bbcotodobien_course_audit', ['id' => $othercourse]));
    }

    /**
     * Zero retention and volume settings leave history untouched.
     */
    public function test_cleanup_disabled_keeps_history(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        [$typeid] = $this->create_type_with_rule();
        writer::create_course_audit((int) $course->id, $typeid, 0);
        writer::create_course_audit((int) $course->id, $typeid, 0);

        set_config('retentiondays', '0', 'local_bbcotodobien');
        set_config('maxhistorypercourse', '0', 'local_bbcotodobien');
        $this->assertSame(0, writer::cleanup_history());
        $this->assertCount(2, $DB->get_records('local_bbcotodobien_course_audit'));
    }

    /**
     * Deleting a course purges its audit history and leaves other courses alone.
     */
    public function test_course_deleted_purges_history(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        [$typeid, $ruleid] = $this->create_type_with_rule();
        $auditid = writer::create_course_audit((int) $course->id, $typeid, 0);
        writer::save_rule_result(
            $auditid,
            $ruleid,
            0,
            (new stub_rule())->evaluate($course, ['pass' => true])
        );
        $kept = writer::create_course_audit((int) $other->id, $typeid, 0);

        $event = \core\event\course_deleted::create([
            'objectid' => $course->id,
            'context' => \context_course::instance($course->id),
            'other' => ['shortname' => $course->shortname, 'fullname' => $course->fullname, 'idnumber' => ''],
        ]);
        observer::course_deleted($event);

        $this->assertFalse($DB->record_exists('local_bbcotodobien_course_audit', ['courseid' => $course->id]));
        $this->assertFalse($DB->record_exists('local_bbcotodobien_rule_result', ['courseauditid' => $auditid]));
        $this->assertTrue($DB->record_exists('local_bbcotodobien_course_audit', ['id' => $kept]));
    }

    /**
     * Scheduled task classes expose names and delegate to the scheduler.
     */
    public function test_task_classes_delegate(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        [$typeid] = $this->create_type_with_rule();

        $audittask = new audit_courses();
        $this->assertSame(get_string('taskauditcourses', 'local_bbcotodobien'), $audittask->get_name());
        ob_start();
        $audittask->execute();
        $output = ob_get_clean();
        $this->assertNotEmpty($output);
        $this->assertTrue($DB->record_exists('local_bbcotodobien_course_audit', [
            'courseid' => $course->id,
            'audittypeid' => $typeid,
        ]));

        set_config('maxhistorypercourse', '1', 'local_bbcotodobien');
        writer::create_course_audit((int) $course->id, $typeid, 0);
        $cleanuptask = new cleanup_history();
        $this->assertSame(get_string('taskcleanuphistory', 'local_bbcotodobien'), $cleanuptask->get_name());
        ob_start();
        $cleanuptask->execute();
        ob_end_clean();
        $this->assertCount(1, $DB->get_records('local_bbcotodobien_course_audit', [
            'courseid' => $course->id,
            'audittypeid' => $typeid,
        ]));
    }
}
