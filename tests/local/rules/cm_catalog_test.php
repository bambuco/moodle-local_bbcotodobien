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

/**
 * Tests for CM catalogue rules RF-R01 to RF-R04.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(cm_locator::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(cm_field_rule::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(cm_content_contains::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(cm_content_excludes::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(cm_html_selector::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(forum_coursecontact::class)]
final class cm_catalog_test extends \advanced_testcase {
    /**
     * Create a page activity with HTML content and an idnumber.
     *
     * @param \stdClass $course Course
     * @param string $idnumber Id number
     * @param string $content HTML content
     * @return \stdClass
     */
    private function create_page(\stdClass $course, string $idnumber, string $content): \stdClass {
        return $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Page ' . $idnumber,
            'idnumber' => $idnumber,
            'content' => $content,
            'contentformat' => FORMAT_HTML,
        ]);
    }

    /**
     * Parameters for the content-contains / excludes rules.
     *
     * @param string $idnumber Id number
     * @param string $pattern Pattern
     * @param string $matchmode Match mode
     * @return array
     */
    private function content_params(
        string $idnumber,
        string $pattern,
        string $matchmode = cm_field_rule::MATCH_LITERAL
    ): array {
        return [
            'modname' => 'page',
            'idnumber' => $idnumber,
            'field' => 'content',
            'pattern' => $pattern,
            'matchmode' => $matchmode,
        ];
    }

    /**
     * Missing activities fail with 0%.
     */
    public function test_content_contains_fails_when_no_cm_matches(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $result = (new cm_content_contains())->evaluate($course, $this->content_params('MISSING', 'Hello'));

        $this->assertSame(0.0, $result->compliance);
        $this->assertSame(result::STATUS_FAIL, $result->status);
        $this->assertCount(1, $result->details);
        $this->assertSame(result::STATUS_FAIL, $result->details[0]->status);
    }

    /**
     * Literal matching uses plain text after format_text.
     */
    public function test_content_contains_literal_pass_and_fail(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->create_page($course, 'GUIDE', '<p>Welcome to the <strong>handbook</strong></p>');
        $rule = new cm_content_contains();

        $pass = $rule->evaluate($course, $this->content_params('GUIDE', 'handbook'));
        $this->assertSame(100.0, $pass->compliance);
        $this->assertSame(result::STATUS_PASS, $pass->status);

        $fail = $rule->evaluate($course, $this->content_params('GUIDE', '<strong>'));
        $this->assertSame(0.0, $fail->compliance);
        $this->assertSame(result::STATUS_FAIL, $fail->status);
    }

    /**
     * Regular expressions are supported; invalid ones become errors.
     */
    public function test_content_contains_regex_and_invalid_pattern(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->create_page($course, 'GUIDE', '<p>Version 12</p>');
        $rule = new cm_content_contains();

        $pass = $rule->evaluate($course, $this->content_params('GUIDE', 'Version\s+\d+', cm_field_rule::MATCH_REGEX));
        $this->assertSame(result::STATUS_PASS, $pass->status);

        $error = $rule->evaluate($course, $this->content_params('GUIDE', '[', cm_field_rule::MATCH_REGEX));
        $this->assertSame(result::STATUS_ERROR, $error->status);
        $this->assertSame(0.0, $error->compliance);
        $this->assertSame('ruleinvalidregex', $error->details[0]->fields['identifier']);
        $this->assertSame(
            get_string('ruleinvalidregex', 'local_bbcotodobien'),
            $rule->render_detail($error->details[0])
        );
    }

    /**
     * Several modules with the same idnumber are all evaluated.
     */
    public function test_content_contains_evaluates_all_matches(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->create_page($course, 'GUIDE', '<p>handbook</p>');
        $this->create_page($course, 'GUIDE', '<p>other</p>');
        $result = (new cm_content_contains())->evaluate($course, $this->content_params('GUIDE', 'handbook'));

        $this->assertCount(2, $result->details);
        $this->assertSame(50.0, $result->compliance);
        $this->assertSame(result::STATUS_FAIL, $result->status);
    }

    /**
     * A missing instance column is a technical error.
     */
    public function test_content_contains_missing_field_is_error(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->create_page($course, 'GUIDE', '<p>handbook</p>');
        $params = $this->content_params('GUIDE', 'handbook');
        $params['field'] = 'notacolumn';
        $result = (new cm_content_contains())->evaluate($course, $params);

        $this->assertSame(result::STATUS_ERROR, $result->status);
        $this->assertSame(0.0, $result->compliance);
    }

    /**
     * An unknown module name is a technical error.
     */
    public function test_content_contains_invalid_module_is_error(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $params = $this->content_params('GUIDE', 'handbook');
        $params['modname'] = 'notamodule';
        $result = (new cm_content_contains())->evaluate($course, $params);

        $this->assertSame(result::STATUS_ERROR, $result->status);
    }

    /**
     * RF-R02 passes when the pattern is absent.
     */
    public function test_content_excludes_pass_and_fail(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->create_page($course, 'GUIDE', '<p>Public handbook</p>');
        $rule = new cm_content_excludes();

        $pass = $rule->evaluate($course, $this->content_params('GUIDE', 'TODO'));
        $this->assertSame(result::STATUS_PASS, $pass->status);
        $this->assertSame(100.0, $pass->compliance);

        $fail = $rule->evaluate($course, $this->content_params('GUIDE', 'handbook'));
        $this->assertSame(result::STATUS_FAIL, $fail->status);
        $this->assertSame(0.0, $fail->compliance);
    }

    /**
     * RF-R02 also fails when no activity matches.
     */
    public function test_content_excludes_fails_when_no_cm_matches(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $result = (new cm_content_excludes())->evaluate($course, $this->content_params('MISSING', 'TODO'));
        $this->assertSame(result::STATUS_FAIL, $result->status);
        $this->assertSame(0.0, $result->compliance);
    }

    /**
     * RF-R03 finds a CSS class and checks its text.
     */
    public function test_html_selector_pass_and_failures(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->create_page($course, 'GUIDE', '<p class="quality-note">Required statement</p><p>Other</p>');
        $rule = new cm_html_selector();
        $base = [
            'modname' => 'page',
            'idnumber' => 'GUIDE',
            'field' => 'content',
            'cssclass' => 'quality-note',
        ];

        $pass = $rule->evaluate($course, $base + ['pattern' => 'Required']);
        $this->assertSame(result::STATUS_PASS, $pass->status);

        $notext = $rule->evaluate($course, $base + ['pattern' => 'Absent']);
        $this->assertSame(result::STATUS_FAIL, $notext->status);
        $this->assertSame('rulehtmlselector_fail_notext', $notext->details[0]->fields['identifier']);
        $this->assertSame('quality-note', $notext->details[0]->fields['cssclass']);
        $this->assertSame(
            get_string('rulehtmlselector_fail_notext', 'local_bbcotodobien', 'quality-note'),
            $rule->render_detail($notext->details[0])
        );

        $noclass = $rule->evaluate($course, [
            'modname' => 'page',
            'idnumber' => 'GUIDE',
            'field' => 'content',
            'cssclass' => '.missing',
            'pattern' => 'Required',
        ]);
        $this->assertSame(result::STATUS_FAIL, $noclass->status);
        $this->assertSame('rulehtmlselector_fail_noclass', $noclass->details[0]->fields['identifier']);
        $this->assertSame('missing', $noclass->details[0]->fields['cssclass']);
        $this->assertSame(
            get_string('rulehtmlselector_fail_noclass', 'local_bbcotodobien', 'missing'),
            $rule->render_detail($noclass->details[0])
        );
    }

    /**
     * RF-R03 can match the class text with a regular expression.
     */
    public function test_html_selector_regex_and_invalid_pattern(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->create_page(
            $course,
            'GUIDE',
            '<p class="quality-note">Contact teacher@example.com</p><p>other@site.org</p>'
        );
        $rule = new cm_html_selector();
        $base = [
            'modname' => 'page',
            'idnumber' => 'GUIDE',
            'field' => 'content',
            'cssclass' => 'quality-note',
            'matchmode' => cm_field_rule::MATCH_REGEX,
        ];

        $pass = $rule->evaluate($course, $base + [
            'pattern' => '[a-zA-Z0-9._+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}',
        ]);
        $this->assertSame(result::STATUS_PASS, $pass->status);

        $outsideclass = $rule->evaluate($course, $base + ['pattern' => 'other@site\.org']);
        $this->assertSame(result::STATUS_FAIL, $outsideclass->status);
        $this->assertSame(
            get_string('rulehtmlselector_fail_notext', 'local_bbcotodobien', 'quality-note'),
            $rule->render_detail($outsideclass->details[0])
        );

        $error = $rule->evaluate($course, $base + ['pattern' => '[']);
        $this->assertSame(result::STATUS_ERROR, $error->status);
        $this->assertSame(
            get_string('ruleinvalidregex', 'local_bbcotodobien'),
            $rule->render_detail($error->details[0])
        );
    }

    /**
     * Unparseable HTML is a technical error.
     */
    public function test_html_selector_invalid_html_is_error(): void {
        $rule = new cm_html_selector();
        $method = new \ReflectionMethod(cm_html_selector::class, 'query_class_nodes');
        $this->assertNull($method->invoke($rule, "\x00broken", 'note'));
        $this->assertSame([], $method->invoke($rule, '', 'note'));
    }

    /**
     * A general forum started by a course contact passes.
     */
    public function test_forum_coursecontact_pass(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $role = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
        $CFG->coursecontact = (string) $role->id;
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $forum = $this->getDataGenerator()->create_module('forum', [
            'course' => $course->id,
            'name' => 'News',
            'idnumber' => 'NEWS',
            'type' => 'general',
        ]);
        $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $course->id,
            'forum' => $forum->id,
            'userid' => $teacher->id,
        ]);

        $result = (new forum_coursecontact())->evaluate($course, ['idnumber' => 'NEWS']);
        $this->assertSame(result::STATUS_PASS, $result->status);
        $this->assertSame(100.0, $result->compliance);
    }

    /**
     * A student discussion is not enough; missing or non-general forums fail.
     */
    public function test_forum_coursecontact_failures(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $role = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
        $CFG->coursecontact = (string) $role->id;
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $rule = new forum_coursecontact();

        $missing = $rule->evaluate($course, ['idnumber' => 'NEWS']);
        $this->assertSame(result::STATUS_FAIL, $missing->status);

        $forum = $this->getDataGenerator()->create_module('forum', [
            'course' => $course->id,
            'name' => 'News',
            'idnumber' => 'NEWS',
            'type' => 'general',
        ]);
        $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $course->id,
            'forum' => $forum->id,
            'userid' => $student->id,
        ]);
        $studentonly = $rule->evaluate($course, ['idnumber' => 'NEWS']);
        $this->assertSame(result::STATUS_FAIL, $studentonly->status);

        $this->getDataGenerator()->create_module('forum', [
            'course' => $course->id,
            'name' => 'Q and A',
            'idnumber' => 'QA',
            'type' => 'qanda',
        ]);
        $wrongtype = $rule->evaluate($course, ['idnumber' => 'QA']);
        $this->assertSame(result::STATUS_FAIL, $wrongtype->status);
        $this->assertSame('ruleforumtypefail', $wrongtype->details[0]->fields['identifier']);
        $this->assertSame(
            get_string('ruleforumtypefail', 'local_bbcotodobien'),
            $rule->render_detail($wrongtype->details[0])
        );
    }

    /**
     * Missing parameters are technical errors.
     */
    public function test_missing_params_are_errors(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $contains = (new cm_content_contains())->evaluate($course, []);
        $this->assertSame(result::STATUS_ERROR, $contains->status);

        $forum = (new forum_coursecontact())->evaluate($course, []);
        $this->assertSame(result::STATUS_ERROR, $forum->status);
    }
}
