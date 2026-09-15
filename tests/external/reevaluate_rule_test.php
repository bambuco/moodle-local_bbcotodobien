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

namespace local_bbcotodobien\external;

use local_bbcotodobien\local\audit_type_manager;
use local_bbcotodobien\local\engine;
use local_bbcotodobien\local\result;
use local_bbcotodobien\tests\fixtures\stub_rule;

/**
 * Tests for the rule re-evaluation web service.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(reevaluate_rule::class)]
final class reevaluate_rule_test extends \core_external\tests\externallib_testcase {
    /**
     * Load the fixture class.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/local/bbcotodobien/tests/fixtures/stub_rule.php');
    }

    /**
     * Teachers with execute can re-run a rule.
     */
    public function test_execute_reevaluates_rule(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $typeid = audit_type_manager::create_type('Quality');
        $ruleid = audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => stub_rule::class,
            'name' => get_string('stubrule', 'local_bbcotodobien'),
            'mandatory' => true,
            'params' => ['pass' => false],
        ]);
        engine::run_audit($course->id, $typeid, 0);

        $this->setUser($teacher);
        $result = reevaluate_rule::execute((int) $course->id, $ruleid);
        $result = reevaluate_rule::clean_returnvalue(reevaluate_rule::execute_returns(), $result);
        $this->assertSame(result::STATUS_FAIL, $result['status']);
        $this->assertSame(0.0, (float) $result['compliance']);
        $this->assertGreaterThan(0, $result['auditid']);
    }

    /**
     * Students cannot re-evaluate rules.
     */
    public function test_execute_requires_capability(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $typeid = audit_type_manager::create_type('Quality');
        $ruleid = audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => stub_rule::class,
            'name' => get_string('stubrule', 'local_bbcotodobien'),
            'mandatory' => true,
            'params' => ['pass' => true],
        ]);
        engine::run_audit($course->id, $typeid, 0);

        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        reevaluate_rule::execute((int) $course->id, $ruleid);
    }
}
