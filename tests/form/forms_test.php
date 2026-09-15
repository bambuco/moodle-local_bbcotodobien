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

namespace local_bbcotodobien\form;

use local_bbcotodobien\tests\fixtures\stub_rule;

/**
 * Tests for audit type and rule configuration forms.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(audit_type_form::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(rule_config_form::class)]
final class forms_test extends \advanced_testcase {
    /**
     * Load the fixture class.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/local/bbcotodobien/tests/fixtures/stub_rule.php');
    }

    /**
     * Prepare a site-admin page context for form rendering.
     */
    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url(new \moodle_url('/local/bbcotodobien/manage.php'));
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * The audit type form exposes name, categories and active fields.
     */
    public function test_audit_type_form_contains_expected_fields(): void {
        $category = $this->getDataGenerator()->create_category(['name' => 'Quality']);
        $form = new audit_type_form();
        $html = $form->render();

        $this->assertStringContainsString('name="name"', $html);
        $this->assertStringContainsString('name="categories[]"', $html);
        $this->assertStringContainsString((string) $category->id, $html);
        $this->assertStringContainsString('name="active"', $html);
        $this->assertStringContainsString(get_string('audittypename', 'local_bbcotodobien'), $html);
        $this->assertStringContainsString(get_string('categories', 'local_bbcotodobien'), $html);
    }

    /**
     * Submitted category IDs are returned as an array.
     */
    public function test_audit_type_form_get_data_categories(): void {
        $category = $this->getDataGenerator()->create_category();
        audit_type_form::mock_submit([
            'id' => 0,
            'name' => 'Institutional',
            'categories' => [$category->id],
            'active' => 1,
        ]);
        $form = new audit_type_form();
        $data = $form->get_data();

        $this->assertNotEmpty($data);
        $this->assertSame('Institutional', $data->name);
        $this->assertEquals([$category->id], array_map('intval', (array) $data->categories));
        $this->assertNotEmpty($data->active);
    }

    /**
     * The rule form includes dynamic parameters for a selected class.
     */
    public function test_rule_config_form_contains_dynamic_parameters(): void {
        $form = new rule_config_form(null, [
            'audittypeid' => 1,
            'id' => 0,
            'ruleclass' => stub_rule::class,
        ]);
        $html = $form->render();

        $this->assertStringContainsString('name="name"', $html);
        $this->assertStringContainsString('name="ruleclass"', $html);
        $this->assertStringContainsString('name="loadparameters"', $html);
        $this->assertStringContainsString('name="mandatory"', $html);
        $this->assertStringContainsString('name="active"', $html);
        $this->assertStringContainsString('name="expected"', $html);
        $this->assertStringContainsString('guidance_editor', $html);
        $this->assertStringContainsString(get_string('stubrule', 'local_bbcotodobien'), $html);
        $this->assertStringContainsString(get_string('stubruleexpected', 'local_bbcotodobien'), $html);
    }

    /**
     * Submitted rule parameters can be extracted into the stored params array.
     */
    public function test_rule_config_form_extracts_params(): void {
        rule_config_form::mock_submit([
            'id' => 0,
            'audittypeid' => 1,
            'name' => 'Check expected',
            'ruleclass' => stub_rule::class,
            'mandatory' => 1,
            'active' => 1,
            'expected' => 'hello',
            'guidance_editor' => [
                'text' => '<p>Help</p>',
                'format' => FORMAT_HTML,
                'itemid' => 0,
            ],
        ]);
        $form = new rule_config_form(null, [
            'audittypeid' => 1,
            'id' => 0,
            'ruleclass' => stub_rule::class,
        ]);
        $data = $form->get_data();

        $this->assertNotEmpty($data);
        $this->assertSame('Check expected', $data->name);
        $this->assertSame(stub_rule::class, $data->ruleclass);
        $this->assertSame('hello', $data->expected);
        $this->assertSame(['expected' => 'hello'], (new stub_rule())->extract_params($data));
    }

    /**
     * An empty or unknown rule class fails validation.
     */
    public function test_rule_config_form_rejects_invalid_class(): void {
        rule_config_form::mock_submit([
            'id' => 0,
            'audittypeid' => 1,
            'name' => 'Broken',
            'ruleclass' => '',
            'mandatory' => 1,
            'active' => 1,
            'guidance_editor' => [
                'text' => '',
                'format' => FORMAT_HTML,
                'itemid' => 0,
            ],
        ]);
        $form = new rule_config_form(null, [
            'audittypeid' => 1,
            'id' => 0,
        ]);

        $this->assertNull($form->get_data());
    }
}
