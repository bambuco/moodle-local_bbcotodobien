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

use local_bbcotodobien\tests\fixtures\stub_rule;

/**
 * Tests for audit type CRUD, category scope and result persistence.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(audit_type_manager::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(scope::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(writer::class)]
final class persistence_test extends \advanced_testcase {
    /**
     * Load the fixture class.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/local/bbcotodobien/tests/fixtures/stub_rule.php');
    }

    /**
     * Empty categories apply to every course except the site course.
     */
    public function test_empty_categories_apply_to_all_courses(): void {
        $this->resetAfterTest();

        $category = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $typeid = audit_type_manager::create_type('Site audit', '');

        $type = audit_type_manager::get_type($typeid);
        $this->assertSame('', $type->categories);
        $this->assertTrue(scope::type_applies_to_course($type->categories, $course));
        $this->assertContainsEquals($course->id, scope::get_course_ids_for_type($type));
        $this->assertArrayHasKey($typeid, scope::get_audit_types_for_course($course->id));
        $this->assertFalse(scope::type_applies_to_course($type->categories, get_course(SITEID)));
    }

    /**
     * Types assigned to a parent category apply to descendant courses.
     */
    public function test_parent_category_applies_to_descendant_courses(): void {
        $this->resetAfterTest();

        $parent = $this->getDataGenerator()->create_category();
        $child = $this->getDataGenerator()->create_category(['parent' => $parent->id]);
        $other = $this->getDataGenerator()->create_category();
        $courseinparent = $this->getDataGenerator()->create_course(['category' => $parent->id]);
        $courseinchild = $this->getDataGenerator()->create_course(['category' => $child->id]);
        $courseother = $this->getDataGenerator()->create_course(['category' => $other->id]);

        $typeid = audit_type_manager::create_type('Faculty audit', [$parent->id]);
        $type = audit_type_manager::get_type($typeid);

        $this->assertSame((string) $parent->id, $type->categories);
        $this->assertTrue(scope::type_applies_to_course($type->categories, $courseinparent));
        $this->assertTrue(scope::type_applies_to_course($type->categories, $courseinchild));
        $this->assertFalse(scope::type_applies_to_course($type->categories, $courseother));

        $courseids = scope::get_course_ids_for_type($type);
        $this->assertContainsEquals($courseinparent->id, $courseids);
        $this->assertContainsEquals($courseinchild->id, $courseids);
        $this->assertNotContainsEquals($courseother->id, $courseids);
    }

    /**
     * Site-wide and category types both apply independently to the same course.
     */
    public function test_multiple_types_apply_independently(): void {
        $this->resetAfterTest();

        $category = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $siteid = audit_type_manager::create_type('Site', '');
        $catid = audit_type_manager::create_type('Category', (string) $category->id);
        audit_type_manager::create_type('Inactive', '', false);

        $types = scope::get_audit_types_for_course($course->id);
        $this->assertCount(2, $types);
        $this->assertArrayHasKey($siteid, $types);
        $this->assertArrayHasKey($catid, $types);
    }

    /**
     * CSV with spaces is normalised and inactive types can still be queried.
     */
    public function test_category_csv_normalisation_and_update(): void {
        $this->resetAfterTest();

        $one = $this->getDataGenerator()->create_category();
        $two = $this->getDataGenerator()->create_category();
        $typeid = audit_type_manager::create_type('CSV', ' ' . $one->id . ' , ' . $two->id . ' ');
        $type = audit_type_manager::get_type($typeid);
        $this->assertSame($one->id . ',' . $two->id, $type->categories);

        audit_type_manager::update_type($typeid, ['active' => false, 'name' => 'Renamed']);
        $updated = audit_type_manager::get_type($typeid);
        $this->assertSame('Renamed', $updated->name);
        $this->assertEquals(0, $updated->active);
        $this->assertArrayNotHasKey($typeid, scope::get_audit_types_for_course(
            $this->getDataGenerator()->create_course(['category' => $one->id])->id
        ));
    }

    /**
     * Saving rule results keeps history and recalculates from the latest per rule.
     */
    public function test_writer_persists_latest_and_recalculates(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $typeid = audit_type_manager::create_type('Quality');
        $mandatoryid = audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => stub_rule::class,
            'name' => get_string('stubrule', 'local_bbcotodobien'),
            'mandatory' => true,
            'params' => ['pass' => true],
        ]);
        $optionalid = audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => stub_rule::class,
            'name' => get_string('stubrule', 'local_bbcotodobien'),
            'mandatory' => false,
        ]);

        $auditid = writer::create_course_audit($course->id, $typeid, 2);
        $rule = new stub_rule();

        writer::save_rule_result($auditid, $mandatoryid, 2, $rule->evaluate($course, ['pass' => true]));
        writer::save_rule_result($auditid, $optionalid, 2, $rule->evaluate($course, ['pass' => false]));

        $audit = writer::require_course_audit($auditid);
        $this->assertSame(100.0, (float) $audit->compliance);
        $this->assertSame(result::STATUS_PASS, $audit->status);

        writer::save_rule_result($auditid, $mandatoryid, 2, $rule->evaluate($course, ['pass' => false]));
        $audit = writer::require_course_audit($auditid);
        $this->assertSame(0.0, (float) $audit->compliance);
        $this->assertSame(result::STATUS_FAIL, $audit->status);

        $this->assertCount(2, $DB->get_records('local_bbcotodobien_rule_result', [
            'courseauditid' => $auditid,
            'ruleconfigid' => $mandatoryid,
        ]));
        $latest = writer::get_latest_rule_result($auditid, $mandatoryid);
        $this->assertSame(result::STATUS_FAIL, $latest->status);
        $this->assertCount(1, writer::get_rule_details($latest->id));
        $this->assertEquals($auditid, writer::get_latest_course_audit($course->id, $typeid)->id);
    }

    /**
     * Deleting a type purges configs and historical executions.
     */
    public function test_delete_type_cascades(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $typeid = audit_type_manager::create_type('To delete');
        $configid = audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => stub_rule::class,
            'name' => get_string('stubrule', 'local_bbcotodobien'),
            'mandatory' => true,
        ]);
        $auditid = writer::create_course_audit($course->id, $typeid);
        writer::save_rule_result($auditid, $configid, 0, (new stub_rule())->evaluate($course, ['pass' => true]));

        audit_type_manager::delete_type($typeid);

        $this->assertFalse($DB->record_exists('local_bbcotodobien_audit_type', ['id' => $typeid]));
        $this->assertFalse($DB->record_exists('local_bbcotodobien_rule_config', ['id' => $configid]));
        $this->assertFalse($DB->record_exists('local_bbcotodobien_course_audit', ['id' => $auditid]));
        $this->assertFalse($DB->record_exists('local_bbcotodobien_rule_result', ['courseauditid' => $auditid]));
    }

    /**
     * Empty categories display as site-wide; missing IDs stay visible.
     */
    public function test_format_categories_for_display(): void {
        $this->resetAfterTest();

        $this->assertSame(
            get_string('allcategories', 'local_bbcotodobien'),
            scope::format_categories_for_display('')
        );

        $category = $this->getDataGenerator()->create_category(['name' => 'Quality']);
        $this->assertStringContainsString(
            'Quality',
            scope::format_categories_for_display((string) $category->id)
        );
        $this->assertSame(
            get_string('unknowncategory', 'local_bbcotodobien', 999999),
            scope::format_categories_for_display('999999')
        );
    }

    /**
     * Missing records throw user-facing exceptions.
     */
    public function test_missing_records_throw_moodle_exception(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        audit_type_manager::get_type(-1);
    }
}
