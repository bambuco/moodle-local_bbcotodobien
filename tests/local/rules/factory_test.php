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

namespace local_bbcotodobien\local\rules;

use local_bbcotodobien\local\result;
use local_bbcotodobien\tests\fixtures\stub_rule;

/**
 * Tests for the audit rule factory.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(factory::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(stub_rule::class)]
final class factory_test extends \advanced_testcase {
    /**
     * Load the fixture class.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/local/bbcotodobien/tests/fixtures/stub_rule.php');
    }

    /**
     * The class menu is keyed by fully-qualified class name.
     */
    public function test_get_class_menu_is_keyed_by_class(): void {
        $menu = factory::get_class_menu();
        foreach ($menu as $classname => $name) {
            $this->assertTrue(factory::is_instantiatable_rule($classname));
            $this->assertSame($classname::get_name(), $name);
        }
        $this->assertArrayNotHasKey(stub_rule::class, $menu);
        $this->assertArrayHasKey(cm_content_contains::class, $menu);
        $this->assertArrayHasKey(cm_content_excludes::class, $menu);
        $this->assertArrayHasKey(cm_html_selector::class, $menu);
        $this->assertArrayHasKey(forum_coursecontact::class, $menu);
        $this->assertArrayHasKey(section_date_label::class, $menu);
        $this->assertArrayHasKey(section_activity_dates::class, $menu);
        $this->assertArrayHasKey(grade_category_moditems::class, $menu);
        $this->assertArrayHasKey(grade_category_weights::class, $menu);
        $this->assertArrayHasKey('cm_content_contains', factory::get_rule_classes());
    }

    /**
     * Discovery skips the abstract base and the factory itself.
     */
    public function test_get_rule_classes_excludes_base_and_factory(): void {
        $classes = factory::get_rule_classes();
        $this->assertArrayNotHasKey('base', $classes);
        $this->assertNotContains(base::class, $classes);
        $this->assertNotContains(factory::class, $classes);
        $this->assertNotContains(cm_locator::class, $classes);
        $this->assertNotContains(cm_field_rule::class, $classes);
        $this->assertNotContains(section_helper::class, $classes);
        $this->assertNotContains(gradebook_rule::class, $classes);
        foreach ($classes as $classname) {
            $this->assertTrue(factory::is_instantiatable_rule($classname));
        }
    }

    /**
     * The test stub is instantiable even though it is not in the production namespace.
     */
    public function test_create_stub_rule(): void {
        $rule = factory::create(stub_rule::class);
        $this->assertInstanceOf(stub_rule::class, $rule);
        $this->assertSame('stub_rule', $rule::get_identifier());
        $this->assertSame(get_string('stubrule', 'local_bbcotodobien'), $rule::get_name());
        $this->assertSame(base::GRANULARITY_COURSE, $rule::get_granularity());
    }

    /**
     * Creating an invalid class throws.
     */
    public function test_create_rejects_invalid_class(): void {
        $this->expectException(\coding_exception::class);
        factory::create(base::class);
    }

    /**
     * Binary evaluation through the stub rule.
     */
    public function test_stub_rule_binary_evaluate(): void {
        $rule = factory::create(stub_rule::class);
        $course = (object) ['id' => 15, 'fullname' => 'Course'];

        $pass = $rule->evaluate($course, ['pass' => true]);
        $this->assertSame(100.0, $pass->compliance);
        $this->assertSame(result::STATUS_PASS, $pass->status);
        $this->assertSame('stubrulepassed', $pass->details[0]->fields['identifier']);
        $this->assertSame(get_string('stubrulepassed', 'local_bbcotodobien'), $rule->render_detail($pass->details[0]));

        $fail = $rule->evaluate($course, ['pass' => false]);
        $this->assertSame(0.0, $fail->compliance);
        $this->assertSame(result::STATUS_FAIL, $fail->status);
    }

    /**
     * Section-style items on the stub use the shared percentage formula.
     */
    public function test_stub_rule_section_percentage(): void {
        $rule = factory::create(stub_rule::class);
        $course = (object) ['id' => 15, 'fullname' => 'Course'];
        $result = $rule->evaluate($course, [
            'items' => [
                ['targetid' => 1, 'targetname' => 'S1', 'status' => result::STATUS_PASS],
                ['targetid' => 2, 'targetname' => 'S2', 'status' => result::STATUS_PASS],
                ['targetid' => 3, 'targetname' => 'S3', 'status' => result::STATUS_FAIL],
            ],
        ]);
        $this->assertSame(66.67, $result->compliance);
        $this->assertSame(result::STATUS_FAIL, $result->status);
        $this->assertCount(3, $result->details);
    }
}
