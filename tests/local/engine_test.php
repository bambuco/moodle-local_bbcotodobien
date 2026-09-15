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
use local_bbcotodobien\tests\fixtures\stub_rule;

/**
 * Tests for the synchronous audit engine.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(engine::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(detail::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(audit_completed::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(rule_reevaluated::class)]
final class engine_test extends \advanced_testcase {
    /**
     * Load the fixture class.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/local/bbcotodobien/tests/fixtures/stub_rule.php');
    }

    /**
     * A full run creates a course audit, persists active rules and fires audit_completed.
     */
    public function test_run_audit_persists_results_and_triggers_event(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $typeid = audit_type_manager::create_type('Quality');
        $activeid = audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => stub_rule::class,
            'name' => get_string('stubrule', 'local_bbcotodobien'),
            'mandatory' => true,
            'active' => true,
            'params' => ['pass' => true],
        ]);
        $inactiveid = audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => stub_rule::class,
            'name' => get_string('stubrule', 'local_bbcotodobien'),
            'mandatory' => true,
            'active' => false,
            'params' => ['pass' => false],
        ]);

        $sink = $this->redirectEvents();
        $audit = engine::run_audit($course->id, $typeid);
        $events = $this->filter_events($sink->get_events(), audit_completed::class);
        $sink->close();

        $this->assertSame(100.0, (float) $audit->compliance);
        $this->assertSame(result::STATUS_PASS, $audit->status);
        $this->assertSame((int) $GLOBALS['USER']->id, (int) $audit->userid);
        $this->assertTrue($DB->record_exists('local_bbcotodobien_rule_result', [
            'courseauditid' => $audit->id,
            'ruleconfigid' => $activeid,
        ]));
        $this->assertFalse($DB->record_exists('local_bbcotodobien_rule_result', [
            'courseauditid' => $audit->id,
            'ruleconfigid' => $inactiveid,
        ]));

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertEquals($audit->id, $event->objectid);
        $this->assertEquals($course->id, $event->courseid);
        $this->assertEquals($typeid, $event->other['audittypeid']);
        $this->assertEquals(100.0, $event->other['compliance']);
        $this->assertStringContainsString((string) $course->id, $event->get_description());
        $this->assertEventContextNotUsed($event);
        $this->assertCount(0, $this->filter_events($sink->get_events(), rule_reevaluated::class));
    }

    /**
     * A second full run creates a new course audit instead of appending to the previous one.
     */
    public function test_run_audit_creates_new_execution(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $typeid = audit_type_manager::create_type('Quality');
        audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => stub_rule::class,
            'name' => get_string('stubrule', 'local_bbcotodobien'),
            'mandatory' => true,
            'params' => ['pass' => true],
        ]);

        $first = engine::run_audit($course->id, $typeid, 0);
        $second = engine::run_audit($course->id, $typeid, 0);

        $this->assertNotEquals($first->id, $second->id);
        $this->assertEquals(0, $first->userid);
        $this->assertCount(2, $DB->get_records('local_bbcotodobien_course_audit', [
            'courseid' => $course->id,
            'audittypeid' => $typeid,
        ]));
        $this->assertEquals($second->id, writer::get_latest_course_audit($course->id, $typeid)->id);
    }

    /**
     * A full run with no active rules stores 100% / na and still fires the event.
     */
    public function test_run_audit_without_active_rules_is_na(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $typeid = audit_type_manager::create_type('Empty');

        $sink = $this->redirectEvents();
        $audit = engine::run_audit($course->id, $typeid, 0);
        $events = $this->filter_events($sink->get_events(), audit_completed::class);
        $sink->close();

        $this->assertSame(100.0, (float) $audit->compliance);
        $this->assertSame(result::STATUS_NA, $audit->status);
        $this->assertCount(1, $events);
    }

    /**
     * Re-evaluating a rule without a previous audit creates one and fires rule_reevaluated.
     */
    public function test_run_rule_creates_audit_when_missing(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $typeid = audit_type_manager::create_type('Quality');
        $configid = audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => stub_rule::class,
            'name' => get_string('stubrule', 'local_bbcotodobien'),
            'mandatory' => true,
            'params' => ['pass' => false],
        ]);

        $sink = $this->redirectEvents();
        $stored = engine::run_rule($course->id, $configid, 0);
        $events = $this->filter_events($sink->get_events(), rule_reevaluated::class);
        $completed = $this->filter_events($sink->get_events(), audit_completed::class);
        $sink->close();

        $this->assertSame(result::STATUS_FAIL, $stored->status);
        $this->assertSame(0.0, (float) $stored->compliance);
        $this->assertEquals(0, $stored->userid);
        $this->assertCount(1, $DB->get_records('local_bbcotodobien_course_audit', [
            'courseid' => $course->id,
            'audittypeid' => $typeid,
        ]));

        $audit = writer::require_course_audit((int) $stored->courseauditid);
        $this->assertSame(0.0, (float) $audit->compliance);
        $this->assertSame(result::STATUS_FAIL, $audit->status);

        $this->assertCount(1, $events);
        $this->assertCount(0, $completed);
        $event = $events[0];
        $this->assertEquals($stored->id, $event->objectid);
        $this->assertEquals($configid, $event->other['ruleconfigid']);
        $this->assertEquals($typeid, $event->other['audittypeid']);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Re-evaluating a rule appends a result to the latest audit and recalculates compliance.
     */
    public function test_run_rule_appends_to_latest_audit(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $typeid = audit_type_manager::create_type('Quality');
        $configid = audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => stub_rule::class,
            'name' => get_string('stubrule', 'local_bbcotodobien'),
            'mandatory' => true,
            'params' => ['pass' => true],
        ]);

        $audit = engine::run_audit($course->id, $typeid, 0);
        $this->assertSame(result::STATUS_PASS, $audit->status);

        audit_type_manager::update_rule_config($configid, ['params' => ['pass' => false]]);
        $stored = engine::run_rule($course->id, $configid, 0);

        $this->assertEquals($audit->id, $stored->courseauditid);
        $this->assertCount(1, $DB->get_records('local_bbcotodobien_course_audit', [
            'courseid' => $course->id,
            'audittypeid' => $typeid,
        ]));
        $this->assertCount(2, $DB->get_records('local_bbcotodobien_rule_result', [
            'courseauditid' => $audit->id,
            'ruleconfigid' => $configid,
        ]));

        $updated = writer::require_course_audit((int) $audit->id);
        $this->assertSame(0.0, (float) $updated->compliance);
        $this->assertSame(result::STATUS_FAIL, $updated->status);
        $this->assertGreaterThanOrEqual((int) $audit->timemodified, (int) $updated->timemodified);
    }

    /**
     * Invalid rule classes are stored as error results instead of aborting the run.
     */
    public function test_run_audit_stores_error_for_invalid_class(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $typeid = audit_type_manager::create_type('Broken');
        $configid = audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => '\\stdClass',
            'name' => 'Broken',
            'mandatory' => true,
        ]);

        $audit = engine::run_audit($course->id, $typeid, 0);
        $latest = writer::get_latest_rule_result((int) $audit->id, $configid);

        $this->assertSame(result::STATUS_ERROR, $audit->status);
        $this->assertSame(0.0, (float) $audit->compliance);
        $this->assertSame(result::STATUS_ERROR, $latest->status);
        $details = writer::get_rule_details($latest->id);
        $this->assertCount(1, $details);
        $detail = reset($details);
        $payload = json_decode($detail->details, true);
        $this->assertIsArray($payload);
        $this->assertSame('ruleevaluationerror', $payload['identifier']);
        $this->assertSame(
            get_string('ruleevaluationerror', 'local_bbcotodobien'),
            base::format_detail(detail::from_storage(
                $detail->targettype,
                (int) $detail->targetid,
                $detail->targetname,
                $detail->status,
                $detail->details
            ))
        );
    }

    /**
     * Types that do not cover the course cannot be executed.
     */
    public function test_run_audit_rejects_out_of_scope_course(): void {
        $this->resetAfterTest();

        $inside = $this->getDataGenerator()->create_category();
        $outside = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $outside->id]);
        $typeid = audit_type_manager::create_type('Faculty', [$inside->id]);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('erroraudittypenotapplicable', 'local_bbcotodobien'));
        engine::run_audit($course->id, $typeid, 0);
    }

    /**
     * The site course cannot be audited.
     */
    public function test_run_audit_rejects_site_course(): void {
        $this->resetAfterTest();
        $typeid = audit_type_manager::create_type('Site');

        $this->expectException(\moodle_exception::class);
        engine::run_audit((int) SITEID, $typeid, 0);
    }

    /**
     * Stored detail JSON is translated when the result is loaded back.
     */
    public function test_stored_detail_json_roundtrips_to_language_string(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $typeid = audit_type_manager::create_type('Quality');
        $configid = audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => stub_rule::class,
            'name' => get_string('stubrule', 'local_bbcotodobien'),
            'mandatory' => true,
            'params' => ['pass' => true],
        ]);

        $audit = engine::run_audit($course->id, $typeid, 0);
        $latest = writer::get_latest_rule_result((int) $audit->id, $configid);
        $rows = writer::get_rule_details($latest->id);
        $this->assertCount(1, $rows);
        $row = reset($rows);

        $payload = json_decode($row->details, true);
        $this->assertSame(['identifier' => 'stubrulepassed'], $payload);

        $detail = detail::from_storage(
            $row->targettype,
            (int) $row->targetid,
            $row->targetname,
            $row->status,
            $row->details
        );
        $rule = new stub_rule();
        $this->assertSame(get_string('stubrulepassed', 'local_bbcotodobien'), $rule->render_detail($detail));
    }

    /**
     * Keep events of a given class from a mixed sink.
     *
     * @param \core\event\base[] $events Captured events
     * @param string $classname Event class
     * @return \core\event\base[]
     */
    private function filter_events(array $events, string $classname): array {
        return array_values(array_filter(
            $events,
            static fn($event) => $event instanceof $classname
        ));
    }
}
