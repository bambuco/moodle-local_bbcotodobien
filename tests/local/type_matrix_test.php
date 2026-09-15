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
 * Tests for audit type snapshot matrix generation.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(type_matrix::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(type_matrix_file::class)]
final class type_matrix_test extends \advanced_testcase {
    /**
     * Load the fixture class.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/local/bbcotodobien/tests/fixtures/stub_rule.php');
    }

    /**
     * Columns include fixed fields and active rules only.
     */
    public function test_columns_include_active_rules_only(): void {
        $this->resetAfterTest();

        $typeid = audit_type_manager::create_type('Quality');
        $active = audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => stub_rule::class,
            'name' => 'Active rule',
            'active' => true,
            'params' => ['pass' => true],
        ]);
        $inactive = audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => stub_rule::class,
            'name' => 'Inactive rule',
            'active' => false,
            'params' => ['pass' => false],
        ]);

        $columns = type_matrix::get_columns($typeid);
        $this->assertArrayHasKey('auditdate', $columns);
        $this->assertArrayHasKey('categorypath', $columns);
        $this->assertArrayHasKey('rule_' . $active, $columns);
        $this->assertSame('Active rule', $columns['rule_' . $active]);
        $this->assertArrayNotHasKey('rule_' . $inactive, $columns);
    }

    /**
     * Duplicate rule names receive an id suffix.
     */
    public function test_duplicate_rule_names_get_id_suffix(): void {
        $this->resetAfterTest();

        $typeid = audit_type_manager::create_type('Quality');
        $first = audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => stub_rule::class,
            'name' => 'Same name',
            'params' => ['pass' => true],
        ]);
        $second = audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => stub_rule::class,
            'name' => 'Same name',
            'params' => ['pass' => true],
        ]);

        $columns = type_matrix::get_columns($typeid);
        $this->assertSame('Same name #' . $first, $columns['rule_' . $first]);
        $this->assertSame('Same name #' . $second, $columns['rule_' . $second]);
    }

    /**
     * Never-audited courses are omitted.
     */
    public function test_never_audited_course_is_omitted(): void {
        $this->resetAfterTest();

        $audited = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_course();
        $typeid = $this->create_type_with_rule();
        engine::run_audit($audited->id, $typeid, 0);

        $rows = $this->rows($typeid);
        $this->assertCount(1, $rows);
        $this->assertSame((int) $audited->id, (int) $rows[0]['courseid']);
    }

    /**
     * Only the latest course audit is included.
     */
    public function test_only_latest_audit_is_included(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $typeid = $this->create_type_with_rule(['pass' => false]);
        $first = engine::run_audit($course->id, $typeid, 0);
        $ruleid = $this->first_active_rule_id($typeid);
        audit_type_manager::update_rule_config($ruleid, ['params' => ['pass' => true]]);
        $second = engine::run_audit($course->id, $typeid, 0);

        $this->assertNotEquals($first->id, $second->id);

        $rows = $this->rows($typeid);
        $this->assertCount(1, $rows);
        $this->assertSame(userdate((int) $second->timemodified), $rows[0]['auditdate']);
        $this->assertSame(format_float(100, 2), $rows[0]['rule_' . $ruleid]);
    }

    /**
     * Hidden courses with a stored audit are included regardless of includehidden.
     */
    public function test_hidden_course_with_audit_is_included(): void {
        $this->resetAfterTest();
        set_config('includehidden', '0', 'local_bbcotodobien');

        $course = $this->getDataGenerator()->create_course(['visible' => 0]);
        $typeid = $this->create_type_with_rule();
        engine::run_audit($course->id, $typeid, 0);

        $rows = $this->rows($typeid);
        $this->assertCount(1, $rows);
        $this->assertSame((int) $course->id, (int) $rows[0]['courseid']);
    }

    /**
     * Courses without contacts produce a single empty contact row.
     */
    public function test_course_without_contacts_has_empty_contact_fields(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course([
            'shortname' => 'C1',
            'fullname' => 'Course one',
            'idnumber' => 'CID1',
        ]);
        $typeid = $this->create_type_with_rule();
        engine::run_audit($course->id, $typeid, 0);

        $rows = $this->rows($typeid);
        $this->assertCount(1, $rows);
        $this->assertSame('', (string) $rows[0]['contactid']);
        $this->assertSame('', $rows[0]['contactusername']);
        $this->assertSame('', $rows[0]['contactemail']);
        $this->assertSame('C1', $rows[0]['courseshortname']);
        $this->assertSame('CID1', $rows[0]['courseidnumber']);
    }

    /**
     * Multiple course contacts duplicate the audit row.
     */
    public function test_multiple_contacts_repeat_the_audit_row(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $role = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
        set_config('coursecontact', $role->id);

        $first = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher', [
            'username' => 'contacta',
            'idnumber' => 'IDA',
            'firstname' => 'Ann',
            'lastname' => 'Alpha',
            'email' => 'ann@example.com',
        ]);
        $second = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher', [
            'username' => 'contactb',
            'idnumber' => 'IDB',
            'firstname' => 'Bob',
            'lastname' => 'Beta',
            'email' => 'bob@example.com',
        ]);

        $typeid = $this->create_type_with_rule();
        $ruleid = $this->first_active_rule_id($typeid);
        engine::run_audit($course->id, $typeid, 0);

        $rows = $this->rows($typeid);
        $this->assertCount(2, $rows);
        $usernames = array_column($rows, 'contactusername');
        sort($usernames);
        $this->assertSame(['contacta', 'contactb'], $usernames);

        $byuser = [];
        foreach ($rows as $row) {
            $byuser[$row['contactusername']] = $row;
        }
        $this->assertSame((int) $first->id, (int) $byuser['contacta']['contactid']);
        $this->assertSame('IDA', $byuser['contacta']['contactidnumber']);
        $this->assertSame('Ann', $byuser['contacta']['contactfirstname']);
        $this->assertSame('Alpha', $byuser['contacta']['contactlastname']);
        $this->assertSame('ann@example.com', $byuser['contacta']['contactemail']);
        $this->assertSame((int) $second->id, (int) $byuser['contactb']['contactid']);
        $this->assertSame(format_float(100, 2), $byuser['contacta']['rule_' . $ruleid]);
        $this->assertSame($byuser['contacta']['rule_' . $ruleid], $byuser['contactb']['rule_' . $ruleid]);
        $this->assertSame($byuser['contacta']['auditdate'], $byuser['contactb']['auditdate']);
    }

    /**
     * Pass and fail show compliance; na, error and missing stay empty.
     */
    public function test_rule_cells_use_compliance_or_empty(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $typeid = audit_type_manager::create_type('Quality');
        $passid = $this->create_stub_rule($typeid, 'Pass rule');
        $failid = $this->create_stub_rule($typeid, 'Fail rule');
        $naid = $this->create_stub_rule($typeid, 'NA rule');
        $errorid = $this->create_stub_rule($typeid, 'Error rule');
        $missingid = $this->create_stub_rule($typeid, 'Missing rule');

        $auditid = writer::create_course_audit($course->id, $typeid, 0);
        writer::save_rule_result($auditid, $passid, 0, new result(100, result::STATUS_PASS));
        writer::save_rule_result($auditid, $failid, 0, new result(66.5, result::STATUS_FAIL));
        writer::save_rule_result($auditid, $naid, 0, new result(100, result::STATUS_NA));
        writer::save_rule_result($auditid, $errorid, 0, new result(0, result::STATUS_ERROR));

        $rows = $this->rows($typeid);
        $this->assertCount(1, $rows);
        $this->assertSame(format_float(100, 2), $rows[0]['rule_' . $passid]);
        $this->assertSame(format_float(66.5, 2), $rows[0]['rule_' . $failid]);
        $this->assertSame('', $rows[0]['rule_' . $naid]);
        $this->assertSame('', $rows[0]['rule_' . $errorid]);
        $this->assertSame('', $rows[0]['rule_' . $missingid]);
    }

    /**
     * Category path includes ancestors from the root.
     */
    public function test_category_path_includes_ancestors(): void {
        $this->resetAfterTest();

        $parent = $this->getDataGenerator()->create_category(['name' => 'Parent cat']);
        $child = $this->getDataGenerator()->create_category(['name' => 'Child cat', 'parent' => $parent->id]);
        $course = $this->getDataGenerator()->create_course(['category' => $child->id]);
        $typeid = $this->create_type_with_rule();
        engine::run_audit($course->id, $typeid, 0);

        $rows = $this->rows($typeid);
        $this->assertCount(1, $rows);
        $this->assertSame((int) $child->id, (int) $rows[0]['coursecategory']);
        $this->assertStringContainsString('Parent cat', $rows[0]['categorypath']);
        $this->assertStringContainsString('Child cat', $rows[0]['categorypath']);
        $this->assertStringContainsString(' / ', $rows[0]['categorypath']);
    }

    /**
     * Generating a snapshot stores a file that is removed with the type.
     */
    public function test_generate_stores_file_and_delete_type_removes_it(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $typeid = $this->create_type_with_rule();
        engine::run_audit($course->id, $typeid, 0);

        $this->assertSame([], type_matrix_file::list_files($typeid));
        $file = type_matrix_file::generate($typeid, 'csv');
        $this->assertSame('.csv', substr($file->get_filename(), -4));
        $this->assertGreaterThan(0, $file->get_filesize());
        $this->assertCount(1, type_matrix_file::list_files($typeid));

        audit_type_manager::delete_type($typeid);
        $this->assertSame([], type_matrix_file::list_files($typeid));
    }

    /**
     * Create a type with one active stub rule.
     *
     * @param array $params Rule params
     * @return int
     */
    protected function create_type_with_rule(array $params = ['pass' => true]): int {
        $typeid = audit_type_manager::create_type('Quality');
        $this->create_stub_rule($typeid, 'Active rule', $params);
        return $typeid;
    }

    /**
     * Create an active stub rule.
     *
     * @param int $typeid Audit type id
     * @param string $name Rule name
     * @param array $params Rule params
     * @return int
     */
    protected function create_stub_rule(int $typeid, string $name, array $params = ['pass' => true]): int {
        return audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => stub_rule::class,
            'name' => $name,
            'mandatory' => true,
            'active' => true,
            'params' => $params,
        ]);
    }

    /**
     * First active rule config id for a type.
     *
     * @param int $typeid Audit type id
     * @return int
     */
    protected function first_active_rule_id(int $typeid): int {
        $configs = audit_type_manager::get_rule_configs($typeid, true);
        $config = reset($configs);
        return (int) $config->id;
    }

    /**
     * Snapshot rows as associative arrays keyed by column id.
     *
     * @param int $typeid Audit type id
     * @return array[]
     */
    protected function rows(int $typeid): array {
        $keys = array_keys(type_matrix::get_columns($typeid));
        $rows = [];
        foreach (type_matrix::iterate_rows($typeid) as $values) {
            $rows[] = array_combine($keys, $values);
        }
        return $rows;
    }
}
